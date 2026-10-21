<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiImplementationCandidate;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * H1-B2: futuro servicio de selección explícita, SIN ruta pública.
 * Requiere migración autorizada, que permanece fuera de database/migrations.
 * No crea plan, propuesta, roadmap, ETL, contrato ni ejecución.
 */
final class DataTransformationBiImplementationCandidateService
{
    public function __construct(
        private readonly DataTransformationBiImplementationHandoffEligibilityPolicy $policy,
        private readonly DataTransformationBiImplementationHandoffOrigin $origin
    ) {}

    /**
     * @return array{candidate:DataTransformationBiImplementationCandidate,created:bool}
     */
    public function selectChallenge(
        User $actor,
        int $requestId,
        int $sessionId,
        int $evaluationId,
        int $challengeId
    ): array {
        if (! $actor->exists || (int) $actor->getKey() <= 0 || (string) $actor->role !== 'admin') {
            throw ValidationException::withMessages(['handoff' => ['Requiere administrador LAUDA.']]);
        }
        if (min($requestId, $sessionId, $evaluationId, $challengeId) <= 0) {
            throw ValidationException::withMessages(['handoff' => ['Identificadores inválidos.']]);
        }

        $expectedHash = null;
        $expectedCompany = null;
        try {
            return DB::transaction(function () use (
                $actor, $requestId, $sessionId, $evaluationId, $challengeId,
                &$expectedHash, &$expectedCompany
            ): array {
                // Orden fijo de bloqueo para evitar inversión entre peticiones.
                $request = TransformationImplementationRequest::query()
                    ->whereKey($requestId)
                    ->where('capability_key', 'data_transformation_bi')
                    ->lockForUpdate()->firstOrFail();

                $companyId = (int) $request->company_id;
                $session = DataTransformationBiIntakeSession::query()
                    ->whereKey($sessionId)
                    ->where('company_id', $companyId)
                    ->where('transformation_implementation_request_id', $requestId)
                    ->lockForUpdate()->firstOrFail();

                $evaluation = DataTransformationBiEvaluation::query()
                    ->whereKey($evaluationId)
                    ->where('company_id', $companyId)
                    ->where('transformation_implementation_request_id', $requestId)
                    ->where('data_transformation_bi_intake_session_id', $sessionId)
                    ->lockForUpdate()->firstOrFail();

                $challenge = DataTransformationBiEvaluationImplementationChallenge::query()
                    ->whereKey($challengeId)
                    ->where('company_id', $companyId)
                    ->where('data_transformation_bi_evaluation_id', $evaluationId)
                    ->lockForUpdate()->firstOrFail();

                // Cargar todos, no ocultar hallazgos desactualizados o cruzados.
                $challenge->load('findings');
                $eligibility = $this->policy->assessSelectedChallenge(
                    $actor, $request, $session, $evaluation, $challenge
                );
                if ($eligibility['eligible'] !== true) {
                    throw ValidationException::withMessages([
                        'handoff' => ['Selección no elegible: '.implode(', ', $eligibility['reasons'])],
                    ]);
                }

                $snapshot = $this->origin->snapshot($request, $session, $evaluation, $challenge);
                $expectedHash = $this->origin->hash($snapshot);
                $expectedCompany = $companyId;

                $existing = DataTransformationBiImplementationCandidate::query()
                    ->where('origin_challenge_id', $challengeId)
                    ->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertEquivalent($existing, $companyId, $expectedHash);
                    return ['candidate' => $existing, 'created' => false];
                }

                $candidate = DataTransformationBiImplementationCandidate::query()->create([
                    'company_id' => $companyId,
                    'origin_request_id' => $requestId,
                    'origin_session_id' => $sessionId,
                    'origin_evaluation_id' => $evaluationId,
                    'origin_challenge_id' => $challengeId,
                    'request_ref_id' => $requestId,
                    'session_ref_id' => $sessionId,
                    'evaluation_ref_id' => $evaluationId,
                    'challenge_ref_id' => $challengeId,
                    'evidence_version' => (int) $evaluation->evidence_version,
                    'selected_by_user_id' => (int) $actor->getKey(),
                    'selected_actor_id' => (int) $actor->getKey(),
                    'selected_at' => now('UTC'),
                    'origin_snapshot' => $snapshot,
                    'origin_sha256' => $expectedHash,
                ]);

                AuditService::log(
                    'data_transformation_bi_implementation_candidate_selected',
                    $candidate,
                    [
                        'origin_request_id' => $requestId,
                        'origin_evaluation_id' => $evaluationId,
                        'origin_challenge_id' => $challengeId,
                        'evidence_version' => (int) $evaluation->evidence_version,
                        'selected_actor_id' => (int) $actor->getKey(),
                    ]
                );

                return ['candidate' => $candidate, 'created' => true];
            }, 3);
        } catch (QueryException $exception) {
            // Únicamente la colisión del índice único específico permite relectura.
            if (! $this->isUniqueChallengeConflict($exception)
                || $expectedHash === null || $expectedCompany === null) {
                throw $exception;
            }
            $existing = DataTransformationBiImplementationCandidate::query()
                ->where('origin_challenge_id', $challengeId)->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertEquivalent($existing, $expectedCompany, $expectedHash);
            return ['candidate' => $existing, 'created' => false];
        }
    }

    private function assertEquivalent(
        DataTransformationBiImplementationCandidate $candidate,
        int $companyId,
        string $expectedHash
    ): void {
        if ((int) $candidate->company_id !== $companyId
            || ! $this->origin->matchesStored($candidate, $expectedHash)) {
            throw ValidationException::withMessages([
                'handoff' => ['Conflicto de integridad: candidato existente con origen diferente.'],
            ]);
        }
    }

    private function isUniqueChallengeConflict(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        return (string) ($errorInfo[0] ?? '') === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062
            && str_contains($exception->getMessage(), 'dtbi_h1_candidate_challenge_uq');
    }
}
