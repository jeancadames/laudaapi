<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppHubOnboardingStepperContractTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_company_profile_has_onboarding_only_stepper(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/components/company/CompanyProfileForm.vue'
            )
        );

        $this->assertIsString($source);

        foreach ([
            'Stepper,',
            'StepperDescription,',
            'StepperIndicator,',
            'StepperItem,',
            'StepperSeparator,',
            'StepperTitle,',
            'StepperTrigger,',
            'v-if="props.onboarding"',
            'const totalSteps = 4',
            "title: 'Empresa'",
            "title: 'Contexto'",
            "title: 'Contacto'",
            "title: 'Confirmar'",
            'highestStepReached',
            'currentStepValid',
            'goToStep',
            'Siguiente',
            'Anterior',
            'Revisa antes de continuar',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_normal_profile_mode_keeps_direct_submit(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/components/company/CompanyProfileForm.vue'
            )
        );

        $this->assertStringContainsString(
            'v-else',
            $source
        );

        $this->assertStringContainsString(
            '{{ props.submitLabel }}',
            $source
        );

        $this->assertStringContainsString(
            ':action="props.action"',
            $source
        );

        $this->assertStringContainsString(
            'method="post"',
            $source
        );
    }

    public function test_backend_payload_field_names_are_preserved(): void
    {
        $source = file_get_contents(
            $this->root(
                'resources/js/components/company/CompanyProfileForm.vue'
            )
        );

        foreach ([
            'name="name"',
            'name="company_name"',
            'name="legal_name"',
            'name="tax_id"',
            'name="taxpayer_type"',
            'name="company_size"',
            'name="country_code"',
            'name="currency"',
            'name="timezone"',
            'name="billing_email"',
            'name="billing_phone"',
            'name="billing_contact_name"',
            'name="address_line1"',
            'name="address_line2"',
            'name="state"',
            'name="city"',
            'name="postal_code"',
            'name="economic_activity_primary_code"',
            'name="economic_activity_primary_name"',
        ] as $field) {
            $this->assertSame(
                1,
                substr_count($source, $field),
                "Expected exactly one {$field} field."
            );
        }
    }
}
