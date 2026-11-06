<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\GowaWebhookController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MoneyAccountController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TransactionController;
use App\Support\DemoData;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

// The shop and the customer's nota (UI-PAGES A2–A4): public, no account.
Route::get('t/{slug}', [ShopController::class, 'show'])->name('shop.show');
Route::post('t/{slug}/pesan', [ShopController::class, 'store'])->name('shop.store');
Route::get('p/{token}', [ShopController::class, 'order'])->name('shop.order');

// GOWA's webhook: signed with HMAC; bootstrap/app.php leaves it out of the CSRF check.
Route::post('webhooks/gowa', GowaWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.gowa');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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
    Route::get('transaksi/{transaction}/bukti', [TransactionController::class, 'proof'])->name('transactions.proof');

    Route::get('pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::post('pesanan', [OrderController::class, 'store'])->name('orders.store');
    Route::patch('pesanan/toko', [OrderController::class, 'updateShop'])->name('orders.shop');
    Route::prefix('pesanan/{code}')->where(['code' => 'A[0-9]+'])->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'show'])->name('show');
        Route::post('terima', [OrderController::class, 'accept'])->name('accept');
        Route::post('tolak', [OrderController::class, 'reject'])->name('reject');
        Route::post('siap', [OrderController::class, 'ready'])->name('ready');
        Route::post('selesai', [OrderController::class, 'complete'])->name('complete');
        Route::post('batal', [OrderController::class, 'cancel'])->name('cancel');
        Route::post('bayar', [OrderController::class, 'pay'])->name('pay');
    });

    Route::get('utang-piutang', [DebtController::class, 'index'])->name('debts.index');
    Route::get('utang-piutang/{contact}', [DebtController::class, 'show'])->name('debts.show');
    Route::post('utang-piutang/{contact}/bayar', [DebtController::class, 'pay'])->name('debts.pay');

    Route::get('log-aktivitas', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/laba-rugi', [ReportController::class, 'profitAndLoss'])->name('reports.profit-loss');
    Route::get('laporan/perubahan-modal', [ReportController::class, 'equity'])->name('reports.equity');
    Route::get('laporan/neraca', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');

    // Every turn is a paid model call, so a runaway client is cut off quickly.
    Route::post('asisten', [AssistantController::class, 'store'])->middleware('throttle:20,1')->name('assistant.store');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::patch('onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('rekening', [MoneyAccountController::class, 'index'])->name('money-accounts.index');
    Route::post('rekening', [MoneyAccountController::class, 'store'])->name('money-accounts.store');
    Route::put('rekening/{moneyAccount}', [MoneyAccountController::class, 'update'])->name('money-accounts.update');
    Route::delete('rekening/{moneyAccount}', [MoneyAccountController::class, 'destroy'])->name('money-accounts.destroy');
});

if (app()->isLocal()) {
    // Living style guide: the visual reference for every page (docs/DESIGN.md).
    Route::get('design-system', function () {
        $demo = new DemoData;

        return Inertia::render('design-system', [
            'transactions' => $demo->recentTransactions(40),
            'salesChart' => $demo->salesChart(),
        ]);
    })->name('design-system');
}

require __DIR__.'/settings.php';
