<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisInvitationAccessContractTest extends TestCase
{
    public function test_signature_is_checked_before_login(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/' . (
                'app/Http/Controllers/Diagnosis/DiagnosisInvitationController.php'
            )
        );

        $signature = strpos(
            $source,
            'if (!$request->hasValidSignature())'
        );
        $login = strpos($source, 'Auth::login(');

        $this->assertNotFalse($signature);
        $this->assertNotFalse($login);
        $this->assertLessThan($login, $signature);

        $this->assertStringContainsString(
            'Diagnosis/InvitationExpired',
            $source
        );

        $this->assertStringContainsString(
            'hasCorrectSignature',
            $source
        );

        $this->assertStringContainsString(
            'signatureHasNotExpired',
            $source
        );
    }

    public function test_email_documents_policy(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/' . (
                'resources/views/emails/diagnosis-invitation.blade.php'
            )
        );

        $this->assertStringContainsString('72 horas', $source);
        $this->assertStringContainsString(
            'Una vez activada tu cuenta',
            $source
        );
        $this->assertStringContainsString(
            'Iniciar sesión',
            $source
        );
    }

    public function test_expired_page_explains_activation_only_expiry(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/' . (
                'resources/js/pages/Diagnosis/InvitationExpired.vue'
            )
        );

        $this->assertStringContainsString(
            'El vencimiento aplica únicamente al enlace de activación',
            $source
        );
        $this->assertStringContainsString(
            'Iniciar sesión',
            $source
        );
    }

    public function test_admin_shows_invitation_state(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/' . (
                'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
            )
        );

        $this->assertStringContainsString(
            'invitationExpired',
            $source
        );
        $this->assertStringContainsString(
            'Enlace expirado',
            $source
        );
        $this->assertStringContainsString(
            'Cuenta activada',
            $source
        );
    }
}
