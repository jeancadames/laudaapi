<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisSingleActiveAssessmentContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.ltrim($file, '/')
        ) ?: '';
    }

    public function test_schema_separates_business_status_from_active_lifecycle(): void
    {
        $migration = $this->source(
            'database/migrations/'
            .'2026_08_30_213000_add_active_lifecycle_to_diagnosis_assessments_table.php'
        );

        foreach ([
            "boolean('is_active')",
            "default(true)",
            "timestamp('inactivated_at')",
            "unsignedBigInteger('superseded_by_assessment_id')",
            "'daa_org_active_idx'",
            "'daa_superseded_fk'",
            "nullOnDelete()",
        ] as $token) {
            $this->assertStringContainsString($token, $migration);
        }

        $model = $this->source('app/Models/DiagnosisAssessment.php');

        foreach ([
            "'is_active'",
            "'inactivated_at'",
            "'superseded_by_assessment_id'",
            "'is_active' => 'boolean'",
            'public function supersededBy(): BelongsTo',
            'return (bool) $this->is_active',
        ] as $token) {
            $this->assertStringContainsString($token, $model);
        }
    }

    public function test_explicit_request_can_create_one_pending_reassessment(): void
    {
        $service = $this->source(
            'app/Services/Diagnosis/InitialDiagnosisCommercialService.php'
        );

        foreach ([
            'public function ensure(User $user): DiagnosisAccessRequest',
            'return $this->ensureForUser($user, false);',
            'public function request(User $user): DiagnosisAccessRequest',
            'return $this->ensureForUser($user, true);',
            'pendingNativeWorkflowForCompany',
            'activeNativeWorkflowForCompany',
            "'reassessment' => \$isReassessment",
            "'supersedes_assessment_id' => \$supersedesAssessmentId",
            "'diagnosis_reassessment_apphub_requested'",
            "->where('meta->company_id', \$company->id)",
            "fn (\$query) => \$query->where('is_active', true)",
            "'can_request_new' =>",
            "'reassessment_pending' =>",
        ] as $token) {
            $this->assertStringContainsString($token, $service);
        }

        foreach ([
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
        ] as $token) {
            $this->assertStringNotContainsString($token, $service);
        }
    }

    public function test_confirmation_atomically_supersedes_previous_assessment(): void
    {
        $service = $this->source(
            'app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            "'is_active' => ! \$isAppHubNative",
            'activateAppHubAssessment(',
            "->where('meta->company_id', \$company->id)",
            "'supersedes_assessment_id'",
            "->where('is_active', true)",
            "'is_active' => false",
            "'inactivated_at' => now()",
            "'superseded_by_assessment_id' => \$assessment->id",
            "'is_active' => true",
            "'diagnosis_assessment_superseded'",
        ] as $token) {
            $this->assertStringContainsString($token, $service);
        }
    }

    public function test_tenant_cannot_view_or_edit_inactive_assessment(): void
    {
        $policy = $this->source(
            'app/Policies/DiagnosisAssessmentPolicy.php'
        );

        foreach ([
            'if ($user->isAdmin())',
            'if (! (bool) $assessment->is_active)',
            'return false;',
            '$assessment->organization_id',
            "->where('active', true)",
            "->wherePivotIn('role', ['owner', 'admin'])",
            '&& $this->view($user, $assessment)',
        ] as $token) {
            $this->assertStringContainsString($token, $policy);
        }
    }

    public function test_app_hub_exposes_only_active_assessment_and_explicit_new_request(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/AppHubDiagnosisController.php'
        );

        $this->assertStringContainsString(
            '$workflow = $commercial->request($user);',
            $controller
        );
        $this->assertStringContainsString(
            "fn (\$query) => \$query->where('is_active', true)",
            $controller
        );

        $component = $this->source(
            'resources/js/pages/App/Diagnosis360.vue'
        );

        foreach ([
            'can_request_new: boolean;',
            'reassessment_pending: boolean;',
            'is_active: boolean;',
            'props.state.assessment.is_active',
            'Solicitar nueva evaluación',
            'Nueva evaluación pendiente',
            'Tu evaluación actual continúa activa',
        ] as $token) {
            $this->assertStringContainsString($token, $component);
        }

        foreach ([
            'Diagnóstico histórico preservado',
            "return 'Acceso histórico'",
        ] as $token) {
            $this->assertStringNotContainsString($token, $component);
        }
    }

    public function test_resume_and_old_invitation_ignore_inactive_assessments(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/Diagnosis/DiagnosisInvitationController.php'
        );

        foreach ([
            "->where('is_active', true)",
            "'Esta evaluación ya no está activa.'",
            "->whereIn('organization_id', \$companyIds)",
            "->wherePivotIn('role', ['owner', 'admin'])",
        ] as $token) {
            $this->assertStringContainsString($token, $controller);
        }
    }

    public function test_reassessment_does_not_cancel_branding_or_other_capabilities(): void
    {
        foreach ([
            'app/Services/Diagnosis/InitialDiagnosisCommercialService.php',
            'app/Services/Diagnosis/DiagnosisAccessService.php',
        ] as $file) {
            $source = $this->source($file);

            foreach ([
                'TransformationCapabilityActivation::STATUS_CANCELLED',
                "'status' => 'cancelled'",
                'cancelled_at',
            ] as $token) {
                $this->assertStringNotContainsString($token, $source);
            }
        }
    }
}
