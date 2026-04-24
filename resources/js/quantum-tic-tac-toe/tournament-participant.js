export function mountTournamentParticipant() {
    const root = document.querySelector('[data-tournament-participant]');

    if (!root) {
        return;
    }

    const statusNode = root.querySelector('[data-tournament-status]');
    const messageNode = root.querySelector('[data-participant-message]');
    const roundNode = root.querySelector('[data-assigned-round]');
    const tableNode = root.querySelector('[data-assigned-table]');
    const opponentNode = root.querySelector('[data-assigned-opponent]');
    let redirected = false;

    function render(payload) {
        if (statusNode) {
            statusNode.textContent = payload.tournament_status;
        }

        if (payload.assigned_match) {
            roundNode.textContent = payload.assigned_match.round ?? '-';
            tableNode.textContent = payload.assigned_match.table ?? '-';
            opponentNode.textContent = payload.assigned_match.opponent ?? 'Waiting';

            if (messageNode) {
                messageNode.textContent = `Go to table ${payload.assigned_match.table}.`;
            }

            if (!redirected) {
                redirected = true;
                window.location.assign(payload.assigned_match.play_url ?? root.dataset.participantPlayUrl);
            }

            return;
        }

        if (messageNode) {
            messageNode.textContent = payload.tournament_status === 'registration'
                ? 'Waiting to start.'
                : payload.tournament_status === 'completed'
                    ? 'Finished.'
                    : 'Waiting for next game.';
        }
    }

    function fetchState() {
        return window.axios.get(root.dataset.participantStateUrl).then((response) => {
            render(response.data);
        });
    }

    if (window.Echo && root.dataset.tournamentId) {
        window.Echo.channel(`tournament.${root.dataset.tournamentId}`)
            .listen('.tournament.updated', () => {
                void fetchState();
            });
    }

    void fetchState();
    window.setInterval(() => {
        void fetchState();
    }, 15000);
}
