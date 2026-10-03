<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Payments: watch open payments (replaces legacy payment cron).
Schedule::command('payments:poll')->everyMinute()->withoutOverlapping();
