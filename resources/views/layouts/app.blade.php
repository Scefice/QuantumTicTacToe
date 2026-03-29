<!DOCTYPE html>
<html lang="en">
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

            <nav class="site-nav" aria-label="Primary navigation">
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('game') }}">Play</a>
            </nav>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>
</body>
</html>
