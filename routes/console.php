<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule sitemap generation daily
Schedule::command('sitemap:generate')->daily();

// Ping availability chain: 3-minute voice callbacks for unanswered pings
// (+ the expiry sweep that tells the pinger the artisan is unavailable).
Schedule::command('voice:dispatch-retries')->everyMinute()->withoutOverlapping();
