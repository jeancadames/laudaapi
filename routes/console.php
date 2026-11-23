<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('dgii:tokens:refresh')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::call(fn () => \App\Services\Billing\BusinessPackageChanges::finishDue())
    ->name('business:finalizar-paquetes')
    ->everyMinute();
