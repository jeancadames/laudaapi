<?php

use App\Http\Controllers\BusinessIdentityController;
use App\Http\Middleware\BusinessIdentityResponse;
use Illuminate\Support\Facades\Route;

Route::get('/business/identity', [BusinessIdentityController::class, 'index'])->middleware(BusinessIdentityResponse::class)->name('business.identity.index');

Route::middleware([BusinessIdentityResponse::class, 'auth', 'verified', 'throttle:20,1'])
    ->prefix('business/identity')->name('business.identity.')->group(function (): void {
        Route::post('/companies/{company}/{service}/requests', [BusinessIdentityController::class, 'start'])->name('start');
        Route::post('/{service}/preview', [BusinessIdentityController::class, 'preview'])->name('preview');
        Route::post('/{service}/ready', [BusinessIdentityController::class, 'ready'])->name('ready');
        Route::delete('/requests/{nonce}', [BusinessIdentityController::class, 'cancel'])->where('nonce', '[a-f0-9]{32}')->name('cancel');
        Route::post('/{service}/confirm', [BusinessIdentityController::class, 'complete'])->name('confirm');
        Route::get('/companies/{company}/services/{service:slug}/launch', [BusinessIdentityController::class, 'launch'])->withoutScopedBindings()->name('launch');
        Route::delete('/companies/{company}/{service}', [BusinessIdentityController::class, 'revoke'])->name('revoke');
    });
