<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceBusinessDomainsUiContractTest
    extends TestCase
{
    private string $vue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vue =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/resources/js/pages/App/DataTransformationBi.vue'
            );
    }

    public function test_source_contract_exposes_dynamic_business_domains(): void
    {
        self::assertStringContainsString(
            'business_domains: SourceBusinessDomain[];',
            $this->vue
        );

        self::assertStringContainsString(
            "value: 'operaciones'",
            $this->vue
        );

        self::assertStringContainsString(
            "value: 'gestion'",
            $this->vue
        );

        self::assertStringContainsString(
            "value: 'finanzas'",
            $this->vue
        );
    }

    public function test_create_and_edit_forms_manage_zero_or_many_domains(): void
    {
        self::assertStringContainsString(
            'createSourceForm.business_domains',
            $this->vue
        );

        self::assertStringContainsString(
            'editSourceForm.business_domains',
            $this->vue
        );

        self::assertStringContainsString(
            'addBusinessDomain(',
            $this->vue
        );

        self::assertStringContainsString(
            'removeBusinessDomain(',
            $this->vue
        );

        self::assertStringContainsString(
            'businessDomainsPayload(',
            $this->vue
        );
    }

    public function test_domain_name_is_free_text_not_a_fixed_select_catalog(): void
    {
        self::assertStringContainsString(
            'v-model="businessDomain.domain"',
            $this->vue
        );

        self::assertStringContainsString(
            'type="text"',
            $this->vue
        );

        self::assertStringContainsString(
            'Los dominios son libres y dependen de tu negocio.',
            $this->vue
        );
    }

    public function test_ui_explains_business_classification_is_optional(): void
    {
        self::assertStringContainsString(
            'Opcional. Identifica las áreas de información',
            $this->vue
        );

        self::assertStringContainsString(
            'Puedes dejar esta clasificación pendiente',
            $this->vue
        );
    }

    public function test_business_domains_are_sent_in_create_and_update_payloads(): void
    {
        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $this->vue,
                'business_domains:'
            )
        );

        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $this->vue,
                'businessDomainsPayload('
            )
        );
    }

    public function test_only_business_group_is_a_controlled_selector(): void
    {
        self::assertStringContainsString(
            'v-model="businessDomain.group"',
            $this->vue
        );

        self::assertStringContainsString(
            'v-for="option in businessGroupOptions"',
            $this->vue
        );

        self::assertStringContainsString(
            'Operaciones',
            $this->vue
        );

        self::assertStringContainsString(
            'Gestión',
            $this->vue
        );

        self::assertStringContainsString(
            'Finanzas',
            $this->vue
        );
    }
}
