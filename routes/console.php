<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Webscepts Enterprise Monitoring Scheduler
|--------------------------------------------------------------------------
| Your Linux cron should run this every minute:
| * * * * * cd /home/ec2-user/laravel-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Fast server monitoring
|--------------------------------------------------------------------------
| Runs every 15 minutes.
| Use this for faster email/SMS alerts when a server, website, cPanel,
| Plesk, LiteSpeed, port, disk, CPU, RAM or SSH issue is detected by
| your servers:check command.
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-quick-check.log'));

/*
|--------------------------------------------------------------------------
| Hourly full monitoring
|--------------------------------------------------------------------------
| Runs every 1 hour.
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-hourly-check.log'));

/*
|--------------------------------------------------------------------------
| Daily full monitoring report/check
|--------------------------------------------------------------------------
| Runs every day at 00:05 server time.
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-daily-check.log'));