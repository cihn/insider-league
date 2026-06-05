<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use Random\Randomizer;

/**
 * Estimates each team's title chance with a Monte Carlo simulation: the
 * remaining (unplayed) matches are simulated many times, and the share of
 * runs a team finishes top becomes its championship percentage.
 *
 * Performance techniques, all of which keep it a true Monte Carlo:
 *  - Incremental base: the played standings are computed once and copied per
 *    run, so only the pending matches are re-applied.
 *  - Antithetic variates: each random draw is paired with its mirror (1 - u),
 *    halving the variance for a given number of runs.
 *  - Adaptive stopping (predict): runs in blocks and stops early once every
 *    percentage's standard error is small enough.
 *
 * Mathematically settled situations need no special-casing — a team that
 * can't be caught tops every run (100%) and eliminated teams reach 0%.
 */
class ChampionshipPredictor
{
    /** Run at least this many iterations before adaptive stopping kicks in. */
    private const int MIN_ITERATIONS = 1000;

    /** Iterations per adaptive block. */
    private const int BLOCK = 1000;

    /** Stop once the largest percentage standard error drops below this (as a fraction). */
    private const float STANDARD_ERROR_TARGET = 0.004;

    public function __construct(
        private readonly MatchSimulator $simulator,
        private readonly LeagueTableService $tableService,
        private readonly int $maxIterations = 5000,
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    /**
     * Championship percentages keyed by team id (0-100), using adaptive
     * stopping. Suitable for the synchronous path and per-chunk consumers.
     *
     * @param  iterable<Team>  $teams
     * @param  iterable<GameMatch>  $matches
     * @return array<int, float>
     */
    public function predict(iterable $teams, iterable $matches): array
    {
        $prepared = $this->prepare($teams, $matches);

        if ($prepared['pending'] === []) {
            return $this->certainOutcome($prepared);
        }

        $counts = array_fill_keys(array_keys($prepared['teamsById']), 0);
        $done = 0;

        while ($done < $this->maxIterations) {
            $block = min(self::BLOCK, $this->maxIterations - $done);
            $this->runIterations($prepared, $block, $counts);
            $done += $block;

            if ($done >= self::MIN_ITERATIONS && $this->hasConverged($counts, $done)) {
                break;
            }
        }

        return $this->toPercentages($counts, $done);
    }

    /**
     * @param  array{teamsById: array<int, Team>, base: array<int, array>, pending: list<GameMatch>}  $prepared
     * @param  array<int, int>  $counts
     */
    private function runIterations(array $prepared, int $iterations, array &$counts): void
    {
        $pairs = intdiv($iterations, 2);

        for ($i = 0; $i < $pairs; $i++) {
            $uniforms = $this->drawUniforms($prepared['pending']);
            $counts[$this->championFor($prepared, $uniforms, false)]++;
            $counts[$this->championFor($prepared, $uniforms, true)]++;
        }

        if ($iterations % 2 === 1) {
            $uniforms = $this->drawUniforms($prepared['pending']);
            $counts[$this->championFor($prepared, $uniforms, false)]++;
        }
    }

    /**
     * Two uniforms per pending match (home and away scorelines).
     *
     * @param  list<GameMatch>  $pending
     * @return list<array{0: float, 1: float}>
     */
    private function drawUniforms(array $pending): array
    {
        $uniforms = [];
        foreach ($pending as $index => $match) {
            $uniforms[$index] = [$this->randomizer->nextFloat(), $this->randomizer->nextFloat()];
        }

        return $uniforms;
    }

    /**
     * Simulate one full season on a copy of the base standings and return the
     * champion's team id. With $antithetic, every uniform is mirrored to 1 - u.
     *
     * @param  array{teamsById: array<int, Team>, base: array<int, array>, pending: list<GameMatch>}  $prepared
     * @param  list<array{0: float, 1: float}>  $uniforms
     */
    private function championFor(array $prepared, array $uniforms, bool $antithetic): int
    {
        $accumulators = $prepared['base'];

        foreach ($prepared['pending'] as $index => $match) {
            [$uHome, $uAway] = $uniforms[$index];
            if ($antithetic) {
                $uHome = 1.0 - $uHome;
                $uAway = 1.0 - $uAway;
            }

            $result = $this->simulator->resultFromUniforms(
                $prepared['teamsById'][$match->home_team_id],
                $prepared['teamsById'][$match->away_team_id],
                $uHome,
                $uAway,
            );

            $this->tableService->applyResult(
                $accumulators,
                $match->home_team_id,
                $match->away_team_id,
                $result->homeGoals,
                $result->awayGoals,
            );
        }

        return $this->tableService->rank($accumulators)[0]->teamId;
    }

    /**
     * Converged when even the least-certain percentage has a small standard
     * error, i.e. more runs would not meaningfully move the numbers.
     *
     * @param  array<int, int>  $counts
     */
    private function hasConverged(array $counts, int $n): bool
    {
        foreach ($counts as $count) {
            $p = $count / $n;
            if (sqrt($p * (1 - $p) / $n) > self::STANDARD_ERROR_TARGET) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  iterable<Team>  $teams
     * @param  iterable<GameMatch>  $matches
     * @return array{teamsById: array<int, Team>, base: array<int, array>, pending: list<GameMatch>}
     */
    private function prepare(iterable $teams, iterable $matches): array
    {
        $teams = $this->toList($teams);
        $matches = $this->toList($matches);

        $teamsById = [];
        foreach ($teams as $team) {
            $teamsById[$team->id] = $team;
        }

        $played = array_values(array_filter($matches, fn (GameMatch $m): bool => $m->played));
        $pending = array_values(array_filter($matches, fn (GameMatch $m): bool => ! $m->played));

        return [
            'teamsById' => $teamsById,
            'base' => $this->tableService->accumulate($teams, $played),
            'pending' => $pending,
        ];
    }

    /**
     * @param  array{teamsById: array<int, Team>, base: array<int, array>}  $prepared
     * @return array<int, float>
     */
    private function certainOutcome(array $prepared): array
    {
        $champion = $this->tableService->rank($prepared['base'])[0]->teamId;

        $percentages = [];
        foreach (array_keys($prepared['teamsById']) as $teamId) {
            $percentages[$teamId] = $teamId === $champion ? 100.0 : 0.0;
        }

        return $percentages;
    }

    /**
     * @param  array<int, int>  $counts
     * @return array<int, float>
     */
    private function toPercentages(array $counts, int $total): array
    {
        if ($total === 0) {
            return array_map(fn (): float => 0.0, $counts);
        }

        return array_map(fn (int $count): float => $count / $total * 100, $counts);
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private function toList(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
