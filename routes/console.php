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
| Linux cron must run every minute:
|
| * * * * * cd /home/ec2-user/laravel-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Fast Server Monitoring
|--------------------------------------------------------------------------
| Runs every 15 minutes.
| Checks:
| - Server online/offline
| - SSH
| - Website ports 80/443
| - cPanel/WHM ports 2087/2083
| - Plesk port 8443
| - LiteSpeed/OpenLiteSpeed
| - CPU/RAM/Disk
| - Firewall
| - Email/SMS down and recovery alerts
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-quick-check.log'));

/*
|--------------------------------------------------------------------------
| Hourly Full Monitoring
|--------------------------------------------------------------------------
| Runs every 1 hour.
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->hourly()
    ->withoutOverlapping(50)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-hourly-check.log'));

/*
|--------------------------------------------------------------------------
| Daily Full Monitoring
|--------------------------------------------------------------------------
| Runs every day at 00:05 server time.
|--------------------------------------------------------------------------
*/
Schedule::command('servers:check')
    ->dailyAt('00:05')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/server-daily-check.log'));

/*
|--------------------------------------------------------------------------
| Queue Restart
|--------------------------------------------------------------------------
| Keeps queued jobs healthy if you use email/SMS jobs.
|--------------------------------------------------------------------------
*/
Schedule::command('queue:restart')
    ->dailyAt('03:30')
    ->appendOutputTo(storage_path('logs/queue-restart.log'));

/*
|--------------------------------------------------------------------------
| Laravel Cache Cleanup
|--------------------------------------------------------------------------
| Clears stale cache daily.
|--------------------------------------------------------------------------
*/
Schedule::command('cache:clear')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/cache-clear.log'));

/*
|--------------------------------------------------------------------------
| Old Log Cleanup
|--------------------------------------------------------------------------
| Keeps storage/logs cleaner. Deletes old monitoring logs after 14 days.
|--------------------------------------------------------------------------
*/
Schedule::call(function () {
    $files = [
        storage_path('logs/server-quick-check.log'),
        storage_path('logs/server-hourly-check.log'),
        storage_path('logs/server-daily-check.log'),
        storage_path('logs/queue-restart.log'),
        storage_path('logs/cache-clear.log'),
    ];

    foreach ($files as $file) {
        if (file_exists($file) && filemtime($file) < now()->subDays(14)->timestamp) {
            @unlink($file);
        }
    }
})
    ->dailyAt('04:30')
    ->name('cleanup-old-monitoring-logs')
    ->withoutOverlapping();