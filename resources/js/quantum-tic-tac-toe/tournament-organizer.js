export function mountTournamentOrganizer() {
    const root = document.querySelector('[data-tournament-organizer]');

    if (!root || !window.Echo || !root.dataset.tournamentId) {
        return;
    }

    window.Echo.channel(`tournament.${root.dataset.tournamentId}`)
        .listen('.tournament.updated', () => {
            window.location.reload();
        });
}
