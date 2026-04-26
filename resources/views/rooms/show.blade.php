@extends('layouts.app')

@section('title', 'Room '.$room->code)

@section('content')
    <section
        class="game-page"
        data-online-room
        @if ($tournamentReturnUrl)
            data-tournament-room="true"
        @endif
        data-room-code="{{ $room->code }}"
        data-room-token="{{ $roomToken }}"
        data-player-mark="{{ $playerMark }}"
        data-player-name="{{ $playerName }}"
        data-player-one-name="{{ $room->player_x_name }}"
        data-player-two-name="{{ $room->player_o_name }}"
        data-room-state-url="{{ route('rooms.state', $room) }}"
        data-room-pick-url="{{ route('rooms.pick', $room) }}"
        data-room-next-round-url="{{ route('rooms.next-round', $room) }}"
        data-room-reset-board-url="{{ route('rooms.reset-board', $room) }}"
        data-room-reset-match-url="{{ route('rooms.reset-match', $room) }}"
        @if ($tournamentReturnUrl)
            data-tournament-return-url="{{ $tournamentReturnUrl }}"
        @endif
    >
        <div class="page-toolbar">
            <span class="status-pill">Room {{ $room->code }}</span>
            <button type="button" class="button button--ghost" data-theme-toggle>Night</button>
        </div>

        <div class="game-page__intro game-page__intro--compact">
            <div>
                <p class="eyebrow">{{ $tournamentReturnUrl ? 'Tournament match' : 'Online room' }}</p>
                <h1>{{ $tournamentReturnUrl ? 'Match' : 'Quantum Tic-Tac-Toe' }}</h1>
            </div>
        </div>

        <div class="game-layout">
            <section class="panel game-panel">
                <section class="setup-box is-hidden" data-setup-box data-ignore-setup></section>

                <div class="match-strip">
                    <div>
                        <span class="status-strip__label">{{ $tournamentReturnUrl ? 'Player 1' : 'X' }}</span>
                        <strong data-player-x-name>{{ $room->player_x_name }}@unless($tournamentReturnUrl) (X)@endunless</strong>
                    </div>
                    <div>
                        <span class="status-strip__label">{{ $tournamentReturnUrl ? 'Player 2' : 'O' }}</span>
                        <strong data-player-o-name>{{ $room->player_o_name ?: 'Waiting...' }}@if($room->player_o_name && ! $tournamentReturnUrl) (O) @endif</strong>
                    </div>
                    <div>
                        <span class="status-strip__label">{{ $tournamentReturnUrl ? 'Sides' : 'Score' }}</span>
                        <strong data-match-score>
                            @if ($tournamentReturnUrl)
                                X: {{ $state['playerNames']['X'] ?? $room->player_x_name }} · O: {{ $state['playerNames']['O'] ?? $room->player_o_name }}
                            @else
                                {{ $state['scoreboard']['X'] ?? 0 }} - {{ $state['scoreboard']['O'] ?? 0 }}
                            @endif
                        </strong>
                    </div>
                </div>

                <div class="board" data-board role="grid" aria-label="Quantum Tic-Tac-Toe online board"></div>

                <div class="game-actions">
                    @if (! $tournamentReturnUrl)
                        <button type="button" class="button button--primary" data-new-game>Reset Match</button>
                        <button type="button" class="button button--ghost" data-next-round>Next Round</button>
                        <button type="button" class="button button--ghost" data-reset-game>Reset Board</button>
                        <button type="button" class="button button--ghost" data-toggle-rules>Help</button>
                    @endif
                </div>

                <div class="message-box" data-message-box aria-live="polite"></div>
            </section>

            <aside class="sidebar">
                <section class="panel panel--links">
                    <h2>Links</h2>
                    <div class="link-map" data-link-map></div>
                    <p class="link-map__note" data-link-map-note></p>
                </section>

                @if (! $tournamentReturnUrl)
                    <section class="panel" data-rules-panel>
                        <h2>Help</h2>
                        <div class="rules-copy">
                            <p>1. Pick two squares.</p>
                            <p>2. Loops make marks settle.</p>
                            <p>3. Real marks win.</p>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </section>
@endsection
