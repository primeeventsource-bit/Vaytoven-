<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Buyer offers expire at an exact clock time 24 hours after submission, so
// this runs every minute rather than hourly — an offer submitted at 19:30
// must read EXPIRED at 19:30 the next day, not at the top of the next hour.
// withoutOverlapping guards against a long sweep colliding with the next tick.
Schedule::command('offers:expire')->everyMinute()->withoutOverlapping();
