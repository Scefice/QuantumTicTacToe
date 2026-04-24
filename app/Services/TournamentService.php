<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentRound;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TournamentService
{
    private const CONFIG_KEY = 'tournament.defaults';

    public function __construct(private readonly QuantumGameStateService $gameStateService)
    {
    }

    public function createTournament(array $data): Tournament
    {
        $defaults = config(self::CONFIG_KEY);

        return Tournament::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'join_code' => $this->generateJoinCode(),
            'stage_name' => $data['stage_name'] ?? null,
            'creator_name' => $data['creator_name'] ?? null,
            'match_length' => (int) ($data['match_length'] ?? $defaults['match_length']),
            'rounds_count' => (int) $defaults['rounds_count'],
            'advancing_count' => (int) ($data['advancing_count'] ?? $defaults['advancing_count']),
            'game_time_limit_minutes' => (int) $defaults['game_time_limit_minutes'],
            'notes' => $data['notes'] ?? null,
            'status' => 'registration',
        ]);
    }

    public function addParticipant(Tournament $tournament, string $displayName): TournamentParticipant
    {
        $displayName = trim($displayName);

        if ($tournament->status !== 'registration') {
            abort(422, 'This tournament is no longer accepting players.');
        }

        $existingParticipant = $tournament->participants()
            ->whereRaw('LOWER(display_name) = ?', [Str::lower($displayName)])
            ->first();

        if ($existingParticipant) {
            return $existingParticipant;
        }

        $seed = (int) $tournament->participants()->max('seed') + 1;

        return $tournament->participants()->create([
            'display_name' => $displayName,
            'seed' => $seed,
            'status' => 'active',
        ]);
    }

    public function startTournament(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament): void {
            $tournament->refresh();

            if ($tournament->participants()->where('status', 'active')->count() < 2) {
                return;
            }

            $tournament->update(['status' => 'active']);
            $this->createNextRound($tournament);
        });
    }

    public function createNextRound(Tournament $tournament): ?TournamentRound
    {
        $tournament->refresh();
        $latestRound = $tournament->rounds()->latest('number')->first();

        if ($latestRound && $latestRound->matches()->where('status', 'pending')->exists()) {
            return null;
        }

        if ($latestRound && $latestRound->number >= $tournament->rounds_count) {
            $this->finalizeTournament($tournament);
            return null;
        }

        $participants = $this->activeStandings($tournament);

        if ($participants->count() < 2) {
            $this->finalizeTournament($tournament);
            return null;
        }

        $round = $tournament->rounds()->create([
            'number' => ($latestRound?->number ?? 0) + 1,
            'status' => 'active',
        ]);

        $pairs = $this->buildPairings($tournament, $participants);

        foreach ($pairs as $tableNumber => $pair) {
            [$playerOne, $playerTwo] = $pair;

            $match = $round->matches()->create([
                'tournament_id' => $tournament->id,
                'table_number' => $tableNumber + 1,
                'player_one_id' => $playerOne->id,
                'player_two_id' => $playerTwo?->id,
                'time_limit_minutes' => $tournament->game_time_limit_minutes,
                'result_type' => $playerTwo ? 'pending' : 'bye',
                'status' => $playerTwo ? 'pending' : 'recorded',
                'winner_participant_id' => $playerTwo ? null : $playerOne->id,
                'finished_at' => $playerTwo ? null : now(),
            ]);

            if (!$playerTwo) {
                $this->applyBye($match);
                continue;
            }

            $this->createGameRoomForMatch($match, $playerOne, $playerTwo);
        }

        return $round;
    }

    public function recordResult(TournamentMatch $match, string $resultType): void
    {
        DB::transaction(function () use ($match, $resultType): void {
            $match->loadMissing(['playerOne', 'playerTwo', 'round', 'tournament']);

            if ($match->status === 'recorded') {
                return;
            }

            $winnerId = match ($resultType) {
                'player_one_win', 'player_two_forfeit' => $match->player_one_id,
                'player_two_win', 'player_one_forfeit' => $match->player_two_id,
                default => null,
            };

            $match->update([
                'result_type' => $resultType,
                'winner_participant_id' => $winnerId,
                'status' => 'recorded',
                'finished_at' => now(),
            ]);

            $playerOne = $match->playerOne;
            $playerTwo = $match->playerTwo;

            if ($resultType === 'draw' && $playerTwo) {
                $playerOne->increment('points', 1);
                $playerOne->increment('draws');
                $playerTwo->increment('points', 1);
                $playerTwo->increment('draws');
            } elseif (in_array($resultType, ['player_one_win', 'player_two_forfeit'], true) && $playerTwo) {
                $playerOne->increment('points', 3);
                $playerOne->increment('wins');
                $playerTwo->increment('losses');
            } elseif (in_array($resultType, ['player_two_win', 'player_one_forfeit'], true) && $playerTwo) {
                $playerTwo->increment('points', 3);
                $playerTwo->increment('wins');
                $playerOne->increment('losses');
            }

            $this->closeRoundIfComplete($match->round);
            $this->maybeFinalizeAfterRound($match->tournament);
        });
    }

    public function dropParticipant(TournamentParticipant $participant): void
    {
        DB::transaction(function () use ($participant): void {
            $participant->update(['status' => 'dropped']);

            TournamentMatch::query()
                ->where('tournament_id', $participant->tournament_id)
                ->where('status', 'pending')
                ->where(fn ($query) => $query
                    ->where('player_one_id', $participant->id)
                    ->orWhere('player_two_id', $participant->id))
                ->get()
                ->each(function (TournamentMatch $match) use ($participant): void {
                    if ($match->player_one_id === $participant->id && $match->player_two_id) {
                        $this->recordResult($match, 'player_one_forfeit');
                    } elseif ($match->player_two_id === $participant->id) {
                        $this->recordResult($match, 'player_two_forfeit');
                    }
                });
        });
    }

    public function createFollowUpTournament(Tournament $sourceTournament, array $data): Tournament
    {
        $defaults = config(self::CONFIG_KEY);

        return DB::transaction(function () use ($sourceTournament, $data, $defaults): Tournament {
            $sourceTournament->refresh();
            $this->finalizeTournament($sourceTournament);

            $nextTournament = Tournament::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'join_code' => $this->generateJoinCode(),
                'stage_name' => $data['stage_name'] ?? null,
                'creator_name' => $data['creator_name'] ?? $sourceTournament->creator_name,
                'match_length' => (int) ($data['match_length'] ?? $sourceTournament->match_length ?? $defaults['match_length']),
                'rounds_count' => (int) $defaults['rounds_count'],
                'advancing_count' => (int) ($data['advancing_count'] ?? $defaults['advancing_count']),
                'game_time_limit_minutes' => (int) $defaults['game_time_limit_minutes'],
                'source_tournament_id' => $sourceTournament->id,
                'status' => 'registration',
            ]);

            $advancingCount = min((int) ($data['selected_count'] ?? $defaults['advancing_count']), $sourceTournament->participants()->count());

            $this->standings($sourceTournament)
                ->take($advancingCount)
                ->values()
                ->each(function (TournamentParticipant $participant, int $index) use ($nextTournament): void {
                    $nextTournament->participants()->create([
                        'display_name' => $participant->display_name,
                        'seed' => $index + 1,
                        'status' => 'active',
                        'source_participant_id' => $participant->id,
                    ]);
                });

            return $nextTournament;
        });
    }

    public function standings(Tournament $tournament): Collection
    {
        return $tournament->participants()
            ->orderByDesc('points')
            ->orderByDesc('wins')
            ->orderBy('losses')
            ->orderBy('display_name')
            ->get()
            ->values()
            ->map(function (TournamentParticipant $participant, int $index) {
                $participant->computed_rank = $index + 1;
                return $participant;
            });
    }

    protected function activeStandings(Tournament $tournament): Collection
    {
        return $this->standings($tournament)->where('status', 'active')->values();
    }

    protected function buildPairings(Tournament $tournament, Collection $participants): array
    {
        $remaining = $participants->values();
        $pairs = [];

        while ($remaining->isNotEmpty()) {
            /** @var TournamentParticipant $playerOne */
            $playerOne = $remaining->shift();

            if ($remaining->isEmpty()) {
                $pairs[] = [$playerOne, null];
                break;
            }

            $opponentIndex = $remaining->search(function (TournamentParticipant $candidate) use ($tournament, $playerOne) {
                return !$this->havePlayed($tournament, $playerOne, $candidate);
            });

            if ($opponentIndex === false) {
                $opponentIndex = 0;
            }

            $playerTwo = $remaining->splice($opponentIndex, 1)->first();
            $pairs[] = [$playerOne, $playerTwo];
        }

        return $pairs;
    }

    protected function havePlayed(Tournament $tournament, TournamentParticipant $first, TournamentParticipant $second): bool
    {
        return TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where(function ($query) use ($first, $second) {
                $query
                    ->where(fn ($inner) => $inner->where('player_one_id', $first->id)->where('player_two_id', $second->id))
                    ->orWhere(fn ($inner) => $inner->where('player_one_id', $second->id)->where('player_two_id', $first->id));
            })
            ->exists();
    }

    protected function applyBye(TournamentMatch $match): void
    {
        $player = $match->playerOne;

        $player->increment('points', 3);
        $player->increment('wins');
        $player->increment('bye_count');

        $this->closeRoundIfComplete($match->round);
        $this->maybeFinalizeAfterRound($match->tournament);
    }

    protected function closeRoundIfComplete(TournamentRound $round): void
    {
        if (!$round->matches()->where('status', 'pending')->exists()) {
            $round->update(['status' => 'completed']);
        }
    }

    protected function maybeFinalizeAfterRound(Tournament $tournament): void
    {
        $tournament->refresh();
        $lastRound = $tournament->rounds()->latest('number')->first();

        if (!$lastRound || $lastRound->status !== 'completed') {
            return;
        }

        if ($lastRound->number >= $tournament->rounds_count || $tournament->participants()->where('status', 'active')->count() < 2) {
            $this->finalizeTournament($tournament);
        }
    }

    public function finalizeTournament(Tournament $tournament): void
    {
        $tournament->refresh();

        if ($tournament->status === 'completed') {
            return;
        }

        $standings = $this->standings($tournament);

        foreach ($standings as $position => $participant) {
            $participant->update([
                'final_rank' => $position + 1,
                'status' => $position < $tournament->advancing_count ? 'advanced' : ($participant->status === 'dropped' ? 'dropped' : 'eliminated'),
            ]);
        }

        $tournament->update(['status' => 'completed']);
    }

    public function currentMatchForParticipant(Tournament $tournament, TournamentParticipant $participant): ?TournamentMatch
    {
        return TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'pending')
            ->where(fn ($query) => $query
                ->where('player_one_id', $participant->id)
                ->orWhere('player_two_id', $participant->id))
            ->with(['playerOne', 'playerTwo', 'round'])
            ->latest('id')
            ->first();
    }

    public function roomForMatch(TournamentMatch $match): ?GameRoom
    {
        return GameRoom::query()->where('tournament_match_id', $match->id)->first();
    }

    public function syncMatchResultFromRoom(GameRoom $room): void
    {
        if (!$room->tournament_match_id || !($room->state['matchWinner'] ?? null)) {
            return;
        }

        $match = TournamentMatch::query()->find($room->tournament_match_id);

        if (!$match || $match->status === 'recorded') {
            return;
        }

        $this->recordResult($match, $room->state['matchWinner'] === 'X' ? 'player_one_win' : 'player_two_win');
    }

    protected function generateJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Tournament::query()->where('join_code', $code)->exists());

        return $code;
    }

    protected function createGameRoomForMatch(
        TournamentMatch $match,
        TournamentParticipant $playerOne,
        TournamentParticipant $playerTwo
    ): GameRoom {
        $match->loadMissing('tournament');
        $existingRoom = GameRoom::query()->where('tournament_match_id', $match->id)->first();

        if ($existingRoom) {
            return $existingRoom;
        }

        $state = $this->gameStateService->activateRoom(
            $this->gameStateService->createWaitingState($playerOne->display_name, (int) $match->tournament->match_length),
            $playerTwo->display_name
        );

        return GameRoom::create([
            'tournament_match_id' => $match->id,
            'code' => $this->generateRoomCode(),
            'player_x_name' => $playerOne->display_name,
            'player_x_token' => Str::random(40),
            'player_o_name' => $playerTwo->display_name,
            'player_o_token' => Str::random(40),
            'match_length' => (int) $match->tournament->match_length,
            'status' => 'active',
            'state' => $state,
        ]);
    }

    protected function generateRoomCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (GameRoom::query()->where('code', $code)->exists());

        return $code;
    }
}
