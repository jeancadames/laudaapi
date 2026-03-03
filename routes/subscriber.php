<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Marketing\ServiceCatalogController;

/** Contact, ActivactionRequest, ActivationControllers */

use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\ActivationController;

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

        Route::post('/company/obligations', [SubscriberCompanyController::class, 'upsertObligations'])
            ->name('company.obligations.upsert');

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
