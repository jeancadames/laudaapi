<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionRevisionFunctionalEditContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_review_endpoint_accepts_existing_functional_blocks(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $start =
            strpos(
                $controller,
                'public function review('
            );

        $end =
            strpos(
                $controller,
                'public function submitForTenantReview(',
                $start
            );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $review =
            substr(
                $controller,
                $start,
                $end - $start
            );

        foreach ([
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'readiness'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $review
            );
        }
    }

    public function test_request_scoped_review_only_allows_latest_version(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'assertLatestDefinition(',
            'transformation_implementation_request_id',
            "orderByDesc(\n                    'version'",
            "orderByDesc(\n                    'id'",
            'Solo la versión más reciente',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_request_scope_identity_is_server_locked(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'normalizeReviewData(',
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
            "'scope_mode'",
            "'capability_key'",
            "'definition_scope_locked_to_request'",
            'true',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_admin_read_model_exposes_exact_existing_structures(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            "'definition_review'",
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'readiness'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_revision_reason_remains_visible_while_editing_v2(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$revisionProvenance',
            "'revision'",
            "'revision_of_definition_id'",
            "'revision_of_definition_version'",
            "'tenant_change_reason'",
            "'current_definition_version'",
            'STATUS_DEFINITION_PREPARATION',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_ui_extends_existing_human_review_form(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        foreach ([
            'humanReviewForm',
            'implementation_scope',
            'deliverables',
            'dependencies',
            'functionalStringList',
            'functionalRecordText',
            'Contenido funcional',
            'Alcance funcional',
            'Guardar revisión humana',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }

        foreach ([
            'functionalScopeJson',
            'functionalDeliverablesJson',
            'functionalDependenciesJson',
            'parseFunctionalEditors',
        ] as $legacyEditor) {
            $this->assertStringNotContainsString(
                $legacyEditor,
                $ui
            );
        }
    }

    public function test_ui_does_not_introduce_a_second_backend_schema(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        /*
         * The readable Data BI UI continues to submit the existing
         * implementation_scope / deliverables / dependencies contract.
         * It does not create parallel JSON editor state.
         */
        foreach ([
            'humanReviewForm',
            'implementation_scope',
            'deliverables',
            'dependencies',
        ] as $existingContract) {
            $this->assertStringContainsString(
                $existingContract,
                $ui
            );
        }

        foreach ([
            'functionalScopeJson',
            'functionalDeliverablesJson',
            'functionalDependenciesJson',
            'parseFunctionalEditors',
        ] as $parallelEditorState) {
            $this->assertStringNotContainsString(
                $parallelEditorState,
                $ui
            );
        }
    }

    public function test_d3_has_no_revision_or_downstream_action(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        foreach ([
            'createRevision(',
            '->markReady(',
            'STATUS_DEFINITION_AGREED',
            'STATUS_READY_FOR_COMMERCIAL',
            'TransformationImplementationExecutionService',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'Subscription::create',
            'Invoice::create',
            'Payment::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }
}
