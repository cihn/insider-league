# Insider One Champions League

A football league simulation: generate a double round-robin fixture list, play
the matches week by week (or all at once), watch the Premier League-style table
update, and - once results come in - see each team's title chance estimated with
a Monte Carlo simulation.

**Laravel 13 / PHP 8.4**, a thin JSON API, and a **Blade + Bootstrap + native-JS
component** front-end. No build step (`npm`) required.

## Demo flow

1. **Tournament Teams** - teams and strengths -> *Generate Fixtures*.
2. **Generated Fixtures** - the full schedule -> *Start Simulation*.
3. **Simulation** - League Table (`P W D L GD Pts`), the selected Week's Results
   (navigable; edit any score inline) and Championship Predictions (`%`), with
   *Play Next Week* / *Play All Weeks* / *Reset Data*.

## Requirements coverage

| Requirement (brief / FAQ) | Where |
| --- | --- |
| Four teams of different strengths | `database/seeders/TeamSeeder.php` |
| Premier League scoring (3/1/0) & ordering (Pts -> GD -> GF) | `LeagueTableService` |
| Double round-robin fixtures (home & away) | `FixtureGenerator` |
| Strength-based sim; weak team keeps a small chance; home advantage | `MatchSimulator` |
| Championship % from remaining games; certainty (100%/0%) handled | `ChampionshipPredictor` |
| PHP / Laravel, OOP | service layer + DTOs |
| JavaScript with a component pattern | `public/js/components/*` |
| Automated tests | `tests/` (33 tests) |
| **Extra:** Play All to the end, results by week | *Play All Weeks* + week navigation |
| **Extra:** Edit a result and recalculate | inline edit -> `MatchController@update` |

## Architecture

Domain logic lives in small single-responsibility services; controllers are thin
and delegate to a `SimulationService` orchestrator. The standings are never
stored - they are **derived from the matches** every request, so the table and
predictions can't drift out of sync, and editing a result recalculates for free.

```
app/
|-- DataObjects/   ScheduledMatch, MatchResult, TableRow (immutable; TableRow has a virtual goalDifference)
|-- Services/      FixtureGenerator, MatchSimulator, LeagueTableService,
|                  ChampionshipPredictor, SimulationService
|-- Http/Controllers/  Page, Simulation, Match
`-- Models/        Team, GameMatch

public/js/         lib/ (ApiClient, Component, dom) - components/ (views + table/results/predictions) - app.js
```

### Simulation

Each side's expected goals comes from the strength ratio (softened by a square
root so weak teams keep a realistic chance), with a home-advantage multiplier:

```
ratio        = sqrt(homeStrength / awayStrength)
homeExpected = 1.35 * ratio * 1.10      awayExpected = 1.35 / ratio / 1.10
```

Goals are drawn from a **Poisson** distribution (inverse-CDF), making upsets
possible but rare. `MatchSimulator` takes an injectable `\Random\Randomizer`, so
tests are deterministic.

### Predictions

`ChampionshipPredictor` runs a **Monte Carlo** simulation - it replays the pending
matches thousands of times and each team's share of "finished top" runs becomes
its percentage. Settled cases need no special-casing (an uncatchable leader tops
every run -> 100%, eliminated teams -> 0%). Per the brief ("after the 4th week") and
FAQ ("the last 3 weeks"), the panel stays **locked until the final
`LEAGUE_PREDICTION_WINDOW_WEEKS` (default 3) weeks** of the season.

Three techniques keep it fast while staying a true Monte Carlo, cutting runtime
~4x versus a naive rebuild: **incremental base** (played standings accumulated
once, copied per run), **antithetic variates** (each draw `u` paired with `1 - u`),
and **adaptive stopping** (stop once every percentage's standard error is small).
Combined with the last-3-weeks window - which keeps the number of pending matches
small exactly when predictions are shown - this stays instant for any realistic
league size.

## Getting started

Requires PHP 8.4+ with `pdo_sqlite` and Composer.

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
```

To change teams, edit `TeamSeeder` and re-run `php artisan migrate:fresh --seed`.
The fixture generator handles **any number of teams** - odd counts add a "bye" so
one team rests each week, so the team count never breaks the schedule. Prefer
MySQL? Set `DB_*` in `.env` and migrate.

## Tests & API

```bash
php artisan test            # 33 tests   -   ./vendor/bin/pint --test   # style
```

Covering fixture generation (incl. byes/scaling), table ordering, the simulator
(determinism, upsets, the favourite winning more), the predictor (certainty, open
races), the last-weeks prediction window, and the full HTTP flow.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/state` | Teams, fixtures, table, predictions |
| POST | `/api/fixtures` | Generate a fresh schedule |
| POST | `/api/simulate/next` - `/all` | Play the next week - all remaining |
| POST | `/api/reset` | Clear fixtures and results |
| PATCH | `/api/matches/{match}` | Edit a result (`home_goals`, `away_goals`) |
