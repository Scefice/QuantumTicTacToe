@extends('layouts.app')

@section('title', 'Tournament Rooms')

@section('content')
    <section class="hero hero--compact">
        <div class="hero__copy">
            <p class="eyebrow">Tournament</p>
            <h1>Join Or Create</h1>

            <div class="hero__actions">
                <a class="button button--primary" href="{{ route('tournaments.create') }}">Create Room</a>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <section class="message-box">
            {{ $errors->first() }}
        </section>
    @endif

    @if (session('success'))
        <section class="message-box">{{ session('success') }}</section>
    @endif

    <section class="dashboard-grid">
        <article class="panel">
            <h2>Join With Code</h2>
            <form method="POST" action="{{ route('tournaments.join') }}" class="admin-form admin-form--compact">
                @csrf
                <label class="field">
                    <span>Code</span>
                    <input type="text" name="join_code" value="{{ old('join_code') }}" maxlength="6" required>
                </label>
                <label class="field">
                    <span>Your name</span>
                    <input type="text" name="display_name" value="{{ old('display_name') }}" required>
                </label>
                <button type="submit" class="button button--primary">Join Tournament</button>
            </form>
        </article>
    </section>
@endsection
