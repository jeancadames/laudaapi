<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/** LaudaERP Controller */

use App\Http\Controllers\LaudaErp\LaudaErpDashboardController;
use App\Http\Controllers\LaudaErp\DgiiCertificationController;
use App\Http\Controllers\LaudaErp\DgiiCertificateController;
use App\Http\Controllers\LaudaErp\DgiiCertificateToolsController;
use App\Http\Controllers\LaudaErp\Support\ErpSupportController;
use App\Http\Controllers\LaudaErp\DgiiEndpointsController;
use App\Http\Controllers\LaudaErp\DgiiTokenController;
use App\Http\Controllers\LaudaErp\DgiiTokenAutoController;
use App\Http\Controllers\LaudaErp\DgiiXmlSignController;
use App\Http\Controllers\LaudaErp\Wrapper\AcecfExcelToXmlController;
use App\Http\Controllers\LaudaErp\Wrapper\ExcelToXmlController;
use App\Http\Controllers\LaudaErp\Wrapper\RfceExcelToXmlController;

use App\Http\Controllers\LaudaErp\FiscalDocumentController;
use App\Http\Controllers\LaudaErp\FiscalCalendarController;
use App\Http\Controllers\LaudaErp\FiscalComplianceController;
use App\Http\Controllers\LaudaErp\ApiFacturacionController;
use App\Http\Controllers\LaudaErp\DgiiXmlSendController;

use App\Http\Controllers\Calendar\IcsFeedController;

/*
|--------------------------------------------------------------------------
| ✅ Calendar ICS feed (PÚBLICO por token)
|--------------------------------------------------------------------------
| IMPORTANTE:
| - Esto NO debe estar detrás de auth/erp.access.
| - Lo consumen clientes externos (Google Calendar/Outlook) con token en URL.
| - Mantenemos el path bajo /erp para consistencia, pero name global "calendar.ics"
|   para que route('calendar.ics') funcione en comandos/servicios.
*/

Route::get('/erp/calendar/ics/{company:slug}/{token}.ics', [IcsFeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('calendar.ics');

/*
|--------------------------------------------------------------------------
| ✅ LaudaERP (Dashboard / App)
|--------------------------------------------------------------------------
| IMPORTANTE: NO va bajo /subscriber. Es su app: /erp
*/

Route::middleware(['auth', 'verified', 'role:subscriber', 'erp.access'])
    ->prefix('erp')
    ->name('erp.')
    ->group(function () {

        Route::get('/', LaudaErpDashboardController::class)->name('dashboard');

        /*
|--------------------------------------------------------------------------
| ✅ ERP / Services (ejecución de servicios)
|--------------------------------------------------------------------------
*/
        Route::prefix('services')->name('services.')->group(function () {

            Route::prefix('calendario-fiscal')
                ->middleware('entitled:calendario-fiscal')
                ->name('calendario-fiscal.')
                ->group(function () {
                    Route::get('/', [FiscalCalendarController::class, 'index'])->name('index');
                });

            Route::prefix('cumplimiento-fiscal')
                ->middleware('entitled:cumplimiento-fiscal')
                ->name('cumplimiento-fiscal.')
                ->group(function () {
                    Route::get('/', [FiscalComplianceController::class, 'index'])->name('index');
                });
            /*
    |----------------------------------------------------------------------
    | ✅ API Facturación (interno) — Fiscal Documents (draft + issue)
    |----------------------------------------------------------------------
    | IMPORTANTE:
    | - tu seeder tiene child slug: api-facturacion
    | - y también existe el suite/parent: api-facturacion-electronica
    | => permitimos cualquiera de los 2
    */
            Route::prefix('api-facturacion')->name('api-facturacion.')->group(function () {

                // Opcional: landing del padre
                Route::get('/', [ApiFacturacionController::class, 'index'])
                    ->middleware('entitled:api-facturacion-electronica')
                    ->name('index');

                // ✅ ESTE ES EL QUE TE FALTA (href actual del child: /electronica)
                Route::get('/electronica', [ApiFacturacionController::class, 'electronica'])
                    ->middleware('entitled:api-facturacion') // o el slug que realmente activas para ese item
                    ->name('electronica');

                Route::prefix('fiscal-documents')->name('fiscal-documents.')->group(function () {
                    Route::post('/', [FiscalDocumentController::class, 'store'])->name('store');
                    Route::put('/{publicId}', [FiscalDocumentController::class, 'update'])
                        ->where('publicId', '[A-Za-z0-9]{20,32}')
                        ->name('update');
                    Route::post('/{publicId}/issue', [FiscalDocumentController::class, 'issue'])
                        ->where('publicId', '[A-Za-z0-9]{20,32}')
                        ->name('issue');
                });
            });

            /*
    |----------------------------------------------------------------------
    | ✅ Certificación Emisor Electrónico
    |----------------------------------------------------------------------
    | service slug real: certificacion-emisor-electronico
    | pero si el cliente compró el suite (api-facturacion-electronica),
    | también debe pasar.
    */
            Route::prefix('certificacion-emisor')
                ->middleware('service.entitled:certificacion-emisor-electronico|api-facturacion-electronica')
                ->name('certificacion-emisor.')
                ->group(function () {

                    Route::get('/ws/activity', [DgiiCertificationController::class, 'wsActivity'])
                        ->name('ws.activity');

                    Route::get('/', [DgiiCertificationController::class, 'index'])->name('index');

                    Route::get('/certificados', [DgiiCertificateController::class, 'index'])->name('certificados.index');
                    Route::post('/certificados', [DgiiCertificateController::class, 'store'])->name('certificados.store');
                    Route::post('/certificados/{cert}/default', [DgiiCertificateController::class, 'setDefault'])->name('certificados.default');
                    Route::delete('/certificados/{cert}', [DgiiCertificateController::class, 'destroy'])->name('certificados.destroy');

                    Route::get('/certificados/health', [DgiiCertificateToolsController::class, 'health'])->name('certificados.health');
                    Route::post('/certificados/{cert}/test-sign', [DgiiCertificateToolsController::class, 'testSign'])->name('certificados.test-sign');
                    Route::post('/certificados/{cert}/refresh', [DgiiCertificateToolsController::class, 'refresh'])->name('certificados.refresh');

                    Route::get('/endpoints', [DgiiEndpointsController::class, 'show'])->name('endpoints.show');
                    Route::post('/endpoints', [DgiiEndpointsController::class, 'update'])->name('endpoints.update');

                    Route::post('/token/generate', [DgiiTokenController::class, 'generate'])->name('token.generate');
                    Route::put('/token/auto', [DgiiTokenAutoController::class, 'update'])->name('token.auto');

                    Route::post('/xml/sign', [DgiiXmlSignController::class, 'sign'])->name('xml.sign');

                    Route::prefix('set-ecf')->group(function () {

                        Route::prefix('ecf')->group(function () {
                            Route::post('/excel-to-xml', [ExcelToXmlController::class, 'convert'])->name('excel-to-xml');
                            Route::get('/excel-to-xml/download', [ExcelToXmlController::class, 'download'])->name('excel-to-xml.download');
                        });

                        Route::prefix('acecf')->group(function () {
                            Route::post('/excel-to-xml', [AcecfExcelToXmlController::class, 'convert'])->name('acecf.excel-to-xml');
                            Route::get('/download', [AcecfExcelToXmlController::class, 'download'])->name('acecf.download');
                        });

                        Route::prefix('rfce')->group(function () {
                            Route::post('/excel-to-xml', [RfceExcelToXmlController::class, 'convert'])->name('rfce.excel-to-xml');
                            Route::get('/download', [RfceExcelToXmlController::class, 'download'])->name('rfce.download');
                        });
                    });

                    Route::post('/xml/send', [DgiiXmlSendController::class, 'send']);
                });
        });

        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [ErpSupportController::class, 'index'])->name('index');
            Route::post('/tickets', [ErpSupportController::class, 'storeTicket'])->name('tickets.store');
            Route::get('/tickets/{ticket}', [ErpSupportController::class, 'showTicket'])->name('tickets.show');
            Route::post('/tickets/{ticket}/messages', [ErpSupportController::class, 'storeMessage'])->name('tickets.messages.store');
            Route::post('/faq/{faqItem}/vote', [ErpSupportController::class, 'voteFaq'])->name('faq.vote');
        });
    });
