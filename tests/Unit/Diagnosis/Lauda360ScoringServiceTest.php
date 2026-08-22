<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\Lauda360ScoringService;
use Tests\TestCase;

class Lauda360ScoringServiceTest extends TestCase
{
    public function test_high_capacity_and_low_urgency_can_recommend_guided(): void
    {
        $service = app(Lauda360ScoringService::class);
        $answers = array_fill_keys($service->allQuestionIds(), 5);

        foreach (config('lauda360_diagnosis.urgency_questions', []) as $id) {
            $answers[$id] = 1;
        }

        $result = $service->calculate($answers);

        $this->assertSame(100, $result['maturity_score']);
        $this->assertSame(100, $result['capacity_score']);
        $this->assertSame(0, $result['urgency_score']);
        $this->assertSame('guided', $result['recommended_modality']);
        $this->assertFalse($result['review_required']);
    }

    public function test_critical_urgency_does_not_auto_recommend_guided(): void
    {
        $service = app(Lauda360ScoringService::class);
        $answers = array_fill_keys($service->allQuestionIds(), 5);

        $result = $service->calculate($answers);

        $this->assertSame(100, $result['urgency_score']);
        $this->assertSame('assisted', $result['recommended_modality']);
        $this->assertTrue($result['review_required']);
    }

    public function test_critical_governance_answer_requires_human_review(): void
    {
        $service = app(Lauda360ScoringService::class);
        $answers = array_fill_keys($service->allQuestionIds(), 3);
        $answers['GOV-01'] = 1;

        $result = $service->calculate($answers);

        $this->assertTrue($result['review_required']);
    }
}
