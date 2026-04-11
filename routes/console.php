<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('paymaya:sync')->cron('0 23 * * 1-5');        // Mon–Fri 23:00 PHT = 15:00 UTC
Schedule::command('messenger:send-reminder')->cron('0 10 * * *'); // Daily 10:00 PHT = 02:00 UTC
