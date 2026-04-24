import { createGameEngine } from './engine';
import { renderGame } from './renderer';

function postJson(url, body) {
    return window.axios.post(url, body).then((response) => response.data);
}

export function mountOnlineRoom() {
    const root = document.querySelector('[data-online-room]');

    if (!root) {
        return;
    }

    const rulesPanel = root.querySelector('[data-rules-panel]');
    const engine = createGameEngine();
    const playerMark = root.dataset.playerMark;
    let rulesOpen = false;
    let nightMode = false;
    let playbackTimers = [];
    let lastPlaybackKey = '';
    let currentState = null;
    let requestChain = Promise.resolve();
    let playback = {
        collapsePlaying: false,
        activeMoveId: null,
        activeCell: null,
        settledCells: [],
        focusMoveIds: [],
    };
    let redirectTimer = null;
    let nextRoundTimer = null;

    const urls = {
        state: root.dataset.roomStateUrl,
        pick: root.dataset.roomPickUrl,
        nextRound: root.dataset.roomNextRoundUrl,
        resetBoard: root.dataset.roomResetBoardUrl,
        resetMatch: root.dataset.roomResetMatchUrl,
    };
    let roomChannel = null;

    if (rulesPanel) {
        rulesPanel.classList.add('is-hidden');
    }

    function clearPlaybackTimers() {
        playbackTimers.forEach((timerId) => window.clearTimeout(timerId));
        playbackTimers = [];
    }

    function resetPlayback() {
        clearPlaybackTimers();
        playback = {
            collapsePlaying: false,
            activeMoveId: null,
            activeCell: null,
            settledCells: [],
            focusMoveIds: [],
        };
    }

    function getPlaybackKey(state) {
        if (!state || state.lastEvent?.type !== 'collapse') {
            return '';
        }

        return [
            state.lastEvent.type,
            state.lastEvent.moveIds.join('-'),
            state.lastEvent.resolvedMoves.map((entry) => `${entry.moveId}:${entry.chosenCell}`).join('|'),
        ].join(':');
    }

    function refresh() {
        currentState = engine.getState();

        if (!currentState) {
            return;
        }

        document.body.classList.toggle('theme-night', nightMode);
        renderGame(root, currentState, { nightMode, ...playback });
    }

    function playCollapseSequence(state) {
        clearPlaybackTimers();
        const resolvedMoves = state.lastEvent.resolvedMoves ?? [];

        playback = {
            collapsePlaying: resolvedMoves.length > 0,
            activeMoveId: null,
            activeCell: null,
            settledCells: [],
            focusMoveIds: state.lastEvent.moveIds ?? [],
        };
        refresh();

        resolvedMoves.forEach((entry, index) => {
            playbackTimers.push(window.setTimeout(() => {
                playback = {
                    collapsePlaying: true,
                    activeMoveId: entry.moveId,
                    activeCell: entry.chosenCell,
                    settledCells: resolvedMoves.slice(0, index + 1).map((item) => item.chosenCell),
                    focusMoveIds: state.lastEvent.moveIds ?? [],
                };
                refresh();
            }, 800 * (index + 1)));
        });

        playbackTimers.push(window.setTimeout(() => {
            playback = {
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: resolvedMoves.map((item) => item.chosenCell),
                focusMoveIds: [],
            };
            refresh();
        }, (resolvedMoves.length + 1) * 800));
    }

    function applyState(state, { authoritative = true } = {}) {
        const previousRoundNumber = currentState?.roundNumber;
        const previousMatchWinner = currentState?.matchWinner;
        currentState = engine.hydrateState(state);
        const playbackKey = getPlaybackKey(state);

        if (playbackKey && playbackKey !== lastPlaybackKey) {
            lastPlaybackKey = playbackKey;
            playCollapseSequence(state);
            return;
        }

        if (!playbackKey) {
            lastPlaybackKey = '';
            resetPlayback();
        }

        if (previousRoundNumber !== undefined && previousRoundNumber !== state.roundNumber) {
            resetPlayback();
            if (nextRoundTimer) {
                window.clearTimeout(nextRoundTimer);
                nextRoundTimer = null;
            }
        }

        if (authoritative && !previousMatchWinner && state.matchWinner && root.dataset.tournamentReturnUrl) {
            if (redirectTimer) {
                window.clearTimeout(redirectTimer);
            }

            redirectTimer = window.setTimeout(() => {
                window.location.assign(root.dataset.tournamentReturnUrl);
            }, 1200);
        }

        if (authoritative && root.dataset.tournamentRoom && state.roundComplete && !state.matchWinner && !nextRoundTimer) {
            nextRoundTimer = window.setTimeout(() => {
                nextRoundTimer = null;
                void enqueueRequest(() => postJson(urls.nextRound, {}), () => engine.nextRound());
            }, 1400);
        }

        refresh();
    }

    function fetchState() {
        return window.axios.get(urls.state).then((response) => {
            applyState(response.data.state);
        });
    }

    function handleRequestFailure() {
        return fetchState().catch(() => {
            refresh();
        });
    }

    function enqueueRequest(action, optimisticUpdate = null) {
        if (optimisticUpdate) {
            optimisticUpdate();
            refresh();
        }

        requestChain = requestChain
            .catch(() => {
                return undefined;
            })
            .then(() => action()
                .then((data) => {
                    applyState(data.state);
                })
                .catch(() => handleRequestFailure())
            );

        return requestChain;
    }

    function applyOptimisticPick(cellIndex) {
        if (!currentState || currentState.currentPlayer !== playerMark || currentState.roundComplete || !currentState.matchStarted || playback.collapsePlaying) {
            if (currentState && currentState.currentPlayer !== playerMark && currentState.matchStarted && !currentState.roundComplete) {
                applyState({
                    ...currentState,
                    alert: true,
                    statusMessage: `Wait for ${currentState.playerNames[currentState.currentPlayer]}.`,
                }, { authoritative: false });
            }

            return false;
        }

        const nextState = engine.selectCell(cellIndex);
        const didChange =
            JSON.stringify(nextState.selectedCells) !== JSON.stringify(currentState?.selectedCells ?? [])
            || nextState.moves.length !== (currentState?.moves.length ?? 0)
            || nextState.statusMessage !== currentState?.statusMessage;

        if (didChange) {
            applyState(nextState, { authoritative: false });
        } else {
            refresh();
        }

        return didChange;
    }

    function runOptimisticAction(action, optimisticUpdate = null) {
        return enqueueRequest(action, optimisticUpdate ? () => {
            const optimisticState = optimisticUpdate();

            if (optimisticState) {
                applyState(optimisticState, { authoritative: false });
            } else {
                refresh();
            }
        } : null)
            .finally(() => {
                refresh();
            })
    }

    root.addEventListener('click', (event) => {
        const cellButton = event.target.closest('[data-cell-index]');

        if (cellButton) {
            const cellIndex = Number(cellButton.dataset.cellIndex);
            const didApply = applyOptimisticPick(cellIndex);

            if (didApply) {
                void enqueueRequest(() => postJson(urls.pick, { cell_index: cellIndex }));
            }
            return;
        }

        if (event.target.closest('[data-new-game]')) {
            void runOptimisticAction(() => postJson(urls.resetMatch, {}), () => engine.resetMatch());
            return;
        }

        if (event.target.closest('[data-reset-game]')) {
            void runOptimisticAction(() => postJson(urls.resetBoard, {}), () => engine.resetBoard());
            return;
        }

        if (event.target.closest('[data-next-round]')) {
            void runOptimisticAction(() => postJson(urls.nextRound, {}), () => engine.nextRound());
            return;
        }

        if (event.target.closest('[data-toggle-rules]')) {
            rulesOpen = !rulesOpen;
            if (rulesPanel) {
                rulesPanel.classList.toggle('is-hidden', !rulesOpen);
            }
            return;
        }

        if (event.target.closest('[data-theme-toggle]')) {
            nightMode = !nightMode;
            refresh();
        }
    });

    root.addEventListener('mouseover', (event) => {
        const token = event.target.closest('[data-move-id]');

        if (!token || !currentState) {
            return;
        }

        currentState = engine.hydrateState({ ...currentState, hoveredMoveId: token.dataset.moveId });
        refresh();
    });

    root.addEventListener('mouseout', (event) => {
        const token = event.target.closest('[data-move-id]');

        if (!token || !currentState) {
            return;
        }

        currentState = engine.hydrateState({ ...currentState, hoveredMoveId: null });
        refresh();
    });

    if (window.Echo) {
        roomChannel = window.Echo.channel(`room.${root.dataset.roomCode}`);
        roomChannel.listen('.room.state.updated', (payload) => {
            applyState(payload.state);
        });
    }

    void fetchState();
    window.setInterval(() => {
        void fetchState();
    }, 15000);
}
