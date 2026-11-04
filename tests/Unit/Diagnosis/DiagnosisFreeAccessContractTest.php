<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisFreeAccessContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_request_and_onboarding_use_invoice_free_access_service(): void
    {
        $controller = $this->source('app/Http/Controllers/AppHubDiagnosisController.php');
        $onboarding = $this->source('app/Http/Controllers/AppHubOnboardingController.php');
        self::assertStringContainsString('$commercial->request($user)', $controller);
        self::assertStringContainsString('$commercial->ensure($user->fresh())', $onboarding);
        foreach ([$controller, $onboarding] as $source) {
            foreach (['Invoice::', 'InvoiceItem::', 'Payment::', 'Subscription::', 'SubscriptionItem::'] as $token) {
                self::assertStringNotContainsString($token, $source);
            }
        }
    }

    public function test_admin_approval_rejection_and_authentication_boundaries_remain_explicit(): void
    {
        $routes = $this->source('routes/admin.php');
        foreach (["['auth', 'verified', 'role:admin']", '/diagnosis-requests/{contact}/approve',
            '/diagnosis-requests/{contact}/reject'] as $token) {
            self::assertStringContainsString($token, $routes);
        }
        $access = $this->source('app/Services/Diagnosis/DiagnosisAccessService.php');
        foreach (['STATUS_REJECTED', 'Company::query()', "->where('active', true)",
            "['user', 'subscriber']", 'sendInvitation($workflow, $admin)', "'confirmed_by_user_id' => ",
            "'confirmation_status' => 'confirmed'"] as $token) {
            self::assertStringContainsString($token, $access);
        }
        $initial = $this->source('app/Services/Diagnosis/InitialDiagnosisCommercialService.php');
        self::assertStringContainsString("->wherePivotIn('role', ['owner', 'admin'])", $initial);
        self::assertStringContainsString('new_requests_blocked', $initial);
        $invitation = $this->source('app/Http/Controllers/Diagnosis/DiagnosisInvitationController.php');
        foreach (['hasValidSignature()', 'authorizeAccessOwner', 'must_change_password', 'session()->regenerate()'] as $token) {
            self::assertStringContainsString($token, $invitation);
        }
    }

    public function test_diagnosis_screen_and_active_messages_have_no_invoice_or_billing_flow(): void
    {
        $page = $this->source('resources/js/pages/App/Diagnosis360.vue');
        foreach (['props.state.invoice', 'endpoints.invoices', 'ReceiptText', 'factura', 'Facturación', 'DOP 0.00', 'flujo comercial manual'] as $token) {
            self::assertStringNotContainsString($token, $page);
        }
        foreach (['Informe Ampliado', 'Roadmap Detallado', 'aprobación por Admin LAUDA',
            'Guiado, Asistido o Gestionado', 'no implica contratación ni autoriza ejecución'] as $token) {
            self::assertStringContainsString($token, $page);
        }
        self::assertStringNotContainsString("'invoices' =>", $this->source('app/Http/Controllers/AppHubDiagnosisController.php'));
        foreach (['app/Http/Controllers/AppHubOnboardingController.php', 'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'] as $path) {
            self::assertStringNotContainsString('factura de cortesía', $this->source($path));
            self::assertStringNotContainsString('factura RD$0.00', $this->source($path));
        }
    }

    public function test_free_deliverables_do_not_require_paid_access_or_modality(): void
    {
        foreach (['app/Services/Diagnosis/DiagnosisFreeDeliverablesOrchestrator.php',
            'app/Http/Controllers/Diagnosis/DiagnosisExpandedReportController.php',
            'app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapController.php',
            'app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php',
            'app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'] as $path) {
            $source = $this->source($path);
            foreach (['hasPaidAccess(', 'Invoice::', 'Payment::', 'Subscription::'] as $token) {
                self::assertStringNotContainsString($token, $source);
            }
        }
        $orchestrator = $this->source('app/Services/Diagnosis/DiagnosisFreeDeliverablesOrchestrator.php');
        foreach (['$this->expandedReports->publish(', '$this->roadmaps->publish(', "'modality_required' => false"] as $token) {
            self::assertStringContainsString($token, $orchestrator);
        }
    }

    public function test_professional_implementation_keeps_its_distinct_commercial_gates(): void
    {
        $plan = $this->source('app/Services/Diagnosis/TransformationImplementationPlanService.php');
        foreach (['private function commercialReadiness(', '$this->commercialReadiness($locked)',
            '$plan->selected_modality', 'TransformationImplementationPhaseEstimate::query()',
            'TransformationImplementationMilestone::query()', "'accepted_at' => now()"] as $token) {
            self::assertStringContainsString($token, $plan);
        }
        $execution = $this->source('app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php');
        self::assertStringContainsString('$plan->accepted_at !== null', $execution);
        $validation = $this->source('app/Services/Diagnosis/DiagnosisDeliverableValidationService.php');
        self::assertStringNotContainsString('acceptPlan(', $validation);
        self::assertStringContainsString("'commercial_acceptance' => false", $validation);
    }

    public function test_shared_company_profile_and_historical_commercial_services_are_preserved(): void
    {
        $profiles = $this->source('app/Services/Companies/CentralCompanyProfileService.php');
        self::assertStringContainsString("'billing_email' => ['required', 'email', 'max:255']", $profiles);
        self::assertStringContainsString("'billing_email' => (string) \$user->email", $profiles);
        foreach (['app/Services/Diagnosis/DiagnosisExpandedReportCommercialService.php',
            'app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php'] as $path) {
            self::assertStringContainsString('public function requestPurchase(', $this->source($path));
        }
        $initial = $this->source('app/Services/Diagnosis/InitialDiagnosisCommercialService.php');
        self::assertStringContainsString("SOURCE = 'lauda360_initial_diagnosis'", $initial);
        self::assertStringContainsString("'initial_diagnosis'", $initial);
        self::assertStringContainsString("is_array(\$meta['initial_diagnosis'] ?? null)", $initial);
    }
}
