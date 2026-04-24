<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'tournament_round_id',
        'table_number',
        'player_one_id',
        'player_two_id',
        'winner_participant_id',
        'result_type',
        'time_limit_minutes',
        'started_at',
        'finished_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'player_two_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_participant_id');
    }
}
