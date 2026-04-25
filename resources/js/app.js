import './bootstrap';
import { mountQuantumGame } from './quantum-tic-tac-toe/index';
import { mountOnlineRoom } from './quantum-tic-tac-toe/online-room';
import { mountTournamentParticipant } from './quantum-tic-tac-toe/tournament-participant';
import { mountTournamentOrganizer } from './quantum-tic-tac-toe/tournament-organizer';
import { initializeTheme, isNightMode, toggleTheme } from './theme';

initializeTheme();

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-global-theme-toggle]');

    if (!toggle) {
        return;
    }

    const nightMode = toggleTheme();
    toggle.textContent = nightMode ? 'Day' : 'Night';
});

const globalToggle = document.querySelector('[data-global-theme-toggle]');

if (globalToggle) {
    globalToggle.textContent = isNightMode() ? 'Day' : 'Night';
}

mountQuantumGame();
mountOnlineRoom();
mountTournamentParticipant();
mountTournamentOrganizer();
