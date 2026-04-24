<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'source_participant_id',
        'display_name',
        'seed',
        'points',
        'wins',
        'losses',
        'draws',
        'bye_count',
        'status',
        'final_rank',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceParticipant(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_participant_id');
    }

    public function matchesAsPlayerOne(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'player_one_id');
    }

    public function matchesAsPlayerTwo(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'player_two_id');
    }
}
