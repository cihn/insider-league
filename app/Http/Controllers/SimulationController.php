<?php

namespace App\Http\Controllers;

use App\Services\SimulationService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class SimulationController extends Controller
{
    public function __construct(
        private readonly SimulationService $simulation,
    ) {}

    /**
     * Current league state: teams, fixtures, table and predictions.
     */
    public function state(): JsonResponse
    {
        return response()->json($this->simulation->getState());
    }

    /**
     * Generate a fresh schedule for the current teams.
     */
    public function generateFixtures(): JsonResponse
    {
        try {
            $this->simulation->generateFixtures();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->simulation->getState());
    }

    /**
     * Simulate the next unplayed week.
     */
    public function playNextWeek(): JsonResponse
    {
        $this->simulation->playNextWeek();

        return response()->json($this->simulation->getState());
    }

    /**
     * Simulate all remaining weeks to the end of the season.
     */
    public function playAllWeeks(): JsonResponse
    {
        $this->simulation->playAllWeeks();

        return response()->json($this->simulation->getState());
    }

    /**
     * Clear all fixtures and results.
     */
    public function reset(): JsonResponse
    {
        $this->simulation->reset();

        return response()->json($this->simulation->getState());
    }
}
