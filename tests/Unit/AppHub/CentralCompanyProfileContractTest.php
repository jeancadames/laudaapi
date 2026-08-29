<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class CentralCompanyProfileContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_onboarding_and_profile_share_one_form(): void
    {
        $onboarding = $this->read(
            'resources/js/pages/Onboarding/AppHub.vue'
        );

        $profile = $this->read(
            'resources/js/pages/Subscriber/Company/Index.vue'
        );

        foreach ([$onboarding, $profile] as $source) {
            $this->assertStringContainsString(
                'CompanyProfileForm',
                $source
            );
        }
    }

    public function test_service_has_declared_company_contract(): void
    {
        $service = $this->read(
            'app/Services/Companies/CentralCompanyProfileService.php'
        );

        foreach ([
            "'company_name'",
            "'legal_name'",
            "'tax_id'",
            "'country_code'",
            "'currency'",
            "'timezone'",
            "'company_size'",
            "'billing_email'",
            "'billing_phone'",
            "'address_line1'",
            "'state'",
            "'city'",
            "'economic_activity_primary_name'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_company_profile_does_not_write_external_fiscal_state(): void
    {
        $service = $this->read(
            'app/Services/Companies/CentralCompanyProfileService.php'
        );

        $saveStart = strpos(
            $service,
            'public function save('
        );

        $normalizeStart = strpos(
            $service,
            'private function normalize('
        );

        $this->assertNotFalse($saveStart);
        $this->assertNotFalse($normalizeStart);

        $saveBlock = substr(
            $service,
            $saveStart,
            $normalizeStart - $saveStart
        );

        foreach ([
            "'dgii_status' =>",
            "'dgii_registered_on' =>",
            "'tax_regime' =>",
            "'rst_modality' =>",
            "'rst_category' =>",
            "'fiscal_year_end_id' =>",
            "'invoicing_mode' =>",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $saveBlock
            );
        }
    }

    public function test_user_email_is_not_overwritten_by_company_save(): void
    {
        $service = $this->read(
            'app/Services/Companies/CentralCompanyProfileService.php'
        );

        $this->assertStringContainsString(
            "'billing_email'",
            $service
        );

        $this->assertStringNotContainsString(
            "\$actor->email =",
            $service
        );

        $this->assertStringNotContainsString(
            "'email' => \$normalized['billing_email']",
            $service
        );
    }

    public function test_only_owner_or_admin_can_edit_company_profile(): void
    {
        $service = $this->read(
            'app/Services/Companies/CentralCompanyProfileService.php'
        );

        $this->assertStringContainsString(
            "->wherePivotIn('role', ['owner', 'admin'])",
            $service
        );

        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberCompanyController.php'
        );

        $this->assertStringContainsString(
            'resolveEditableContext($user)',
            $controller
        );
    }

    public function test_onboarding_and_subscriber_controller_use_same_service(): void
    {
        $onboarding = $this->read(
            'app/Http/Controllers/AppHubOnboardingController.php'
        );

        $subscriber = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberCompanyController.php'
        );

        foreach ([$onboarding, $subscriber] as $source) {
            $this->assertStringContainsString(
                'CentralCompanyProfileService',
                $source
            );
        }
    }

    public function test_all_company_profile_text_fields_use_native_inputs(): void
    {
        $form = $this->read(
            'resources/js/components/company/CompanyProfileForm.vue'
        );

        foreach ([
            'company_name',
            'legal_name',
            'tax_id',
            'country_code',
            'billing_email',
            'billing_phone',
            'billing_contact_name',
            'address_line1',
            'address_line2',
            'state',
            'city',
            'postal_code',
            'timezone',
            'economic_activity_primary_code',
            'economic_activity_primary_name',
        ] as $name) {
            $this->assertMatchesRegularExpression(
                '/<input[\s\S]*?name="'.preg_quote($name, '/').'"/',
                $form,
                'Campo no nativo: '.$name
            );
        }

        $this->assertMatchesRegularExpression(
            '/<input[\s\S]*?name="tax_id"[\s\S]*?maxlength="50"/',
            $form
        );

        $this->assertMatchesRegularExpression(
            '/<input[\s\S]*?name="billing_phone"[\s\S]*?type="tel"[\s\S]*?maxlength="50"/',
            $form
        );

        foreach ([
            'address_line1',
            'address_line2',
            'state',
            'city',
            'postal_code',
        ] as $locationField) {
            $this->assertStringContainsString(
                'name="'.$locationField.'"',
                $form
            );
        }
    }


    public function test_legacy_compliance_endpoint_is_preserved(): void
    {
        $controller = $this->read(
            'app/Http/Controllers/Subscriber/SubscriberCompanyController.php'
        );

        $this->assertStringContainsString(
            'public function upsertObligations(Request $request)',
            $controller
        );
    }
}
