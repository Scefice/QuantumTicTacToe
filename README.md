# Quantum Tic-Tac-Toe

A local two-player educational web game built with Laravel, Blade, CSS, and vanilla JavaScript.

This project is meant to show the main idea behind Quantum Tic-Tac-Toe in a way that is playable in the browser:

- one move can begin in two squares at once
- linked moves can form a loop
- when a loop appears, the linked moves settle into real board positions
- only real settled marks count for winning

The Laravel side only serves pages and assets. The actual game logic runs in the browser.

## Features

- local two-player play on one screen
- online room play and tournament rooms
- no login or accounts
- match setup with player names
- best-of-3 or best-of-5 match mode
- light mode and night mode
- animated collapse sequence
- side diagram with curved links between entangled moves
- responsive UI for desktop and mobile

## Tech Stack

- Laravel
- Blade templates
- Vite
- vanilla JavaScript
- plain CSS

## Run Locally

From the project root:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

For frontend development with live rebuilds:

```bash
npm run dev
```

## Deploy To Railway

Use [`.env.railway.example`](/abs/path/c/Users/davis/Desktop/Bachelors%20Thesis/QuantumTicTacToe/QuantumTicTacToe/.env.railway.example) as the production template.

Minimum production setup:

```bash
php artisan key:generate --show
php artisan migrate --force
```

Railway notes:

- attach a `Postgres` service and keep `DB_CONNECTION=pgsql`
- set `DB_URL` to `${{Postgres.DATABASE_URL}}`
- set a real `APP_KEY`
- create a separate `Reverb` service that runs `php artisan reverb:start --host=0.0.0.0 --port=8080`
- create a separate worker service that runs `php artisan queue:work --verbose --tries=3 --timeout=90`
- set `VITE_REVERB_HOST` to your public domain
- set `REVERB_HOST` to the internal Railway hostname for the Reverb service, for example `reverb.railway.internal`

## How the Game Works

Each turn, a player places one numbered move such as `X1` or `O2` into two different squares.

Those two copies are linked. They stay unresolved until the game detects that the network of links makes a loop.

When a loop appears:

1. the game finds the moves involved in the loop
2. it expands that to the connected unresolved part of the board
3. it settles those moves into single squares using a fixed deterministic rule
4. the UI plays that collapse step by step so the player can watch it happen

After that, the board continues with the new settled marks.

## Winning Rule

Only real settled `X` and `O` marks count for victory.

If a player makes three real marks in a row, they win the round.

If the round ends without a winning line, it is a draw.

## Project Structure

### Laravel pages and routing

- `routes/web.php`
- `app/Http/Controllers/GameController.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/home.blade.php`
- `resources/views/game.blade.php`

### Frontend entry files

- `resources/js/app.js`
- `resources/css/app.css`

### Game logic

- `resources/js/quantum-tic-tac-toe/state.js`
  - initial state, match state, board state
- `resources/js/quantum-tic-tac-toe/engine.js`
  - turn flow, match flow, round resets, win/draw handling
- `resources/js/quantum-tic-tac-toe/cycle-detector.js`
  - link graph building and loop detection
- `resources/js/quantum-tic-tac-toe/collapse-resolver.js`
  - deterministic collapse resolution
- `resources/js/quantum-tic-tac-toe/renderer.js`
  - board rendering, link map rendering, visual collapse states
- `resources/js/quantum-tic-tac-toe/index.js`
  - event wiring, theme toggle, collapse playback timing

## Cycle Detection

Cycle detection is handled in:

- `resources/js/quantum-tic-tac-toe/cycle-detector.js`

The code treats unresolved moves as edges between cells.

When a new move is added, the game checks whether its two endpoint cells were already connected by earlier unresolved moves.

If they were, the new move closes a loop.

That file then gathers the full unresolved connected component affected by the loop so the collapse can be applied to the whole relevant network.

## Collapse Rule

Collapse resolution is handled in:

- `resources/js/quantum-tic-tac-toe/collapse-resolver.js`

The current rule is deterministic:

- the move that closes the loop settles first
- it chooses its lower-indexed square first
- any conflicting linked move is then forced into its other square
- this continues until the affected unresolved moves are all settled

This rule was chosen so the behavior is consistent and easier to follow.

## Visual Collapse Playback

The collapse animation is handled by:

- `resources/js/quantum-tic-tac-toe/index.js`
- `resources/js/quantum-tic-tac-toe/renderer.js`

When a loop settles, the UI:

- highlights the linked loop in the side diagram
- shows the settling one move at a time
- pulses the chosen square on the main board
- marks already settled squares as the sequence continues

## How to Change the Rules Later

If you want to change the gameplay, these are the main files to edit:

- change turn flow or match rules in `resources/js/quantum-tic-tac-toe/engine.js`
- change loop detection in `resources/js/quantum-tic-tac-toe/cycle-detector.js`
- change the settle rule in `resources/js/quantum-tic-tac-toe/collapse-resolver.js`
- change how the sequence looks in `resources/js/quantum-tic-tac-toe/renderer.js`

## Notes

- game state is kept in browser memory only
- no database is used by the gameplay
- refreshing the page resets the current in-browser state

## License

This project uses the MIT license.
