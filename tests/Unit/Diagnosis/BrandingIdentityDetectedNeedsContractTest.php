<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityDetectedNeedsContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_generic_need_persistence_is_scoped_to_activation(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_08_30_201000_create_transformation_capability_needs_table.php'
        );

        foreach ([
            "'transformation_capability_needs'",
            "'transformation_capability_activation_id'",
            "'sequence'",
            "'need_key'",
            "'title'",
            "'description'",
            "'source_type'",
            "'source_snapshot'",
            "'status'",
            "'identified_at'",
            "'tcn_activation_need_uq'",
            "'tcn_activation_status_idx'",
            "'tcn_activation_fk'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $migration
            );
        }

        foreach ([
            "'priority'",
            "'recommended_phase'",
            "'dependencies'",
            "'price'",
            "'currency'",
            "'modality'",
            "'subscription_id'",
            "'invoice_id'",
            "'payment_id'",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $migration
            );
        }
    }

    public function test_branding_catalog_contains_exact_six_canonical_needs(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityNeedCatalog.php'
        );

        $definitions = [
            'positioning_refinement' =>
                'Refinamiento de posicionamiento',
            'visual_identity_update' =>
                'Actualización de identidad visual',
            'brand_kit' =>
                'Creación de Brand Kit',
            'social_normalization' =>
                'Normalización de redes sociales',
            'commercial_documents' =>
                'Adaptación de documentos comerciales',
            'web_application' =>
                'Aplicación en presencia web',
        ];

        foreach ($definitions as $key => $title) {
            $this->assertStringContainsString(
                "'".$key."'",
                $source
            );

            $this->assertStringContainsString(
                "'".$title."'",
                $source
            );
        }

        $this->assertSame(
            6,
            substr_count(
                $source,
                "'need_key' =>"
            )
        );

        foreach ([
            "'priority'",
            "'recommended_phase'",
            "'dependencies'",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_need_sync_is_idempotent_and_does_not_reset_existing_status(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityNeedService.php'
        );

        foreach ([
            'TransformationCapabilityNeedCatalog::forCapability(',
            'firstOrNew([',
            '$isNew = ! $need->exists;',
            'if ($isNew)',
            'TransformationCapabilityNeed::STATUS_IDENTIFIED',
            "'identified_at'",
            "'source_snapshot'",
            "'catalog_version'",
            "'activation_source'",
            "'roadmap_recommendation'",
            "'free_activation'",
            'transformation_capability_needs_identified',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'updateOrCreate(',
            'delete(',
            'deleteOrFail(',
            'truncate(',
            'progress_percentage',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_activation_post_synchronizes_needs_for_new_and_existing_activation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        $this->assertStringContainsString(
            'TransformationCapabilityNeedService $needs',
            $source
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $source,
                '$this->needs->syncForActivation('
            )
        );

        $this->assertStringContainsString(
            'return $existing->fresh();',
            $source
        );
    }

    public function test_workspace_reads_persisted_needs_without_writing_on_get(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityWorkspaceService.php'
        );

        foreach ([
            "'needs' =>",
            '->needs()',
            'TransformationCapabilityNeed $need',
            "'status_label'",
            "'identified_at'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'TransformationCapabilityNeedService',
            'syncForActivation(',
            'create(',
            'save(',
            'update(',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_tenant_workspace_renders_backend_needs_without_hardcoded_need_keys(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'BrandingIdentity.vue'
        );

        foreach ([
            'type BrandingNeed =',
            'needs: BrandingNeed[]',
            'Áreas de evaluación',
            'Áreas de Branding a revisar',
            'props.branding.needs.length',
            'v-for="need in props.branding.needs"',
            '{{ need.title }}',
            '{{ need.status_label }}',
            'Las áreas se revisan de forma independiente',
            'contexto relevante en el Plan consultivo vigente',
            'se utiliza únicamente como referencia para la',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'positioning_refinement',
            'visual_identity_update',
            'brand_kit',
            'social_normalization',
            'commercial_documents',
            'web_application',
            'progress_percentage',
            'DOP ',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_activation_model_exposes_generic_needs_relation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Models/'
            .'TransformationCapabilityActivation.php'
        );

        foreach ([
            'function needs(): HasMany',
            'TransformationCapabilityNeed::class',
            "'transformation_capability_activation_id'",
            "->orderBy('sequence')",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
