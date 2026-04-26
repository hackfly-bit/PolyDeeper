<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('polymarket:sync-markets --queue')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('polymarket:sync-orders --queue')->everyMinute()->withoutOverlapping();
Schedule::command('polymarket:rotate-credentials --days=30')->daily()->withoutOverlapping();
