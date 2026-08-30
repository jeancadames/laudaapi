<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisAssessmentCompanyLinkContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_apphub_native_assessment_uses_validated_company_context(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            'use App\Models\Company;',
            '$isAppHubNative',
            '$appHubCompany = null;',
            "'company_id'",
            "'subscriber_id'",
            'Company::query()',
            "->whereKey(\$companyId)",
            "'subscriber_id',",
            '$subscriberId',
            "->where('active', true)",
            "'organization_id' =>",
            '$appHubCompany?->id',
            "'organization_name' =>",
            '$appHubCompany?->name',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_apphub_company_is_server_validated_before_assessment_creation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            '$companyId <= 0 || $subscriberId <= 0',
            'La solicitud App Hub no contiene un contexto de empresa válido.',
            'La empresa de la solicitud App Hub no pertenece al tenant indicado o no está activa.',
            "'company_id',",
            '$appHubCompany->id',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'Company::find($companyId)',
            $source
        );
    }

    public function test_complimentary_invoice_must_belong_to_validated_company(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            'Invoice::query()',
            '->whereKey($invoiceId)',
            "'company_id',",
            '$appHubCompany->id',
            "$invoice->status !== 'issued'",
            'round((float) $invoice->total, 2) !== 0.0',
            "'complimentary'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_legacy_flow_preserves_nullable_organization_contract(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            '$appHubCompany = null;',
            '$appHubCompany?->id',
            '$appHubCompany?->name',
            '$contact->company',
            "'Empresa por definir'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'organization_id' => \$companyId",
            $source
        );
    }

    public function test_fix_does_not_backfill_existing_assessments(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            'updateOrCreate(',
            "->update(['organization_id'",
            "forceFill(['organization_id'",
            'DB::table(\'diagnosis_assessments\')->update',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_access_audit_keeps_company_lineage(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            "'diagnosis_access_approved'",
            "'organization_id' => \$assessment->organization_id",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
