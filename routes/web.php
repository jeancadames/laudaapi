<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\Marketing\ServiceCatalogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public / Marketing
|--------------------------------------------------------------------------
| Landing + formularios públicos (sin auth).
*/

// Landing principal LaudaAPI
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Catálogo anterior de servicios, conservado en una ruta aparte
Route::get('/servicios', ServiceCatalogController::class)->name('services.index');

/** Contact request route público */
Route::post('/contact', [ContactRequestController::class, 'store'])->name('contact.store');

/** Activation 30 days free request route público */
Route::post('/activation', [ActivationRequestController::class, 'store'])->name('activation.store');

/*
|--------------------------------------------------------------------------
| Legal pages público
|--------------------------------------------------------------------------
*/
Route::get('/legal', fn() => Inertia::render('Legal/Index'))->name('legal.index');
Route::get('/legal/terminos', fn() => Inertia::render('Legal/Terms'))->name('legal.terms');
Route::get('/legal/privacidad', fn() => Inertia::render('Legal/Privacy'))->name('legal.privacy');

/*
|--------------------------------------------------------------------------
| Activation link signed
|--------------------------------------------------------------------------
| Link firmado para aceptar activación público, protegido por signed.
*/
Route::get('/activations/{activation}/accept', [ActivationController::class, 'accept'])
    ->name('activations.accept')
    ->middleware('signed');

/*
|--------------------------------------------------------------------------
| Authenticated sin rol
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/activation-requests/{activation}', [ActivationRequestController::class, 'show'])
        ->name('activation.show');
});

require __DIR__ . '/settings.php';
