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

// BEGIN LAUDA360 DIAGNOSIS ADMIN ROUTES
\Illuminate\Support\Facades\Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'index']
        )->name('diagnosis_requests.index');

        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'show']
        )->name('diagnosis_requests.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/status',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'updateStatus']
        )->name('diagnosis_requests.status');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/approve',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'approve']
        )->name('diagnosis_requests.approve');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/resend',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'resend']
        )->name('diagnosis_requests.resend');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/reject',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'reject']
        )->name('diagnosis_requests.reject');
        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/save-review',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'saveReview']
        )->name('diagnosis_requests.save_review');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/publish-result',
            [\App\Http\Controllers\Admin\AdminDiagnosisAccessRequestController::class, 'publishResult']
        )->name('diagnosis_requests.publish_result');


        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}/expanded-report',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'show']
        )->name('diagnosis_requests.expanded_report.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/generate',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'generate']
        )->name('diagnosis_requests.expanded_report.generate');

        \Illuminate\Support\Facades\Route::patch(
            '/diagnosis-requests/{contact}/expanded-report/{report}/review-notes',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'saveReview']
        )->name('diagnosis_requests.expanded_report.save_review');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/{report}/review',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'review']
        )->name('diagnosis_requests.expanded_report.review');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/{report}/regenerate',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'regenerate']
        )->name('diagnosis_requests.expanded_report.regenerate');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/{report}/publish',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportController::class, 'publish']
        )->name('diagnosis_requests.expanded_report.publish');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/order/{order}/prepare-invoice',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportCommercialController::class, 'prepareInvoice']
        )->name('diagnosis_requests.expanded_report.prepare_invoice');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/expanded-report/order/{order}/record-payment',
            [\App\Http\Controllers\Admin\AdminDiagnosisExpandedReportCommercialController::class, 'recordPayment']
        )->name('diagnosis_requests.expanded_report.record_payment');


        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}/detailed-roadmap',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'show']
        )->name('diagnosis_requests.detailed_roadmap.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/generate',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'generate']
        )->name('diagnosis_requests.detailed_roadmap.generate');

        \Illuminate\Support\Facades\Route::patch(
            '/diagnosis-requests/{contact}/detailed-roadmap/{roadmap}/review-notes',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'saveReview']
        )->name('diagnosis_requests.detailed_roadmap.save_review');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/{roadmap}/review',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'review']
        )->name('diagnosis_requests.detailed_roadmap.review');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/{roadmap}/regenerate',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'regenerate']
        )->name('diagnosis_requests.detailed_roadmap.regenerate');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/{roadmap}/publish',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapController::class, 'publish']
        )->name('diagnosis_requests.detailed_roadmap.publish');


        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/order/{order}/prepare-invoice',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapCommercialController::class, 'prepareInvoice']
        )->name('diagnosis_requests.detailed_roadmap.prepare_invoice');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/detailed-roadmap/order/{order}/record-payment',
            [\App\Http\Controllers\Admin\AdminDiagnosisDetailedRoadmapCommercialController::class, 'recordPayment']
        )->name('diagnosis_requests.detailed_roadmap.record_payment');

    });
// END LAUDA360 DIAGNOSIS ADMIN ROUTES
