<?php

namespace App\Http\Controllers;

use App\Events\RoomStateUpdated;
use App\Events\TournamentUpdated;
use App\Models\GameRoom;
use App\Models\TournamentMatch;
use App\Services\QuantumGameStateService;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class OnlineRoomController extends Controller
{
    public function __construct(
        private readonly QuantumGameStateService $gameStateService,
        private readonly TournamentService $tournamentService,
    ) {
    }

    public function index(): View
    {
        return view('rooms.index');
    }

    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'player_x_name' => ['required', 'string', 'max:255'],
            'match_length' => ['required', 'integer', 'in:3,5'],
        ]);

        $token = Str::random(40);
        $room = GameRoom::create([
            'code' => strtoupper(Str::random(6)),
            'player_x_name' => trim($data['player_x_name']),
            'player_x_token' => $token,
            'match_length' => (int) $data['match_length'],
            'status' => 'waiting',
            'state' => $this->gameStateService->createWaitingState(trim($data['player_x_name']), (int) $data['match_length']),
        ]);

        $this->storeRoomToken($request, $room, $token);

        return redirect()->route('rooms.show', $room);
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'player_o_name' => ['required', 'string', 'max:255'],
        ]);

        /** @var GameRoom $room */
        $room = GameRoom::query()->where('code', strtoupper($data['code']))->firstOrFail();
        $existingToken = $this->getRoomToken($request, $room);

        if ($existingToken === $room->player_o_token) {
            return redirect()->route('rooms.show', $room);
        }

        abort_if($room->player_o_name !== null, 422, 'Room is already full.');

        $token = Str::random(40);
        $state = $this->gameStateService->activateRoom($room->state, trim($data['player_o_name']));

        $room->update([
            'player_o_name' => trim($data['player_o_name']),
            'player_o_token' => $token,
            'status' => 'active',
            'state' => $state,
            'version' => $room->version + 1,
        ]);
        $this->dispatchRoomStateUpdated($room->fresh());

        $this->storeRoomToken($request, $room, $token);

        return redirect()->route('rooms.show', $room);
    }

    public function show(Request $request, GameRoom $room): View
    {
        $playerName = $this->resolvePlayerName($request, $room);
        $playerMark = $this->resolvePlayerMark($request, $room);

        abort_if($playerMark === null || $playerName === null, 403);

        $tournamentReturnUrl = null;

        if ($room->tournament_match_id) {
            $match = TournamentMatch::query()->with('tournament')->find($room->tournament_match_id);
            $tournamentReturnUrl = $match?->tournament
                ? route('tournaments.show', $match->tournament)
                : null;
        }

        return view('rooms.show', [
            'room' => $room,
            'state' => $room->state,
            'playerMark' => $playerMark,
            'playerName' => $playerName,
            'tournamentReturnUrl' => $tournamentReturnUrl,
        ]);
    }

    public function state(Request $request, GameRoom $room): JsonResponse
    {
        $playerMark = $this->resolvePlayerMark($request, $room);
        abort_if($playerMark === null, 403);

        return response()->json([
            'room' => [
                'code' => $room->code,
                'status' => $room->status,
                'player_mark' => $playerMark,
                'version' => $room->version,
                'player_x_name' => $room->player_x_name,
                'player_o_name' => $room->player_o_name,
            ],
            'state' => $room->state,
        ]);
    }

    public function pick(Request $request, GameRoom $room): JsonResponse
    {
        $playerMark = $this->resolvePlayerMark($request, $room);
        abort_if($playerMark === null, 403);

        $data = $request->validate([
            'cell_index' => ['required', 'integer', 'min:0', 'max:8'],
        ]);

        $room->refresh();
        $state = $this->gameStateService->selectCell($room->state, $playerMark, (int) $data['cell_index']);
        $room->update([
            'state' => $state,
            'status' => $state['matchWinner'] ? 'completed' : ($state['matchStarted'] ? 'active' : 'waiting'),
            'version' => $room->version + 1,
        ]);

        $this->tournamentService->syncMatchResultFromRoom($room->fresh());
        $freshRoom = $room->fresh();
        $this->dispatchRoomStateUpdated($freshRoom);
        $this->broadcastTournamentIfNeeded($freshRoom);

        return response()->json([
            'state' => $freshRoom->state,
            'version' => $freshRoom->version,
        ]);
    }

    public function resetBoard(Request $request, GameRoom $room): JsonResponse
    {
        $playerMark = $this->resolvePlayerMark($request, $room);
        abort_if($playerMark === null, 403);

        $room->refresh();
        $state = $this->gameStateService->resetBoard($room->state);
        $room->update([
            'state' => $state,
            'version' => $room->version + 1,
        ]);
        $this->dispatchRoomStateUpdated($room->fresh());

        $freshRoom = $room->fresh();

        return response()->json([
            'state' => $freshRoom->state,
            'version' => $freshRoom->version,
        ]);
    }

    public function nextRound(Request $request, GameRoom $room): JsonResponse
    {
        $playerMark = $this->resolvePlayerMark($request, $room);
        abort_if($playerMark === null, 403);

        $room->refresh();
        $state = $this->gameStateService->nextRound($room->state);
        $room->update([
            'state' => $state,
            'status' => $state['matchWinner'] ? 'completed' : 'active',
            'version' => $room->version + 1,
        ]);

        $this->tournamentService->syncMatchResultFromRoom($room->fresh());
        $freshRoom = $room->fresh();
        $this->dispatchRoomStateUpdated($freshRoom);
        $this->broadcastTournamentIfNeeded($freshRoom);

        return response()->json([
            'state' => $freshRoom->state,
            'version' => $freshRoom->version,
        ]);
    }

    public function resetMatch(Request $request, GameRoom $room): JsonResponse
    {
        $playerMark = $this->resolvePlayerMark($request, $room);
        abort_if($playerMark === null || $playerMark !== 'X', 403);

        $room->refresh();
        $state = $this->gameStateService->resetMatch($room->state);
        $room->update([
            'state' => $state,
            'status' => 'active',
            'version' => $room->version + 1,
        ]);
        $this->dispatchRoomStateUpdated($room->fresh());

        $freshRoom = $room->fresh();

        return response()->json([
            'state' => $freshRoom->state,
            'version' => $freshRoom->version,
        ]);
    }

    private function roomSessionKey(GameRoom $room): string
    {
        return 'room_tokens.'.$room->code;
    }

    private function storeRoomToken(Request $request, GameRoom $room, string $token): void
    {
        $request->session()->put($this->roomSessionKey($room), $token);
    }

    private function getRoomToken(Request $request, GameRoom $room): ?string
    {
        return $request->session()->get($this->roomSessionKey($room));
    }

    private function resolvePlayerMark(Request $request, GameRoom $room): ?string
    {
        $playerName = $this->resolvePlayerName($request, $room);

        if ($playerName === null) {
            return null;
        }

        return match ($playerName) {
            $room->state['playerNames']['X'] ?? null => 'X',
            $room->state['playerNames']['O'] ?? null => 'O',
            default => null,
        };
    }

    private function resolvePlayerName(Request $request, GameRoom $room): ?string
    {
        $token = $this->getRoomToken($request, $room);

        return match ($token) {
            $room->player_x_token => $room->player_x_name,
            $room->player_o_token => $room->player_o_name,
            default => null,
        };
    }

    private function broadcastTournamentIfNeeded(GameRoom $room): void
    {
        if (!$room->tournament_match_id) {
            return;
        }

        $match = TournamentMatch::query()->with('tournament')->find($room->tournament_match_id);

        if ($match?->tournament) {
            try {
                TournamentUpdated::dispatch($match->tournament->fresh());
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function dispatchRoomStateUpdated(GameRoom $room): void
    {
        try {
            RoomStateUpdated::dispatch($room);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
