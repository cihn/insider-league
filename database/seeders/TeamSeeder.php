<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * The four league teams with distinct strengths.
     *
     * @var list<array{name: string, strength: int}>
     */
    private const TEAMS = [
        ['name' => 'M. City', 'strength' => 90],
        ['name' => 'Liverpool', 'strength' => 85],
        ['name' => 'Chelsea', 'strength' => 78],
        ['name' => 'Arsenal', 'strength' => 72],
    ];

    public function run(): void
    {
        foreach (self::TEAMS as $team) {
            Team::query()->updateOrCreate(['name' => $team['name']], $team);
        }
    }
}
