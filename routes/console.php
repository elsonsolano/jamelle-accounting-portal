<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('paymaya:sync')->cron('0 15 * * 1-5');       // Mon–Fri 15:00 UTC = 11 PM PHT
Schedule::command('messenger:send-reminder')->cron('0 2 * * *'); // Daily 02:00 UTC = 10 AM PHT
