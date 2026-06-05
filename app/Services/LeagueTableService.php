<?php

namespace App\Services;

use App\DataObjects\TableRow;
use App\Models\GameMatch;
use App\Models\Team;

/**
 * Derives the league standings purely from the played matches, so the table
 * is always consistent with the results and never stored separately.
 *
 * The accumulator-based API (newAccumulators / applyResult / rank) lets the
 * Monte Carlo predictor compute a played "base" once and then cheaply layer
 * simulated results on top of a copy, instead of rebuilding from scratch.
 *
 * @phpstan-type Accumulator array{name: string, played: int, won: int, drawn: int, lost: int, goalsFor: int, goalsAgainst: int, points: int}
 */
class LeagueTableService
{
    private const int POINTS_FOR_WIN = 3;

    private const int POINTS_FOR_DRAW = 1;

    /**
     * @param  iterable<Team>  $teams
     * @param  iterable<GameMatch>  $matches
     * @return list<TableRow>
     */
    public function calculate(iterable $teams, iterable $matches): array
    {
        return $this->rank($this->accumulate($teams, $matches));
    }

    /**
     * Build the played-match accumulators for the given teams and matches.
     *
     * @param  iterable<Team>  $teams
     * @param  iterable<GameMatch>  $matches
     * @return array<int, Accumulator>
     */
    public function accumulate(iterable $teams, iterable $matches): array
    {
        $accumulators = $this->newAccumulators($teams);

        foreach ($matches as $match) {
            if (! $match->played) {
                continue;
            }

            $this->applyResult(
                $accumulators,
                $match->home_team_id,
                $match->away_team_id,
                (int) $match->home_goals,
                (int) $match->away_goals,
            );
        }

        return $accumulators;
    }

    /**
     * Empty accumulators keyed by team id.
     *
     * @param  iterable<Team>  $teams
     * @return array<int, Accumulator>
     */
    public function newAccumulators(iterable $teams): array
    {
        $accumulators = [];

        foreach ($teams as $team) {
            $accumulators[$team->id] = [
                'name' => $team->name,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goalsFor' => 0,
                'goalsAgainst' => 0,
                'points' => 0,
            ];
        }

        return $accumulators;
    }

    /**
     * Apply a single result to the accumulators using raw scores (no model
     * instance needed — this is the hot path inside the predictor).
     *
     * @param  array<int, Accumulator>  $accumulators
     */
    public function applyResult(array &$accumulators, int $homeId, int $awayId, int $homeGoals, int $awayGoals): void
    {
        if (! isset($accumulators[$homeId], $accumulators[$awayId])) {
            return;
        }

        $accumulators[$homeId]['played']++;
        $accumulators[$awayId]['played']++;

        $accumulators[$homeId]['goalsFor'] += $homeGoals;
        $accumulators[$homeId]['goalsAgainst'] += $awayGoals;
        $accumulators[$awayId]['goalsFor'] += $awayGoals;
        $accumulators[$awayId]['goalsAgainst'] += $homeGoals;

        if ($homeGoals > $awayGoals) {
            $this->recordWin($accumulators[$homeId]);
            $this->recordLoss($accumulators[$awayId]);
        } elseif ($homeGoals < $awayGoals) {
            $this->recordWin($accumulators[$awayId]);
            $this->recordLoss($accumulators[$homeId]);
        } else {
            $this->recordDraw($accumulators[$homeId]);
            $this->recordDraw($accumulators[$awayId]);
        }
    }

    /**
     * Turn accumulators into a sorted list of standings rows.
     *
     * @param  array<int, Accumulator>  $accumulators
     * @return list<TableRow>
     */
    public function rank(array $accumulators): array
    {
        $rows = array_map(
            fn (int $teamId, array $acc): TableRow => $this->toRow($teamId, $acc),
            array_keys($accumulators),
            array_values($accumulators),
        );

        return $this->sort($rows);
    }

    /**
     * @param  Accumulator  $acc
     */
    private function recordWin(array &$acc): void
    {
        $acc['won']++;
        $acc['points'] += self::POINTS_FOR_WIN;
    }

    /**
     * @param  Accumulator  $acc
     */
    private function recordDraw(array &$acc): void
    {
        $acc['drawn']++;
        $acc['points'] += self::POINTS_FOR_DRAW;
    }

    /**
     * @param  Accumulator  $acc
     */
    private function recordLoss(array &$acc): void
    {
        $acc['lost']++;
    }

    /**
     * @param  Accumulator  $acc
     */
    private function toRow(int $teamId, array $acc): TableRow
    {
        return new TableRow(
            teamId: $teamId,
            teamName: $acc['name'],
            played: $acc['played'],
            won: $acc['won'],
            drawn: $acc['drawn'],
            lost: $acc['lost'],
            goalsFor: $acc['goalsFor'],
            goalsAgainst: $acc['goalsAgainst'],
            points: $acc['points'],
        );
    }

    /**
     * Order by points, then goal difference, then goals scored, then name.
     *
     * @param  list<TableRow>  $rows
     * @return list<TableRow>
     */
    private function sort(array $rows): array
    {
        usort($rows, function (TableRow $a, TableRow $b): int {
            return $b->points <=> $a->points
                ?: $b->goalDifference <=> $a->goalDifference
                ?: $b->goalsFor <=> $a->goalsFor
                ?: strcmp($a->teamName, $b->teamName);
        });

        return $rows;
    }
}
