<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiImplementationHandoffEligibilityPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

final class DataTransformationBiImplementationHandoffEligibilityPolicyTest extends TestCase
{
    /** @return array<string,Model> */
    private function context(): array
    {
        $actor = $this->model(User::class, [
            'id' => 1, 'role' => 'admin',
        ]);
        $request = $this->model(TransformationImplementationRequest::class, [
            'id' => 20, 'company_id' => 19,
            'capability_key' => 'data_transformation_bi',
            'status' => 'definition_agreed',
        ]);
        $session = $this->model(DataTransformationBiIntakeSession::class, [
            'id' => 30, 'company_id' => 19,
            'transformation_implementation_request_id' => 20,
            'status' => 'submitted_for_evaluation',
        ]);
        $evaluation = $this->model(DataTransformationBiEvaluation::class, [
            'id' => 40, 'company_id' => 19,
            'transformation_implementation_request_id' => 20,
            'data_transformation_bi_intake_session_id' => 30,
            'status' => 'published',
            'published_at' => '2026-10-21 10:00:00',
            'evidence_version' => 2,
        ]);
        $challenge = $this->model(DataTransformationBiEvaluationImplementationChallenge::class, [
            'id' => 50, 'company_id' => 19,
            'data_transformation_bi_evaluation_id' => 40,
            'evidence_version' => 2,
        ]);
        $challenge->setRelation('findings', new Collection());
        return compact('actor', 'request', 'session', 'evaluation', 'challenge');
    }

    /** @param class-string<Model> $class */
    private function model(string $class, array $attributes): Model
    {
        $model = new $class();
        $model->forceFill($attributes);
        $model->exists = true;
        return $model;
    }

    private function assess(array $c): array
    {
        return (new DataTransformationBiImplementationHandoffEligibilityPolicy())
            ->assessSelectedChallenge(
                $c['actor'], $c['request'], $c['session'],
                $c['evaluation'], $c['challenge']
            );
    }

    private function reject(array $c, string $reason): void
    {
        $result = $this->assess($c);
        self::assertFalse($result['eligible']);
        self::assertContains($reason, $result['reasons']);
        self::assertNull($result['origin']);
    }

    public function test_explicit_current_challenge_without_findings_is_eligible_but_not_transferred(): void
    {
        $result = $this->assess($this->context());
        self::assertTrue($result['eligible']);
        self::assertSame([], $result['reasons']);
        self::assertSame(40, $result['origin']['evaluation_id']);
        self::assertSame(50, $result['origin']['challenge_id']);
        self::assertSame(2, $result['origin']['evidence_version']);
    }

    public function test_current_linked_finding_is_eligible(): void
    {
        $c = $this->context();
        $f = $this->model(DataTransformationBiEvaluationFinding::class, [
            'id' => 60, 'data_transformation_bi_evaluation_id' => 40,
            'evidence_version' => 2,
        ]);
        $c['challenge']->setRelation('findings', new Collection([$f]));
        self::assertTrue($this->assess($c)['eligible']);
    }

    public function test_tenant_cannot_select_challenge(): void
    {
        $c = $this->context();
        $c['actor']->role = 'tenant_admin';
        $this->reject($c, 'lauda_admin_required');
    }

    public function test_unpublished_evaluation_is_ineligible(): void
    {
        $c = $this->context();
        $c['evaluation']->status = 'draft';
        $this->reject($c, 'evaluation_not_published');
    }

    public function test_missing_published_at_is_ineligible(): void
    {
        $c = $this->context();
        $c['evaluation']->published_at = null;
        $this->reject($c, 'evaluation_not_published');
    }

    public function test_session_from_another_request_is_ineligible(): void
    {
        $c = $this->context();
        $c['session']->transformation_implementation_request_id = 21;
        $this->reject($c, 'session_out_of_scope');
    }

    public function test_evaluation_from_another_company_is_ineligible(): void
    {
        $c = $this->context();
        $c['evaluation']->company_id = 21;
        $this->reject($c, 'evaluation_out_of_scope');
    }

    public function test_evaluation_from_another_session_is_ineligible(): void
    {
        $c = $this->context();
        $c['evaluation']->data_transformation_bi_intake_session_id = 31;
        $this->reject($c, 'evaluation_out_of_scope');
    }

    public function test_challenge_from_another_evaluation_is_ineligible(): void
    {
        $c = $this->context();
        $c['challenge']->data_transformation_bi_evaluation_id = 41;
        $this->reject($c, 'challenge_out_of_scope');
    }

    public function test_challenge_from_another_company_is_ineligible(): void
    {
        $c = $this->context();
        $c['challenge']->company_id = 21;
        $this->reject($c, 'challenge_out_of_scope');
    }

    public function test_stale_challenge_evidence_is_ineligible(): void
    {
        $c = $this->context();
        $c['challenge']->evidence_version = 1;
        $this->reject($c, 'challenge_evidence_stale');
    }

    public function test_stale_linked_finding_is_ineligible(): void
    {
        $c = $this->context();
        $f = $this->model(DataTransformationBiEvaluationFinding::class, [
            'id' => 60, 'data_transformation_bi_evaluation_id' => 40,
            'evidence_version' => 1,
        ]);
        $c['challenge']->setRelation('findings', new Collection([$f]));
        $this->reject($c, 'linked_finding_out_of_scope_or_stale');
    }

    public function test_linked_finding_from_another_evaluation_is_ineligible(): void
    {
        $c = $this->context();
        $f = $this->model(DataTransformationBiEvaluationFinding::class, [
            'id' => 60, 'data_transformation_bi_evaluation_id' => 41,
            'evidence_version' => 2,
        ]);
        $c['challenge']->setRelation('findings', new Collection([$f]));
        $this->reject($c, 'linked_finding_out_of_scope_or_stale');
    }

    public function test_findings_must_be_explicitly_loaded(): void
    {
        $c = $this->context();
        $c['challenge']->unsetRelation('findings');
        $this->reject($c, 'linked_findings_not_loaded');
    }

    public function test_request_must_be_data_bi(): void
    {
        $c = $this->context();
        $c['request']->capability_key = 'branding_identity';
        $this->reject($c, 'invalid_request');
    }

    public function test_unsaved_challenge_is_ineligible(): void
    {
        $c = $this->context();
        $c['challenge']->exists = false;
        $this->reject($c, 'challenge_out_of_scope');
    }
}
