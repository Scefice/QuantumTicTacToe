<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentRound;
use App\Services\QuantumGameStateService;
use App\Services\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Quantum Tic-Tac-Toe');
    }

    public function test_game_page_loads(): void
    {
        $response = $this->get('/game');

        $response->assertStatus(200);
        $response->assertSee('Quantum Tic-Tac-Toe');
    }

    public function test_tournaments_index_loads(): void
    {
        $response = $this->get('/tournaments');

        $response->assertStatus(200);
        $response->assertSee('Join Or Create');
        $response->assertSee('Join With Code');
        $response->assertSee('Create Room');
    }

    public function test_tournaments_index_does_not_list_tournament_names(): void
    {
        Tournament::create([
            'name' => 'Secret School Final',
            'slug' => 'secret-school-final',
            'join_code' => 'SECRET',
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'registration',
        ]);

        $response = $this->get('/tournaments');

        $response->assertStatus(200);
        $response->assertDontSee('Secret School Final');
    }

    public function test_tournament_create_page_loads(): void
    {
        $response = $this->get('/tournaments/create');

        $response->assertStatus(200);
        $response->assertSee('Create Tournament');
        $response->assertSee('6');
        $response->assertSee('4 minutes');
    }

    public function test_online_room_hub_loads(): void
    {
        $response = $this->get('/play-online');

        $response->assertStatus(200);
        $response->assertSee('Create Or Join');
    }

    public function test_joining_room_assigns_second_player_token(): void
    {
        $createResponse = $this->post(route('rooms.create'), [
            'player_x_name' => 'Alice',
            'match_length' => 3,
        ]);

        /** @var GameRoom $room */
        $room = GameRoom::query()->firstOrFail();

        $createResponse->assertRedirect(route('rooms.show', $room));
        $this->assertSame($room->player_x_token, session('room_tokens.'.$room->code));

        $joinResponse = $this->post(route('rooms.join'), [
            'code' => $room->code,
            'player_o_name' => 'Bob',
        ]);

        $room->refresh();

        $joinResponse->assertRedirect(route('rooms.show', $room));
        $this->assertSame('Bob', $room->player_o_name);
        $this->assertSame($room->player_o_token, session('room_tokens.'.$room->code));

        $showResponse = $this->get(route('rooms.show', $room));

        $showResponse->assertOk();
        $showResponse->assertSee('data-player-mark="O"', false);
    }

    public function test_next_round_swaps_symbols_and_starting_player(): void
    {
        $service = app(QuantumGameStateService::class);
        $state = $service->activateRoom($service->createWaitingState('Alice', 3), 'Bob');
        $state['scoreboard'] = ['X' => 1, 'O' => 0];
        $state['roundComplete'] = true;
        $state['winner'] = 'X';

        $nextState = $service->nextRound($state);

        $this->assertSame(2, $nextState['roundNumber']);
        $this->assertSame('Bob', $nextState['playerNames']['X']);
        $this->assertSame('Alice', $nextState['playerNames']['O']);
        $this->assertSame(['X' => 0, 'O' => 1], $nextState['scoreboard']);
        $this->assertSame('X', $nextState['currentPlayer']);
    }

    public function test_tournament_room_includes_return_url_on_game_page(): void
    {
        $tournament = Tournament::create([
            'name' => 'School Final',
            'slug' => 'school-final-test',
            'join_code' => 'FINAL1',
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'active',
        ]);

        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'number' => 1,
            'status' => 'active',
        ]);

        $playerOne = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
            'seed' => 1,
            'status' => 'active',
        ]);

        $playerTwo = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 2,
            'status' => 'active',
        ]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'table_number' => 1,
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'result_type' => 'pending',
            'time_limit_minutes' => 4,
            'status' => 'pending',
        ]);

        $room = GameRoom::create([
            'tournament_match_id' => $match->id,
            'code' => 'ROOM12',
            'player_x_name' => 'Alice',
            'player_x_token' => 'token-x',
            'player_o_name' => 'Bob',
            'player_o_token' => 'token-o',
            'match_length' => 3,
            'status' => 'active',
            'state' => [
                'cells' => [],
                'moves' => [],
                'currentPlayer' => 'X',
                'nextMoveNumber' => 1,
                'selectedCells' => [],
                'hoveredMoveId' => null,
                'winner' => null,
                'draw' => false,
                'roundComplete' => false,
                'matchWinner' => null,
                'matchStarted' => true,
                'matchLength' => 3,
                'winsNeeded' => 2,
                'scoreboard' => ['X' => 0, 'O' => 0],
                'playerNames' => ['X' => 'Alice', 'O' => 'Bob'],
                'roundNumber' => 1,
                'collapseLog' => [],
                'statusMessage' => '',
                'alert' => false,
                'lastEvent' => ['type' => 'idle', 'moveIds' => [], 'cells' => [], 'explanation' => '', 'resolvedMoves' => []],
                'boardMode' => 'Fresh',
            ],
        ]);

        $response = $this
            ->withSession(['room_tokens.'.$room->code => 'token-x'])
            ->get(route('rooms.show', $room));

        $response->assertStatus(200);
        $response->assertSee('data-tournament-return-url="'.route('tournaments.show', $tournament).'"', false);
    }

    public function test_player_can_join_tournament_with_code(): void
    {
        $tournament = Tournament::create([
            'name' => 'Class 10 Room',
            'slug' => 'class-10-room-test',
            'join_code' => 'ABC123',
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'registration',
        ]);

        $response = $this->post('/tournaments/join', [
            'join_code' => 'abc123',
            'display_name' => 'Alice',
        ]);

        $response->assertRedirect(route('tournaments.show', $tournament));
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
        ]);
    }

    public function test_participant_cannot_start_tournament(): void
    {
        $tournament = Tournament::create([
            'name' => 'Class 11 Room',
            'slug' => 'class-11-room-test',
            'join_code' => 'JOIN11',
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'registration',
        ]);

        $participant = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 1,
            'status' => 'active',
        ]);

        $response = $this
            ->withSession(['tournament_access.'.$tournament->id => ['role' => 'participant', 'participant_id' => $participant->id]])
            ->post(route('tournaments.start', $tournament));

        $response->assertForbidden();
    }

    public function test_starting_tournament_creates_game_room_for_pairing(): void
    {
        $tournament = Tournament::create([
            'name' => 'Class 12 Room',
            'slug' => 'class-12-room-test',
            'join_code' => 'JOIN12',
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'registration',
        ]);

        $playerOne = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
            'seed' => 1,
            'status' => 'active',
        ]);

        $playerTwo = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 2,
            'status' => 'active',
        ]);

        $response = $this
            ->withSession(['tournament_access.'.$tournament->id => ['role' => 'organizer']])
            ->post(route('tournaments.start', $tournament));

        $response->assertRedirect(route('tournaments.show', $tournament));

        $this->assertDatabaseHas('tournament_matches', [
            'tournament_id' => $tournament->id,
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
        ]);

        $matchId = $tournament->matches()->firstOrFail()->id;

        $this->assertDatabaseHas('game_rooms', [
            'tournament_match_id' => $matchId,
            'player_x_name' => 'Alice',
            'player_o_name' => 'Bob',
            'match_length' => 3,
        ]);

        $this->assertInstanceOf(GameRoom::class, GameRoom::query()->where('tournament_match_id', $matchId)->first());
    }

    public function test_tournament_store_uses_fixed_round_and_time_values(): void
    {
        $response = $this->post(route('tournaments.store'), [
            'name' => 'Static Rules Room',
            'creator_name' => 'Davis',
            'match_length' => 5,
            'advancing_count' => 5,
            'rounds_count' => 99,
            'game_time_limit_minutes' => 99,
        ]);

        $tournament = Tournament::query()->firstOrFail();

        $response->assertRedirect(route('tournaments.show', $tournament));
        $this->assertSame(6, $tournament->rounds_count);
        $this->assertSame(4, $tournament->game_time_limit_minutes);
        $this->assertSame(5, $tournament->match_length);
        $this->assertSame(5, $tournament->advancing_count);
    }

    public function test_participant_state_returns_assigned_match_after_tournament_starts(): void
    {
        $tournament = $this->createTournament([
            'name' => 'Round Pairing Room',
            'slug' => 'round-pairing-room',
            'join_code' => 'ROUND1',
        ]);

        $alice = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
            'seed' => 1,
            'status' => 'active',
        ]);

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 2,
            'status' => 'active',
        ]);

        $this->withSession(['tournament_access.'.$tournament->id => ['role' => 'organizer']])
            ->post(route('tournaments.start', $tournament))
            ->assertRedirect(route('tournaments.show', $tournament));

        $response = $this
            ->withSession(['tournament_access.'.$tournament->id => ['role' => 'participant', 'participant_id' => $alice->id]])
            ->getJson(route('tournaments.participant-state', $tournament));

        $response->assertOk();
        $response->assertJsonPath('tournament_status', 'active');
        $response->assertJsonPath('assigned_match.round', 1);
        $response->assertJsonPath('assigned_match.table', 1);
        $response->assertJsonPath('assigned_match.opponent', 'Bob');
        $response->assertJsonPath('assigned_match.play_url', route('tournaments.play', $tournament));
    }

    public function test_tournament_play_redirects_participant_to_assigned_room(): void
    {
        $tournament = $this->createTournament([
            'name' => 'Play Redirect Room',
            'slug' => 'play-redirect-room',
            'join_code' => 'PLAY12',
            'status' => 'active',
        ]);

        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'number' => 1,
            'status' => 'active',
        ]);

        $playerOne = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
            'seed' => 1,
            'status' => 'active',
        ]);

        $playerTwo = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 2,
            'status' => 'active',
        ]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'table_number' => 1,
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'result_type' => 'pending',
            'time_limit_minutes' => 4,
            'status' => 'pending',
        ]);

        $room = GameRoom::create([
            'tournament_match_id' => $match->id,
            'code' => 'PLAYRM',
            'player_x_name' => 'Alice',
            'player_x_token' => 'token-x',
            'player_o_name' => 'Bob',
            'player_o_token' => 'token-o',
            'match_length' => 3,
            'status' => 'active',
            'state' => app(QuantumGameStateService::class)->activateRoom(
                app(QuantumGameStateService::class)->createWaitingState('Alice', 3),
                'Bob'
            ),
        ]);

        $response = $this
            ->withSession(['tournament_access.'.$tournament->id => ['role' => 'participant', 'participant_id' => $playerOne->id]])
            ->get(route('tournaments.play', $tournament));

        $response->assertRedirect(route('rooms.show', $room));
        $this->assertSame('token-x', session('room_tokens.'.$room->code));
    }

    public function test_next_round_route_resets_board_and_swaps_sides(): void
    {
        $service = app(QuantumGameStateService::class);
        $state = $service->activateRoom($service->createWaitingState('Alice', 3), 'Bob');
        $state['scoreboard'] = ['X' => 1, 'O' => 0];
        $state['winner'] = 'X';
        $state['roundComplete'] = true;
        $state['statusMessage'] = 'Alice wins this round.';

        $room = GameRoom::create([
            'code' => 'NEXTRD',
            'player_x_name' => 'Alice',
            'player_x_token' => 'token-x',
            'player_o_name' => 'Bob',
            'player_o_token' => 'token-o',
            'match_length' => 3,
            'status' => 'active',
            'state' => $state,
        ]);

        $response = $this
            ->withSession(['room_tokens.'.$room->code => 'token-x'])
            ->postJson(route('rooms.next-round', $room));

        $response->assertOk();
        $response->assertJsonPath('state.roundNumber', 2);
        $response->assertJsonPath('state.currentPlayer', 'X');
        $response->assertJsonPath('state.playerNames.X', 'Bob');
        $response->assertJsonPath('state.playerNames.O', 'Alice');
        $response->assertJsonPath('state.scoreboard.X', 0);
        $response->assertJsonPath('state.scoreboard.O', 1);
        $response->assertJsonPath('state.roundComplete', false);
    }

    public function test_state_endpoint_reflects_swapped_player_mark_after_next_round(): void
    {
        $service = app(QuantumGameStateService::class);
        $state = $service->activateRoom($service->createWaitingState('Alice', 3), 'Bob');
        $state['scoreboard'] = ['X' => 1, 'O' => 0];
        $state['winner'] = 'X';
        $state['roundComplete'] = true;

        $room = GameRoom::create([
            'code' => 'SWAPMK',
            'player_x_name' => 'Alice',
            'player_x_token' => 'token-x',
            'player_o_name' => 'Bob',
            'player_o_token' => 'token-o',
            'match_length' => 3,
            'status' => 'active',
            'state' => $service->nextRound($state),
        ]);

        $response = $this
            ->withSession(['room_tokens.'.$room->code => 'token-x'])
            ->getJson(route('rooms.state', $room));

        $response->assertOk();
        $response->assertJsonPath('room.player_mark', 'O');
        $response->assertJsonPath('state.playerNames.X', 'Bob');
        $response->assertJsonPath('state.playerNames.O', 'Alice');
    }

    public function test_tournament_result_uses_live_player_side_after_round_swap(): void
    {
        $tournament = $this->createTournament([
            'name' => 'Swap Winner Room',
            'slug' => 'swap-winner-room',
            'status' => 'active',
        ]);

        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'number' => 1,
            'status' => 'active',
        ]);

        $playerOne = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Alice',
            'seed' => 1,
            'status' => 'active',
        ]);

        $playerTwo = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'display_name' => 'Bob',
            'seed' => 2,
            'status' => 'active',
        ]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'table_number' => 1,
            'player_one_id' => $playerOne->id,
            'player_two_id' => $playerTwo->id,
            'result_type' => 'pending',
            'time_limit_minutes' => 4,
            'status' => 'pending',
        ]);

        $service = app(QuantumGameStateService::class);
        $state = $service->activateRoom($service->createWaitingState('Alice', 3), 'Bob');
        $state['scoreboard'] = ['X' => 1, 'O' => 0];
        $state['winner'] = 'X';
        $state['roundComplete'] = true;
        $state = $service->nextRound($state);
        $state['scoreboard'] = ['X' => 1, 'O' => 1];
        $state['winner'] = 'X';
        $state['roundComplete'] = true;
        $state['matchWinner'] = 'X';
        $state['statusMessage'] = 'Bob wins the match.';

        $room = GameRoom::create([
            'tournament_match_id' => $match->id,
            'code' => 'SWPWIN',
            'player_x_name' => 'Alice',
            'player_x_token' => 'token-x',
            'player_o_name' => 'Bob',
            'player_o_token' => 'token-o',
            'match_length' => 3,
            'status' => 'completed',
            'state' => $state,
        ]);

        app(TournamentService::class)->syncMatchResultFromRoom($room);

        $match->refresh();
        $playerOne->refresh();
        $playerTwo->refresh();

        $this->assertSame('player_two_win', $match->result_type);
        $this->assertSame($playerTwo->id, $match->winner_participant_id);
        $this->assertSame(0, $playerOne->wins);
        $this->assertSame(1, $playerTwo->wins);
        $this->assertSame(0, $playerOne->points);
        $this->assertSame(3, $playerTwo->points);
    }

    private function createTournament(array $overrides = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Tournament '.Str::lower(Str::random(6)),
            'slug' => 'tournament-'.Str::lower(Str::random(6)),
            'join_code' => Str::upper(Str::random(6)),
            'match_length' => 3,
            'rounds_count' => 6,
            'advancing_count' => 2,
            'game_time_limit_minutes' => 4,
            'status' => 'registration',
        ], $overrides));
    }
}
