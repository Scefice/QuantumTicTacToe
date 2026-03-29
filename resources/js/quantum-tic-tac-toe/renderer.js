const CELL_POSITIONS = [
    [16, 16],
    [50, 16],
    [84, 16],
    [16, 50],
    [50, 50],
    [84, 50],
    [16, 84],
    [50, 84],
    [84, 84],
];

function getPlayerLabel(state, player) {
    return `${state.playerNames[player]} (${player})`;
}

function createQuantumToken(move, isLinked, isActiveCollapse) {
    const token = document.createElement('span');
    token.className = `quantum-token quantum-token--${move.player.toLowerCase()}${isLinked ? ' is-linked' : ''}${isActiveCollapse ? ' is-collapsing' : ''}`;
    token.dataset.moveId = move.id;
    token.textContent = `${move.player}${move.moveNumber}`;
    token.setAttribute('aria-label', `Quantum move ${move.player}${move.moveNumber}`);
    return token;
}

function createCellButton(cell, state, moveMap, uiState) {
    const button = document.createElement('button');
    button.type = 'button';

    const isSelected = state.selectedCells.includes(cell.index);
    const isClassical = cell.classicalMark !== null;
    const playbackFocus = uiState.focusMoveIds?.length
        ? uiState.focusMoveIds.some((moveId) => cell.quantumMoveIds.includes(moveId) || cell.classicalMoveId === moveId)
        : false;
    const linkedMove = state.hoveredMoveId
        ? cell.quantumMoveIds.includes(state.hoveredMoveId) || cell.classicalMoveId === state.hoveredMoveId
        : playbackFocus || state.lastEvent.cells.includes(cell.index);
    const isActiveCollapseCell = uiState.activeCell === cell.index;
    const isSettledCell = uiState.settledCells?.includes(cell.index);

    button.className = [
        'board-cell',
        isClassical ? 'is-classical' : '',
        isSelected ? 'is-selected' : '',
        linkedMove ? 'is-linked' : '',
        isActiveCollapseCell ? 'is-collapsing' : '',
        isSettledCell ? 'is-settled' : '',
        !state.matchStarted || state.roundComplete ? 'is-disabled' : '',
    ].filter(Boolean).join(' ');
    button.dataset.cellIndex = String(cell.index);
    button.setAttribute('role', 'gridcell');
    button.setAttribute('aria-label', `Cell ${cell.index + 1}`);

    const index = document.createElement('span');
    index.className = 'board-cell__index';
    index.textContent = String(cell.index + 1);
    button.append(index);

    if (isClassical) {
        const classicalMark = document.createElement('div');
        classicalMark.className = `board-cell__classical board-cell__classical--${cell.classicalMark.toLowerCase()}`;
        classicalMark.textContent = cell.classicalMark;
        button.append(classicalMark);
        return button;
    }

    if (cell.quantumMoveIds.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'board-cell__empty';
        empty.textContent = state.matchStarted ? '' : '';
        button.append(empty);
        return button;
    }

    const grid = document.createElement('div');
    grid.className = 'board-cell__quantum-grid';

    cell.quantumMoveIds.forEach((moveId) => {
        const move = moveMap.get(moveId);
        const isLinked = state.hoveredMoveId === moveId || state.lastEvent.moveIds.includes(moveId) || uiState.focusMoveIds?.includes(moveId);
        const isActiveCollapse = uiState.activeMoveId === moveId;
        grid.append(createQuantumToken(move, isLinked, isActiveCollapse));
    });

    button.append(grid);

    return button;
}

function getPhaseLabel(state) {
    if (!state.matchStarted) {
        return 'Setup';
    }

    if (state.matchWinner) {
        return 'Match won';
    }

    if (state.winner) {
        return 'Round won';
    }

    if (state.draw) {
        return 'Draw';
    }

    return state.selectedCells.length === 0 ? 'Pick 1' : 'Pick 2';
}

function getSelectionSummary(state, uiState) {
    if (!state.matchStarted) {
        return 'Set names. Start.';
    }

    if (uiState.collapsePlaying) {
        return 'Watch settle.';
    }

    if (state.matchWinner) {
        return `${getPlayerLabel(state, state.matchWinner)} wins.`;
    }

    if (state.winner) {
        return `${getPlayerLabel(state, state.winner)} wins round.`;
    }

    if (state.draw) {
        return 'Draw.';
    }

    if (state.selectedCells.length === 0) {
        return `${getPlayerLabel(state, state.currentPlayer)}`;
    }

    return `${getPlayerLabel(state, state.currentPlayer)} pick one more.`;
}

function getSelectionPath(state, uiState) {
    if (uiState.collapsePlaying && uiState.activeMoveId && uiState.activeCell !== null) {
        return `${uiState.activeMoveId.toUpperCase()} -> ${uiState.activeCell + 1}`;
    }

    if (state.selectedCells.length === 0) {
        return '';
    }

    if (state.selectedCells.length === 1) {
        return `${state.selectedCells[0] + 1}`;
    }

    return `${state.selectedCells[0] + 1} - ${state.selectedCells[1] + 1}`;
}

function renderHistory(historyRoot, state) {
    historyRoot.innerHTML = '';

    if (state.collapseLog.length === 0) {
        const item = document.createElement('li');
        item.textContent = '...';
        historyRoot.append(item);
        return;
    }

    state.collapseLog.slice(0, 8).forEach((entry) => {
        const item = document.createElement('li');
        const label = document.createElement('strong');
        label.textContent = entry.moveId.replace('m', '').toUpperCase();
        item.append(label);
        historyRoot.append(item);
    });
}

function createCurvePath(startIndex, endIndex, bendSeed) {
    const [x1, y1] = CELL_POSITIONS[startIndex];
    const [x2, y2] = CELL_POSITIONS[endIndex];
    const midX = (x1 + x2) / 2;
    const midY = (y1 + y2) / 2;
    const dx = x2 - x1;
    const dy = y2 - y1;
    const length = Math.sqrt((dx * dx) + (dy * dy)) || 1;
    const normalX = (-dy / length) * bendSeed;
    const normalY = (dx / length) * bendSeed;

    return `M ${x1} ${y1} Q ${midX + normalX} ${midY + normalY} ${x2} ${y2}`;
}

function renderMovePath(svg, move, className, bendSeed) {
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', createCurvePath(move.cells[0], move.cells[1], bendSeed));
    path.setAttribute('class', className);
    svg.append(path);
}

function renderLinkMap(mapRoot, noteRoot, state, uiState) {
    mapRoot.innerHTML = '';

    if (!state.matchStarted) {
        noteRoot.textContent = '';
        return;
    }

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 100 100');
    svg.setAttribute('class', 'link-map__svg');

    const moveMap = new Map(state.moves.map((move) => [move.id, move]));
    const unresolvedMoves = state.moves.filter((move) => !move.collapsed);

    unresolvedMoves.forEach((move, index) => {
        const isFocus = state.hoveredMoveId === move.id || uiState.focusMoveIds?.includes(move.id);
        const bend = ((index % 2 === 0 ? 1 : -1) * (8 + (index % 3) * 3));
        renderMovePath(svg, move, `link-map__path link-map__path--${move.player.toLowerCase()}${isFocus ? ' is-focus' : ''}`, bend);
    });

    if (state.lastEvent.type === 'collapse') {
        state.lastEvent.moveIds.forEach((moveId, index) => {
            const move = moveMap.get(moveId);

            if (!move) {
                return;
            }

            const isActive = uiState.activeMoveId === moveId;
            const isSeen = uiState.focusMoveIds?.includes(moveId);
            const bend = ((index % 2 === 0 ? 1 : -1) * (8 + (index % 3) * 3));

            renderMovePath(
                svg,
                move,
                `link-map__path link-map__path--ghost link-map__path--${move.player.toLowerCase()}${isSeen ? ' is-focus' : ''}${isActive ? ' is-current' : ''}`,
                bend
            );
        });
    }

    state.cells.forEach((cell) => {
        const [cx, cy] = CELL_POSITIONS[cell.index];
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        const isActive = uiState.activeCell === cell.index;
        const isSettled = uiState.settledCells?.includes(cell.index);
        const isFocus = state.lastEvent.cells.includes(cell.index) || isActive || isSettled;

        circle.setAttribute('cx', String(cx));
        circle.setAttribute('cy', String(cy));
        circle.setAttribute('r', '8');
        circle.setAttribute('class', `link-map__node${isFocus ? ' is-focus' : ''}${isActive ? ' is-current' : ''}${isSettled ? ' is-settled' : ''}`);
        svg.append(circle);

        const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        label.setAttribute('x', String(cx));
        label.setAttribute('y', String(cy + 1));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('class', 'link-map__label');
        label.textContent = cell.classicalMark ?? String(cell.index + 1);
        svg.append(label);
    });

    mapRoot.append(svg);

    if (uiState.collapsePlaying && uiState.activeMoveId && uiState.activeCell !== null) {
        noteRoot.textContent = `${uiState.activeMoveId.toUpperCase()} -> ${uiState.activeCell + 1}`;
        return;
    }

    if (state.lastEvent.type === 'collapse') {
        noteRoot.textContent = 'loop -> settle';
        return;
    }

    if (state.lastEvent.type === 'move') {
        noteRoot.textContent = 'link';
        return;
    }

    noteRoot.textContent = '';
}

export function renderGame(root, state, uiState = {}) {
    const board = root.querySelector('[data-board]');
    const messageBox = root.querySelector('[data-message-box]');
    const setupBox = root.querySelector('[data-setup-box]');
    const playerXName = root.querySelector('[data-player-x-name]');
    const playerOName = root.querySelector('[data-player-o-name]');
    const matchScore = root.querySelector('[data-match-score]');
    const nextRoundButton = root.querySelector('[data-next-round]');
    const themeToggle = root.querySelector('[data-theme-toggle]');
    const linkMap = root.querySelector('[data-link-map]');
    const linkMapNote = root.querySelector('[data-link-map-note]');

    const moveMap = new Map(state.moves.map((move) => [move.id, move]));

    board.innerHTML = '';
    state.cells.forEach((cell) => {
        board.append(createCellButton(cell, state, moveMap, uiState));
    });

    setupBox.classList.toggle('is-hidden', state.matchStarted);
    playerXName.textContent = getPlayerLabel(state, 'X');
    playerOName.textContent = getPlayerLabel(state, 'O');
    matchScore.textContent = `${state.scoreboard.X} - ${state.scoreboard.O}`;
    nextRoundButton.disabled = !state.roundComplete || Boolean(state.matchWinner);
    messageBox.textContent = uiState.collapsePlaying ? 'Settling...' : state.statusMessage;
    messageBox.classList.toggle('message-box--alert', Boolean(state.alert || uiState.collapsePlaying));
    themeToggle.textContent = uiState.nightMode ? 'Day' : 'Night';

    renderLinkMap(linkMap, linkMapNote, state, uiState);
}
