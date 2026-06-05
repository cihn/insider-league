<?php

namespace Tests\Unit;

use App\DataObjects\ScheduledMatch;
use App\Services\FixtureGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FixtureGeneratorTest extends TestCase
{
    private FixtureGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new FixtureGenerator;
    }

    public function test_four_teams_produce_twelve_matches_across_six_weeks(): void
    {
        $schedule = $this->generator->generate([1, 2, 3, 4]);

        $this->assertCount(12, $schedule);
        $this->assertSame(6, $this->weekCount($schedule));
    }

    public function test_each_week_has_two_matches_and_every_team_plays_once(): void
    {
        $schedule = $this->generator->generate([1, 2, 3, 4]);

        foreach ($this->groupByWeek($schedule) as $week => $matches) {
            $this->assertCount(2, $matches, "Week {$week} should have two matches");

            $teamsThisWeek = [];
            foreach ($matches as $match) {
                $teamsThisWeek[] = $match->homeTeamId;
                $teamsThisWeek[] = $match->awayTeamId;
            }

            $this->assertEqualsCanonicalizing([1, 2, 3, 4], $teamsThisWeek);
        }
    }

    public function test_every_pair_meets_twice_once_home_and_once_away(): void
    {
        $schedule = $this->generator->generate([1, 2, 3, 4]);

        $orderedPairs = array_map(
            fn (ScheduledMatch $m): string => "{$m->homeTeamId}-{$m->awayTeamId}",
            $schedule,
        );

        // No directed fixture is repeated...
        $this->assertCount(12, array_unique($orderedPairs));

        // ...and every unordered pair appears exactly twice (home + away).
        $unorderedCounts = [];
        foreach ($schedule as $match) {
            $key = implode('-', $this->sortedPair($match));
            $unorderedCounts[$key] = ($unorderedCounts[$key] ?? 0) + 1;
        }

        $this->assertCount(6, $unorderedCounts);
        foreach ($unorderedCounts as $count) {
            $this->assertSame(2, $count);
        }
    }

    public function test_second_leg_mirrors_the_first_with_reversed_venue(): void
    {
        $schedule = $this->generator->generate([1, 2, 3, 4]);

        $firstLeg = array_filter($schedule, fn (ScheduledMatch $m): bool => $m->week <= 3);
        $secondLeg = array_filter($schedule, fn (ScheduledMatch $m): bool => $m->week > 3);

        $firstPairs = array_map(fn (ScheduledMatch $m): string => "{$m->homeTeamId}-{$m->awayTeamId}", $firstLeg);

        foreach ($secondLeg as $match) {
            $this->assertContains("{$match->awayTeamId}-{$match->homeTeamId}", $firstPairs);
        }
    }

    public function test_an_odd_line_up_uses_a_bye_without_breaking(): void
    {
        // Five teams: double round-robin => C(5,2) * 2 = 20 matches over 10
        // weeks, with one team resting (a bye) each week.
        $schedule = $this->generator->generate([1, 2, 3, 4, 5]);

        $this->assertCount(20, $schedule);
        $this->assertSame(10, $this->weekCount($schedule));

        // No bye placeholder (0) ever leaks into a real fixture.
        foreach ($schedule as $match) {
            $this->assertNotSame(0, $match->homeTeamId);
            $this->assertNotSame(0, $match->awayTeamId);
        }
    }

    public function test_with_an_odd_line_up_every_team_still_plays_each_rival_home_and_away(): void
    {
        $schedule = $this->generator->generate([1, 2, 3, 4, 5]);

        $appearances = array_fill_keys([1, 2, 3, 4, 5], 0);
        foreach ($schedule as $match) {
            $appearances[$match->homeTeamId]++;
            $appearances[$match->awayTeamId]++;
        }

        // Each team meets the other four twice => 8 matches each.
        foreach ($appearances as $teamId => $count) {
            $this->assertSame(8, $count, "Team {$teamId} should play 8 matches");
        }

        // At most two matches per week (five teams => one rests).
        foreach ($this->groupByWeek($schedule) as $week => $matches) {
            $this->assertLessThanOrEqual(2, count($matches), "Week {$week} has too many matches");
        }
    }

    public function test_it_rejects_fewer_than_two_teams(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([1]);
    }

    public function test_it_scales_to_more_teams(): void
    {
        // Six teams: double round-robin => 6 * 5 = 30 matches over 10 weeks.
        $schedule = $this->generator->generate([1, 2, 3, 4, 5, 6]);

        $this->assertCount(30, $schedule);
        $this->assertSame(10, $this->weekCount($schedule));
    }

    /**
     * @param  list<ScheduledMatch>  $schedule
     * @return array<int, list<ScheduledMatch>>
     */
    private function groupByWeek(array $schedule): array
    {
        $weeks = [];
        foreach ($schedule as $match) {
            $weeks[$match->week][] = $match;
        }

        return $weeks;
    }

    /**
     * @param  list<ScheduledMatch>  $schedule
     */
    private function weekCount(array $schedule): int
    {
        return count($this->groupByWeek($schedule));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function sortedPair(ScheduledMatch $match): array
    {
        $pair = [$match->homeTeamId, $match->awayTeamId];
        sort($pair);

        return $pair;
    }
}
