<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional rental module; the command does nothing when RENTALS_ENABLED=false.
Schedule::command('rentals:run')->dailyAt('00:15')->withoutOverlapping();
