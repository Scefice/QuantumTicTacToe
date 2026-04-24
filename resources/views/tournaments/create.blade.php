@extends('layouts.app')

@section('title', 'Create Tournament Room')

@section('content')
    <section class="hero hero--compact">
        <div class="hero__copy">
            <p class="eyebrow">New room</p>
            <h1>Create Tournament</h1>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('tournaments.store') }}" class="admin-form">
            @csrf

            <label class="field">
                <span>Room name</span>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>

            <label class="field">
                <span>Teacher name</span>
                <input type="text" name="creator_name" value="{{ old('creator_name') }}">
            </label>

            <label class="field">
                <span>Each match</span>
                <select name="match_length">
                    <option value="3" @selected(old('match_length', $defaults['match_length']) == 3)>Best of 3</option>
                    <option value="5" @selected(old('match_length') == 5)>Best of 5</option>
                </select>
            </label>

            <label class="field">
                <span>Top players advancing</span>
                <input type="number" name="advancing_count" value="{{ old('advancing_count', $defaults['advancing_count']) }}" min="1" max="20" required>
            </label>

            <div class="field field--static">
                <span>Rounds</span>
                <strong>{{ $defaults['rounds_count'] }}</strong>
            </div>

            <div class="field field--static">
                <span>Time per game</span>
                <strong>{{ $defaults['game_time_limit_minutes'] }} minutes</strong>
            </div>

            <div class="hero__actions">
                <button type="submit" class="button button--primary">Create Room</button>
                <a class="button button--ghost" href="{{ route('tournaments.index') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
