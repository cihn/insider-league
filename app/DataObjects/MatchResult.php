<?php

namespace App\DataObjects;

/**
 * Immutable result of a simulated (or edited) match score.
 */
final readonly class MatchResult
{
    public function __construct(
        public int $homeGoals,
        public int $awayGoals,
    ) {}

    public function isHomeWin(): bool
    {
        return $this->homeGoals > $this->awayGoals;
    }

    public function isAwayWin(): bool
    {
        return $this->awayGoals > $this->homeGoals;
    }

    public function isDraw(): bool
    {
        return $this->homeGoals === $this->awayGoals;
    }
}
