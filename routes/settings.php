<?php

use App\Http\Controllers\Settings\BusinessController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PushSubscriptionController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Named, so the sidebar's Pengaturan stays active on every tab.
    Route::redirect('settings', '/settings/business')->name('settings');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/business', [BusinessController::class, 'edit'])->name('business.edit');
    Route::patch('settings/business', [BusinessController::class, 'update'])->name('business.update');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/bot', [WhatsAppController::class, 'edit'])->name('whatsapp.edit');
    Route::post('settings/bot/kode', [WhatsAppController::class, 'code'])->middleware('throttle:6,1')->name('whatsapp.code');
    Route::delete('settings/bot', [WhatsAppController::class, 'unlink'])->name('whatsapp.unlink');
    Route::patch('settings/bot/ringkasan', [WhatsAppController::class, 'summary'])->name('whatsapp.summary');
    Route::post('settings/notifikasi', [PushSubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('push.store');
    Route::delete('settings/notifikasi', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
    Route::post('settings/bot/ringkasan/kirim', [WhatsAppController::class, 'sendSummary'])->middleware('throttle:6,1')->name('whatsapp.summary.send');

});
