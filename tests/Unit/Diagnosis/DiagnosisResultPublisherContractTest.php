<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisResultPublisherContractTest extends TestCase
{
    public function test_diagnosis_publication_is_not_gated_by_commercial_modality(): void
    {
        $publisher = file_get_contents(
            base_path('app/Services/Diagnosis/DiagnosisResultPublisher.php')
        );

        $request = file_get_contents(
            base_path('app/Http/Requests/Diagnosis/PublishDiagnosisResultRequest.php')
        );

        $this->assertStringNotContainsString(
            'MODALITY_LABELS',
            $publisher
        );

        $this->assertStringNotContainsString(
            'labelForModality',
            $publisher
        );

        $this->assertStringNotContainsString(
            "['final_modality']",
            $publisher
        );

        $this->assertStringNotContainsString(
            "'final_modality' =>",
            $request
        );

        $this->assertStringContainsString(
            '$this->deliverables->generateAndPresent(',
            $publisher
        );

        $this->assertStringContainsString(
            "'commercial_modality_required' => false",
            $publisher
        );
    }
}
