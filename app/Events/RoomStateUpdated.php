<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public GameRoom $room)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('room.'.$this->room->code)];
    }

    public function broadcastAs(): string
    {
        return 'room.state.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'room' => [
                'code' => $this->room->code,
                'status' => $this->room->status,
                'version' => $this->room->version,
                'player_x_name' => $this->room->player_x_name,
                'player_o_name' => $this->room->player_o_name,
            ],
            'state' => $this->room->state,
        ];
    }
}
