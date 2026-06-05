<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Championship Prediction Iterations
    |--------------------------------------------------------------------------
    |
    | Number of Monte Carlo runs used to estimate each team's title chance.
    | Higher values are more accurate but slower; a few thousand is a good
    | balance for a four-team league.
    |
    */

    'prediction_iterations' => (int) env('LEAGUE_PREDICTION_ITERATIONS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Prediction Window
    |--------------------------------------------------------------------------
    |
    | Championship predictions are only shown during the last N weeks of the
    | season, matching the brief ("after the 4th week") and FAQ ("the last 3
    | weeks"). Before that the panel stays locked.
    |
    */

    'prediction_window_weeks' => (int) env('LEAGUE_PREDICTION_WINDOW_WEEKS', 3),

];
