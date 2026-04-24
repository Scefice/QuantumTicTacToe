@extends('layouts.app')

@section('title', $tournament->name)

@section('content')
    <section class="hero hero--compact">
        <div class="hero__copy">
            <p class="eyebrow">Tournament</p>
            <h1>{{ $tournament->name }}</h1>

            <div class="hero__actions">
                <a class="button button--ghost" href="{{ route('tournaments.index') }}">Back</a>
            </div>
        </div>
    </section>

    @if (session('success'))
        <section class="message-box">{{ session('success') }}</section>
    @endif

    @if ($errors->any())
        <section class="message-box">{{ $errors->first() }}</section>
    @endif

    @if ($viewerMode === 'participant' && $viewerParticipant)
        <section
            class="dashboard-grid dashboard-grid--participant"
            data-tournament-participant
            data-tournament-id="{{ $tournament->id }}"
            data-participant-state-url="{{ route('tournaments.participant-state', $tournament) }}"
            data-participant-play-url="{{ route('tournaments.play', $tournament) }}"
        >
            <article class="panel panel--accent participant-panel">
                <h2>{{ $viewerParticipant->display_name }}</h2>
                <div class="meta-grid meta-grid--simple">
                    <div><strong>Status</strong><span data-tournament-status>{{ ucfirst($tournament->status) }}</span></div>
                    <div><strong>Code</strong><span>{{ $tournament->join_code }}</span></div>
                </div>

                <p class="participant-message" data-participant-message>
                    @if ($assignedMatch)
                        Go to table {{ $assignedMatch->table_number }}.
                    @elseif ($tournament->status === 'registration')
                        Waiting to start.
                    @elseif ($tournament->status === 'completed')
                        Finished.
                    @else
                        Waiting for next game.
                    @endif
                </p>

                @if ($assignedMatch)
                    <div class="hero__actions participant-actions">
                        <a class="button button--primary" href="{{ route('tournaments.play', $tournament) }}" data-play-now>Go To Game</a>
                    </div>
                @endif
            </article>

            <article class="panel participant-panel">
                <h2>Match</h2>
                <div class="meta-grid meta-grid--simple">
                    <div><strong>Round</strong><span data-assigned-round>{{ $assignedMatch?->round?->number ?? '-' }}</span></div>
                    <div><strong>Table</strong><span data-assigned-table>{{ $assignedMatch?->table_number ?? '-' }}</span></div>
                    <div><strong>Opponent</strong><span data-assigned-opponent>{{ $assignedMatch ? ($assignedMatch->player_one_id === $viewerParticipant->id ? $assignedMatch->playerTwo?->display_name : $assignedMatch->playerOne?->display_name) : '-' }}</span></div>
                    <div><strong>Match</strong><span>Best of {{ $tournament->match_length }}</span></div>
                    <div><strong>Time</strong><span>{{ $tournament->game_time_limit_minutes }} min</span></div>
                </div>
            </article>
        </section>
    @else
        <section class="simple-stack" data-tournament-organizer data-tournament-id="{{ $tournament->id }}">
            <article class="panel panel--accent organizer-panel">
                <h2>{{ $tournament->join_code }}</h2>

                <div class="meta-grid meta-grid--simple">
                    <div><strong>Status</strong><span>{{ ucfirst($tournament->status) }}</span></div>
                    <div><strong>Players</strong><span>{{ $tournament->participants->count() }}</span></div>
                    <div><strong>Match</strong><span>Best of {{ $tournament->match_length }}</span></div>
                    <div><strong>Rounds</strong><span>{{ $tournament->rounds_count }}</span></div>
                    <div><strong>Time</strong><span>{{ $tournament->game_time_limit_minutes }} min</span></div>
                </div>

                <div class="hero__actions organizer-actions">
                    @if ($viewerMode === 'organizer' && $tournament->status === 'registration')
                        <form method="POST" action="{{ route('tournaments.start', $tournament) }}">
                            @csrf
                            <button type="submit" class="button button--primary button--large">Start Tournament</button>
                        </form>
                    @endif

                    @if ($viewerMode === 'organizer' && $tournament->status === 'active')
                        <form method="POST" action="{{ route('tournaments.next-round', $tournament) }}">
                            @csrf
                            <button type="submit" class="button button--primary button--large">Next Round</button>
                        </form>
                    @endif
                </div>
            </article>

            @if ($viewerMode === 'organizer' && $tournament->status === 'registration')
                <article class="panel">
                    <h2>Players</h2>

                    <form method="POST" action="{{ route('tournaments.participants.store', $tournament) }}" class="admin-form admin-form--compact">
                        @csrf
                        <label class="field">
                            <span>Name</span>
                            <input type="text" name="display_name" required>
                        </label>
                        <button type="submit" class="button button--primary">Add</button>
                    </form>

                    <ul class="simple-list simple-list--boxed">
                        @forelse ($tournament->participants->sortBy('seed') as $participant)
                            <li>{{ $participant->display_name }}</li>
                        @empty
                            <li>No players yet.</li>
                        @endforelse
                    </ul>
                </article>
            @endif

            @if ($tournament->status === 'active' && $currentRound)
                <article class="panel">
                    <h2>Round {{ $currentRound->number }}</h2>

                    <div class="simple-pairings">
                        @foreach ($currentRound->matches->sortBy('table_number') as $match)
                            <div class="pairing-card">
                                <strong>Table {{ $match->table_number }}</strong>
                                <span>{{ $match->playerOne->display_name }}</span>
                                <span>{{ $match->playerTwo?->display_name ?: 'Bye' }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif

            @if ($tournament->status === 'completed')
                <article class="panel">
                    <h2>Final Order</h2>

                    <ol class="simple-list simple-list--boxed">
                        @foreach ($standings as $participant)
                            <li>{{ $participant->display_name }}</li>
                        @endforeach
                    </ol>
                </article>
            @endif
        </section>
    @endif
@endsection
