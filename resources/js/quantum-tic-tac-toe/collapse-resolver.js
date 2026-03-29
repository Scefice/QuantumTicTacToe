function findMove(state, moveId) {
    return state.moves.find((move) => move.id === moveId);
}

function removeQuantumReference(cell, moveId) {
    cell.quantumMoveIds = cell.quantumMoveIds.filter((candidateId) => candidateId !== moveId);
}

function assignMoveToCell(state, move, chosenCell, resolutionOrder) {
    if (move.collapsed) {
        return;
    }

    const [firstCell, secondCell] = move.cells;
    const otherCell = firstCell === chosenCell ? secondCell : firstCell;
    const chosenBoardCell = state.cells[chosenCell];
    const otherBoardCell = state.cells[otherCell];

    // If propagation reaches a filled classical square, use the other endpoint.
    if (chosenBoardCell.classicalMark !== null && chosenBoardCell.classicalMoveId !== move.id) {
        assignMoveToCell(state, move, otherCell, resolutionOrder);
        return;
    }

    move.collapsed = true;
    move.collapsedCell = chosenCell;

    chosenBoardCell.classicalMark = move.player;
    chosenBoardCell.classicalMoveId = move.id;

    removeQuantumReference(chosenBoardCell, move.id);
    removeQuantumReference(otherBoardCell, move.id);

    resolutionOrder.push({
        moveId: move.id,
        player: move.player,
        moveNumber: move.moveNumber,
        chosenCell,
    });

    const forcedMoveIds = [...chosenBoardCell.quantumMoveIds];
    chosenBoardCell.quantumMoveIds = [];

    forcedMoveIds.forEach((forcedMoveId) => {
        const forcedMove = findMove(state, forcedMoveId);

        if (!forcedMove || forcedMove.collapsed) {
            return;
        }

        const forcedCell = forcedMove.cells.find((cellIndex) => cellIndex !== chosenCell);
        assignMoveToCell(state, forcedMove, forcedCell, resolutionOrder);
    });
}

export function resolveCollapse(state, collapseComponent, cycle) {
    const resolutionOrder = [];
    const seedMove = findMove(state, cycle.triggeringMoveId);
    const chosenSeedCell = Math.min(...seedMove.cells);

    // The collapse rule is deterministic:
    // the move that closed the cycle resolves to its lower-indexed square first,
    // then every conflicting move is forced to its opposite square.
    assignMoveToCell(state, seedMove, chosenSeedCell, resolutionOrder);

    collapseComponent.moveIds.forEach((moveId) => {
        const move = findMove(state, moveId);

        if (!move || move.collapsed) {
            return;
        }

        const preferredCell = [...move.cells].sort((left, right) => left - right).find((cellIndex) => {
            return state.cells[cellIndex].classicalMark === null;
        });

        assignMoveToCell(state, move, preferredCell ?? Math.min(...move.cells), resolutionOrder);
    });

    return {
        seedMoveId: seedMove.id,
        seedCell: chosenSeedCell,
        resolvedMoves: resolutionOrder,
    };
}
