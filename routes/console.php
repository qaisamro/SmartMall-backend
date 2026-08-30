<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --sleep=3 --tries=3 --stop-when-empty')->everyMinute()->withoutOverlapping();

Schedule::command('products:backup-to-google-drive')
    ->dailyAt(config('services.google_drive.backup_time', '03:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup-google-drive.log'));
