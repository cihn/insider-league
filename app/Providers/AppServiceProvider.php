<?php

namespace App\Providers;

use App\Services\ChampionshipPredictor;
use App\Services\LeagueTableService;
use App\Services\MatchSimulator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChampionshipPredictor::class, function ($app): ChampionshipPredictor {
            return new ChampionshipPredictor(
                $app->make(MatchSimulator::class),
                $app->make(LeagueTableService::class),
                (int) config('league.prediction_iterations'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
