<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMatch>
 */
class GameMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week' => fake()->numberBetween(1, 6),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_goals' => null,
            'away_goals' => null,
            'played' => false,
        ];
    }

    /**
     * Mark the match as played with the given score.
     */
    public function played(int $homeGoals, int $awayGoals): static
    {
        return $this->state(fn (array $attributes): array => [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played' => true,
        ]);
    }
}
