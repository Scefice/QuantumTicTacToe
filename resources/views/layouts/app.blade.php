<!DOCTYPE html>
<html
    lang="en"
    data-reverb-app-key="{{ config('broadcasting.connections.reverb.key') }}"
    data-reverb-host="{{ env('VITE_REVERB_HOST', parse_url(config('app.url'), PHP_URL_HOST)) }}"
    data-reverb-port="{{ env('VITE_REVERB_PORT', parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' ? 443 : 80) }}"
    data-reverb-scheme="{{ env('VITE_REVERB_SCHEME', parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https') }}"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Quantum Tic-Tac-Toe')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="site-shell">
        <header class="site-header">
            <a class="site-brand" href="{{ route('home') }}">
                <span class="site-brand__badge">QTTT</span>
                <span>Quantum Tic-Tac-Toe</span>
            </a>

            <div class="site-header__actions">
                <nav class="site-nav" aria-label="Primary navigation">
                    <a href="{{ route('home') }}">Home</a>
                    <a href="{{ route('game') }}">Play</a>
                    <a href="{{ route('rooms.index') }}">Online Rooms</a>
                    <a href="{{ route('tournaments.index') }}">Tournaments</a>
                </nav>

                <button type="button" class="button button--ghost button--small" data-global-theme-toggle>Night</button>
            </div>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>
</body>
</html>
