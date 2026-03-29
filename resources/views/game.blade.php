@extends('layouts.app')

@section('title', 'Play Quantum Tic-Tac-Toe')

@section('content')
    <section
        class="game-page"
        data-quantum-game
        data-home-url="{{ route('home') }}"
    >
        <div class="page-toolbar">
            <button type="button" class="button button--ghost" data-theme-toggle>Night Mode</button>
        </div>

        <div class="game-page__intro game-page__intro--compact">
            <div>
                <p class="eyebrow">QTTT</p>
                <h1>Quantum Tic-Tac-Toe</h1>
            </div>
        </div>

        <div class="game-layout">
            <section class="panel game-panel">
                <section class="setup-box" data-setup-box>
                    <div class="setup-box__header">
                        <div>
                            <h2>Start</h2>
                        </div>
                        <span class="setup-badge">X / O</span>
                    </div>

                    <form class="setup-form" data-setup-form>
                        <label class="field">
                            <span>X</span>
                            <input type="text" name="playerX" value="Player 1" maxlength="20" placeholder="Player X">
                        </label>

                        <label class="field">
                            <span>O</span>
                            <input type="text" name="playerO" value="Player 2" maxlength="20" placeholder="Player O">
                        </label>

                        <label class="field">
                            <span>Match</span>
                            <select name="matchLength">
                                <option value="3">Best of 3</option>
                                <option value="5">Best of 5</option>
                            </select>
                        </label>

                        <button type="submit" class="button button--primary">Start Match</button>
                    </form>
                </section>

                <div class="match-strip">
                    <div>
                        <span class="status-strip__label">X Player</span>
                        <strong data-player-x-name>Player 1</strong>
                    </div>
                    <div>
                        <span class="status-strip__label">O Player</span>
                        <strong data-player-o-name>Player 2</strong>
                    </div>
                    <div>
                        <span class="status-strip__label">Score</span>
                        <strong data-match-score>0 - 0</strong>
                    </div>
                </div>

                <div class="board" data-board role="grid" aria-label="Quantum Tic-Tac-Toe board"></div>

                <div class="game-actions">
                    <button type="button" class="button button--primary" data-new-game>New Match</button>
                    <button type="button" class="button button--ghost" data-next-round>Next Round</button>
                    <button type="button" class="button button--ghost" data-reset-game>Reset Board</button>
                    <button type="button" class="button button--ghost" data-toggle-rules>Help</button>
                </div>

                <div class="message-box" data-message-box aria-live="polite"></div>
            </section>

            <aside class="sidebar">
                <section class="panel panel--links">
                    <h2>Links</h2>
                    <div class="link-map" data-link-map></div>
                    <p class="link-map__note" data-link-map-note>
                        Watch the links.
                    </p>
                </section>

                <section class="panel" data-rules-panel>
                    <h2>Help</h2>
                    <div class="rules-copy">
                        <p>1. Pick two squares.</p>
                        <p>2. Loops make marks settle.</p>
                        <p>3. Real marks win.</p>
                    </div>
                </section>
            </aside>
        </div>
    </section>
@endsection
