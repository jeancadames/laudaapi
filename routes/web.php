<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\Marketing\ServiceCatalogController;
use App\Http\Controllers\Diagnosis\DigitalDiagnosisController;
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

Route::middleware(['auth', 'verified'])
    ->prefix('diagnostico')
    ->name('diagnosis.')
    ->group(function (): void {
        Route::get('/{assessment}', [DigitalDiagnosisController::class, 'show'])
            ->name('show');

        Route::patch('/{assessment}', [DigitalDiagnosisController::class, 'update'])
            ->name('update');

        Route::post('/{assessment}/submit', [DigitalDiagnosisController::class, 'submit'])
            ->name('submit');
    });


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

// BEGIN LAUDA360 DIAGNOSIS ROUTES
\Illuminate\Support\Facades\Route::middleware(['auth'])
    ->prefix('diagnostico')
    ->name('diagnosis.')
    ->group(function (): void {
        \Illuminate\Support\Facades\Route::get('/{assessment}', [\App\Http\Controllers\Diagnosis\DigitalDiagnosisController::class, 'show'])
            ->name('show');

        \Illuminate\Support\Facades\Route::patch('/{assessment}', [\App\Http\Controllers\Diagnosis\DigitalDiagnosisController::class, 'update'])
            ->name('update');

        \Illuminate\Support\Facades\Route::post('/{assessment}/submit', [\App\Http\Controllers\Diagnosis\DigitalDiagnosisController::class, 'submit'])
            ->name('submit');

        \Illuminate\Support\Facades\Route::post(
            '/{assessment}/informe-ampliado/solicitar',
            [\App\Http\Controllers\Diagnosis\DiagnosisExpandedReportCommercialController::class, 'requestPurchase']
        )->name('expanded_report.request');

        \Illuminate\Support\Facades\Route::get(
            '/{assessment}/informe-ampliado',
            [\App\Http\Controllers\Diagnosis\DiagnosisExpandedReportController::class, 'show']
        )->name('expanded_report.show');


        \Illuminate\Support\Facades\Route::get(
            '/{assessment}/roadmap-detallado',
            [\App\Http\Controllers\Diagnosis\DiagnosisDetailedRoadmapController::class, 'show']
        )->name('detailed_roadmap.show');


        \Illuminate\Support\Facades\Route::post(
            '/{assessment}/roadmap-detallado/solicitar',
            [\App\Http\Controllers\Diagnosis\DiagnosisDetailedRoadmapCommercialController::class, 'requestPurchase']
        )->name('detailed_roadmap.request');

    });
// END LAUDA360 DIAGNOSIS ROUTES

// BEGIN LAUDA360 DIAGNOSIS ACCESS ROUTES
\Illuminate\Support\Facades\Route::get(
    '/diagnostico-invitacion/{access}',
    [\App\Http\Controllers\Diagnosis\DiagnosisInvitationController::class, 'accept']
)
    ->name('diagnosis.invitation.accept');

\Illuminate\Support\Facades\Route::middleware(['auth', 'verified'])
    ->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '/diagnostico-acceso/{access}/password',
            [\App\Http\Controllers\Diagnosis\DiagnosisInvitationController::class, 'password']
        )->name('diagnosis.access.password.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnostico-acceso/{access}/password',
            [\App\Http\Controllers\Diagnosis\DiagnosisInvitationController::class, 'storePassword']
        )->name('diagnosis.access.password.store');

        \Illuminate\Support\Facades\Route::get(
            '/mi-diagnostico',
            [\App\Http\Controllers\Diagnosis\DiagnosisInvitationController::class, 'resume']
        )->name('diagnosis.resume');
    });
// END LAUDA360 DIAGNOSIS ACCESS ROUTES
