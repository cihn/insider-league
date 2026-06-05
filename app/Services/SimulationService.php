<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * High-level orchestration of the league lifecycle: generating fixtures,
 * playing weeks, editing results and resetting. Controllers stay thin and
 * delegate everything here.
 */
class SimulationService
{
    /** Prediction panel is locked until the season reaches its final weeks. */
    private const STATUS_LOCKED = 'locked';

    private const STATUS_READY = 'ready';

    public function __construct(
        private readonly FixtureGenerator $fixtureGenerator,
        private readonly MatchSimulator $simulator,
        private readonly LeagueTableService $tableService,
        private readonly ChampionshipPredictor $predictor,
    ) {}

    /**
     * Generate a fresh double round-robin schedule for the current teams,
     * discarding any previous fixtures and results.
     */
    public function generateFixtures(): void
    {
        $teamIds = Team::query()->orderBy('id')->pluck('id')->all();
        $schedule = $this->fixtureGenerator->generate($teamIds);

        DB::transaction(function () use ($schedule): void {
            GameMatch::query()->delete();

            foreach ($schedule as $fixture) {
                GameMatch::query()->create([
                    'week' => $fixture->week,
                    'home_team_id' => $fixture->homeTeamId,
                    'away_team_id' => $fixture->awayTeamId,
                ]);
            }
        });
    }

    /**
     * Play the next unplayed week, returning the week number played
     * or null if the season is already complete.
     */
    public function playNextWeek(): ?int
    {
        $week = $this->nextUnplayedWeek();

        if ($week === null) {
            return null;
        }

        $this->playWeek($week);

        return $week;
    }

    /**
     * Play every remaining week to the end of the season.
     */
    public function playAllWeeks(): void
    {
        while (($week = $this->nextUnplayedWeek()) !== null) {
            $this->playWeek($week);
        }
    }

    /**
     * Overwrite a single match result and mark it played. The standings and
     * predictions are always recomputed from matches, so they update for free.
     */
    public function updateMatchResult(GameMatch $match, int $homeGoals, int $awayGoals): GameMatch
    {
        $match->update([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played' => true,
        ]);

        return $match;
    }

    /**
     * Remove all fixtures and results, returning to a clean slate.
     */
    public function reset(): void
    {
        GameMatch::query()->delete();
    }

    private function playWeek(int $week): void
    {
        $teams = Team::query()->get()->keyBy('id');
        $matches = GameMatch::query()->forWeek($week)->where('played', false)->get();

        DB::transaction(function () use ($matches, $teams): void {
            foreach ($matches as $match) {
                $result = $this->simulator->simulate(
                    $teams[$match->home_team_id],
                    $teams[$match->away_team_id],
                );

                $match->update([
                    'home_goals' => $result->homeGoals,
                    'away_goals' => $result->awayGoals,
                    'played' => true,
                ]);
            }
        });
    }

    private function nextUnplayedWeek(): ?int
    {
        $week = GameMatch::query()->where('played', false)->min('week');

        return $week === null ? null : (int) $week;
    }

    /**
     * Build the full view-state for the simulation screen.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $teams = Team::query()->orderBy('id')->get();
        $matches = GameMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        $table = $this->tableService->calculate($teams, $matches);
        $prediction = $this->predictionState($teams, $matches);

        return [
            'teams' => $teams->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'strength' => $team->strength,
            ])->all(),
            'fixtures' => $this->fixturesByWeek($matches),
            'table' => array_map(fn ($row): array => $row->toArray(), $table),
            'predictions' => $prediction['data'],
            'predictions_status' => $prediction['status'],
            'total_weeks' => (int) $matches->max('week'),
            'next_week' => $this->nextUnplayedWeek(),
            'has_fixtures' => $matches->isNotEmpty(),
            'is_complete' => $matches->isNotEmpty() && $this->nextUnplayedWeek() === null,
        ];
    }

    /**
     * Group matches into weeks for display.
     *
     * @param  Collection<int, GameMatch>  $matches
     * @return list<array<string, mixed>>
     */
    private function fixturesByWeek(Collection $matches): array
    {
        return $matches
            ->groupBy('week')
            ->map(fn (Collection $weekMatches, int $week): array => [
                'week' => $week,
                'matches' => $weekMatches->map(fn (GameMatch $match): array => [
                    'id' => $match->id,
                    'home_team' => $match->homeTeam->name,
                    'away_team' => $match->awayTeam->name,
                    'home_goals' => $match->home_goals,
                    'away_goals' => $match->away_goals,
                    'played' => $match->played,
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Championship prediction state. Per the brief/FAQ, predictions are shown
     * only in the season's last few weeks ("after the 4th week" / "the last 3
     * weeks") — until then the panel is locked.
     *
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, GameMatch>  $matches
     * @return array{status: string, data: array<int, float>}
     */
    private function predictionState(Collection $teams, Collection $matches): array
    {
        if (! $this->predictionsOpen($matches)) {
            $zeros = $teams->mapWithKeys(fn (Team $team): array => [$team->id => 0.0])->all();

            return ['status' => self::STATUS_LOCKED, 'data' => $zeros];
        }

        return ['status' => self::STATUS_READY, 'data' => $this->predictor->predict($teams, $matches)];
    }

    /**
     * Whether the season has reached its final weeks, when predictions are
     * shown. Needs at least one played week, and to be within the configured
     * window of the end of the season.
     *
     * @param  Collection<int, GameMatch>  $matches
     */
    private function predictionsOpen(Collection $matches): bool
    {
        $totalWeeks = (int) $matches->max('week');

        if ($totalWeeks === 0) {
            return false;
        }

        $playedWeeks = $matches->where('played', true)->pluck('week')->unique()->count();
        $window = (int) config('league.prediction_window_weeks');

        return $playedWeeks >= max(1, $totalWeeks - $window);
    }
}
