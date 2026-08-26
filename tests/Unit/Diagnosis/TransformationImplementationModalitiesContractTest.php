<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationModalitiesContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_catalog_defines_exactly_three_modalities(): void
    {
        $catalog = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationModalityCatalog.php'
        ));

        $this->assertStringContainsString("public const GUIDED = 'guided';", $catalog);
        $this->assertStringContainsString("public const ASSISTED = 'assisted';", $catalog);
        $this->assertStringContainsString("public const MANAGED = 'managed';", $catalog);

        $this->assertStringContainsString("'label' => 'Guiado'", $catalog);
        $this->assertStringContainsString("'label' => 'Asistido'", $catalog);
        $this->assertStringContainsString("'label' => 'Gestionado'", $catalog);
    }

    public function test_catalog_explains_lauda_and_client_roles(): void
    {
        $catalog = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationModalityCatalog.php'
        ));

        $this->assertStringContainsString("'lauda_role'", $catalog);
        $this->assertStringContainsString("'client_role'", $catalog);
        $this->assertStringContainsString("'summary'", $catalog);
    }

    public function test_recommended_and_selected_modalities_remain_separate(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationModalityService.php'
        ));

        $this->assertStringContainsString('recommended_modality', $service);
        $this->assertStringContainsString('selected_modality', $service);
        $this->assertStringNotContainsString(
            "'recommended_modality' => \$modality",
            $service
        );
    }

    public function test_selection_is_only_mutable_before_acceptance(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationModalityService.php'
        ));

        $this->assertStringContainsString(
            "in_array(\$plan->status, ['draft', 'presented'], true)",
            $service
        );

        $this->assertStringContainsString(
            'La modalidad solo puede cambiarse antes de aceptar el Plan de Implementación.',
            $service
        );
    }

    public function test_selection_writes_audit_user_and_label(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationModalityService.php'
        ));

        $this->assertStringContainsString("'selected_modality' => \$modality", $service);
        $this->assertStringContainsString("'selected_modality_label'", $service);
        $this->assertStringContainsString("'updated_by_user_id' => \$userId", $service);
    }

    public function test_r2d_does_not_price_bill_or_start_subscription(): void
    {
        $files = [
            file_get_contents($this->project(
                'app/Services/Diagnosis/TransformationImplementationModalityCatalog.php'
            )),
            file_get_contents($this->project(
                'app/Services/Diagnosis/TransformationImplementationModalityService.php'
            )),
        ];

        $all = implode("\n", $files);

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
            'price',
            'amount',
            'milestone',
            'go_live',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $all);
        }
    }
}
