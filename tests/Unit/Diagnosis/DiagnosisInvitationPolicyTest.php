<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAccessRequest;
use App\Services\Diagnosis\DiagnosisAccessService;
use Tests\TestCase;

class DiagnosisInvitationPolicyTest extends TestCase
{
    public function test_default_invitation_ttl_is_72_hours(): void
    {
        config()->set(
            'lauda360_access.invitation_ttl_hours',
            72
        );

        $this->assertSame(
            72,
            app(DiagnosisAccessService::class)
                ->invitationTtlHours()
        );
    }

    public function test_resending_active_access_does_not_downgrade_it(): void
    {
        $workflow = new DiagnosisAccessRequest([
            'status' => DiagnosisAccessRequest::STATUS_ACTIVE,
        ]);

        $this->assertSame(
            DiagnosisAccessRequest::STATUS_ACTIVE,
            app(DiagnosisAccessService::class)
                ->invitationStatusAfterSend($workflow)
        );
    }

    public function test_previously_accepted_access_stays_active(): void
    {
        $workflow = new DiagnosisAccessRequest([
            'status' => DiagnosisAccessRequest::STATUS_INVITED,
            'invitation_accepted_at' => now(),
        ]);

        $this->assertSame(
            DiagnosisAccessRequest::STATUS_ACTIVE,
            app(DiagnosisAccessService::class)
                ->invitationStatusAfterSend($workflow)
        );
    }

    public function test_unaccepted_approved_access_becomes_invited(): void
    {
        $workflow = new DiagnosisAccessRequest([
            'status' => DiagnosisAccessRequest::STATUS_APPROVED,
        ]);

        $this->assertSame(
            DiagnosisAccessRequest::STATUS_INVITED,
            app(DiagnosisAccessService::class)
                ->invitationStatusAfterSend($workflow)
        );
    }
}
