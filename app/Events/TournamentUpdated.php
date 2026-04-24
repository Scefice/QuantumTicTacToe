<?php

namespace App\Events;

use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Tournament $tournament)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('tournament.'.$this->tournament->id)];
    }

    public function broadcastAs(): string
    {
        return 'tournament.updated';
    }

    public function broadcastWith(): array
    {
        $currentRound = $this->tournament->rounds()->latest('number')->first();

        return [
            'tournament' => [
                'id' => $this->tournament->id,
                'status' => $this->tournament->status,
                'participants_count' => $this->tournament->participants()->count(),
                'current_round' => $currentRound?->number,
            ],
        ];
    }
}
