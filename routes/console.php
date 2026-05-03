<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('servers:check-hourly')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/server-hourly-check.log'));

Schedule::command('servers:check-hourly')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/server-daily-check.log'));
