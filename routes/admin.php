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



        /*
        |--------------------------------------------------------------------------
        | Transformación 360 · Supervisor funcional
        |--------------------------------------------------------------------------
        |
        | Superficie administrativa transversal.
        | Termina en Definición de Implementación.
        |
        */

        \Illuminate\Support\Facades\Route::get(
            '/transformation-360',
            [
                \App\Http\Controllers\Admin\AdminTransformation360OverviewController::class,
                'index'
            ]
        )->name(
            'transformation360.index'
        );

        \Illuminate\Support\Facades\Route::get(
            '/transformation-360/data-bi',
            [
                \App\Http\Controllers\Admin\AdminTransformation360OverviewController::class,
                'dataBi'
            ]
        )->name(
            'transformation360.data_bi'
        );

        /*
        |--------------------------------------------------------------------------
        | Transformación 360 · Solicitudes de Implementación
        |--------------------------------------------------------------------------
        |
        | F4B: bandeja y detalle read-only.
        | Las mutaciones de estado se incorporan en F4C.
        |
        */
        \Illuminate\Support\Facades\Route::get(
            '/transformation-360/implementation-requests',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestController::class,
                'index'
            ]
        )->name(
            'transformation360.implementation_requests.index'
        );

        \Illuminate\Support\Facades\Route::get(
            '/transformation-360/implementation-requests/{implementationRequest}',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestController::class,
                'show'
            ]
        )->name(
            'transformation360.implementation_requests.show'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/transformation-360/implementation-requests/{implementationRequest}/assign',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestController::class,
                'assign'
            ]
        )->name(
            'transformation360.implementation_requests.assign'
        );

        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/transition',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestController::class,
                'transition'
            ]
        )->name(
            'transformation360.implementation_requests.transition'
        );

        /*
        |--------------------------------------------------------------------------
        | Solicitud de implementación · Definition request-scoped
        |--------------------------------------------------------------------------
        |
        | Acción explícita de Admin LAUDA.
        | No autogenera contenido y no cambia el lifecycle del Request.
        |
        */

        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/definition',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'store'
            ]
        )->name(
            'transformation360.implementation_requests.definition.create'
        );

        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/generate',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'generate'
            ]
        )->name(
            'transformation360.implementation_requests.definition.generate'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/review',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'review'
            ]
        )->name(
            'transformation360.implementation_requests.definition.review'
        );

        /*
        |--------------------------------------------------------------------------
        | Request-scoped Definition → Tenant review
        |--------------------------------------------------------------------------
        |
        | Endpoint dedicado: no se habilita la transición genérica
        | definition_preparation → awaiting_tenant_review.
        |
        */
        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/{definition}/submit-tenant-review',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'submitForTenantReview'
            ]
        )->name(
            'transformation360.implementation_requests.definition.submit_tenant_review'
        );

        /*
        |--------------------------------------------------------------------------
        | Request-scoped Definition · nueva versión por cambios del tenant
        |--------------------------------------------------------------------------
        |
        | El navegador no selecciona la Definition anterior.
        | El controller resuelve la última versión request-scoped.
        |
        */
        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/revision',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'createRevision'
            ]
        )->name(
            'transformation360.implementation_requests.definition.revision.create'
        );

        /*
        |--------------------------------------------------------------------------
        | Definition acordada · cierre funcional explícito LAUDA
        |--------------------------------------------------------------------------
        |
        | Request-only. El navegador no selecciona Definition.
        | La versión exacta acordada se recupera desde el evento
        | definition_agreed_by_tenant.
        |
        */
        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/definition/finalize-functional',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'finalizeFunctional'
            ]
        )->name(
            'transformation360.implementation_requests.definition.functional_finalize'
        );

        /*
        |--------------------------------------------------------------------------
        | Cierre de ciclo funcional · listo para etapa comercial
        |--------------------------------------------------------------------------
        |
        | Request-only.
        |
        | Este gate NO crea ni acepta elementos comerciales.
        | Solo marca que la Definition funcional acordada ya terminó.
        |
        */
        \Illuminate\Support\Facades\Route::post(
            '/transformation-360/implementation-requests/{implementationRequest}/ready-for-commercial',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationRequestDefinitionActionController::class,
                'readyForCommercial'
            ]
        )->name(
            'transformation360.implementation_requests.ready_for_commercial'
        );











        /*
        |--------------------------------------------------------------------------
        | Transformación 360 · Matriz comercial de implementación
        |--------------------------------------------------------------------------
        |
        | Configuración global. No corresponde al pricing recurrente
        | de Services / ServicePlans.
        |
        */
        \Illuminate\Support\Facades\Route::get(
            '/transformation-360/commercial-settings',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationCommercialSettingsController::class,
                'show'
            ]
        )->name(
            'transformation360.commercial_settings.show'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/transformation-360/commercial-settings',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationCommercialSettingsController::class,
                'update'
            ]
        )->name(
            'transformation360.commercial_settings.update'
        );


        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}/implementation-plan',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationPlanController::class, 'show']
        )->name('diagnosis_requests.implementation_plan.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationPlanController::class, 'create']
        )->name('diagnosis_requests.implementation_plan.create');


        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/regenerate',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationPlanController::class,
                'regenerateStructure'
            ]
        )->name(
            'diagnosis_requests.implementation_plan.regenerate'
        );


        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/phases',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationPlanController::class, 'storePhase']
        )->name('diagnosis_requests.implementation_plan.phase.store');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/present',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationPlanController::class, 'present']
        )->name('diagnosis_requests.implementation_plan.present');



        /*
        |--------------------------------------------------------------------------
        | LAUDA 360 · Definición funcional/técnica de Implementación
        |--------------------------------------------------------------------------
        |
        | Esta capa NO inicia ejecución y NO contiene pricing/comercial.
        |
        */

        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}/implementation-plan/{plan}/definition',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationDefinitionController::class,
                'show'
            ]
        )->name(
            'diagnosis_requests.implementation_definition.show'
        );

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/{plan}/definition',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationDefinitionController::class,
                'create'
            ]
        )->name(
            'diagnosis_requests.implementation_definition.create'
        );

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/{plan}/definition/{definition}/regenerate',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationDefinitionController::class,
                'regenerate'
            ]
        )->name(
            'diagnosis_requests.implementation_definition.regenerate'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/diagnosis-requests/{contact}/implementation-plan/{plan}/definition/{definition}/review',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationDefinitionController::class,
                'review'
            ]
        )->name(
            'diagnosis_requests.implementation_definition.review'
        );

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/{plan}/definition/{definition}/ready',
            [
                \App\Http\Controllers\Admin\AdminTransformationImplementationDefinitionController::class,
                'ready'
            ]
        )->name(
            'diagnosis_requests.implementation_definition.ready'
        );


        \Illuminate\Support\Facades\Route::get(
            '/diagnosis-requests/{contact}/implementation-plan/execution',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'show']
        )->name('diagnosis_requests.implementation_execution.show');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/phases/{phase}/initialize',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'initializePhase']
        )->name('diagnosis_requests.implementation_execution.phase.initialize');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/capabilities/{capability}/start',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'startCapability']
        )->name('diagnosis_requests.implementation_execution.capability.start');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/capabilities/{capability}/progress',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'updateProgress']
        )->name('diagnosis_requests.implementation_execution.capability.progress');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/capabilities/{capability}/complete',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'completeCapability']
        )->name('diagnosis_requests.implementation_execution.capability.complete');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/capabilities/{capability}/go-live',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'createGoLive']
        )->name('diagnosis_requests.implementation_execution.go_live.create');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/go-lives/{goLive}/ready',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'markGoLiveReady']
        )->name('diagnosis_requests.implementation_execution.go_live.ready');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/go-lives/{goLive}/live',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'goLive']
        )->name('diagnosis_requests.implementation_execution.go_live.live');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/go-lives/{goLive}/subscription',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'activateSubscription']
        )->name('diagnosis_requests.implementation_execution.subscription.activate');

        \Illuminate\Support\Facades\Route::post(
            '/diagnosis-requests/{contact}/implementation-plan/execution/go-lives/{goLive}/service',
            [\App\Http\Controllers\Admin\AdminTransformationImplementationExecutionController::class, 'activateService']
        )->name('diagnosis_requests.implementation_execution.service.activate');

        /*
        |--------------------------------------------------------------------------
        | Branding e Identidad Digital · Evaluación Admin
        |--------------------------------------------------------------------------
        */
        \Illuminate\Support\Facades\Route::get(
            '/branding-evaluations',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'index'
            ]
        )->name(
            'branding_evaluations.index'
        );

        \Illuminate\Support\Facades\Route::get(
            '/branding-evaluations/{activation}',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'show'
            ]
        )->name(
            'branding_evaluations.show'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/branding-evaluations/{activation}/areas/{need}',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'evaluateNeed'
            ]
        )->name(
            'branding_evaluations.needs.evaluate'
        );

        \Illuminate\Support\Facades\Route::delete(
            '/branding-evaluations/{activation}/areas/{need}/reset',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'resetNeed'
            ]
        )->name(
            'branding_evaluations.needs.reset'
        );

        \Illuminate\Support\Facades\Route::post(
            '/branding-evaluations/{activation}/generate-drafts',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'generateDrafts'
            ]
        )->name(
            'branding_evaluations.generate_drafts'
        );

        \Illuminate\Support\Facades\Route::post(
            '/branding-evaluations/{activation}/summary/generate',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'generateSummary'
            ]
        )->name(
            'branding_evaluations.summary.generate'
        );

        \Illuminate\Support\Facades\Route::post(
            '/branding-evaluations/{activation}/ready-for-review',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'markReadyForReview'
            ]
        )->name(
            'branding_evaluations.ready_for_review'
        );

        \Illuminate\Support\Facades\Route::patch(
            '/branding-evaluations/{activation}/summary/review',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'reviewSummary'
            ]
        )->name(
            'branding_evaluations.summary.review'
        );

        \Illuminate\Support\Facades\Route::post(
            '/branding-evaluations/{activation}/validate',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'validateEvaluation'
            ]
        )->name(
            'branding_evaluations.validate'
        );

        \Illuminate\Support\Facades\Route::post(
            '/branding-evaluations/{activation}/complete',
            [
                \App\Http\Controllers\Admin\AdminBrandingEvaluationController::class,
                'completeEvaluation'
            ]
        )->name(
            'branding_evaluations.complete'
        );


    });
// END LAUDA360 DIAGNOSIS ADMIN ROUTES
