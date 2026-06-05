<?php

namespace Tests\Unit;

use App\DataObjects\TableRow;
use App\Models\GameMatch;
use App\Models\Team;
use App\Services\LeagueTableService;
use PHPUnit\Framework\TestCase;

class LeagueTableServiceTest extends TestCase
{
    private LeagueTableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeagueTableService;
    }

    public function test_win_is_worth_three_points_and_draw_one(): void
    {
        $teams = [$this->team(1, 'A', 80), $this->team(2, 'B', 70), $this->team(3, 'C', 60)];
        $matches = [
            $this->match(1, 2, 2, 0), // A beats B
            $this->match(1, 3, 1, 1), // A draws C
        ];

        $rows = $this->keyById($this->service->calculate($teams, $matches));

        $this->assertSame(4, $rows[1]->points); // win + draw
        $this->assertSame(0, $rows[2]->points); // loss
        $this->assertSame(1, $rows[3]->points); // draw
        $this->assertSame(1, $rows[1]->won);
        $this->assertSame(1, $rows[1]->drawn);
    }

    public function test_unplayed_matches_are_ignored(): void
    {
        $teams = [$this->team(1, 'A', 80), $this->team(2, 'B', 70)];
        $matches = [
            $this->match(1, 2, 3, 0),
            $this->unplayedMatch(2, 1),
        ];

        $rows = $this->keyById($this->service->calculate($teams, $matches));

        $this->assertSame(1, $rows[1]->played);
        $this->assertSame(1, $rows[2]->played);
        $this->assertSame(3, $rows[1]->points);
    }

    public function test_goal_difference_is_tracked(): void
    {
        $teams = [$this->team(1, 'A', 80), $this->team(2, 'B', 70)];
        $matches = [$this->match(1, 2, 4, 1)];

        $rows = $this->keyById($this->service->calculate($teams, $matches));

        $this->assertSame(4, $rows[1]->goalsFor);
        $this->assertSame(1, $rows[1]->goalsAgainst);
        $this->assertSame(3, $rows[1]->goalDifference);
        $this->assertSame(-3, $rows[2]->goalDifference);
    }

    public function test_table_is_ordered_by_points_then_goal_difference_then_goals_for(): void
    {
        $teams = [
            $this->team(1, 'Equal Low GD', 50),
            $this->team(2, 'Top Points', 50),
            $this->team(3, 'Equal High GD', 50),
        ];

        // Team 2 has the most points. Teams 1 and 3 are level on points,
        // but team 3 has the better goal difference.
        $matches = [
            $this->match(2, 1, 5, 0), // Top beats Equal Low
            $this->match(3, 1, 1, 0), // Equal High edges Equal Low
            $this->match(2, 3, 1, 0), // Top beats Equal High
        ];

        $rows = $this->service->calculate($teams, $matches);

        $this->assertSame('Top Points', $rows[0]->teamName);
        $this->assertSame('Equal High GD', $rows[1]->teamName);
        $this->assertSame('Equal Low GD', $rows[2]->teamName);
    }

    public function test_an_all_draw_round_leaves_everyone_level(): void
    {
        $teams = [$this->team(1, 'A', 50), $this->team(2, 'B', 50)];
        $matches = [$this->match(1, 2, 1, 1)];

        $rows = $this->service->calculate($teams, $matches);

        $this->assertSame(1, $rows[0]->points);
        $this->assertSame(1, $rows[1]->points);
        $this->assertSame(0, $rows[0]->goalDifference);
    }

    private function team(int $id, string $name, int $strength): Team
    {
        $team = new Team(['name' => $name, 'strength' => $strength]);
        $team->id = $id;

        return $team;
    }

    private function match(int $home, int $away, int $homeGoals, int $awayGoals): GameMatch
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

    private function unplayedMatch(int $home, int $away): GameMatch
    {
        return new GameMatch([
            'week' => 2,
            'home_team_id' => $home,
            'away_team_id' => $away,
            'played' => false,
        ]);
    }

    /**
     * @param  list<TableRow>  $rows
     * @return array<int, TableRow>
     */
    private function keyById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->teamId] = $row;
        }

        return $byId;
    }
}
