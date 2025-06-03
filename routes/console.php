<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled commands
Schedule::command('app:sync-availability')->daily();
// Or for more frequent testing:
// Schedule::command('app:sync-availability')->everyFiveMinutes();
// Schedule::command('app:sync-availability')->hourly();
