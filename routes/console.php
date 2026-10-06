<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Audit Logs keep one year (config/activitylog.php → delete_records_older_than_days).
// Runs only when the server's cron calls `php artisan schedule:run` every minute.
Schedule::command('activitylog:clean --force')
    ->dailyAt('03:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onOneServer();
