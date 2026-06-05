<?php

namespace Tests\Unit;

use App\Models\GameMatch;
use App\Models\Team;
use App\Services\ChampionshipPredictor;
use App\Services\LeagueTableService;
use App\Services\MatchSimulator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class ChampionshipPredictorTest extends TestCase
{
    public function test_a_completed_season_gives_the_winner_one_hundred_percent(): void
    {
        $teams = [$this->team(1, 90), $this->team(2, 80), $this->team(3, 70)];
        $matches = [
            $this->played(1, 2, 3, 0),
            $this->played(1, 3, 2, 0),
            $this->played(2, 3, 1, 0),
        ];

        $prediction = $this->predictor(2000)->predict($teams, $matches);

        $this->assertSame(100.0, $prediction[1]);
        $this->assertSame(0.0, $prediction[2]);
        $this->assertSame(0.0, $prediction[3]);
    }

    public function test_a_mathematically_certain_leader_is_one_hundred_percent_despite_pending_games(): void
    {
        // Team 1 already has 6 points; the only pending match (2 v 3) can give
        // its winner at most 3 points, so team 1 cannot be caught.
        $teams = [$this->team(1, 70), $this->team(2, 70), $this->team(3, 70)];
        $matches = [
            $this->played(1, 2, 1, 0),
            $this->played(1, 3, 1, 0),
            $this->pending(2, 3),
        ];

        $prediction = $this->predictor(500)->predict($teams, $matches);

        $this->assertSame(100.0, $prediction[1]);
        $this->assertSame(0.0, $prediction[2]);
        $this->assertSame(0.0, $prediction[3]);
    }

    public function test_percentages_sum_to_one_hundred(): void
    {
        $teams = [$this->team(1, 85), $this->team(2, 75), $this->team(3, 70), $this->team(4, 60)];
        $matches = [
            $this->played(1, 2, 2, 1),
            $this->pending(3, 4),
            $this->pending(1, 3),
            $this->pending(2, 4),
        ];

        $prediction = $this->predictor(2000)->predict($teams, $matches);

        $this->assertEqualsWithDelta(100.0, array_sum($prediction), 0.0001);
    }

    public function test_an_open_two_horse_race_splits_the_chances(): void
    {
        // Equal strengths, a single decider: both teams must have a real chance
        // and neither should be certain.
        $teams = [$this->team(1, 75), $this->team(2, 75)];
        $matches = [$this->pending(1, 2)];

        $prediction = $this->predictor(3000)->predict($teams, $matches);

        $this->assertGreaterThan(0, $prediction[1]);
        $this->assertGreaterThan(0, $prediction[2]);
        $this->assertLessThan(100, $prediction[1]);
    }

    private function predictor(int $iterations): ChampionshipPredictor
    {
        return new ChampionshipPredictor(
            new MatchSimulator(new Randomizer(new Mt19937(2025))),
            new LeagueTableService,
            $iterations,
            // Seed the predictor's own randomizer too, so the Monte Carlo runs
            // are deterministic and these assertions can never flake.
            new Randomizer(new Mt19937(777)),
        );
    }

    private function team(int $id, int $strength): Team
    {
        $team = new Team(['name' => "Team {$id}", 'strength' => $strength]);
        $team->id = $id;

        return $team;
    }

    private function played(int $home, int $away, int $homeGoals, int $awayGoals): GameMatch
    {
        return new GameMatch([
            'week' => 1,
            'home_team_id' => $home,
            'away_team_id' => $away,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played' => true,
        ]);
    }

    private function pending(int $home, int $away): GameMatch
    {
        return new GameMatch([
            'week' => 2,
            'home_team_id' => $home,
            'away_team_id' => $away,
            'played' => false,
        ]);
    }
}
