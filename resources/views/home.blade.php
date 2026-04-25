@extends('layouts.app')

@section('title', 'Quantum Tic-Tac-Toe Home')

@section('content')
    <section class="hero hero--simple">
        <div class="hero__copy">
            <p class="eyebrow">Classroom game</p>
            <h1>Quantum Tic-Tac-Toe</h1>
            <p class="hero__lead">Pick two squares each turn. Loops make marks settle. Real marks win.</p>

            <div class="hero__actions">
                <a class="button button--primary" href="{{ route('game') }}">Play Here</a>
                <a class="button button--ghost" href="{{ route('rooms.index') }}">Online Room</a>
                <a class="button button--ghost" href="{{ route('tournaments.index') }}">Tournaments</a>
            </div>
        </div>

        <aside class="hero__card">
            <h2>Quick Start</h2>
            <ul class="simple-list simple-list--tight">
                <li>Two players</li>
                <li>Pick two squares</li>
                <li>Loops settle</li>
                <li>Make three real marks</li>
            </ul>
        </aside>
    </section>
@endsection
