import './bootstrap';
import { mountQuantumGame } from './quantum-tic-tac-toe/index';
import { mountOnlineRoom } from './quantum-tic-tac-toe/online-room';
import { mountTournamentParticipant } from './quantum-tic-tac-toe/tournament-participant';
import { mountTournamentOrganizer } from './quantum-tic-tac-toe/tournament-organizer';

mountQuantumGame();
mountOnlineRoom();
mountTournamentParticipant();
mountTournamentOrganizer();
