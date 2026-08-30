<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityPlanContextContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_plan_context_is_read_only_and_uses_latest_public_plan(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityPlanContextService.php'
        );

        foreach ([
            'TransformationImplementationPlan::query()',
            "'diagnosis_assessment_id'",
            'PUBLIC_PLAN_STATUSES',
            'STATUS_PRESENTED',
            'STATUS_ACCEPTED',
            'STATUS_ACTIVE',
            'STATUS_COMPLETED',
            "'presented_at'",
            "'phases.capabilities'",
            "->orderByDesc('version')",
            "->orderByDesc('id')",
            "'diagnosis.implementation_plan.show'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'create(',
            'save(',
            'update(',
            'delete(',
            'forceFill(',
            'DB::',
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'TransformationImplementationExecutionService',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_branding_is_located_in_its_real_plan_phase(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityPlanContextService.php'
        );

        foreach ([
            "'branding_identity'",
            "'linked_initiative_keys'",
            "'initiatives'",
            "'horizon'",
            "'related_initiatives'",
            "'priorities'",
            "'dependencies'",
            "'deliverables'",
            "'includes'",
            "'phase'",
            "'sequence'",
            "'objective'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'recommended_phase'",
            "'priority' => 'high'",
            "'phase' => 1",
            "'horizon' => 'Fase 1'",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_priority_and_dependencies_come_only_from_related_plan_initiatives(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityPlanContextService.php'
        );

        foreach ([
            '$linkedInitiativeKeys',
            "->contains(",
            "'priority'",
            'priorityLabel(',
            "'dependencies'",
            '$relatedInitiatives',
            "->flatMap(",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            '$phaseSnapshot[\'dependencies\']',
            $source
        );
    }

    public function test_workspace_composes_plan_context_without_writing(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityWorkspaceService.php'
        );

        foreach ([
            'BrandingIdentityPlanContextService $planContext',
            "'plan_context' =>",
            '$this->planContext->forActivation(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'save(',
            'update(',
            'create(',
            'TransformationImplementationExecutionService',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_tenant_workspace_renders_real_plan_context_without_hardcoded_business_facts(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'BrandingIdentity.vue'
        );

        foreach ([
            'type BrandingPlanContext =',
            'plan_context: BrandingPlanContext',
            'Ubicación en el Plan consultivo',
            'Fase sugerida',
            'Horizonte',
            'Prioridad',
            'Dependencias',
            'Iniciativas relacionadas',
            'Entregables previstos',
            'props.branding.plan_context.phase',
            '.sequence',
            'props.branding.plan_context.phase.name',
            'props.branding.plan_context.priorities',
            'props.branding.plan_context.dependencies',
            'props.branding.plan_context.related_initiatives',
            'props.branding.plan_context.deliverables',
            'Ver Plan V',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Prioridad: Alta',
            'Fase 1 ·',
            '91-180 días',
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
            'DOP ',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_s12f_does_not_duplicate_plan_context_into_need_persistence(): void
    {
        foreach ([
            '/app/Models/TransformationCapabilityNeed.php',
            '/app/Services/Diagnosis/TransformationCapabilityNeedCatalog.php',
            '/app/Services/Diagnosis/TransformationCapabilityNeedService.php',
        ] as $relative) {
            $source = file_get_contents(
                $this->root().$relative
            );

            foreach ([
                "'recommended_phase'",
                "'plan_phase_id'",
                "'plan_id'",
                "'priority' =>",
            ] as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $source
                );
            }
        }
    }
}
