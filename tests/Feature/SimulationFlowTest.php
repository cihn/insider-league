<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed four-team line-up so the flow assertions don't depend on the
        // seeder's contents.
        Team::factory()->createMany([
            ['name' => 'Alpha', 'strength' => 90],
            ['name' => 'Bravo', 'strength' => 80],
            ['name' => 'Charlie', 'strength' => 70],
            ['name' => 'Delta', 'strength' => 60],
        ]);
    }

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_home_page_works_with_the_database_session_driver(): void
    {
        // The app runs with SESSION_DRIVER=database, which writes to the
        // sessions table — this guards that schema (e.g. the user_id column).
        config(['session.driver' => 'database']);

        $this->get('/')->assertOk();
    }

    public function test_state_exposes_teams_and_empty_fixtures_initially(): void
    {
        $this->getJson('/api/state')
            ->assertOk()
            ->assertJsonCount(4, 'teams')
            ->assertJsonPath('has_fixtures', false)
            ->assertJsonPath('fixtures', []);
    }

    public function test_generating_fixtures_creates_a_full_schedule(): void
    {
        $this->postJson('/api/fixtures')
            ->assertOk()
            ->assertJsonPath('has_fixtures', true)
            ->assertJsonPath('total_weeks', 6);

        $this->assertSame(12, GameMatch::query()->count());
        $this->assertSame(0, GameMatch::query()->where('played', true)->count());
    }

    public function test_playing_the_next_week_plays_exactly_one_week(): void
    {
        $this->postJson('/api/fixtures');

        $this->postJson('/api/simulate/next')
            ->assertOk()
            ->assertJsonPath('next_week', 2);

        $this->assertSame(2, GameMatch::query()->where('played', true)->count());
    }

    public function test_generating_fixtures_with_too_few_teams_returns_422(): void
    {
        Team::query()->delete();
        Team::factory()->create(['name' => 'Solo', 'strength' => 50]);

        $this->postJson('/api/fixtures')
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_predictions_are_locked_until_the_last_weeks(): void
    {
        $this->postJson('/api/fixtures');
        $this->postJson('/api/simulate/next'); // week 1 of 6 — too early

        $this->getJson('/api/state')
            ->assertOk()
            ->assertJsonPath('predictions_status', 'locked');
    }

    public function test_playing_all_weeks_completes_the_season(): void
    {
        $this->postJson('/api/fixtures');

        $response = $this->postJson('/api/simulate/all')
            ->assertOk()
            ->assertJsonPath('is_complete', true)
            ->assertJsonPath('next_week', null);

        $this->assertSame(12, GameMatch::query()->where('played', true)->count());

        // Predictions resolve to a single champion on 100%.
        $predictions = $response->json('predictions');
        $this->assertContains(100.0, array_map('floatval', $predictions));
        $this->assertEqualsWithDelta(100.0, array_sum($predictions), 0.0001);
    }

    public function test_resetting_clears_all_fixtures(): void
    {
        $this->postJson('/api/fixtures');
        $this->assertSame(12, GameMatch::query()->count());

        $this->postJson('/api/reset')
            ->assertOk()
            ->assertJsonPath('has_fixtures', false);

        $this->assertSame(0, GameMatch::query()->count());
    }

    public function test_a_match_result_can_be_edited(): void
    {
        $this->postJson('/api/fixtures');
        $this->postJson('/api/simulate/all');

        $match = GameMatch::query()->firstOrFail();

        $this->patchJson("/api/matches/{$match->id}", [
            'home_goals' => 7,
            'away_goals' => 1,
        ])->assertOk();

        $match->refresh();
        $this->assertSame(7, $match->home_goals);
        $this->assertSame(1, $match->away_goals);
        $this->assertTrue($match->played);
    }

    public function test_editing_a_match_validates_the_score(): void
    {
        $this->postJson('/api/fixtures');
        $match = GameMatch::query()->firstOrFail();

        $this->patchJson("/api/matches/{$match->id}", [
            'home_goals' => -1,
            'away_goals' => 'oops',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['home_goals', 'away_goals']);
    }
}
