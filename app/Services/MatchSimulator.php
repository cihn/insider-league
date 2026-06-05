<?php

namespace App\Services;

use App\DataObjects\MatchResult;
use App\Models\Team;
use Random\Randomizer;

/**
 * Simulates a match score from the two teams' strengths.
 *
 * Each side's expected goals (a Poisson rate) is derived from the strength
 * ratio, softened by a square root so that a much weaker team still keeps a
 * small but real chance of scoring/winning. The home side gets a fixed
 * advantage multiplier. Goal counts are then drawn from a Poisson
 * distribution, which is what makes upsets possible yet rare.
 *
 * Goals are drawn via inverse-CDF sampling (one uniform per scoreline), which
 * lets the predictor feed in its own uniforms for antithetic variates.
 */
class MatchSimulator
{
    /** League-average goals scored by a single team in a balanced match. */
    private const float BASE_GOALS = 1.35;

    /** Multiplier applied to the home side's expected goals. */
    private const float HOME_ADVANTAGE = 1.10;

    /** Bounds keep expected goals in a realistic football range. */
    private const float MIN_EXPECTED_GOALS = 0.20;

    private const float MAX_EXPECTED_GOALS = 5.0;

    /** Safety cap on the inverse-CDF loop (a 5.0 mean never reaches this). */
    private const int MAX_GOALS = 30;

    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    /**
     * Simulate a scoreline using this instance's randomizer.
     */
    public function simulate(Team $home, Team $away): MatchResult
    {
        return $this->resultFromUniforms(
            $home,
            $away,
            $this->randomizer->nextFloat(),
            $this->randomizer->nextFloat(),
        );
    }

    /**
     * Simulate a scoreline from two externally supplied uniforms in [0, 1).
     * Used by the predictor so it can pair each draw with its antithetic
     * counterpart (1 - u) for variance reduction.
     */
    public function resultFromUniforms(Team $home, Team $away, float $uHome, float $uAway): MatchResult
    {
        [$homeExpected, $awayExpected] = $this->expectedGoals($home, $away);

        return new MatchResult(
            $this->poissonInverse($homeExpected, $uHome),
            $this->poissonInverse($awayExpected, $uAway),
        );
    }

    /**
     * Expected goals (Poisson means) for the home and away side.
     *
     * @return array{0: float, 1: float}
     */
    private function expectedGoals(Team $home, Team $away): array
    {
        // Guard against a zero strength so the ratio is always well-defined.
        $ratio = sqrt(max(1, $home->strength) / max(1, $away->strength));

        return [
            $this->clamp(self::BASE_GOALS * $ratio * self::HOME_ADVANTAGE),
            $this->clamp(self::BASE_GOALS / $ratio / self::HOME_ADVANTAGE),
        ];
    }

    private function clamp(float $expectedGoals): float
    {
        return max(self::MIN_EXPECTED_GOALS, min(self::MAX_EXPECTED_GOALS, $expectedGoals));
    }

    /**
     * Inverse-CDF Poisson sampling: the smallest k whose cumulative
     * probability reaches the uniform u.
     */
    private function poissonInverse(float $lambda, float $u): int
    {
        $probability = exp(-$lambda);
        $cumulative = $probability;
        $k = 0;

        while ($u > $cumulative && $k < self::MAX_GOALS) {
            $k++;
            $probability *= $lambda / $k;
            $cumulative += $probability;
        }

        return $k;
    }
}
