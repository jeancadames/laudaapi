<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

/** Contact, ActivationControllers */

use App\Http\Controllers\ActivationController;

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
| Admin Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | Activations (admin view)
        |----------------------------------------------------------------------
        */
        Route::get('/activations/{activation}', [ActivationController::class, 'show'])
            ->name('activations.show');

        /*
        |----------------------------------------------------------------------
        | Contacts
        |----------------------------------------------------------------------
        */
        Route::get('/contacts', [AdminContactRequestController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/{contact}', [AdminContactRequestController::class, 'show'])->name('contacts.show');
        Route::post('/contacts/{contact}/read', [AdminContactRequestController::class, 'markAsRead'])->name('contacts.read');
        Route::post('/contacts/read-all', [AdminContactRequestController::class, 'markAllAsRead'])->name('contacts.readAll');

        /*
        |----------------------------------------------------------------------
        | Activation Requests
        |----------------------------------------------------------------------
        */
        Route::get('/requests', [AdminActivationRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{activationRequest}', [AdminActivationRequestController::class, 'show'])->name('requests.show');

        Route::post('/activations/{activation}/discard', [AdminActivationController::class, 'discard'])->name('activations.discard');
        Route::post('/activations/{activation}/remind', [AdminActivationController::class, 'remind'])->name('activations.remind');

        /*
        |----------------------------------------------------------------------
        | Subscriptions & Subscribers
        |----------------------------------------------------------------------
        */
        Route::get('/subscriptions', [AdminSubscriptionsController::class, 'index'])->name('subscriptions.index');

        Route::get('/subscribers', [AdminSubscribersController::class, 'index'])->name('subscribers.index');
        Route::patch('/subscribers/toggle/{subscriber}', [AdminSubscribersController::class, 'toggleActive'])->name('subscribers.toggle');
        Route::patch('/subscribers/{subscriber}', [AdminSubscribersController::class, 'update'])->name('subscribers.update');

        /*
        |----------------------------------------------------------------------
        | Services catalog (admin)
        |----------------------------------------------------------------------
        */
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/{parent:slug}', [AdminServiceController::class, 'index'])->name('index');
            Route::patch('/toggle/{service}', [AdminServiceController::class, 'toggleActive'])->name('toggle');
            Route::patch('/{service}', [AdminServiceController::class, 'update'])->name('update');
            Route::post('/{parent:slug}', [AdminServiceController::class, 'storeChild'])->name('storeChild');
        });

        /*
        |----------------------------------------------------------------------
        | Company / Invoices / Payments
        |----------------------------------------------------------------------
        */
        Route::get('/company', [AdminCompanyController::class, 'index'])->name('company.index');
        Route::get('/company/{company}/tax-profile', [AdminCompanyController::class, 'taxProfile'])->name('company.tax_profile.show');
        Route::get('/company/{company}/transactions', [AdminCompanyController::class, 'transactions'])->name('company.transactions.index');

        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');

        /*
        |----------------------------------------------------------------------
        | Audit / Error logs
        |----------------------------------------------------------------------
        */
        Route::get('/auditlog', [AdminAuditLogController::class, 'index'])->name('auditlog.index');
        Route::get('/auditlog/{auditLog}', [AdminAuditLogController::class, 'show'])->name('auditlog.show');

        Route::get('/errorlog', [AdminErrorLogController::class, 'index'])->name('errorlog.index');
        Route::get('/errorlog/{errorLog}', [AdminErrorLogController::class, 'show'])->name('errorlog.show');
    });
