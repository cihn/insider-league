<?php

namespace App\DataObjects;

/**
 * A single fixture in the generated schedule, before it is persisted/played.
 */
final readonly class ScheduledMatch
{
    public function __construct(
        public int $week,
        public int $homeTeamId,
        public int $awayTeamId,
    ) {}
}
