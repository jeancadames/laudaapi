<?php

use App\Http\Controllers\BusinessCommercial\CapacityController;
use App\Http\Controllers\BusinessCommercial\CommercialController;
use Illuminate\Support\Facades\Route;

Route::post('/api/business/commercial/v1/{service}', CommercialController::class)
    ->whereIn('service', ['ecf', 'pos', 'bys', 'cumplimiento'])
    ->middleware('throttle:120,1')->name('business.commercial.exchange');

Route::post('/api/business/capacity/v1/{service}', CapacityController::class)
    ->whereIn('service', ['ecf', 'pos', 'bys', 'cumplimiento'])
    ->middleware('throttle:120,1')->name('business.capacity.exchange');
