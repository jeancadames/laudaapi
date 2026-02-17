<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Marketing\ServiceCatalogController;

/** Contact, ActivactionRequest, ActivationControllers */

use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\ActivationController;

/** Admin Dashboard Controllers */

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminContactRequestController;
use App\Http\Controllers\Admin\AdminActivationRequestController;
use App\Http\Controllers\Admin\AdminActivationController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminSubscriptionsController;
use App\Http\Controllers\Admin\AdminSubscribersController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminErrorLogController;

/** Subscribers Controllers */

use App\Http\Controllers\Subscriber\SubscriberDashboardController;
use App\Http\Controllers\Subscriber\SubscriberServiceCatalogController;
use App\Http\Controllers\Subscriber\SubscriberServiceRequestController;
use App\Http\Controllers\Subscriber\SubscriberActivationController;
use App\Http\Controllers\Subscriber\SubscriberCompanyController;
use App\Http\Controllers\Subscriber\SubscriberMyServicesController;
use App\Http\Controllers\Subscriber\SubscriberSubscriptionController;
use App\Http\Controllers\Subscriber\SubscriberServiceActivationController;
use App\Http\Controllers\Subscriber\SubscriberServiceCancellationController;
use App\Http\Controllers\Subscriber\SubscriberInvoiceController;
use App\Http\Controllers\Subscriber\SubscriberPaymentController;
use App\Http\Controllers\Subscriber\SubscriberPaymentMethodController;
use App\Http\Controllers\Subscriber\SubscriberUsageController;
use App\Http\Controllers\Subscriber\SubscriberSupportController;

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

/*
|--------------------------------------------------------------------------
| Public / Marketing
|--------------------------------------------------------------------------
| Landing + formularios públicos (sin auth).
*/

Route::get('/', ServiceCatalogController::class)->name('home');

/** Contact request route (público) */
Route::post('/contact', [ContactRequestController::class, 'store'])->name('contact.store');

/** Activation 30 days free request route (público) */
Route::post('/activation', [ActivationRequestController::class, 'store'])->name('activation.store');

/*
|--------------------------------------------------------------------------
| Legal pages (público)
|--------------------------------------------------------------------------
*/
Route::get('/legal', fn() => Inertia::render('Legal/Index'))->name('legal.index');
Route::get('/legal/terminos', fn() => Inertia::render('Legal/Terms'))->name('legal.terms');
Route::get('/legal/privacidad', fn() => Inertia::render('Legal/Privacy'))->name('legal.privacy');

/*
|--------------------------------------------------------------------------
| Activation link (signed)
|--------------------------------------------------------------------------
| Link firmado para aceptar activación (público, protegido por signed).
*/
Route::get('/activations/{activation}/accept', [ActivationController::class, 'accept'])
    ->name('activations.accept')
    ->middleware('signed');

/*
|--------------------------------------------------------------------------
| Authenticated (sin rol)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Ver una activation request (si lo usas en frontend)
    Route::get('/activation-requests/{activation}', [ActivationRequestController::class, 'show'])
        ->name('activation.show');
});

/*
|--------------------------------------------------------------------------
| Admin (dashboard global)
|--------------------------------------------------------------------------
| Importante: name('dashboard') debe existir para Wayfinder => export `dashboard()`
*/
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Subscriber (dashboard)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:subscriber', 'activation.accepted'])->group(function () {
    Route::get('/subscriber', SubscriberDashboardController::class)->name('subscriber');
});

/*
|--------------------------------------------------------------------------
| Subscriber Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:subscriber', 'activation.accepted'])
    ->prefix('subscriber')
    ->name('subscriber.')
    ->group(function () {

        // ✅ Activation
        Route::get('/activation', [SubscriberActivationController::class, 'show'])
            ->name('activation.show');

        Route::post('/activation/activate', [SubscriberActivationController::class, 'activate'])
            ->name('activation.activate');

        // ✅ Services
        Route::prefix('services')->name('services.')->group(function () {

            // ✅ Mis servicios (IMPORTANTE: antes de {categorySlug})
            Route::get('/my', [SubscriberMyServicesController::class, 'show'])
                ->name('my');

            // ✅ Solicitar/quitar (toggle) desde el catálogo
            Route::post('/request', [SubscriberServiceRequestController::class, 'toggle'])
                ->middleware('subscription.active')
                ->name('request.toggle');

            // ✅ Activar servicio solicitado (pasa a subscription_items)
            Route::post('/activate', [SubscriberServiceActivationController::class, 'activate'])
                ->middleware('subscription.active')
                ->name('activate');

            // ✅ Cancelar subscription_item
            Route::post('/cancel', [SubscriberServiceCancellationController::class, 'cancel'])
                ->middleware('subscription.active')
                ->name('cancel');

            // ✅ Catálogo por categoría
            Route::get('/{categorySlug}', [SubscriberServiceCatalogController::class, 'category'])
                ->whereIn('categorySlug', ['api-facturacion-electronica', 'marketplace', 'laudaone'])
                ->name('category');
        });

        // ✅ Support
        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [SubscriberSupportController::class, 'index'])->name('index');

            // ✅ Tickets
            Route::post('/tickets', [SubscriberSupportController::class, 'storeTicket'])->name('tickets.store');
            Route::get('/tickets/{ticket}', [SubscriberSupportController::class, 'showTicket'])->name('tickets.show');
            Route::post('/tickets/{ticket}/messages', [SubscriberSupportController::class, 'storeMessage'])->name('tickets.messages.store');

            // ✅ FAQ helpful votes (opcional)
            Route::post('/faq/{faqItem}/vote', [SubscriberSupportController::class, 'voteFaq'])->name('faq.vote');
        });

        /*
        |--------------------------------------------------------------------------
        | ✅ Empresa (Company + CompanyTaxProfile unificado - 1:1)
        |--------------------------------------------------------------------------
        */
        Route::get('/company', [SubscriberCompanyController::class, 'show'])
            ->name('company.show');

        Route::post('/company', [SubscriberCompanyController::class, 'upsert'])
            ->name('company.upsert');

        // ✅ Usage
        Route::get('/usage', [SubscriberUsageController::class, 'index'])
            ->name('usage.index');

        /*
        |--------------------------------------------------------------------------
        | ✅ Métodos de pago (Company -> hasMany PaymentMethod)
        |--------------------------------------------------------------------------
        */
        Route::prefix('payment-methods')->name('payment_methods.')->group(function () {
            Route::get('/', [SubscriberPaymentMethodController::class, 'index'])->name('index');
            Route::post('/', [SubscriberPaymentMethodController::class, 'store'])->name('store');
            Route::patch('/{paymentMethod}', [SubscriberPaymentMethodController::class, 'update'])->name('update');
            Route::delete('/{paymentMethod}', [SubscriberPaymentMethodController::class, 'destroy'])->name('destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | ✅ /tax-profile reservado para “Métodos de pago” (NO existe todavía)
        |--------------------------------------------------------------------------
        | Cuando lo implementes, aquí irían esas rutas.
        | Por ahora no definimos nada para evitar comportamiento legacy.
        */

        // ✅ Mi suscripción
        Route::get('/subscription', [SubscriberSubscriptionController::class, 'show'])
            ->name('subscription.show');

        // ✅ Invoices
        Route::get('/invoices', [SubscriberInvoiceController::class, 'index'])
            ->name('invoices.index');

        Route::get('/invoices/{invoice}', [SubscriberInvoiceController::class, 'show'])
            ->name('invoices.show');

        // ✅ Payments
        Route::get('/payments', [SubscriberPaymentController::class, 'index'])
            ->name('payments.index');

        Route::get('/payments/{payment}', [SubscriberPaymentController::class, 'show'])
            ->name('payments.show');
    });

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

            Route::prefix('certificacion-emisor')->name('certificacion-emisor.')->group(function () {

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
                        
                        Route::post('/excel-to-xml', [ExcelToXmlController::class, 'convert'])
                        ->name('excel-to-xml');
                        
                        Route::get('/excel-to-xml/download', [ExcelToXmlController::class, 'download'])
                        ->name('excel-to-xml.download');
                    });

                    Route::prefix('acecf')->group(function () {

                        Route::post('/excel-to-xml', [AcecfExcelToXmlController::class, 'convert'])
                            ->name('acecf.excel-to-xml');

                        Route::get('/download', [AcecfExcelToXmlController::class, 'download'])
                            ->name('acecf.download');
                    });
                    
                    Route::prefix('rfce')->group(function () {

                        Route::post('/excel-to-xml', [RfceExcelToXmlController::class, 'convert'])
                            ->name('rfce.excel-to-xml');

                        Route::get('/download', [RfceExcelToXmlController::class, 'download'])
                            ->name('rfce.download');
                    });
                });
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
/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Activations (admin view)
        |--------------------------------------------------------------------------
        */
        Route::get('/activations/{activation}', [ActivationController::class, 'show'])
            ->name('activations.show');

        /*
        |--------------------------------------------------------------------------
        | Contacts
        |--------------------------------------------------------------------------
        */
        Route::get('/contacts', [AdminContactRequestController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/{contact}', [AdminContactRequestController::class, 'show'])->name('contacts.show');
        Route::post('/contacts/{contact}/read', [AdminContactRequestController::class, 'markAsRead'])->name('contacts.read');
        Route::post('/contacts/read-all', [AdminContactRequestController::class, 'markAllAsRead'])->name('contacts.readAll');

        /*
        |--------------------------------------------------------------------------
        | Activation Requests
        |--------------------------------------------------------------------------
        */
        Route::get('/requests', [AdminActivationRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{activationRequest}', [AdminActivationRequestController::class, 'show'])->name('requests.show');

        Route::post('/activations/{activation}/discard', [AdminActivationController::class, 'discard'])->name('activations.discard');
        Route::post('/activations/{activation}/remind', [AdminActivationController::class, 'remind'])->name('activations.remind');

        /*
        |--------------------------------------------------------------------------
        | Subscriptions & Subscribers
        |--------------------------------------------------------------------------
        */
        Route::get('/subscriptions', [AdminSubscriptionsController::class, 'index'])->name('subscriptions.index');

        Route::get('/subscribers', [AdminSubscribersController::class, 'index'])->name('subscribers.index');
        Route::patch('/subscribers/toggle/{subscriber}', [AdminSubscribersController::class, 'toggleActive'])->name('subscribers.toggle');
        Route::patch('/subscribers/{subscriber}', [AdminSubscribersController::class, 'update'])->name('subscribers.update');

        /*
        |--------------------------------------------------------------------------
        | Services catalog (admin)
        |--------------------------------------------------------------------------
        */
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('{parent:slug}', [AdminServiceController::class, 'index'])->name('index');
            Route::patch('toggle/{service}', [AdminServiceController::class, 'toggleActive'])->name('toggle');
            Route::patch('{service}', [AdminServiceController::class, 'update'])->name('update');
            Route::post('{parent:slug}', [AdminServiceController::class, 'storeChild'])->name('storeChild');
        });

        /*
        |--------------------------------------------------------------------------
        | Company / Invoices / Payments
        |--------------------------------------------------------------------------
        */
        Route::get('/company', [AdminCompanyController::class, 'index'])->name('company.index');
        Route::get('/company/{company}/tax-profile', [AdminCompanyController::class, 'taxProfile'])->name('company.tax_profile.show');
        Route::get('/company/{company}/transactions', [AdminCompanyController::class, 'transactions'])->name('company.transactions.index');

        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');

        /*
        |--------------------------------------------------------------------------
        | Audit / Error logs
        |--------------------------------------------------------------------------
        */
        Route::get('/auditlog', [AdminAuditLogController::class, 'index'])->name('auditlog.index');
        Route::get('/auditlog/{auditLog}', [AdminAuditLogController::class, 'show'])->name('auditlog.show');

        Route::get('/errorlog', [AdminErrorLogController::class, 'index'])->name('errorlog.index');
        Route::get('/errorlog/{errorLog}', [AdminErrorLogController::class, 'show'])->name('errorlog.show');
    });

require __DIR__ . '/settings.php';
