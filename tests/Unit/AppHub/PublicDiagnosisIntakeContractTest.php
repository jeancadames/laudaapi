<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class PublicDiagnosisIntakeContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_welcome_posts_real_native_payload_instead_of_redirecting(): void
    {
        $source = file_get_contents(
            $this->root('resources/js/pages/Welcome.vue')
        );

        $this->assertStringContainsString(
            "request_type: 'digital_diagnosis_access_request'",
            $source
        );
        $this->assertStringContainsString(
            "diagnosis_access: 'apphub_native'",
            $source
        );
        $this->assertStringContainsString(
            "topic: 'Solicitud de acceso al Diagnóstico LAUDA 360'",
            $source
        );
        $this->assertStringContainsString(
            'fixed top-5 right-5',
            $source
        );
    }

    public function test_contact_controller_routes_diagnosis_to_intake_service(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Http/Controllers/ContactRequestController.php'
            )
        );

        $this->assertStringContainsString(
            'PublicDiagnosisIntakeService',
            $source
        );
        $this->assertStringContainsString(
            '$diagnosisIntake->submit($data)',
            $source
        );
        $this->assertStringNotContainsString(
            'DiagnosisRequestEmailGuard $emailGuard',
            $source
        );
    }

    public function test_intake_is_idempotent_and_uses_secure_password_setup(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Services/Diagnosis/PublicDiagnosisIntakeService.php'
            )
        );

        foreach ([
            'GET_LOCK',
            'apphub_native',
            'InitialDiagnosisCommercialService::SOURCE',
            'DiagnosisAccessRequest::STATUS_PENDING',
            "'must_change_password' => true",
            'Password::broker()->createToken($user)',
            'account_mail_sent_at',
            'https://app.laudaapi.com',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $this->assertStringNotContainsString('Payment::', $source);
        $this->assertStringNotContainsString('Subscription::', $source);
        $this->assertStringNotContainsString(
            'password temporal',
            strtolower($source)
        );
    }

    public function test_password_reset_accepts_pre_tenant_diagnosis_account(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Actions/Fortify/ResetUserPassword.php'
            )
        );

        $this->assertStringContainsString(
            '$isInitialDiagnosisAccess',
            $source
        );
        $this->assertStringContainsString(
            "'lauda360_initial_diagnosis'",
            $source
        );
        $this->assertStringContainsString(
            '$isInitialTenantAccess || $isInitialDiagnosisAccess',
            $source
        );
    }

    public function test_onboarding_prefills_and_materializes_free_invoice(): void
    {
        $source = file_get_contents(
            $this->root(
                'app/Http/Controllers/AppHubOnboardingController.php'
            )
        );

        foreach ([
            'nativeDiagnosisWorkflow',
            'diagnosisProfilePrefill',
            "'1 a 10 personas' => '1-10'",
            '$commercial->ensure($user->fresh())',
            'RD$0.00',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    public function test_dedicated_account_email_exists(): void
    {
        $mail = file_get_contents(
            $this->root('app/Mail/DiagnosisAccountAccessMail.php')
        );
        $view = file_get_contents(
            $this->root(
                'resources/views/emails/diagnosis-account-access.blade.php'
            )
        );

        $this->assertStringContainsString(
            'Configura tu cuenta LAUDAAPI',
            $mail
        );
        $this->assertStringContainsString(
            'no enviamos contraseñas temporales',
            $view
        );
        $this->assertStringContainsString(
            'Continuar en App Hub',
            $view
        );
    }
}
