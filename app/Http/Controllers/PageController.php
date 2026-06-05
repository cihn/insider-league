<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Render the single-page application shell. All data is loaded
     * afterwards through the JSON API.
     */
    public function index(): View
    {
        return view('app');
    }
}
