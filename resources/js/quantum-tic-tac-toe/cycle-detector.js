function buildAdjacency(state, skipMoveId = null) {
    const adjacency = new Map();

    state.moves
        .filter((move) => !move.collapsed && move.id !== skipMoveId)
        .forEach((move) => {
            const [firstCell, secondCell] = move.cells;

            if (!adjacency.has(firstCell)) {
                adjacency.set(firstCell, []);
            }

            if (!adjacency.has(secondCell)) {
                adjacency.set(secondCell, []);
            }

            adjacency.get(firstCell).push({ neighbor: secondCell, moveId: move.id });
            adjacency.get(secondCell).push({ neighbor: firstCell, moveId: move.id });
        });

    return adjacency;
}

function findPath(adjacency, startCell, endCell) {
    const queue = [startCell];
    const visited = new Set([startCell]);
    const previousByCell = new Map();

    while (queue.length > 0) {
        const currentCell = queue.shift();

        if (currentCell === endCell) {
            break;
        }

        const edges = adjacency.get(currentCell) ?? [];

        edges.forEach((edge) => {
            if (visited.has(edge.neighbor)) {
                return;
            }

            visited.add(edge.neighbor);
            previousByCell.set(edge.neighbor, {
                cell: currentCell,
                moveId: edge.moveId,
            });
            queue.push(edge.neighbor);
        });
    }

    if (!visited.has(endCell)) {
        return null;
    }

    const pathMoveIds = [];
    let cursor = endCell;

    while (cursor !== startCell) {
        const previous = previousByCell.get(cursor);
        pathMoveIds.push(previous.moveId);
        cursor = previous.cell;
    }

    pathMoveIds.reverse();

    return {
        moveIds: pathMoveIds,
        cells: new Set([...visited]),
    };
}

export function detectCycle(state, move) {
    const adjacency = buildAdjacency(state, move.id);
    const [firstCell, secondCell] = move.cells;

    // If the two endpoints are already connected, the new edge closes a loop.
    const existingPath = findPath(adjacency, firstCell, secondCell);

    if (!existingPath) {
        return null;
    }

    const cycleMoveIds = [...existingPath.moveIds, move.id];
    const cycleCellSet = new Set([firstCell, secondCell]);

    cycleMoveIds.forEach((moveId) => {
        const linkedMove = state.moves.find((candidate) => candidate.id === moveId);
        linkedMove.cells.forEach((cellIndex) => cycleCellSet.add(cellIndex));
    });

    return {
        moveIds: cycleMoveIds,
        cells: Array.from(cycleCellSet).sort((left, right) => left - right),
        triggeringMoveId: move.id,
    };
}

export function collectCollapseComponent(state, cycle) {
    const pendingMoveIds = [...cycle.moveIds];
    const componentMoveIds = new Set();
    const componentCells = new Set(cycle.cells);

    // A collapse affects the full unresolved component connected to the cycle,
    // not only the edges that sit directly on the loop itself.
    while (pendingMoveIds.length > 0) {
        const moveId = pendingMoveIds.pop();

        if (componentMoveIds.has(moveId)) {
            continue;
        }

        componentMoveIds.add(moveId);

        const move = state.moves.find((candidate) => candidate.id === moveId);

        move.cells.forEach((cellIndex) => {
            componentCells.add(cellIndex);

            state.cells[cellIndex].quantumMoveIds.forEach((linkedMoveId) => {
                const linkedMove = state.moves.find((candidate) => candidate.id === linkedMoveId);

                if (linkedMove && !linkedMove.collapsed && !componentMoveIds.has(linkedMoveId)) {
                    pendingMoveIds.push(linkedMoveId);
                }
            });
        });
    }

    return {
        moveIds: Array.from(componentMoveIds).sort((left, right) => left - right),
        cells: Array.from(componentCells).sort((left, right) => left - right),
    };
}
