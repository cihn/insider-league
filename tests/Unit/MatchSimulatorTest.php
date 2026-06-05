<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\MatchSimulator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class MatchSimulatorTest extends TestCase
{
    public function test_it_is_deterministic_for_a_fixed_seed(): void
    {
        $strong = $this->team(90);
        $weak = $this->team(60);

        $first = $this->seededSimulator(123)->simulate($strong, $weak);
        $second = $this->seededSimulator(123)->simulate($strong, $weak);

        $this->assertSame($first->homeGoals, $second->homeGoals);
        $this->assertSame($first->awayGoals, $second->awayGoals);
    }

    public function test_scores_are_never_negative(): void
    {
        $simulator = $this->seededSimulator(7);
        $a = $this->team(85);
        $b = $this->team(75);

        for ($i = 0; $i < 200; $i++) {
            $result = $simulator->simulate($a, $b);
            $this->assertGreaterThanOrEqual(0, $result->homeGoals);
            $this->assertGreaterThanOrEqual(0, $result->awayGoals);
        }
    }

    public function test_the_much_stronger_team_wins_far_more_often(): void
    {
        $simulator = $this->seededSimulator(2024);
        $strong = $this->team(95);
        $weak = $this->team(45);

        $strongWins = 0;
        $weakWins = 0;
        $runs = 1000;

        for ($i = 0; $i < $runs; $i++) {
            $result = $simulator->simulate($strong, $weak);
            if ($result->isHomeWin()) {
                $strongWins++;
            } elseif ($result->isAwayWin()) {
                $weakWins++;
            }
        }

        $this->assertGreaterThan($weakWins, $strongWins);
        // The favourite should dominate, taking well over half of all matches.
        $this->assertGreaterThan($runs * 0.6, $strongWins);
    }

    public function test_even_the_weaker_team_can_win_sometimes(): void
    {
        // Across many matches an upset must occur at least once, satisfying the
        // requirement that a weaker team still has a small but real chance.
        $simulator = $this->seededSimulator(99);
        $strong = $this->team(95);
        $weak = $this->team(50);

        $weakWins = 0;
        for ($i = 0; $i < 1000; $i++) {
            if ($simulator->simulate($strong, $weak)->isAwayWin()) {
                $weakWins++;
            }
        }

        $this->assertGreaterThan(0, $weakWins);
    }

    public function test_zero_strength_does_not_divide_by_zero(): void
    {
        $result = $this->seededSimulator(1)->simulate($this->team(0), $this->team(0));

        $this->assertGreaterThanOrEqual(0, $result->homeGoals);
        $this->assertGreaterThanOrEqual(0, $result->awayGoals);
    }

    private function seededSimulator(int $seed): MatchSimulator
    {
        return new MatchSimulator(new Randomizer(new Mt19937($seed)));
    }

    private function team(int $strength): Team
    {
        $team = new Team(['name' => "Team {$strength}", 'strength' => $strength]);
        $team->id = $strength;

        return $team;
    }
}
