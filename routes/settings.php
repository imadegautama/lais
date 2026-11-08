<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WhatsAppController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/bot', [WhatsAppController::class, 'edit'])->name('whatsapp.edit');
    Route::post('settings/bot/kode', [WhatsAppController::class, 'code'])->middleware('throttle:6,1')->name('whatsapp.code');
    Route::delete('settings/bot', [WhatsAppController::class, 'unlink'])->name('whatsapp.unlink');
    Route::patch('settings/bot/ringkasan', [WhatsAppController::class, 'summary'])->name('whatsapp.summary');

});
