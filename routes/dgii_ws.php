<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveCompanyFromSubdomain;
use App\Http\Controllers\DgiiWs\SemillaController;
use App\Http\Controllers\DgiiWs\ValidacionCertificadoController;
use App\Http\Controllers\DgiiWs\RecepcionEcfController;
use App\Http\Controllers\DgiiWs\AprobacionComercialEcfController;

$base = config('app.base_domain', 'laudaapi.com');

Route::domain("{tenant}.{$base}")
    ->where([
        'tenant' => '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?',
    ])
    ->middleware([
        ResolveCompanyFromSubdomain::class,
        // 'throttle:120,1',
    ])
    ->name('dgii_ws.')
    ->group(function () {
        Route::get('/fe/autenticacion/api/semilla', SemillaController::class)
            ->name('semilla');

        Route::post('/fe/autenticacion/api/validacioncertificado', ValidacionCertificadoController::class)
            ->name('validacioncertificado');

        Route::post('/fe/recepcion/api/ecf', RecepcionEcfController::class)
            ->name('recepcion.ecf');

        Route::post('/fe/aprobacioncomercial/api/ecf', AprobacionComercialEcfController::class)
            ->name('aprobacion.ecf');
    });
