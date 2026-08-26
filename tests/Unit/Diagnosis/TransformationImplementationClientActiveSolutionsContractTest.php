<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientActiveSolutionsContractTest extends TestCase
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

    public function test_client_solution_access_reuses_central_entitlement_resolver(): void
    {
        $source = $this->read(
            'app/Services/Diagnosis/TransformationImplementationClientSolutionAccessService.php'
        );

        foreach ([
            'ServiceAccessResolver',
            'SubscriberResolver',
            'userCanAccess(',
            "'entitlement_allowed' =>",
            "'access_url' =>",
            "route(",
            "'erp.services.open'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "['active', 'trialing']",
            $source,
            'El servicio cliente no debe duplicar la política central.'
        );
    }

    public function test_outer_phase_mapper_captures_solution_access_service(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        $this->assertMatchesRegularExpression(
            '/\\$clientPhases\\s*=\\s*\\$commercialPhases->map\\s*\\(\\s*'
            .'function\\s*\\(\\s*array\\s+\\$phaseData\\s*\\)\\s*use\\s*\\('
            .'(?:(?!\\)\\s*\\{).)*\\$solutionAccessService'
            .'(?:(?!\\)\\s*\\{).)*\\)\\s*\\{/s',
            $source
        );

        $this->assertStringContainsString(
            '$solutionAccessService->resolve(',
            $source
        );
    }

    public function test_client_payload_exposes_only_safe_recurring_solution_fields(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'recurring_solution' =>",
            "'solution_access_summary' => \$solutionAccessSummary",
            "'r2j_activated_count' =>",
            "'entitled_count' =>",
            "'access_unavailable_count' =>",
            "'live_without_r2j_count' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'price_snapshot' =>",
            "'internal_notes' =>",
            "'meta' =>",
            "'source_snapshot' =>",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_ui_only_renders_cta_from_entitlement_result(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        foreach ([
            'Acceso a tus soluciones',
            'Solución recurrente',
            '.recurring_solution',
            '.entitlement_allowed',
            '.access_url',
            'Abrir solución',
            'Go-Live completado.',
            'La activación recurrente',
            'todavía está pendiente.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_direct_service_launcher_enforces_same_resolver(): void
    {
        $source = $this->read(
            'app/Http/Controllers/LaudaErp/ServiceLaunchController.php'
        );

        $guard =
            'if (! $this->accessResolver->userCanAccess('
            .'$user, $company, $service)) {';

        $this->assertStringContainsString(
            $guard,
            $source
        );

        $this->assertStringNotContainsString(
            '// '.$guard,
            $source
        );
    }

    public function test_client_layer_remains_read_only(): void
    {
        $combined =
            $this->read(
                'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
            )
            ."\n"
            .$this->read(
                'app/Services/Diagnosis/TransformationImplementationClientSolutionAccessService.php'
            )
            ."\n"
            .$this->read(
                'resources/js/pages/Diagnosis/ImplementationPlan.vue'
            );

        foreach ([
            'activateFromGoLive(',
            'activateServiceForGoLive(',
            'activateSubscriptionForGoLive(',
            'Subscription::create(',
            'SubscriptionItem::create(',
            "->status = 'cancelled'",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $combined
            );
        }
    }
}
