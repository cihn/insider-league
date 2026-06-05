<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchRequest;
use App\Models\GameMatch;
use App\Services\SimulationService;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function __construct(
        private readonly SimulationService $simulation,
    ) {}

    /**
     * Edit a single match result; the table and predictions are recomputed
     * from the updated standings on the next state request.
     */
    public function update(UpdateMatchRequest $request, GameMatch $match): JsonResponse
    {
        $this->simulation->updateMatchResult(
            $match,
            $request->integer('home_goals'),
            $request->integer('away_goals'),
        );

        return response()->json($this->simulation->getState());
    }
}
