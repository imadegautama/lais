<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The evening summary (PRD M8): 21.00 WIB, only to owners who linked WhatsApp and left it on.
Schedule::command('lais:ringkasan')->dailyAt('21:00')->timezone('Asia/Jakarta')->withoutOverlapping();
