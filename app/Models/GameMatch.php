<?php

namespace App\Models;

use Database\Factories\GameMatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    /** @use HasFactory<GameMatchFactory> */
    use HasFactory;

    protected $fillable = [
        'week',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'played',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'played' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @param  Builder<GameMatch>  $query
     */
    public function scopePlayed(Builder $query): void
    {
        $query->where('played', true);
    }

    /**
     * @param  Builder<GameMatch>  $query
     */
    public function scopeForWeek(Builder $query, int $week): void
    {
        $query->where('week', $week);
    }

    public function isDraw(): bool
    {
        return $this->played && $this->home_goals === $this->away_goals;
    }

    /**
     * Id of the winning team, or null for a draw or an unplayed match.
     */
    public function winnerTeamId(): ?int
    {
        if (! $this->played || $this->isDraw()) {
            return null;
        }

        return $this->home_goals > $this->away_goals
            ? $this->home_team_id
            : $this->away_team_id;
    }
}
