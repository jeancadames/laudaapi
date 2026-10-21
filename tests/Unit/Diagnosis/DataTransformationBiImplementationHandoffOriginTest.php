<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiImplementationCandidate;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataTransformationBiImplementationHandoffOrigin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Tests\TestCase;

/** QA antes de migración: modelos en memoria, cero consultas SQL. */
final class DataTransformationBiImplementationHandoffOriginTest extends TestCase
{
    private function model(string $class, array $data): Model
    {
        $model = new $class();
        $model->forceFill($data);
        $model->exists = true;
        return $model;
    }

    private function context(): array
    {
        $request = $this->model(TransformationImplementationRequest::class, [
            'id' => 8, 'company_id' => 12, 'capability_key' => 'data_transformation_bi',
        ]);
        $session = $this->model(DataTransformationBiIntakeSession::class, ['id' => 9]);
        $evaluation = $this->model(DataTransformationBiEvaluation::class, [
            'id' => 10, 'evidence_version' => 3,
        ]);
        $challenge = $this->model(DataTransformationBiEvaluationImplementationChallenge::class, [
            'id' => 11, 'title' => 'Criterio de negocio', 'details' => 'Alcance técnico',
            'recommended_response' => 'Revisar después', 'priority' => 'medium',
        ]);
        $challenge->setRelation('findings', new Collection());
        return [$request, $session, $evaluation, $challenge];
    }

    public function test_snapshot_contains_only_planning_origin_and_no_actor_or_price(): void
    {
        $origin = new DataTransformationBiImplementationHandoffOrigin();
        $snapshot = $origin->snapshot(...$this->context());
        self::assertSame('planning_review_only', $snapshot['intent']);
        self::assertSame(1, $snapshot['schema_version']);
        self::assertSame(12, $snapshot['company_id']);
        self::assertSame(11, $snapshot['challenge']['id']);
        self::assertSame([], $snapshot['linked_findings']);
        self::assertArrayNotHasKey('actor_id', $snapshot);
        self::assertArrayNotHasKey('selected_at', $snapshot);
        self::assertArrayNotHasKey('price', $snapshot);
        self::assertArrayNotHasKey('plan_id', $snapshot);
    }

    public function test_finding_order_does_not_change_hash(): void
    {
        [$req, $session, $eval, $challenge] = $this->context();
        $a = $this->model(DataTransformationBiEvaluationFinding::class, ['id'=>14,'company_id'=>12,'evidence_version'=>3]);
        $b = $this->model(DataTransformationBiEvaluationFinding::class, ['id'=>13,'company_id'=>12,'evidence_version'=>3]);
        $origin = new DataTransformationBiImplementationHandoffOrigin();
        $challenge->setRelation('findings', new Collection([$a, $b]));
        $first = $origin->snapshot($req, $session, $eval, $challenge);
        $challenge->setRelation('findings', new Collection([$b, $a]));
        $second = $origin->snapshot($req, $session, $eval, $challenge);
        self::assertSame([13, 14], array_column($first['linked_findings'], 'id'));
        self::assertSame($origin->hash($first), $origin->hash($second));
    }

    public function test_linked_finding_from_another_company_is_rejected(): void
    {
        [$request, $session, $evaluation, $challenge] = $this->context();
        $finding = $this->model(DataTransformationBiEvaluationFinding::class, [
            'id' => 15, 'company_id' => 99, 'evidence_version' => 3,
        ]);
        $challenge->setRelation('findings', new Collection([$finding]));
        $this->expectException(LogicException::class);
        (new DataTransformationBiImplementationHandoffOrigin())->snapshot(
            $request, $session, $evaluation, $challenge
        );
    }

    public function test_existing_candidate_requires_its_snapshot_to_match_stored_hash(): void
    {
        $origin = new DataTransformationBiImplementationHandoffOrigin();
        $snapshot = $origin->snapshot(...$this->context());
        $expectedHash = $origin->hash($snapshot);
        $candidate = new DataTransformationBiImplementationCandidate();
        $candidate->forceFill([
            'origin_snapshot' => $snapshot,
            'origin_sha256' => $expectedHash,
        ]);
        self::assertTrue($origin->matchesStored($candidate, $expectedHash));
        $candidate->origin_snapshot = ['altered' => true];
        self::assertFalse($origin->matchesStored($candidate, $expectedHash));
        $candidate->origin_snapshot = $snapshot;
        self::assertFalse($origin->matchesStored($candidate, str_repeat('0', 64)));
    }

    public function test_json_roundtrip_keeps_hash_stable(): void
    {
        $origin = new DataTransformationBiImplementationHandoffOrigin();
        $snapshot = $origin->snapshot(...$this->context());
        $roundtrip = json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($origin->hash($snapshot), $origin->hash($roundtrip));
        self::assertSame(64, strlen($origin->hash($snapshot)));
    }

    public function test_key_order_does_not_change_hash(): void
    {
        $origin = new DataTransformationBiImplementationHandoffOrigin();
        $a = ['z'=>['b'=>2,'a'=>1], 'a'=>3];
        $b = ['a'=>3, 'z'=>['a'=>1,'b'=>2]];
        self::assertSame($origin->hash($a), $origin->hash($b));
    }

    public function test_unloaded_findings_are_rejected_without_query(): void
    {
        $context = $this->context();
        $context[3]->unsetRelation('findings');
        $this->expectException(LogicException::class);
        (new DataTransformationBiImplementationHandoffOrigin())->snapshot(...$context);
    }

    public function test_migration_is_registered_for_future_laravel_deploy(): void
    {
        $path = base_path('database/migrations/2026_10_21_220000_create_data_transformation_bi_implementation_candidates.php');
        self::assertFileExists($path);
        self::assertFileDoesNotExist(base_path('database/h1_pending_migrations/'.basename($path)));
        $source = file_get_contents($path);
        self::assertStringContainsString("'origin_challenge_id', 'dtbi_h1_candidate_challenge_uq'", $source);
        self::assertStringContainsString('->nullOnDelete()', $source);
        self::assertStringContainsString('->cascadeOnDelete()', $source);
    }

    public function test_service_guards_concurrent_insert_with_unique_index_and_transaction(): void
    {
        $text = file_get_contents(base_path('app/Services/Diagnosis/DataTransformationBiImplementationCandidateService.php'));
        self::assertStringContainsString('DB::transaction(', $text);
        self::assertStringContainsString('->lockForUpdate()', $text);
        self::assertStringContainsString('assessSelectedChallenge(', $text);
        self::assertStringContainsString('dtbi_h1_candidate_challenge_uq', $text);
        self::assertStringContainsString('isUniqueChallengeConflict(', $text);
        self::assertStringNotContainsString('TransformationImplementationPlanService', $text);
        self::assertStringNotContainsString('CommercialEngagementService', $text);
    }
}
