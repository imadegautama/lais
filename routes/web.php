<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TransactionController;
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

    Route::get('produk', [ItemController::class, 'index'])->name('items.index');
    Route::get('produk/baru', [ItemController::class, 'create'])->name('items.create');
    Route::post('produk', [ItemController::class, 'store'])->name('items.store');
    Route::get('produk/{item}/ubah', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('produk/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('produk/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::post('produk/{item}/koreksi-stok', [ItemController::class, 'adjustStock'])->name('items.adjust-stock');

    Route::get('transaksi', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transaksi/baru', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('transaksi', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('transaksi/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('transaksi/{transaction}/ubah', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::put('transaksi/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::post('transaksi/{transaction}/batalkan', [TransactionController::class, 'cancel'])->name('transactions.cancel');

    Route::get('log-aktivitas', [ActivityController::class, 'index'])->name('activity.index');
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
