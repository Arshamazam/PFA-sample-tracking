<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flag abandoned draft sampling events (never finalized within 24h) once a day.
Schedule::command('sampling:prune-drafts')->dailyAt('01:00');

// Flag settled reference parts as eligible for destruction (never auto-destroys).
Schedule::command('retention:process')->dailyAt('01:30');
