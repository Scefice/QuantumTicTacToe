<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRoom extends Model
{
    protected $fillable = [
        'tournament_match_id',
        'code',
        'player_x_name',
        'player_x_token',
        'player_o_name',
        'player_o_token',
        'match_length',
        'status',
        'version',
        'state',
    ];

    protected $casts = [
        'state' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
