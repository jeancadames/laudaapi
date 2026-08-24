<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisBusinessProfileUiContractTest extends TestCase
{
    public function test_client_blocks_wizard_until_profile_is_saved(): void
    {
        $source = file_get_contents(
            base_path(
                'resources/js/pages/Diagnosis/Show.vue'
            )
        );

        $this->assertStringContainsString(
            'Guardar perfil y continuar',
            $source
        );

        $this->assertStringContainsString(
            'v-if="profileSaved"',
            $source
        );

        $this->assertStringContainsString(
            'Tipo de operación logística',
            $source
        );
    }

    public function test_profile_travels_in_save_and_submit(): void
    {
        $source = file_get_contents(
            base_path(
                'resources/js/pages/Diagnosis/Show.vue'
            )
        );

        $this->assertGreaterThanOrEqual(
            3,
            substr_count(
                $source,
                'businessProfilePayload()'
            )
        );
    }

    public function test_profile_is_explicitly_non_scoring_context(): void
    {
        $client = file_get_contents(
            base_path(
                'resources/js/pages/Diagnosis/Show.vue'
            )
        );

        $admin = file_get_contents(
            base_path(
                'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
            )
        );

        $this->assertStringContainsString(
            'Esta información no modifica la puntuación',
            $client
        );

        $this->assertMatchesRegularExpression(
            '/No\\s+forma\\s+parte\\s+de\\s+la\\s+puntuación/u',
            $admin
        );
    }

    public function test_admin_can_review_logistics_context(): void
    {
        $source = file_get_contents(
            base_path(
                'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
            )
        );

        $this->assertStringContainsString(
            '<CardTitle>',
            $source
        );

        $this->assertStringContainsString(
            'Perfil comercial',
            $source
        );

        $this->assertStringContainsString(
            'Operación logística',
            $source
        );

        $this->assertStringContainsString(
            'businessProfileOptions',
            $source
        );
    }
}
