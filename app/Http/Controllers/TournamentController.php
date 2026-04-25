<?php

namespace App\Http\Controllers;

use App\Events\TournamentUpdated;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Services\TournamentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TournamentController extends Controller
{
    public function __construct(private readonly TournamentService $tournamentService)
    {
    }

    public function index(): View
    {
        return view('tournaments.index');
    }

    public function create(): View
    {
        return view('tournaments.create', [
            'defaults' => config('tournament.defaults'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage_name' => ['nullable', 'string', 'max:255'],
            'creator_name' => ['nullable', 'string', 'max:255'],
            'match_length' => ['nullable', 'integer', 'in:3,5'],
            'advancing_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $tournament = $this->tournamentService->createTournament($data);
        $this->storeOrganizerAccess($request, $tournament);

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Tournament room created.');
    }

    public function show(Request $request, Tournament $tournament): View
    {
        $tournament->load([
            'participants',
            'rounds.matches.playerOne',
            'rounds.matches.playerTwo',
            'rounds.matches.winner',
            'sourceTournament',
            'childTournaments',
        ]);

        $access = $this->tournamentAccess($request, $tournament);
        $participant = null;
        $assignedMatch = null;

        if (($access['role'] ?? null) === 'participant' && !empty($access['participant_id'])) {
            $participant = $tournament->participants->firstWhere('id', $access['participant_id']);

            if ($participant) {
                $assignedMatch = $this->tournamentService->currentMatchForParticipant($tournament, $participant);
            }
        }

        return view('tournaments.show', [
            'tournament' => $tournament,
            'standings' => $this->tournamentService->standings($tournament),
            'viewerMode' => $access['role'] ?? 'guest',
            'viewerParticipant' => $participant,
            'assignedMatch' => $assignedMatch,
            'currentRound' => $tournament->rounds->sortByDesc('number')->first(),
        ]);
    }

    public function addParticipant(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $this->tournamentService->addParticipant($tournament, $data['display_name']);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return back()->with('success', 'Participant added.');
    }

    public function joinByCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'join_code' => ['required', 'string', 'size:6'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $tournament = Tournament::query()
            ->where('join_code', strtoupper($data['join_code']))
            ->first();

        if (!$tournament) {
            return back()
                ->withInput()
                ->withErrors(['join_code' => 'That code does not match a tournament.']);
        }

        if ($tournament->status !== 'registration') {
            return back()
                ->withInput()
                ->withErrors(['join_code' => 'That tournament has already started.']);
        }

        $participant = $this->tournamentService->addParticipant($tournament, $data['display_name']);
        $this->storeParticipantAccess($request, $tournament, $participant);
        $this->dispatchTournamentUpdated($tournament->fresh());
        $message = $participant->wasRecentlyCreated
            ? 'You joined the tournament.'
            : 'You were already in that tournament.';

        return redirect()->route('tournaments.show', $tournament)->with('success', $message);
    }

    public function start(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);
        $this->tournamentService->startTournament($tournament);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return redirect()->route('tournaments.show', $tournament)->with('success', 'Tournament started. Pairings are live.');
    }

    public function recordResult(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);
        abort_unless($match->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'result_type' => ['required', 'in:player_one_win,player_two_win,draw'],
        ]);

        $this->tournamentService->recordResult($match, $data['result_type']);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return back()->with('success', 'Match result recorded.');
    }

    public function nextRound(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);
        $this->tournamentService->createNextRound($tournament);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return back()->with('success', 'Next round generated or tournament completed.');
    }

    public function dropParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $this->tournamentService->dropParticipant($participant);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return back()->with('success', 'Participant marked as dropped.');
    }

    public function createFollowUp(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->ensureOrganizer($request, $tournament);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage_name' => ['nullable', 'string', 'max:255'],
            'creator_name' => ['nullable', 'string', 'max:255'],
            'match_length' => ['nullable', 'integer', 'in:3,5'],
            'advancing_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'selected_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $nextTournament = $this->tournamentService->createFollowUpTournament($tournament, $data);
        $this->dispatchTournamentUpdated($tournament->fresh());

        return redirect()->route('tournaments.show', $nextTournament)->with('success', 'Follow-up room created from top players.');
    }

    public function participantState(Request $request, Tournament $tournament): JsonResponse
    {
        $access = $this->tournamentAccess($request, $tournament);
        abort_unless(($access['role'] ?? null) === 'participant', 403);

        $participant = TournamentParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->findOrFail($access['participant_id'] ?? 0);

        $assignedMatch = $this->tournamentService->currentMatchForParticipant($tournament, $participant);

        return response()->json([
            'tournament_status' => $tournament->status,
            'participant_status' => $participant->status,
            'assigned_match' => $assignedMatch ? [
                'round' => $assignedMatch->round?->number,
                'table' => $assignedMatch->table_number,
                'opponent' => $assignedMatch->player_one_id === $participant->id
                    ? $assignedMatch->playerTwo?->display_name
                    : $assignedMatch->playerOne?->display_name,
                'play_url' => route('tournaments.play', $tournament),
            ] : null,
        ]);
    }

    public function play(Request $request, Tournament $tournament): RedirectResponse
    {
        $access = $this->tournamentAccess($request, $tournament);
        abort_unless(($access['role'] ?? null) === 'participant', 403);

        $participant = TournamentParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->findOrFail($access['participant_id'] ?? 0);

        $match = $this->tournamentService->currentMatchForParticipant($tournament, $participant);
        abort_unless($match !== null, 404);

        $room = $this->tournamentService->roomForMatch($match);
        abort_unless($room !== null, 404);

        $token = $match->player_one_id === $participant->id ? $room->player_x_token : $room->player_o_token;
        $request->session()->put('room_tokens.'.$room->code, $token);

        return redirect()->route('rooms.show', $room);
    }

    private function tournamentSessionKey(Tournament $tournament): string
    {
        return 'tournament_access.'.$tournament->id;
    }

    private function tournamentAccess(Request $request, Tournament $tournament): array
    {
        return $request->session()->get($this->tournamentSessionKey($tournament), []);
    }

    private function storeOrganizerAccess(Request $request, Tournament $tournament): void
    {
        $request->session()->put($this->tournamentSessionKey($tournament), [
            'role' => 'organizer',
        ]);
    }

    private function storeParticipantAccess(Request $request, Tournament $tournament, TournamentParticipant $participant): void
    {
        $request->session()->put($this->tournamentSessionKey($tournament), [
            'role' => 'participant',
            'participant_id' => $participant->id,
        ]);
    }

    private function ensureOrganizer(Request $request, Tournament $tournament): void
    {
        abort_unless(($this->tournamentAccess($request, $tournament)['role'] ?? null) === 'organizer', 403);
    }

    private function dispatchTournamentUpdated(Tournament $tournament): void
    {
        try {
            TournamentUpdated::dispatch($tournament);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
