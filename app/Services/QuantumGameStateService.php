<?php

namespace App\Services;

class QuantumGameStateService
{
    private const WINNING_LINES = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6],
    ];

    public function createWaitingState(string $playerXName, int $matchLength): array
    {
        return [
            'cells' => $this->createCells(),
            'moves' => [],
            'currentPlayer' => 'X',
            'nextMoveNumber' => 1,
            'selectedCells' => [],
            'hoveredMoveId' => null,
            'winner' => null,
            'draw' => false,
            'roundComplete' => false,
            'matchWinner' => null,
            'matchStarted' => false,
            'matchLength' => $matchLength,
            'winsNeeded' => (int) ceil($matchLength / 2),
            'scoreboard' => ['X' => 0, 'O' => 0],
            'playerNames' => ['X' => $playerXName, 'O' => 'Waiting...'],
            'roundNumber' => 1,
            'collapseLog' => [],
            'statusMessage' => 'Waiting for player O to join.',
            'alert' => false,
            'lastEvent' => [
                'type' => 'idle',
                'moveIds' => [],
                'cells' => [],
                'explanation' => 'No links yet.',
                'resolvedMoves' => [],
            ],
            'boardMode' => 'Waiting to start',
        ];
    }

    public function activateRoom(array $state, string $playerOName): array
    {
        $state = $this->normalizeState($state);
        $state['matchStarted'] = true;
        $state['playerNames']['O'] = $playerOName;
        $state['statusMessage'] = sprintf('Round %d. %s starts as X.', $state['roundNumber'], $state['playerNames']['X']);
        $state['boardMode'] = $this->getBoardMode($state);

        return $state;
    }

    public function resetMatch(array $state): array
    {
        $state = $this->normalizeState($state);

        return $this->activateRoom(
            $this->createWaitingState($state['playerNames']['X'], (int) $state['matchLength']),
            $state['playerNames']['O']
        );
    }

    public function resetBoard(array $state): array
    {
        $state = $this->normalizeState($state);

        if (!$state['matchStarted']) {
            return $state;
        }

        $this->clearBoardForNextRound($state);
        $state['statusMessage'] = sprintf('Round %d was reset. %s starts again.', $state['roundNumber'], $state['playerNames']['X']);
        $state['boardMode'] = $this->getBoardMode($state);

        return $state;
    }

    public function nextRound(array $state): array
    {
        $state = $this->normalizeState($state);

        if (!$state['matchStarted'] || !$state['roundComplete'] || $state['matchWinner']) {
            return $state;
        }

        $state['roundNumber']++;
        $this->swapRoundSides($state);
        $this->clearBoardForNextRound($state);
        $state['statusMessage'] = sprintf('Round %d. %s starts as X.', $state['roundNumber'], $state['playerNames']['X']);
        $state['boardMode'] = $this->getBoardMode($state);

        return $state;
    }

    public function selectCell(array $state, string $player, int $cellIndex): array
    {
        $state = $this->normalizeState($state);

        if (!$state['matchStarted']) {
            $state['statusMessage'] = 'Waiting for both players.';
            $state['alert'] = true;
            return $state;
        }

        if ($state['currentPlayer'] !== $player) {
            $state['statusMessage'] = sprintf('It is %s turn.', $state['playerNames'][$state['currentPlayer']]);
            $state['alert'] = true;
            return $state;
        }

        if ($state['roundComplete']) {
            $state['statusMessage'] = $state['matchWinner']
                ? sprintf('%s already won the match.', $state['playerNames'][$state['matchWinner']])
                : 'This round is over.';
            $state['alert'] = true;
            return $state;
        }

        if (!isset($state['cells'][$cellIndex])) {
            return $state;
        }

        $cell = $state['cells'][$cellIndex];

        if ($cell['classicalMark'] !== null) {
            $state['statusMessage'] = 'That square already has a real mark.';
            $state['alert'] = true;
            return $state;
        }

        if (in_array($cellIndex, $state['selectedCells'], true)) {
            $state['statusMessage'] = 'Pick two different squares.';
            $state['alert'] = true;
            return $state;
        }

        $state['selectedCells'][] = $cellIndex;
        $state['alert'] = false;

        if (count($state['selectedCells']) === 1) {
            $state['statusMessage'] = sprintf('%s picked cell %d. Choose one more square.', $state['playerNames'][$player], $cellIndex + 1);
            $state['boardMode'] = $this->getBoardMode($state);
            return $state;
        }

        [$firstCell, $secondCell] = $state['selectedCells'];
        $move = [
            'id' => 'm'.$state['nextMoveNumber'],
            'moveNumber' => $state['nextMoveNumber'],
            'player' => $player,
            'cells' => [$firstCell, $secondCell],
            'collapsed' => false,
            'collapsedCell' => null,
        ];

        $state['moves'][] = $move;
        $this->addQuantumMoveToBoard($state, $move);

        $cycle = $this->detectCycle($state, $move);

        if ($cycle !== null) {
            $collapseComponent = $this->collectCollapseComponent($state, $cycle);
            $collapseResult = $this->resolveCollapse($state, $collapseComponent, $cycle);

            $state['collapseLog'] = array_merge([[
                'moveId' => $move['id'],
                'text' => 'Loop detected and settled.',
            ]], $state['collapseLog']);

            $state['statusMessage'] = sprintf('A loop was made after %s%d.', $move['player'], $move['moveNumber']);
            $state['alert'] = true;
            $state['lastEvent'] = [
                'type' => 'collapse',
                'moveIds' => $collapseComponent['moveIds'],
                'cells' => $collapseComponent['cells'],
                'explanation' => 'Loop closed.',
                'resolvedMoves' => $collapseResult['resolvedMoves'],
            ];
        } else {
            array_unshift($state['collapseLog'], [
                'moveId' => $move['id'],
                'text' => sprintf('%s%d linked two squares.', $move['player'], $move['moveNumber']),
            ]);

            $state['statusMessage'] = sprintf('%s%d placed.', $move['player'], $move['moveNumber']);
            $state['lastEvent'] = [
                'type' => 'move',
                'moveIds' => [$move['id']],
                'cells' => [$firstCell, $secondCell],
                'explanation' => 'Link added.',
                'resolvedMoves' => [],
            ];
        }

        $state['selectedCells'] = [];
        $this->updateOutcome($state);

        if (!$state['roundComplete']) {
            $state['currentPlayer'] = $state['currentPlayer'] === 'X' ? 'O' : 'X';
            $state['nextMoveNumber']++;
            $state['alert'] = false;
        }

        $state['boardMode'] = $this->getBoardMode($state);

        return $state;
    }

    private function createCells(): array
    {
        $cells = [];

        for ($index = 0; $index < 9; $index++) {
            $cells[] = [
                'index' => $index,
                'classicalMark' => null,
                'classicalMoveId' => null,
                'quantumMoveIds' => [],
            ];
        }

        return $cells;
    }

    private function normalizeState(array $state): array
    {
        $state['collapseLog'] ??= [];
        $state['selectedCells'] ??= [];
        $state['hoveredMoveId'] ??= null;
        $state['lastEvent'] ??= [
            'type' => 'idle',
            'moveIds' => [],
            'cells' => [],
            'explanation' => 'No links yet.',
            'resolvedMoves' => [],
        ];
        $state['boardMode'] = $this->getBoardMode($state);

        return $state;
    }

    private function getWinningMarks(array $state): array
    {
        $winningMarks = [];

        foreach (self::WINNING_LINES as $line) {
            $firstMark = $state['cells'][$line[0]]['classicalMark'];

            if ($firstMark === null) {
                continue;
            }

            if ($state['cells'][$line[1]]['classicalMark'] === $firstMark && $state['cells'][$line[2]]['classicalMark'] === $firstMark) {
                $winningMarks[] = $firstMark;
            }
        }

        return array_values(array_unique($winningMarks));
    }

    private function getBoardMode(array $state): string
    {
        if (!$state['matchStarted']) {
            return 'Waiting';
        }

        $classicalCount = count(array_filter($state['cells'], fn (array $cell) => $cell['classicalMark'] !== null));
        $unresolvedCount = count(array_filter($state['moves'], fn (array $move) => !$move['collapsed']));

        if ($state['roundComplete']) {
            return 'Round over';
        }

        if ($classicalCount === 0) {
            return 'Fresh';
        }

        if ($unresolvedCount === 0) {
            return 'Settled';
        }

        return 'Mixed';
    }

    private function clearBoardForNextRound(array &$state): void
    {
        $state['cells'] = $this->createCells();
        $state['moves'] = [];
        $state['currentPlayer'] = 'X';
        $state['nextMoveNumber'] = 1;
        $state['selectedCells'] = [];
        $state['hoveredMoveId'] = null;
        $state['winner'] = null;
        $state['draw'] = false;
        $state['roundComplete'] = false;
        $state['alert'] = false;
        $state['lastEvent'] = [
            'type' => 'idle',
            'moveIds' => [],
            'cells' => [],
            'explanation' => 'No links yet.',
            'resolvedMoves' => [],
        ];
    }

    private function swapRoundSides(array &$state): void
    {
        [$state['playerNames']['X'], $state['playerNames']['O']] = [$state['playerNames']['O'], $state['playerNames']['X']];
        [$state['scoreboard']['X'], $state['scoreboard']['O']] = [$state['scoreboard']['O'], $state['scoreboard']['X']];
    }

    private function addQuantumMoveToBoard(array &$state, array $move): void
    {
        foreach ($move['cells'] as $cellIndex) {
            $state['cells'][$cellIndex]['quantumMoveIds'][] = $move['id'];
            usort($state['cells'][$cellIndex]['quantumMoveIds'], function (string $left, string $right) use ($state): int {
                return $this->findMove($state, $left)['moveNumber'] <=> $this->findMove($state, $right)['moveNumber'];
            });
        }
    }

    private function updateOutcome(array &$state): void
    {
        $winningMarks = $this->getWinningMarks($state);

        if (count($winningMarks) > 1) {
            $state['winner'] = null;
            $state['draw'] = true;
            $state['roundComplete'] = true;
            $state['statusMessage'] = 'Draw.';
            $state['alert'] = true;
            return;
        }

        if (count($winningMarks) === 1) {
            $winner = $winningMarks[0];
            $state['winner'] = $winner;
            $state['draw'] = false;
            $state['roundComplete'] = true;
            $state['scoreboard'][$winner]++;
            $state['statusMessage'] = sprintf('%s wins this round.', $state['playerNames'][$winner]);

            if ($state['scoreboard'][$winner] >= $state['winsNeeded']) {
                $state['matchWinner'] = $winner;
                $state['statusMessage'] = sprintf('%s wins the match.', $state['playerNames'][$winner]);
            }

            $state['alert'] = true;
            return;
        }

        $boardIsFull = count(array_filter($state['cells'], fn (array $cell) => $cell['classicalMark'] !== null)) === 9;
        $available = count(array_filter($state['cells'], fn (array $cell) => $cell['classicalMark'] === null));

        if ($boardIsFull || $available < 2) {
            $state['winner'] = null;
            $state['draw'] = true;
            $state['roundComplete'] = true;
            $state['statusMessage'] = 'Draw.';
            $state['alert'] = true;
        }
    }

    private function detectCycle(array $state, array $move): ?array
    {
        $adjacency = $this->buildAdjacency($state, $move['id']);
        [$firstCell, $secondCell] = $move['cells'];
        $pathMoveIds = $this->findPath($adjacency, $firstCell, $secondCell);

        if ($pathMoveIds === null) {
            return null;
        }

        $cycleMoveIds = array_values(array_unique([...$pathMoveIds, $move['id']]));
        $cycleCells = [$firstCell, $secondCell];

        foreach ($cycleMoveIds as $moveId) {
            foreach ($this->findMove($state, $moveId)['cells'] as $cellIndex) {
                $cycleCells[] = $cellIndex;
            }
        }

        $cycleCells = array_values(array_unique($cycleCells));
        sort($cycleCells);

        return [
            'moveIds' => $cycleMoveIds,
            'cells' => $cycleCells,
            'triggeringMoveId' => $move['id'],
        ];
    }

    private function collectCollapseComponent(array $state, array $cycle): array
    {
        $pendingMoveIds = $cycle['moveIds'];
        $componentMoveIds = [];
        $componentCells = $cycle['cells'];

        while (!empty($pendingMoveIds)) {
            $moveId = array_pop($pendingMoveIds);

            if (in_array($moveId, $componentMoveIds, true)) {
                continue;
            }

            $componentMoveIds[] = $moveId;
            $move = $this->findMove($state, $moveId);

            foreach ($move['cells'] as $cellIndex) {
                $componentCells[] = $cellIndex;

                foreach ($state['cells'][$cellIndex]['quantumMoveIds'] as $linkedMoveId) {
                    $linkedMove = $this->findMove($state, $linkedMoveId);

                    if ($linkedMove !== null && !$linkedMove['collapsed'] && !in_array($linkedMoveId, $componentMoveIds, true)) {
                        $pendingMoveIds[] = $linkedMoveId;
                    }
                }
            }
        }

        $componentMoveIds = array_values(array_unique($componentMoveIds));
        sort($componentMoveIds);
        $componentCells = array_values(array_unique($componentCells));
        sort($componentCells);

        return [
            'moveIds' => $componentMoveIds,
            'cells' => $componentCells,
        ];
    }

    private function resolveCollapse(array &$state, array $collapseComponent, array $cycle): array
    {
        $resolutionOrder = [];
        $seedMove = $this->findMove($state, $cycle['triggeringMoveId']);
        $chosenSeedCell = min($seedMove['cells']);

        $this->assignMoveToCell($state, $seedMove['id'], $chosenSeedCell, $resolutionOrder);

        foreach ($collapseComponent['moveIds'] as $moveId) {
            $move = $this->findMove($state, $moveId);

            if ($move === null || $move['collapsed']) {
                continue;
            }

            $preferredCell = null;
            $cells = $move['cells'];
            sort($cells);

            foreach ($cells as $cellIndex) {
                if ($state['cells'][$cellIndex]['classicalMark'] === null) {
                    $preferredCell = $cellIndex;
                    break;
                }
            }

            $this->assignMoveToCell($state, $move['id'], $preferredCell ?? min($move['cells']), $resolutionOrder);
        }

        return [
            'seedMoveId' => $seedMove['id'],
            'seedCell' => $chosenSeedCell,
            'resolvedMoves' => $resolutionOrder,
        ];
    }

    private function assignMoveToCell(array &$state, string $moveId, int $chosenCell, array &$resolutionOrder): void
    {
        $moveIndex = $this->findMoveIndex($state, $moveId);

        if ($moveIndex === null || $state['moves'][$moveIndex]['collapsed']) {
            return;
        }

        $move = $state['moves'][$moveIndex];
        [$firstCell, $secondCell] = $move['cells'];
        $otherCell = $firstCell === $chosenCell ? $secondCell : $firstCell;

        if ($state['cells'][$chosenCell]['classicalMark'] !== null && $state['cells'][$chosenCell]['classicalMoveId'] !== $moveId) {
            $this->assignMoveToCell($state, $moveId, $otherCell, $resolutionOrder);
            return;
        }

        $state['moves'][$moveIndex]['collapsed'] = true;
        $state['moves'][$moveIndex]['collapsedCell'] = $chosenCell;
        $state['cells'][$chosenCell]['classicalMark'] = $move['player'];
        $state['cells'][$chosenCell]['classicalMoveId'] = $moveId;

        $state['cells'][$chosenCell]['quantumMoveIds'] = array_values(array_filter(
            $state['cells'][$chosenCell]['quantumMoveIds'],
            fn (string $candidateId) => $candidateId !== $moveId
        ));
        $state['cells'][$otherCell]['quantumMoveIds'] = array_values(array_filter(
            $state['cells'][$otherCell]['quantumMoveIds'],
            fn (string $candidateId) => $candidateId !== $moveId
        ));

        $resolutionOrder[] = [
            'moveId' => $moveId,
            'player' => $move['player'],
            'moveNumber' => $move['moveNumber'],
            'chosenCell' => $chosenCell,
        ];

        $forcedMoveIds = $state['cells'][$chosenCell]['quantumMoveIds'];
        $state['cells'][$chosenCell]['quantumMoveIds'] = [];

        foreach ($forcedMoveIds as $forcedMoveId) {
            $forcedMove = $this->findMove($state, $forcedMoveId);

            if ($forcedMove === null || $forcedMove['collapsed']) {
                continue;
            }

            $forcedCell = $forcedMove['cells'][0] === $chosenCell ? $forcedMove['cells'][1] : $forcedMove['cells'][0];
            $this->assignMoveToCell($state, $forcedMoveId, $forcedCell, $resolutionOrder);
        }
    }

    private function buildAdjacency(array $state, ?string $skipMoveId = null): array
    {
        $adjacency = [];

        foreach ($state['moves'] as $move) {
            if ($move['collapsed'] || $move['id'] === $skipMoveId) {
                continue;
            }

            [$firstCell, $secondCell] = $move['cells'];
            $adjacency[$firstCell] ??= [];
            $adjacency[$secondCell] ??= [];
            $adjacency[$firstCell][] = ['neighbor' => $secondCell, 'moveId' => $move['id']];
            $adjacency[$secondCell][] = ['neighbor' => $firstCell, 'moveId' => $move['id']];
        }

        return $adjacency;
    }

    private function findPath(array $adjacency, int $startCell, int $endCell): ?array
    {
        $queue = [$startCell];
        $visited = [$startCell];
        $previousByCell = [];

        while (!empty($queue)) {
            $currentCell = array_shift($queue);

            if ($currentCell === $endCell) {
                break;
            }

            foreach ($adjacency[$currentCell] ?? [] as $edge) {
                if (in_array($edge['neighbor'], $visited, true)) {
                    continue;
                }

                $visited[] = $edge['neighbor'];
                $previousByCell[$edge['neighbor']] = [
                    'cell' => $currentCell,
                    'moveId' => $edge['moveId'],
                ];
                $queue[] = $edge['neighbor'];
            }
        }

        if (!in_array($endCell, $visited, true)) {
            return null;
        }

        $pathMoveIds = [];
        $cursor = $endCell;

        while ($cursor !== $startCell) {
            $previous = $previousByCell[$cursor];
            $pathMoveIds[] = $previous['moveId'];
            $cursor = $previous['cell'];
        }

        return array_reverse($pathMoveIds);
    }

    private function findMove(array $state, string $moveId): ?array
    {
        foreach ($state['moves'] as $move) {
            if ($move['id'] === $moveId) {
                return $move;
            }
        }

        return null;
    }

    private function findMoveIndex(array $state, string $moveId): ?int
    {
        foreach ($state['moves'] as $index => $move) {
            if ($move['id'] === $moveId) {
                return $index;
            }
        }

        return null;
    }
}
