@extends('layouts.app')

@section('title', 'Quantum Tic-Tac-Toe Home')

@section('content')
    <section class="hero">
        <div class="hero__copy">
            <p class="eyebrow">Local two-player classroom game</p>
            <h1>Play a strange version of tic-tac-toe where one move can start in two places.</h1>
            <p class="hero__lead">
                Each turn, a player puts the same move into two squares at the same time. Later, some of those
                floating moves snap into one real square, and only the real X and O marks can win.
            </p>

            <div class="hero__actions">
                <a class="button button--primary" href="{{ route('game') }}">Start Game</a>
                <a class="button button--ghost" href="#how-it-works">How It Works</a>
            </div>
        </div>

        <aside class="hero__card">
            <h2>What students should notice</h2>
            <ul class="simple-list">
                <li>Each move is labeled by player and move number, such as X1 or O2.</li>
                <li>One move can sit in two squares for a while.</li>
                <li>If the links make a loop, some marks must settle down.</li>
                <li>Settled marks become normal X and O marks.</li>
                <li>Only normal settled marks count for a win.</li>
            </ul>
        </aside>
    </section>

    <section id="how-it-works" class="info-grid">
        <article class="info-card">
            <h2>One Move, Two Squares</h2>
            <p>
                A player clicks two squares. That single move shows up in both of them until the board forces it to
                choose one.
            </p>
        </article>

        <article class="info-card">
            <h2>Loop</h2>
            <p>
                If the links between moves make a loop, the board can no longer keep everything floating.
            </p>
        </article>

        <article class="info-card">
            <h2>Settle</h2>
            <p>
                The linked moves settle into single squares and become regular X and O marks. Then the game keeps
                going.
            </p>
        </article>
    </section>
@endsection
