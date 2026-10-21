<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * H1-A: evaluación PURA de una selección humana ya realizada.
 *
 * No carga relaciones ni consulta la BD, no registra handoffs, no genera
 * candidatos y no habilita automáticamente ninguna fase posterior.
 * El futuro endpoint H1-B debe resolver recursos mediante queries scoped
 * server-owned, comprobar concurrencia e idempotencia en una transacción.
 */
final class DataTransformationBiImplementationHandoffEligibilityPolicy
{
    /**
     * @return array{eligible:bool,reasons:list<string>,origin:array<string,int>|null}
     */
    public function assessSelectedChallenge(
        User $actor,
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge
    ): array {
        $reasons = [];

        if (
            ! $this->persisted($actor)
            || (string) $actor->role !== 'admin'
        ) {
            $reasons[] = 'lauda_admin_required';
        }

        if (
            ! $this->persisted($request)
            || (string) $request->capability_key !== 'data_transformation_bi'
            || (int) $request->company_id <= 0
        ) {
            $reasons[] = 'invalid_request';
        }

        if (
            ! $this->persisted($session)
            || (int) $session->company_id !== (int) $request->company_id
            || (int) $session->transformation_implementation_request_id
                !== (int) $request->getKey()
            || (string) $session->status !== 'submitted_for_evaluation'
        ) {
            $reasons[] = 'session_out_of_scope';
        }

        if (
            ! $this->persisted($evaluation)
            || (int) $evaluation->company_id !== (int) $request->company_id
            || (int) $evaluation->transformation_implementation_request_id
                !== (int) $request->getKey()
            || (int) $evaluation->data_transformation_bi_intake_session_id
                !== (int) $session->getKey()
        ) {
            $reasons[] = 'evaluation_out_of_scope';
        }

        if (
            ! $evaluation->isPublished()
            || $evaluation->published_at === null
            || (int) $evaluation->evidence_version <= 0
        ) {
            $reasons[] = 'evaluation_not_published';
        }

        if (
            ! $this->persisted($challenge)
            || (int) $challenge->company_id !== (int) $request->company_id
            || (int) $challenge->data_transformation_bi_evaluation_id
                !== (int) $evaluation->getKey()
        ) {
            $reasons[] = 'challenge_out_of_scope';
        }

        if (
            (int) $challenge->evidence_version <= 0
            || (int) $challenge->evidence_version
                !== (int) $evaluation->evidence_version
        ) {
            $reasons[] = 'challenge_evidence_stale';
        }

        // Nunca lazy-load: las relaciones deben obtenerse scoped en el servidor.
        if (! $challenge->relationLoaded('findings')) {
            $reasons[] = 'linked_findings_not_loaded';
        } else {
            $findings = $challenge->getRelation('findings');
            if (! $findings instanceof Collection) {
                $reasons[] = 'invalid_linked_findings';
            } else {
                foreach ($findings as $finding) {
                    if (
                        ! $finding instanceof DataTransformationBiEvaluationFinding
                        || ! $this->persisted($finding)
                        || (int) $finding->data_transformation_bi_evaluation_id
                            !== (int) $evaluation->getKey()
                        || (int) $finding->evidence_version <= 0
                        || (int) $finding->evidence_version
                            !== (int) $evaluation->evidence_version
                    ) {
                        $reasons[] = 'linked_finding_out_of_scope_or_stale';
                        break;
                    }
                }
            }
        }

        if ($reasons !== []) {
            return [
                'eligible' => false,
                'reasons' => $reasons,
                'origin' => null,
            ];
        }

        // Trazabilidad disponible para futura validación H1-B, no persistida.
        return [
            'eligible' => true,
            'reasons' => [],
            'origin' => [
                'company_id' => (int) $request->company_id,
                'request_id' => (int) $request->getKey(),
                'session_id' => (int) $session->getKey(),
                'evaluation_id' => (int) $evaluation->getKey(),
                'evidence_version' => (int) $evaluation->evidence_version,
                'challenge_id' => (int) $challenge->getKey(),
                'actor_id' => (int) $actor->getKey(),
            ],
        ];
    }

    private function persisted(Model $model): bool
    {
        return $model->exists && (int) $model->getKey() > 0;
    }
}
