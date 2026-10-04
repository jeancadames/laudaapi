<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiEvaluationImplementationChallengeService
{
    /**
     * Create one explicitly human-authored implementation challenge.
     *
     * Diagnostic analyses and findings may provide professional context,
     * but this service never derives title, details, response or priority
     * automatically.
     *
     * @param array<string,mixed> $input
     */
    public function create(
        DataTransformationBiEvaluation $evaluation,
        User $actor,
        array $input
    ): DataTransformationBiEvaluationImplementationChallenge {
        return DB::transaction(
            function () use (
                $evaluation,
                $actor,
                $input
            ): DataTransformationBiEvaluationImplementationChallenge {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $normalized =
                    $this->normalizeInput(
                        $lockedEvaluation,
                        $input
                    );

                $nextSortOrder =
                    (
                        (int)
                        DataTransformationBiEvaluationImplementationChallenge
                            ::query()
                            ->where(
                                'data_transformation_bi_evaluation_id',
                                $lockedEvaluation->getKey()
                            )
                            ->max('sort_order')
                    ) + 1;

                $challenge =
                    DataTransformationBiEvaluationImplementationChallenge
                        ::query()
                        ->create([
                            'data_transformation_bi_evaluation_id' =>
                                $lockedEvaluation->getKey(),

                            'company_id' =>
                                $lockedEvaluation->company_id,

                            'title' =>
                                $normalized['title'],

                            'details' =>
                                $normalized['details'],

                            'recommended_response' =>
                                $normalized['recommended_response'],

                            'priority' =>
                                $normalized['priority'],

                            'evidence_version' =>
                                (int)
                                $lockedEvaluation
                                    ->evidence_version,

                            'sort_order' =>
                                $nextSortOrder,

                            'created_by_user_id' =>
                                $actor->getKey(),

                            'updated_by_user_id' =>
                                $actor->getKey(),
                        ]);

                $challenge
                    ->findings()
                    ->sync(
                        $normalized['finding_ids']
                    );

                $this->audit(
                    'data_transformation_bi_implementation_challenge_created',
                    $challenge,
                    $actor,
                    $normalized['finding_ids']
                );

                return $challenge
                    ->load('findings');
            },
            3
        );
    }

    /**
     * Update an explicitly human-authored implementation challenge.
     *
     * @param array<string,mixed> $input
     */
    public function update(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge,
        User $actor,
        array $input
    ): DataTransformationBiEvaluationImplementationChallenge {
        return DB::transaction(
            function () use (
                $evaluation,
                $challenge,
                $actor,
                $input
            ): DataTransformationBiEvaluationImplementationChallenge {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedChallenge =
                    $this->lockedChallenge(
                        $lockedEvaluation,
                        $challenge
                    );

                $normalized =
                    $this->normalizeInput(
                        $lockedEvaluation,
                        $input
                    );

                $lockedChallenge
                    ->forceFill([
                        'title' =>
                            $normalized['title'],

                        'details' =>
                            $normalized['details'],

                        'recommended_response' =>
                            $normalized[
                                'recommended_response'
                            ],

                        'priority' =>
                            $normalized['priority'],

                        /*
                         * An explicit human update reconfirms this
                         * challenge against the evaluation evidence
                         * currently being reviewed.
                         */
                        'evidence_version' =>
                            (int)
                            $lockedEvaluation
                                ->evidence_version,

                        'updated_by_user_id' =>
                            $actor->getKey(),
                    ])
                    ->save();

                $lockedChallenge
                    ->findings()
                    ->sync(
                        $normalized['finding_ids']
                    );

                $this->audit(
                    'data_transformation_bi_implementation_challenge_updated',
                    $lockedChallenge,
                    $actor,
                    $normalized['finding_ids']
                );

                return $lockedChallenge
                    ->load('findings');
            },
            3
        );
    }

    /**
     * Explicitly reconfirm an unchanged human challenge against the
     * evaluation's current frozen evidence version.
     *
     * Linked findings are NOT reconfirmed automatically. If a challenge
     * references findings, those findings must already be current for the
     * same evaluation evidence version.
     */
    public function reconfirm(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge,
        User $actor
    ): DataTransformationBiEvaluationImplementationChallenge {
        return DB::transaction(
            function () use (
                $evaluation,
                $challenge,
                $actor
            ): DataTransformationBiEvaluationImplementationChallenge {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedChallenge =
                    $this->lockedChallenge(
                        $lockedEvaluation,
                        $challenge
                    );

                $findingIds =
                    $lockedChallenge
                        ->findings()
                        ->pluck(
                            'data_transformation_bi_evaluation_findings.id'
                        )
                        ->map(
                            static fn ($id): int =>
                                (int) $id
                        )
                        ->values()
                        ->all();

                $this->assertCurrentFindings(
                    $lockedEvaluation,
                    $findingIds
                );

                $lockedChallenge
                    ->forceFill([
                        'evidence_version' =>
                            (int)
                            $lockedEvaluation
                                ->evidence_version,

                        'updated_by_user_id' =>
                            $actor->getKey(),
                    ])
                    ->save();

                $this->audit(
                    'data_transformation_bi_implementation_challenge_reconfirmed',
                    $lockedChallenge,
                    $actor,
                    $findingIds
                );

                return $lockedChallenge
                    ->load('findings');
            },
            3
        );
    }

    public function delete(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge,
        User $actor
    ): void {
        DB::transaction(
            function () use (
                $evaluation,
                $challenge,
                $actor
            ): void {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedChallenge =
                    $this->lockedChallenge(
                        $lockedEvaluation,
                        $challenge
                    );

                $findingIds =
                    $lockedChallenge
                        ->findings()
                        ->pluck(
                            'data_transformation_bi_evaluation_findings.id'
                        )
                        ->map(
                            static fn ($id): int =>
                                (int) $id
                        )
                        ->values()
                        ->all();

                $this->audit(
                    'data_transformation_bi_implementation_challenge_deleted',
                    $lockedChallenge,
                    $actor,
                    $findingIds
                );

                $lockedChallenge->delete();
            },
            3
        );
    }

    private function lockedDraftEvaluation(
        DataTransformationBiEvaluation $evaluation
    ): DataTransformationBiEvaluation {
        $locked =
            DataTransformationBiEvaluation
                ::query()
                ->whereKey(
                    $evaluation->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (! $locked->isDraft()) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Los retos de implementación solo pueden gestionarse mientras la evaluación está en borrador.',
                ],
            ]);
        }

        return $locked;
    }

    private function lockedChallenge(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationImplementationChallenge $challenge
    ): DataTransformationBiEvaluationImplementationChallenge {
        return DataTransformationBiEvaluationImplementationChallenge
            ::query()
            ->whereKey(
                $challenge->getKey()
            )
            ->where(
                'data_transformation_bi_evaluation_id',
                $evaluation->getKey()
            )
            ->where(
                'company_id',
                $evaluation->company_id
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param array<string,mixed> $input
     *
     * @return array{
     *     title:string,
     *     details:string,
     *     recommended_response:?string,
     *     priority:?string,
     *     finding_ids:list<int>
     * }
     */
    private function normalizeInput(
        DataTransformationBiEvaluation $evaluation,
        array $input
    ): array {
        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        if (
            $title === ''
            || mb_strlen($title) > 191
        ) {
            throw ValidationException::withMessages([
                'title' => [
                    'El título del reto es obligatorio y no puede superar 191 caracteres.',
                ],
            ]);
        }

        $details =
            trim(
                (string) (
                    $input['details']
                    ?? ''
                )
            );

        if ($details === '') {
            throw ValidationException::withMessages([
                'details' => [
                    'La descripción profesional del reto es obligatoria.',
                ],
            ]);
        }

        $recommendedResponse =
            $this->nullableString(
                $input['recommended_response']
                ?? null
            );

        $priority =
            $this->nullableString(
                $input['priority']
                ?? null
            );

        if (
            $priority !== null
            && ! in_array(
                $priority,
                DataTransformationBiEvaluationImplementationChallenge
                    ::PRIORITIES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'priority' => [
                    'La prioridad profesional indicada no es válida.',
                ],
            ]);
        }

        $findingIds =
            $this->findingIds(
                $input['finding_ids']
                ?? []
            );

        $this->assertCurrentFindings(
            $evaluation,
            $findingIds
        );

        return [
            'title' =>
                $title,

            'details' =>
                $details,

            'recommended_response' =>
                $recommendedResponse,

            'priority' =>
                $priority,

            'finding_ids' =>
                $findingIds,
        ];
    }

    /**
     * @return list<int>
     */
    private function findingIds(
        mixed $value
    ): array {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'finding_ids' => [
                    'Los hallazgos relacionados deben enviarse como una lista.',
                ],
            ]);
        }

        $ids = [];

        foreach ($value as $candidate) {
            if (
                filter_var(
                    $candidate,
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $candidate < 1
            ) {
                throw ValidationException::withMessages([
                    'finding_ids' => [
                        'Cada hallazgo relacionado debe tener un identificador válido.',
                    ],
                ]);
            }

            $ids[] =
                (int) $candidate;
        }

        return array_values(
            array_unique(
                $ids
            )
        );
    }

    /**
     * Optional finding links are strict professional traceability only.
     *
     * Every linked finding must:
     * - belong to the same evaluation;
     * - belong to the same company;
     * - already be confirmed against the evaluation's current evidence.
     *
     * This service never reconfirms or mutates findings automatically.
     *
     * @param list<int> $findingIds
     */
    private function assertCurrentFindings(
        DataTransformationBiEvaluation $evaluation,
        array $findingIds
    ): void {
        if ($findingIds === []) {
            return;
        }

        $matchedIds =
            DataTransformationBiEvaluationFinding
                ::query()
                ->whereIn(
                    'id',
                    $findingIds
                )
                ->where(
                    'data_transformation_bi_evaluation_id',
                    $evaluation->getKey()
                )
                ->where(
                    'company_id',
                    $evaluation->company_id
                )
                ->where(
                    'evidence_version',
                    (int)
                    $evaluation->evidence_version
                )
                ->pluck('id')
                ->map(
                    static fn ($id): int =>
                        (int) $id
                )
                ->sort()
                ->values()
                ->all();

        $expectedIds =
            collect(
                $findingIds
            )
                ->sort()
                ->values()
                ->all();

        if ($matchedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'finding_ids' => [
                    'Todos los hallazgos relacionados deben pertenecer a esta evaluación y estar reconfirmados contra su evidencia actual.',
                ],
            ]);
        }
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalized =
            trim(
                (string) $value
            );

        return $normalized !== ''
            ? $normalized
            : null;
    }

    /**
     * @param list<int> $findingIds
     */
    private function audit(
        string $event,
        DataTransformationBiEvaluationImplementationChallenge $challenge,
        User $actor,
        array $findingIds
    ): void {
        AuditService::log(
            $event,
            $challenge,
            [
                'data_transformation_bi_evaluation_id' =>
                    (int)
                    $challenge
                        ->data_transformation_bi_evaluation_id,

                'company_id' =>
                    (int)
                    $challenge->company_id,

                'priority' =>
                    $challenge->priority,

                'evidence_version' =>
                    (int)
                    $challenge->evidence_version,

                'finding_ids' =>
                    array_values(
                        $findingIds
                    ),

                'actor_user_id' =>
                    (int)
                    $actor->getKey(),
            ]
        );
    }
}
