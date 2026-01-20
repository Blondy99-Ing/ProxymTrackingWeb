<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GpsWatchOfflineCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


// ✅ Toutes les minutes : surveille OFFLINE/ONLINE + écrit gps_device_status + gps_offline_historique + alerts(offline)
// Toutes les minutes
Schedule::command(GpsWatchOfflineCommand::class, [
    '--limit' => 500,
    '--chunk' => 100,
    '--sleep' => 0,
    // '--threshold' => 25, // décommente si tu veux forcer
])
->everyMinute()
->withoutOverlapping(55);