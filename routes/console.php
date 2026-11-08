<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Depreciation of the month just ended (SAK EMKM par. 11.11), once it is over.
Schedule::command('lais:penyusutan')->monthlyOn(1, '00:10')->timezone(config('app.timezone'))->withoutOverlapping();

// The evening summary (PRD M8): 21.00 in the app time zone (APP_TIMEZONE), only to owners who linked WhatsApp and left it on.
Schedule::command('lais:ringkasan')->dailyAt('21:00')->timezone(config('app.timezone'))->withoutOverlapping();
