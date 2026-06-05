<?php

namespace App\Services;

use App\DataObjects\ScheduledMatch;
use InvalidArgumentException;

/**
 * Builds a double round-robin schedule (every team plays every other team
 * once at home and once away) using the circle method.
 *
 * It works for any number of teams: with an even count the first leg spans
 * n-1 weeks with n/2 matches each; with an odd count a "bye" placeholder is
 * added so the rotation still works, and the team drawn against the bye simply
 * rests that week. The second leg mirrors the first with home/away reversed.
 */
class FixtureGenerator
{
    /** Placeholder opponent for the resting team when the count is odd. */
    private const int BYE = 0;

    /**
     * @param  list<int>  $teamIds
     * @return list<ScheduledMatch>
     */
    public function generate(array $teamIds): array
    {
        $teamIds = array_values($teamIds);

        if (count($teamIds) < 2) {
            throw new InvalidArgumentException('At least two teams are required to generate fixtures.');
        }

        // Pad an odd line-up with a bye so the circle method stays balanced.
        $participants = $teamIds;
        if (count($participants) % 2 !== 0) {
            $participants[] = self::BYE;
        }

        $weeksPerLeg = count($participants) - 1;
        $firstLeg = $this->buildFirstLeg($participants);

        return $this->appendReverseLeg($firstLeg, $weeksPerLeg);
    }

    /**
     * @param  list<int>  $teamIds
     * @return list<ScheduledMatch>
     */
    private function buildFirstLeg(array $teamIds): array
    {
        $count = count($teamIds);
        $rounds = $count - 1;
        $half = intdiv($count, 2);

        // The first team stays fixed; the rest rotate clockwise each round.
        $rotating = $teamIds;
        $fixed = array_shift($rotating);

        $matches = [];

        for ($round = 0; $round < $rounds; $round++) {
            $ordered = array_merge([$fixed], $rotating);

            for ($i = 0; $i < $half; $i++) {
                $home = $ordered[$i];
                $away = $ordered[$count - 1 - $i];

                // Alternate home/away each round so the fixed team isn't
                // always the home side, keeping the schedule balanced.
                if ($round % 2 === 1 && $i === 0) {
                    [$home, $away] = [$away, $home];
                }

                // Skip the bye pairing — that team simply rests this week.
                if ($home === self::BYE || $away === self::BYE) {
                    continue;
                }

                $matches[] = new ScheduledMatch($round + 1, $home, $away);
            }

            // Rotate: move the last rotating element to the front.
            array_unshift($rotating, array_pop($rotating));
        }

        return $matches;
    }

    /**
     * @param  list<ScheduledMatch>  $firstLeg
     * @return list<ScheduledMatch>
     */
    private function appendReverseLeg(array $firstLeg, int $weeksPerLeg): array
    {
        $secondLeg = array_map(
            fn (ScheduledMatch $match): ScheduledMatch => new ScheduledMatch(
                $match->week + $weeksPerLeg,
                $match->awayTeamId,
                $match->homeTeamId,
            ),
            $firstLeg,
        );

        return array_merge($firstLeg, $secondLeg);
    }
}
