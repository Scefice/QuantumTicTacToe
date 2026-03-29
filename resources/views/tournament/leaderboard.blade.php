<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Leaderboard</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <div class="container">
        <h1>Tournament Leaderboard</h1>
        <table class="table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Player</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($players as $rank => $player)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $player['name'] }}</td>
                    <td>{{ $player['score'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>