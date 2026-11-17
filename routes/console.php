<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



Schedule::command('ksvr:send-appointment-pushes')->dailyAt('06:30');
Schedule::command('ksvr:send-appointment-pushes')->dailyAt('17:30');