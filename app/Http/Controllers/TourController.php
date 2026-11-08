<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /**
     * The owner finished or skipped the dashboard tour, so it stops opening by itself.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->tour_completed_at ??= now();
        $user->save();

        return back();
    }
}
