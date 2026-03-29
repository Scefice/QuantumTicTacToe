import { CELL_COUNT } from './constants';

function createCell(index) {
    return {
        index,
        classicalMark: null,
        classicalMoveId: null,
        quantumMoveIds: [],
    };
}

export function createInitialState() {
    return {
        cells: Array.from({ length: CELL_COUNT }, (_, index) => createCell(index)),
        moves: [],
        currentPlayer: 'X',
        nextMoveNumber: 1,
        selectedCells: [],
        hoveredMoveId: null,
        winner: null,
        draw: false,
        roundComplete: false,
        matchWinner: null,
        matchStarted: false,
        matchLength: 3,
        winsNeeded: 2,
        scoreboard: { X: 0, O: 0 },
        playerNames: { X: 'Player 1', O: 'Player 2' },
        roundNumber: 1,
        collapseLog: [],
        statusMessage: 'Enter player names and start the match.',
        alert: false,
        lastEvent: {
            type: 'idle',
            moveIds: [],
            cells: [],
            explanation: 'No links yet.',
            resolvedMoves: [],
        },
    };
}
