<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'join_code',
        'stage_name',
        'creator_name',
        'match_length',
        'rounds_count',
        'advancing_count',
        'game_time_limit_minutes',
        'scheduled_at',
        'source_tournament_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TournamentRound::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function sourceTournament(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_tournament_id');
    }

    public function childTournaments(): HasMany
    {
        return $this->hasMany(self::class, 'source_tournament_id');
    }
}
