<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stock:check-alerts')->dailyAt('09:00');
Schedule::command('inventory:expire')->dailyAt('00:00'); // تصفير الكميات للأدوية المنتهية الصلاحية يومياً
