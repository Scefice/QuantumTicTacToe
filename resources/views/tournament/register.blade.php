<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Registration</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <div class="container">
        <h1>Register for the Tournament</h1>
        <form action="/register" method="POST">
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">Player Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
</body>
</html>