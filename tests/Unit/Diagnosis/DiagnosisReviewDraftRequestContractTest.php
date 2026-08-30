<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Requests\Diagnosis\SaveDiagnosisReviewRequest;
use PHPUnit\Framework\TestCase;

class DiagnosisReviewDraftRequestContractTest extends TestCase
{
    public function test_review_draft_contains_only_consultive_fields(): void
    {
        $rules = (new SaveDiagnosisReviewRequest())->rules();

        $this->assertArrayHasKey('review_summary', $rules);
        $this->assertArrayHasKey('review_priorities', $rules);
        $this->assertArrayHasKey('review_priorities.*', $rules);

        $this->assertContains('nullable', $rules['review_summary']);
        $this->assertContains('nullable', $rules['review_priorities']);

        $this->assertArrayNotHasKey('final_modality', $rules);
    }
}
