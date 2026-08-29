<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppHubDiagnosis360CommercialContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents((dirname(__DIR__, 3).'/'.ltrim($file, '/'))) ?: '';
    }

    public function test_initial_diagnosis_is_explicitly_complimentary(): void
    {
        $config = $this->source(
            'config/lauda360_commercial.php'
        );

        $this->assertStringContainsString(
            "'initial_diagnosis' => [",
            $config
        );
        $this->assertStringContainsString(
            "'subtotal' => 0.00",
            $config
        );
        $this->assertStringContainsString(
            "'complimentary' => true",
            $config
        );
        $this->assertStringContainsString(
            "'manual_confirmation_required' => true",
            $config
        );
    }

    public function test_free_invoice_has_no_fake_payment_or_subscription(): void
    {
        $service = $this->source(
            'app/Services/Diagnosis/InitialDiagnosisCommercialService.php'
        );

        $this->assertStringContainsString(
            "'status' => 'issued'",
            $service
        );
        $this->assertStringContainsString(
            "'total' => 0",
            $service
        );
        $this->assertStringContainsString(
            'InvoiceItem::query()->create',
            $service
        );

        $this->assertStringNotContainsString(
            'Payment::',
            $service
        );
        $this->assertStringNotContainsString(
            'Subscription::',
            $service
        );
        $this->assertStringNotContainsString(
            'SubscriptionItem::',
            $service
        );
    }

    public function test_welcome_enters_app_hub(): void
    {
        $welcome = $this->source(
            'resources/js/pages/Welcome.vue'
        );

        /*
         * S10-F4.12-D:
         * Welcome ya no salta al login antes de persistir el intake.
         * Primero envía /contact con el marcador apphub_native; el correo
         * seguro es quien conduce después al App Hub.
         */
        $this->assertStringContainsString(
            "const CONTACT_REQUEST_ENDPOINT = '/contact';",
            $welcome
        );
        $this->assertStringContainsString(
            "request_type: 'digital_diagnosis_access_request'",
            $welcome
        );
        $this->assertStringContainsString(
            "diagnosis_access: 'apphub_native'",
            $welcome
        );
        $this->assertStringNotContainsString(
            "`${APP_URL}/app/diagnostico-360/entrada`",
            $welcome
        );
    }

    public function test_navigation_contains_diagnosis_360(): void
    {
        $nav = $this->source(
            'resources/js/config/navigationByRole.ts'
        );

        $this->assertStringContainsString(
            "title: 'Diagnóstico 360'",
            $nav
        );
        $this->assertStringContainsString(
            "href: '/app/diagnostico-360'",
            $nav
        );
    }

    public function test_native_confirmation_skips_legacy_invitation_branch(): void
    {
        $access = $this->source(
            'app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        $this->assertStringContainsString(
            'InitialDiagnosisCommercialService::SOURCE',
            $access
        );
        $this->assertStringContainsString(
            "'confirmation_status' => 'confirmed'",
            $access
        );
        $this->assertStringContainsString(
            'DiagnosisAccessRequest::STATUS_ACTIVE',
            $access
        );
        $this->assertStringContainsString(
            'return $this->sendInvitation($workflow, $admin);',
            $access
        );
    }
}
