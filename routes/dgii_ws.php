<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveCompanyFromSubdomain;
use App\Http\Controllers\DgiiWs\SemillaController;
use App\Http\Controllers\DgiiWs\ValidacionCertificadoController;
use App\Http\Controllers\DgiiWs\RecepcionEcfController;
use App\Http\Controllers\DgiiWs\AprobacionComercialEcfController;

$base = config('app.base_domain');

Route::domain("{tenant}.{$base}")
    ->where([
        // igual que el middleware: solo subdominios válidos
        'tenant' => '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?',
    ])
    ->middleware([
        ResolveCompanyFromSubdomain::class,
        // 'throttle:120,1',
    ])
    ->group(function () {
        Route::get('/fe/autenticacion/api/semilla', SemillaController::class);
        Route::post('/fe/autenticacion/api/validacioncertificado', ValidacionCertificadoController::class);

        Route::post('/fe/recepcion/api/ecf', RecepcionEcfController::class);
        Route::post('/fe/aprobacioncomercial/api/ecf', AprobacionComercialEcfController::class);
    });
