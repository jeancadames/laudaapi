<?php

use App\Http\Controllers\DgiiWs\AprobacionComercialEcfController;
use App\Http\Controllers\DgiiWs\RecepcionEcfController;
use App\Http\Controllers\DgiiWs\SemillaController;
use App\Http\Controllers\DgiiWs\ValidacionCertificadoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveCompanyFromSubdomain;


$base = config('app.base_domain', 'laudaapi.com');

Route::domain("{tenant}.{$base}")
    ->where([
        'tenant' => '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?',
    ])
    ->middleware([
        ResolveCompanyFromSubdomain::class,
    ])
    ->name('dgii_ws.')
    ->any('/{path}', function (Request $request, string $tenant, string $path = '') {
        $normalized = strtolower(trim($path, '/'));

        return match ($normalized) {
            'fe/autenticacion/api/semilla' => $request->isMethod('get')
                ? app(SemillaController::class)($request)
                : abort(405),

            'fe/autenticacion/api/validacioncertificado' => $request->isMethod('post')
                ? app(ValidacionCertificadoController::class)($request)
                : abort(405),

            'fe/recepcion/api/ecf' => $request->isMethod('post')
                ? app(RecepcionEcfController::class)($request)
                : abort(405),

            'fe/aprobacioncomercial/api/ecf' => $request->isMethod('post')
                ? app(AprobacionComercialEcfController::class)($request)
                : abort(405),

            default => abort(404),
        };
    })
    ->where('path', '.*');