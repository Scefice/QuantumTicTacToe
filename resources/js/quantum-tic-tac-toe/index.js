import { createGameEngine } from './engine';
import { renderGame } from './renderer';
import { isNightMode, onThemeChange, toggleTheme } from '../theme';

export function mountQuantumGame() {
    const root = document.querySelector('[data-quantum-game]');

    if (!root) {
        return;
    }

    const engine = createGameEngine();
    const rulesPanel = root.querySelector('[data-rules-panel]');
    const setupForm = root.querySelector('[data-setup-form]');
    let rulesOpen = false;
    let nightMode = isNightMode();
    let playbackTimers = [];
    let lastPlaybackKey = '';
    let playback = {
        collapsePlaying: false,
        activeMoveId: null,
        activeCell: null,
        settledCells: [],
        focusMoveIds: [],
    };

    rulesPanel.classList.add('is-hidden');

    function clearPlaybackTimers() {
        playbackTimers.forEach((timerId) => window.clearTimeout(timerId));
        playbackTimers = [];
    }

    function setPlayback(nextPlayback) {
        playback = nextPlayback;
        refresh();
    }

    function getPlaybackKey(state) {
        if (state.lastEvent.type !== 'collapse') {
            return '';
        }

        return [
            state.lastEvent.type,
            state.lastEvent.moveIds.join('-'),
            state.lastEvent.resolvedMoves.map((entry) => `${entry.moveId}:${entry.chosenCell}`).join('|'),
        ].join(':');
    }

    function playCollapseSequence(state) {
        clearPlaybackTimers();

        const resolvedMoves = state.lastEvent.resolvedMoves;

        if (resolvedMoves.length === 0) {
            setPlayback({
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: [],
                focusMoveIds: state.lastEvent.moveIds,
            });
            return;
        }

        setPlayback({
            collapsePlaying: true,
            activeMoveId: null,
            activeCell: null,
            settledCells: [],
            focusMoveIds: state.lastEvent.moveIds,
        });

        resolvedMoves.forEach((entry, index) => {
            const timerId = window.setTimeout(() => {
                setPlayback({
                    collapsePlaying: true,
                    activeMoveId: entry.moveId,
                    activeCell: entry.chosenCell,
                    settledCells: resolvedMoves.slice(0, index + 1).map((item) => item.chosenCell),
                    focusMoveIds: state.lastEvent.moveIds,
                });
            }, 450 * (index + 1));

            playbackTimers.push(timerId);
        });

        playbackTimers.push(window.setTimeout(() => {
            setPlayback({
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: resolvedMoves.map((item) => item.chosenCell),
                focusMoveIds: [],
            });
        }, (resolvedMoves.length + 1) * 450));
    }

    function refresh() {
        const state = engine.getState();

        const playbackKey = getPlaybackKey(state);

        if (playbackKey && playbackKey !== lastPlaybackKey) {
            lastPlaybackKey = playbackKey;
            playCollapseSequence(state);
            renderGame(root, state, { nightMode, ...playback });
            return;
        }

        if (!playbackKey) {
            lastPlaybackKey = '';
        }

        renderGame(root, state, { nightMode, ...playback });
    }

    setupForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(setupForm);

        clearPlaybackTimers();
        playback = {
            collapsePlaying: false,
            activeMoveId: null,
            activeCell: null,
            settledCells: [],
            focusMoveIds: [],
        };

        engine.startMatch({
            playerX: String(formData.get('playerX') ?? ''),
            playerO: String(formData.get('playerO') ?? ''),
            matchLength: Number(formData.get('matchLength') ?? 3),
        });

        refresh();
    });

    root.addEventListener('click', (event) => {
        const cellButton = event.target.closest('[data-cell-index]');

        if (cellButton) {
            engine.selectCell(Number(cellButton.dataset.cellIndex));
            refresh();
            return;
        }

        if (event.target.closest('[data-new-game]')) {
            clearPlaybackTimers();
            engine.resetMatch();
            playback = {
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: [],
                focusMoveIds: [],
            };
            refresh();
            return;
        }

        if (event.target.closest('[data-reset-game]')) {
            clearPlaybackTimers();
            engine.resetBoard();
            playback = {
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: [],
                focusMoveIds: [],
            };
            refresh();
            return;
        }

        if (event.target.closest('[data-next-round]')) {
            clearPlaybackTimers();
            engine.nextRound();
            playback = {
                collapsePlaying: false,
                activeMoveId: null,
                activeCell: null,
                settledCells: [],
                focusMoveIds: [],
            };
            refresh();
            return;
        }

        if (event.target.closest('[data-toggle-rules]')) {
            rulesOpen = engine.toggleRules(rulesOpen);
            rulesPanel.classList.toggle('is-hidden', !rulesOpen);
            return;
        }

        if (event.target.closest('[data-theme-toggle]')) {
            nightMode = toggleTheme();
            refresh();
        }
    });

    root.addEventListener('mouseover', (event) => {
        const token = event.target.closest('[data-move-id]');

        if (!token) {
            return;
        }

        engine.toggleHover(token.dataset.moveId);
        refresh();
    });

    root.addEventListener('mouseout', (event) => {
        const token = event.target.closest('[data-move-id]');

        if (!token) {
            return;
        }

        engine.toggleHover(null);
        refresh();
    });

    onThemeChange((nextNightMode) => {
        nightMode = nextNightMode;
        refresh();
    });

    refresh();
}
