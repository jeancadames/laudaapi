<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAccessRequest;
use PHPUnit\Framework\TestCase;

class DiagnosisAccessRequestContractTest extends TestCase
{
    public function test_access_request_exposes_expected_states(): void
    {
        $this->assertSame([
            'pending',
            'under_review',
            'more_info_required',
            'approved',
            'invited',
            'active',
            'rejected',
        ], DiagnosisAccessRequest::STATUSES);
    }

    public function test_route_key_uses_public_ulid(): void
    {
        $model = new DiagnosisAccessRequest();

        $this->assertSame('public_id', $model->getRouteKeyName());
    }
}
