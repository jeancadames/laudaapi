<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAccessRequest;
use App\Services\Diagnosis\DiagnosisRequestEmailGuard;
use PHPUnit\Framework\TestCase;

class DiagnosisRequestEmailGuardContractTest extends TestCase
{
    private DiagnosisRequestEmailGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new DiagnosisRequestEmailGuard();
    }

    public function test_pending_workflow_is_blocked(): void
    {
        $this->assertStringContainsString(
            'Ya existe una solicitud',
            $this->guard->messageForStatus(
                DiagnosisAccessRequest::STATUS_PENDING
            )
        );
    }

    public function test_under_review_workflow_is_blocked(): void
    {
        $this->assertStringContainsString(
            'Ya existe una solicitud',
            $this->guard->messageForStatus(
                DiagnosisAccessRequest::STATUS_UNDER_REVIEW
            )
        );
    }

    public function test_invited_workflow_points_user_to_email(): void
    {
        $this->assertStringContainsString(
            'Revise su correo',
            $this->guard->messageForStatus(
                DiagnosisAccessRequest::STATUS_INVITED
            )
        );
    }

    public function test_active_workflow_points_user_to_existing_access(): void
    {
        $this->assertStringContainsString(
            'ya tiene acceso',
            $this->guard->messageForStatus(
                DiagnosisAccessRequest::STATUS_ACTIVE
            )
        );
    }
}
