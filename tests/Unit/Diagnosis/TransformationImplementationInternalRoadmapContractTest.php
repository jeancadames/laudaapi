<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationInternalRoadmapContractTest extends TestCase
{
    private function root(): string { return dirname(__DIR__, 3); }
    private function read(string $path): string { return file_get_contents($this->root().'/'.$path); }

    public function test_nullable_migration_is_prepared(): void
    {
        $m=$this->read('database/migrations/2026_08_25_193000_make_transformation_implementation_plan_roadmap_nullable.php');
        $this->assertStringContainsString('MODIFY diagnosis_detailed_roadmap_id BIGINT UNSIGNED NULL',$m);
        $this->assertStringContainsString("whereNull('diagnosis_detailed_roadmap_id')",$m);
    }

    public function test_generator_supports_internal_assessment(): void
    {
        $g=$this->read('app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php');
        foreach(['?DiagnosisExpandedReport $report = null','generateFromAssessment(','$report?->sections ?? []',"'expanded_report' => \$report ? [",'TransformationServiceCapabilityCatalog::all()'] as $token) $this->assertStringContainsString($token,$g);
    }

    public function test_plan_supports_both_sources_without_commercial_side_effects(): void
    {
        $s=$this->read('app/Services/Diagnosis/TransformationImplementationPlanService.php');
        foreach(['createDraftFromPublishedRoadmap(','createDraftFromAssessment(',"'published_roadmap'","'internal_assessment'","'internal_roadmap_source' =>","'internal_roadmap' =>",'generateFromAssessment($locked)'] as $token) $this->assertStringContainsString($token,$s);
        foreach(['DiagnosisDetailedRoadmap::query()->create','DiagnosisDetailedRoadmapOrder::','DiagnosisExpandedReportOrder::','Invoice::','Payment::','Subscription::','SubscriptionItem::'] as $token) $this->assertStringNotContainsString($token,$s);
    }

    public function test_internal_source_requires_official_result(): void
    {
        $s=$this->read('app/Services/Diagnosis/TransformationImplementationPlanService.php');
        $this->assertStringContainsString("\$assessment->status !== 'reviewed'",$s);
        $this->assertStringContainsString('$assessment->published_at === null',$s);
        $this->assertStringContainsString('requiere un resultado oficial del Diagnóstico publicado',$s);
    }

    public function test_admin_and_client_are_source_aware(): void
    {
        $a=$this->read('app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php');
        $c=$this->read('app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $ap=$this->read('resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue');
        $cp=$this->read('resources/js/pages/Diagnosis/ImplementationPlan.vue');
        $this->assertStringContainsString('? $planService->createDraftFromPublishedRoadmap(',$a);
        $this->assertStringContainsString(': $planService->createDraftFromAssessment(',$a);
        $this->assertStringNotContainsString('Debe existir un Roadmap Detallado publicado antes de crear el Plan.',$a);
        $this->assertStringContainsString('$plan->diagnosis_detailed_roadmap_id',$c);
        $this->assertStringNotContainsString("'source_snapshot' =>",$c);
        $this->assertStringNotContainsString('if (!props.roadmap) return;',$ap);
        $this->assertStringNotContainsString(':disabled="!roadmap"',$ap);
        $this->assertStringContainsString('Diagnóstico oficial · snapshot interno',$ap);
        $this->assertStringContainsString('roadmap_url: string | null;',$cp);
        $this->assertStringContainsString('v-if="roadmap_url"',$cp);
    }

    public function test_paid_gates_and_r2c_snapshot_validation_remain(): void
    {
        $s=$this->read('app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php');
        $a=$this->read('app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php');
        $c=$this->read('app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapController.php');
        $p=$this->read('app/Services/Diagnosis/TransformationImplementationPhaseService.php');
        $this->assertStringContainsString('DiagnosisExpandedReport::STATUS_PUBLISHED',$s);
        $this->assertStringContainsString('Debe existir un Informe Ampliado publicado antes de preparar el Roadmap Detallado.',$s);
        $this->assertStringContainsString('hasPaidAccess(',$a);
        $this->assertStringContainsString('hasPaidAccess(',$c);
        $this->assertStringContainsString('snapshotContainsToken',$p);
    }
}
