<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiImplementationCandidate;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

/** Manifiesto estable del origen, sin actor ni fecha: reintentos equivalentes. */
final class DataTransformationBiImplementationHandoffOrigin
{
    /** @return array<string,mixed> */
    public function snapshot(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge
    ): array {
        if (! $challenge->relationLoaded('findings')) {
            throw new LogicException('Los hallazgos deben estar cargados explícitamente.');
        }
        $findings = $challenge->getRelation('findings');
        if (! $findings instanceof EloquentCollection) {
            throw new LogicException('Colección de hallazgos no válida.');
        }
        foreach ($findings as $finding) {
            if (! $finding instanceof DataTransformationBiEvaluationFinding
                || (int) $finding->company_id !== (int) $request->company_id) {
                throw new LogicException('Hallazgo enlazado fuera de la empresa de origen.');
            }
        }
        $linked = $findings->map(static fn ($finding): array => [
            'id' => (int) $finding->getKey(),
            'evidence_version' => (int) $finding->evidence_version,
        ])->sortBy('id')->values()->all();

        return [
            'schema_version' => 1,
            'intent' => 'planning_review_only',
            'company_id' => (int) $request->company_id,
            'request_id' => (int) $request->getKey(),
            'session_id' => (int) $session->getKey(),
            'evaluation_id' => (int) $evaluation->getKey(),
            'evidence_version' => (int) $evaluation->evidence_version,
            'challenge' => [
                'id' => (int) $challenge->getKey(),
                'title' => (string) $challenge->title,
                'details' => (string) $challenge->details,
                'recommended_response' => $challenge->recommended_response === null
                    ? null : (string) $challenge->recommended_response,
                'priority' => $challenge->priority === null
                    ? null : (string) $challenge->priority,
            ],
            'linked_findings' => $linked,
        ];
    }

    /** SHA estable del JSON canónico. */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($snapshot),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    /** Compara el hash guardado y el manifiesto real, no solo el hash recibido. */
    public function matchesStored(
        DataTransformationBiImplementationCandidate $candidate,
        string $expectedHash
    ): bool {
        $stored = $candidate->origin_snapshot;
        $persistedHash = (string) $candidate->origin_sha256;
        return is_array($stored)
            && strlen($persistedHash) === 64
            && hash_equals($persistedHash, $expectedHash)
            && hash_equals($persistedHash, $this->hash($stored));
    }

    private function canonicalize(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->canonicalize($child) : $child,
                $value
            );
        }
        ksort($value, SORT_STRING);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = $this->canonicalize($child);
            }
        }
        unset($child);
        return $value;
    }
}
