<?php

namespace App\DataObjects;

/**
 * A single, immutable row of the league standings.
 *
 * Goal difference is a virtual property derived from goals for/against,
 * so it can never drift out of sync with the underlying counts.
 */
final class TableRow
{
    public function __construct(
        public readonly int $teamId,
        public readonly string $teamName,
        public readonly int $played,
        public readonly int $won,
        public readonly int $drawn,
        public readonly int $lost,
        public readonly int $goalsFor,
        public readonly int $goalsAgainst,
        public readonly int $points,
    ) {}

    public int $goalDifference {
        get => $this->goalsFor - $this->goalsAgainst;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'played' => $this->played,
            'won' => $this->won,
            'drawn' => $this->drawn,
            'lost' => $this->lost,
            'goals_for' => $this->goalsFor,
            'goals_against' => $this->goalsAgainst,
            'goal_difference' => $this->goalDifference,
            'points' => $this->points,
        ];
    }
}
