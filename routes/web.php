<?php

use App\Support\DemoData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // TODO(M1): replace DemoData with the real ledger queries.
    Route::get('dashboard', function (Request $request) {
        return Inertia::render('dashboard', (new DemoData)->dashboard(
            (string) $request->query('periode', 'hari_ini'),
            $request->query('keadaan') === 'kosong',
        ));
    })->name('dashboard');
});

if (app()->isLocal()) {
    // Living style guide: the visual reference for every page (docs/DESIGN.md).
    Route::get('design-system', function () {
        $demo = new DemoData;

        return Inertia::render('design-system', [
            'transactions' => $demo->recentTransactions(40),
            'salesChart' => $demo->dashboard('bulan_ini')['salesChart'],
        ]);
    })->name('design-system');
}

require __DIR__.'/settings.php';
