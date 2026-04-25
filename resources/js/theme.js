const STORAGE_KEY = 'qttt-theme';
const EVENT_NAME = 'qttt:theme-changed';

function readStoredTheme() {
    return window.localStorage.getItem(STORAGE_KEY) === 'night';
}

export function isNightMode() {
    return document.body.classList.contains('theme-night');
}

export function applyTheme(nightMode) {
    document.body.classList.toggle('theme-night', nightMode);
    window.localStorage.setItem(STORAGE_KEY, nightMode ? 'night' : 'day');
    window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { nightMode } }));
}

export function initializeTheme() {
    applyTheme(readStoredTheme());
}

export function toggleTheme() {
    const next = !isNightMode();
    applyTheme(next);
    return next;
}

export function onThemeChange(listener) {
    window.addEventListener(EVENT_NAME, (event) => {
        listener(Boolean(event.detail?.nightMode));
    });
}
