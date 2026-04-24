@extends('layouts.app')

@section('title', 'Online Rooms')

@section('content')
    <section class="hero hero--compact">
        <div class="hero__copy">
            <p class="eyebrow">Online room</p>
            <h1>Create Or Join</h1>
        </div>
    </section>

    <section class="dashboard-grid">
        <article class="panel form-panel">
            <h2>Create Room</h2>
            <form method="POST" action="{{ route('rooms.create') }}" class="admin-form admin-form--compact">
                @csrf
                <label class="field">
                    <span>Your name</span>
                    <input type="text" name="player_x_name" required>
                </label>
                <label class="field">
                    <span>Match</span>
                    <select name="match_length">
                        <option value="3">Best of 3</option>
                        <option value="5">Best of 5</option>
                    </select>
                </label>
                <button type="submit" class="button button--primary">Create</button>
            </form>
        </article>

        <article class="panel form-panel">
            <h2>Join Room</h2>
            <form method="POST" action="{{ route('rooms.join') }}" class="admin-form admin-form--compact">
                @csrf
                <label class="field">
                    <span>Room code</span>
                    <input type="text" name="code" maxlength="6" required>
                </label>
                <label class="field">
                    <span>Your name</span>
                    <input type="text" name="player_o_name" required>
                </label>
                <button type="submit" class="button button--primary">Join</button>
            </form>
        </article>
    </section>
@endsection
