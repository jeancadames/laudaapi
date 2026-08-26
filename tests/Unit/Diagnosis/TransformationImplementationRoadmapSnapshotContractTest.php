<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationRoadmapSnapshotContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    private function read(string $path): string
    {
        return file_get_contents($this->project($path));
    }

    public function test_plan_copies_published_roadmap_into_immutable_source_snapshot(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );

        $this->assertStringContainsString(
            "'published_roadmap_source' =>",
            $service
        );

        $this->assertStringContainsString(
            '$roadmap->source_snapshot ?? []',
            $service
        );

        $this->assertStringContainsString(
            "'published_roadmap' =>",
            $service
        );

        $this->assertStringContainsString(
            '$roadmap->roadmap ?? []',
            $service
        );

        $this->assertStringContainsString(
            "'roadmap_methodology_version' =>",
            $service
        );
    }

    public function test_plan_supports_internal_snapshot_without_published_roadmap(): void
    {
        $service = $this->read('app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $this->assertStringContainsString('createDraftFromAssessment', $service);
        $this->assertStringContainsString("'internal_roadmap' =>", $service);
        $this->assertStringContainsString("'internal_assessment'", $service);
        $this->assertStringContainsString('DiagnosisDetailedRoadmap::STATUS_PUBLISHED', $service);
    }

    public function test_r2c_validates_capability_against_plan_snapshot(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPhaseService.php'
        );

        $this->assertStringContainsString(
            '$snapshot = $this->normalizeSnapshot($plan->source_snapshot)',
            $service
        );

        $this->assertStringContainsString(
            '!$this->snapshotContainsToken($snapshot, $key)',
            $service
        );
    }

    public function test_published_roadmap_contains_transformation_capability_keys(): void
    {
        $generator = $this->read(
            'app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        $this->assertStringContainsString(
            "'transformation_capabilities' =>",
            $generator
        );

        $this->assertStringContainsString(
            "'procedures_guide' =>",
            $generator
        );

        $this->assertStringContainsString(
            "'branding_identity' =>",
            $generator
        );
    }

    public function test_snapshot_copy_does_not_start_subscription(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );

        foreach ([
            'Subscription::query()->create',
            'SubscriptionItem::query()->create',
            'Subscriber::query()->create',
            'Company::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
