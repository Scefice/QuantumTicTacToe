import { WINNING_LINES } from './constants';
import { resolveCollapse } from './collapse-resolver';
import { collectCollapseComponent, detectCycle } from './cycle-detector';
import { createInitialState } from './state';

function findMove(state, moveId) {
    return state.moves.find((move) => move.id === moveId);
}

function getCellName(cellIndex) {
    return `cell ${cellIndex + 1}`;
}

function getPlayerName(state, player) {
    return state.playerNames[player];
}

function createRoundCells() {
    return Array.from({ length: 9 }, (_, index) => ({
        index,
        classicalMark: null,
        classicalMoveId: null,
        quantumMoveIds: [],
    }));
}

function getWinner(state) {
    const winningLine = WINNING_LINES.find((line) => {
        const firstMark = state.cells[line[0]].classicalMark;
        return firstMark !== null && line.every((cellIndex) => state.cells[cellIndex].classicalMark === firstMark);
    });

    return winningLine ? state.cells[winningLine[0]].classicalMark : null;
}

function hasAvailablePair(state) {
    return state.cells.filter((cell) => cell.classicalMark === null).length >= 2;
}

function getBoardMode(state) {
    if (!state.matchStarted) {
        return 'Waiting to start';
    }

    const classicalCount = state.cells.filter((cell) => cell.classicalMark !== null).length;
    const unresolvedCount = state.moves.filter((move) => !move.collapsed).length;

    if (state.roundComplete) {
        return 'Round over';
    }

    if (classicalCount === 0) {
        return 'Fresh board';
    }

    if (unresolvedCount === 0) {
        return 'All settled';
    }

    return 'Some marks settled';
}

function clearBoardForNextRound(state) {
    state.cells = createRoundCells();
    state.moves = [];
    state.currentPlayer = 'X';
    state.nextMoveNumber = 1;
    state.selectedCells = [];
    state.hoveredMoveId = null;
    state.winner = null;
    state.draw = false;
    state.roundComplete = false;
    state.alert = false;
    state.lastEvent = {
        type: 'idle',
        moveIds: [],
        cells: [],
        explanation: 'No links yet.',
        resolvedMoves: [],
    };
}

function startRoundMessage(state) {
    return `Round ${state.roundNumber}. ${getPlayerName(state, 'X')} starts as X.`;
}

function updateOutcome(state) {
    const winner = getWinner(state);

    if (winner) {
        state.winner = winner;
        state.draw = false;
        state.roundComplete = true;
        state.scoreboard[winner] += 1;

        const playerName = getPlayerName(state, winner);
        state.statusMessage = `${playerName} wins this round with a full line of real marks.`;
        state.alert = true;

        if (state.scoreboard[winner] >= state.winsNeeded) {
            state.matchWinner = winner;
            state.statusMessage = `${playerName} wins the match.`;
        }

        return;
    }

    const boardIsFull = state.cells.every((cell) => cell.classicalMark !== null);

    if (boardIsFull || !hasAvailablePair(state)) {
        state.winner = null;
        state.draw = true;
        state.roundComplete = true;
        state.statusMessage = 'This round is a draw.';
        state.alert = true;
        return;
    }

    state.winner = null;
    state.draw = false;
}

function getSelectionMessage(state) {
    if (!state.matchStarted) {
        return 'Enter player names and start the match.';
    }

    if (state.roundComplete) {
        return state.statusMessage;
    }

    if (state.selectedCells.length === 0) {
        return `${getPlayerName(state, state.currentPlayer)} is choosing the first square.`;
    }

    return `${getPlayerName(state, state.currentPlayer)} picked ${getCellName(state.selectedCells[0])}. Choose one more square.`;
}

function buildMoveRecord(state, firstCell, secondCell) {
    return {
        id: `m${state.nextMoveNumber}`,
        moveNumber: state.nextMoveNumber,
        player: state.currentPlayer,
        cells: [firstCell, secondCell],
        collapsed: false,
        collapsedCell: null,
    };
}

function addQuantumMoveToBoard(state, move) {
    move.cells.forEach((cellIndex) => {
        state.cells[cellIndex].quantumMoveIds.push(move.id);
        state.cells[cellIndex].quantumMoveIds.sort((left, right) => {
            const leftMove = findMove(state, left);
            const rightMove = findMove(state, right);
            return leftMove.moveNumber - rightMove.moveNumber;
        });
    });
}

function finalizeTurn(state) {
    state.currentPlayer = state.currentPlayer === 'X' ? 'O' : 'X';
    state.nextMoveNumber += 1;
    state.alert = false;
}

export function createGameEngine() {
    let state = createInitialState();

    function getState() {
        return {
            ...state,
            cells: state.cells.map((cell) => ({ ...cell, quantumMoveIds: [...cell.quantumMoveIds] })),
            moves: state.moves.map((move) => ({ ...move, cells: [...move.cells] })),
            selectedCells: [...state.selectedCells],
            collapseLog: state.collapseLog.map((entry) => ({ ...entry })),
            scoreboard: { ...state.scoreboard },
            playerNames: { ...state.playerNames },
            lastEvent: {
                ...state.lastEvent,
                moveIds: [...state.lastEvent.moveIds],
                cells: [...state.lastEvent.cells],
                resolvedMoves: state.lastEvent.resolvedMoves.map((entry) => ({ ...entry })),
            },
            boardMode: getBoardMode(state),
        };
    }

    function startMatch(settings) {
        state = createInitialState();
        state.matchStarted = true;
        state.matchLength = settings.matchLength;
        state.winsNeeded = Math.ceil(settings.matchLength / 2);
        state.playerNames = {
            X: settings.playerX.trim() || 'Player 1',
            O: settings.playerO.trim() || 'Player 2',
        };
        state.statusMessage = startRoundMessage(state);
        return getState();
    }

    function resetMatch() {
        state = createInitialState();
        return getState();
    }

    function resetBoard() {
        if (!state.matchStarted) {
            return getState();
        }

        clearBoardForNextRound(state);
        state.statusMessage = `Round ${state.roundNumber} was reset. ${getPlayerName(state, 'X')} starts again.`;
        return getState();
    }

    function nextRound() {
        if (!state.matchStarted || !state.roundComplete || state.matchWinner) {
            return getState();
        }

        state.roundNumber += 1;
        clearBoardForNextRound(state);
        state.statusMessage = startRoundMessage(state);
        return getState();
    }

    function toggleHover(moveId) {
        state.hoveredMoveId = moveId;
        return getState();
    }

    function toggleRules(currentlyOpen) {
        return !currentlyOpen;
    }

    function selectCell(cellIndex) {
        if (!state.matchStarted) {
            state.statusMessage = 'Start the match first.';
            state.alert = true;
            return getState();
        }

        if (state.roundComplete) {
            state.statusMessage = state.matchWinner
                ? `${getPlayerName(state, state.matchWinner)} already won the match.`
                : 'This round is over. Start the next round or reset the board.';
            state.alert = true;
            return getState();
        }

        const cell = state.cells[cellIndex];

        if (!cell || cell.classicalMark !== null) {
            state.statusMessage = 'That square already has a real mark. Choose a different square.';
            state.alert = true;
            return getState();
        }

        if (state.selectedCells.includes(cellIndex)) {
            state.statusMessage = 'Pick two different squares for one move.';
            state.alert = true;
            return getState();
        }

        state.selectedCells.push(cellIndex);
        state.alert = false;

        if (state.selectedCells.length === 1) {
            state.statusMessage = getSelectionMessage(state);
            return getState();
        }

        const [firstCell, secondCell] = state.selectedCells;
        const move = buildMoveRecord(state, firstCell, secondCell);
        state.moves.push(move);
        addQuantumMoveToBoard(state, move);

        const cycle = detectCycle(state, move);

        if (cycle) {
            const collapseComponent = collectCollapseComponent(state, cycle);
            const collapseResult = resolveCollapse(state, collapseComponent, cycle);
            const resolvedLabels = collapseResult.resolvedMoves.map((entry) => {
                return `${entry.player}${entry.moveNumber} -> ${getCellName(entry.chosenCell)}`;
            });

            state.collapseLog.unshift({
                moveId: move.id,
                text: `A loop was made, so the floating marks settled: ${resolvedLabels.join(', ')}.`,
            });

            state.statusMessage = `A loop was made after ${move.player}${move.moveNumber}, so some marks settled.`;
            state.alert = true;
            state.lastEvent = {
                type: 'collapse',
                moveIds: collapseComponent.moveIds,
                cells: collapseComponent.cells,
                explanation: `${move.player}${move.moveNumber} closed a loop. The linked moves had to choose one square each.`,
                resolvedMoves: collapseResult.resolvedMoves,
            };
        } else {
            state.collapseLog.unshift({
                moveId: move.id,
                text: `${move.player}${move.moveNumber} was placed in ${getCellName(firstCell)} and ${getCellName(secondCell)}.`,
            });

            state.statusMessage = `${move.player}${move.moveNumber} is now in ${getCellName(firstCell)} and ${getCellName(secondCell)}.`;
            state.lastEvent = {
                type: 'move',
                moveIds: [move.id],
                cells: [firstCell, secondCell],
                explanation: `${move.player}${move.moveNumber} links ${getCellName(firstCell)} and ${getCellName(secondCell)}.`,
                resolvedMoves: [],
            };
        }

        state.selectedCells = [];
        updateOutcome(state);

        if (!state.roundComplete) {
            finalizeTurn(state);
        }

        return getState();
    }

    return {
        getState,
        startMatch,
        resetMatch,
        resetBoard,
        nextRound,
        selectCell,
        toggleHover,
        toggleRules,
    };
}
