<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiEvaluationFindingService
{
    public function create(
        DataTransformationBiEvaluation $evaluation,
        User $actor,
        array $input
    ): DataTransformationBiEvaluationFinding {
        return DB::transaction(
            function () use (
                $evaluation,
                $actor,
                $input
            ): DataTransformationBiEvaluationFinding {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $normalized =
                    $this->normalizeInput(
                        $lockedEvaluation,
                        $input
                    );

                $maxSortOrder =
                    DataTransformationBiEvaluationFinding::query()
                        ->where(
                            'data_transformation_bi_evaluation_id',
                            $lockedEvaluation->getKey()
                        )
                        ->max('sort_order');

                $nextSortOrder =
                    $maxSortOrder === null
                        ? 0
                        : ((int) $maxSortOrder) + 1;

                $finding =
                    DataTransformationBiEvaluationFinding::create([
                        'data_transformation_bi_evaluation_id' =>
                            $lockedEvaluation->getKey(),

                        'company_id' =>
                            (int) $lockedEvaluation->company_id,

                        'finding_type' =>
                            $normalized['finding_type'],

                        'title' =>
                            $normalized['title'],

                        'details' =>
                            $normalized['details'],

                        'recommendation' =>
                            $normalized['recommendation'],

                        'priority' =>
                            $normalized['priority'],

                        'evidence_version' =>
                            (int) $lockedEvaluation
                                ->evidence_version,

                        'sort_order' =>
                            $nextSortOrder,

                        'created_by_user_id' =>
                            $actor->getKey(),

                        'updated_by_user_id' =>
                            $actor->getKey(),
                    ]);

                $finding
                    ->sources()
                    ->sync(
                        $normalized['source_ids']
                    );

                $this->audit(
                    'data_transformation_bi_evaluation_finding_created',
                    $finding,
                    $lockedEvaluation,
                    $actor,
                    $normalized['source_ids']
                );

                return $finding->fresh(
                    'sources'
                );
            },
            3
        );
    }

    public function update(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationFinding $finding,
        User $actor,
        array $input
    ): DataTransformationBiEvaluationFinding {
        return DB::transaction(
            function () use (
                $evaluation,
                $finding,
                $actor,
                $input
            ): DataTransformationBiEvaluationFinding {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedFinding =
                    $this->lockedFinding(
                        $lockedEvaluation,
                        $finding
                    );

                $normalized =
                    $this->normalizeInput(
                        $lockedEvaluation,
                        $input
                    );

                /*
                 * Any explicit professional update also confirms the
                 * finding against the current evidence version.
                 */
                $lockedFinding->forceFill([
                    'finding_type' =>
                        $normalized['finding_type'],

                    'title' =>
                        $normalized['title'],

                    'details' =>
                        $normalized['details'],

                    'recommendation' =>
                        $normalized['recommendation'],

                    'priority' =>
                        $normalized['priority'],

                    'evidence_version' =>
                        (int) $lockedEvaluation
                            ->evidence_version,

                    'updated_by_user_id' =>
                        $actor->getKey(),
                ])->save();

                $lockedFinding
                    ->sources()
                    ->sync(
                        $normalized['source_ids']
                    );

                $this->audit(
                    'data_transformation_bi_evaluation_finding_updated',
                    $lockedFinding,
                    $lockedEvaluation,
                    $actor,
                    $normalized['source_ids']
                );

                return $lockedFinding->fresh(
                    'sources'
                );
            },
            3
        );
    }

    /**
     * Explicitly confirm an existing human finding against the
     * current evidence after the evaluation draft was refreshed.
     */
    public function reconfirm(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationFinding $finding,
        User $actor
    ): DataTransformationBiEvaluationFinding {
        return DB::transaction(
            function () use (
                $evaluation,
                $finding,
                $actor
            ): DataTransformationBiEvaluationFinding {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedFinding =
                    $this->lockedFinding(
                        $lockedEvaluation,
                        $finding
                    );

                $sourceIds =
                    $lockedFinding
                        ->sources()
                        ->pluck(
                            'data_transformation_bi_source_assets.id'
                        )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->values()
                        ->all();

                $this->assertEvidenceSources(
                    $lockedEvaluation,
                    $sourceIds
                );

                $lockedFinding->forceFill([
                    'evidence_version' =>
                        (int) $lockedEvaluation
                            ->evidence_version,

                    'updated_by_user_id' =>
                        $actor->getKey(),
                ])->save();

                $this->audit(
                    'data_transformation_bi_evaluation_finding_reconfirmed',
                    $lockedFinding,
                    $lockedEvaluation,
                    $actor,
                    $sourceIds
                );

                return $lockedFinding->fresh(
                    'sources'
                );
            },
            3
        );
    }

    public function delete(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationFinding $finding,
        User $actor
    ): void {
        DB::transaction(
            function () use (
                $evaluation,
                $finding,
                $actor
            ): void {
                $lockedEvaluation =
                    $this->lockedDraftEvaluation(
                        $evaluation
                    );

                $lockedFinding =
                    $this->lockedFinding(
                        $lockedEvaluation,
                        $finding
                    );

                $sourceIds =
                    $lockedFinding
                        ->sources()
                        ->pluck(
                            'data_transformation_bi_source_assets.id'
                        )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->values()
                        ->all();

                $this->audit(
                    'data_transformation_bi_evaluation_finding_deleted',
                    $lockedFinding,
                    $lockedEvaluation,
                    $actor,
                    $sourceIds
                );

                $lockedFinding->delete();
            },
            3
        );
    }

    private function lockedDraftEvaluation(
        DataTransformationBiEvaluation $evaluation
    ): DataTransformationBiEvaluation {
        $locked =
            DataTransformationBiEvaluation::query()
                ->whereKey(
                    $evaluation->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (! $locked->isDraft()) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Los hallazgos solo pueden modificarse mientras la evaluación está en borrador.',
                ],
            ]);
        }

        return $locked;
    }

    private function lockedFinding(
        DataTransformationBiEvaluation $evaluation,
        DataTransformationBiEvaluationFinding $finding
    ): DataTransformationBiEvaluationFinding {
        return DataTransformationBiEvaluationFinding::query()
            ->whereKey(
                $finding->getKey()
            )
            ->where(
                'data_transformation_bi_evaluation_id',
                $evaluation->getKey()
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{
     *     finding_type:string,
     *     title:string,
     *     details:string,
     *     recommendation:?string,
     *     priority:?string,
     *     source_ids:list<int>
     * }
     */
    private function normalizeInput(
        DataTransformationBiEvaluation $evaluation,
        array $input
    ): array {
        $findingType =
            trim(
                (string) (
                    $input['finding_type']
                    ?? ''
                )
            );

        if (
            ! in_array(
                $findingType,
                DataTransformationBiEvaluationFinding::TYPES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'finding_type' => [
                    'El tipo de hallazgo no es válido.',
                ],
            ]);
        }

        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => [
                    'El título del hallazgo es obligatorio.',
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
                    'El detalle del hallazgo es obligatorio.',
                ],
            ]);
        }

        $recommendation =
            $this->nullableString(
                $input['recommendation']
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
                DataTransformationBiEvaluationFinding::PRIORITIES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'priority' => [
                    'La prioridad profesional no es válida.',
                ],
            ]);
        }

        $sourceIds =
            $this->sourceIds(
                $input['source_ids']
                ?? []
            );

        $this->assertEvidenceSources(
            $evaluation,
            $sourceIds
        );

        return [
            'finding_type' =>
                $findingType,

            'title' =>
                $title,

            'details' =>
                $details,

            'recommendation' =>
                $recommendation,

            'priority' =>
                $priority,

            'source_ids' =>
                $sourceIds,
        ];
    }

    /**
     * @return list<int>
     */
    private function sourceIds(
        mixed $value
    ): array {
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'source_ids' => [
                    'Las fuentes de evidencia deben enviarse como una lista.',
                ],
            ]);
        }

        $ids = [];

        foreach ($value as $item) {
            if (
                ! is_int($item)
                && ! (
                    is_string($item)
                    && ctype_digit($item)
                )
            ) {
                throw ValidationException::withMessages([
                    'source_ids' => [
                        'Una referencia de fuente no es válida.',
                    ],
                ]);
            }

            $id =
                (int) $item;

            if ($id <= 0) {
                throw ValidationException::withMessages([
                    'source_ids' => [
                        'Una referencia de fuente no es válida.',
                    ],
                ]);
            }

            $ids[] =
                $id;
        }

        $ids =
            array_values(
                array_unique(
                    $ids
                )
            );

        sort(
            $ids,
            SORT_NUMERIC
        );

        return $ids;
    }

    /**
     * Sources are optional, but any selected source must belong
     * to the exact evidence snapshot currently under review.
     *
     * @param list<int> $sourceIds
     */
    private function assertEvidenceSources(
        DataTransformationBiEvaluation $evaluation,
        array $sourceIds
    ): void {
        if ($sourceIds === []) {
            return;
        }

        $snapshot =
            is_array(
                $evaluation->evidence_snapshot
            )
                ? $evaluation->evidence_snapshot
                : [];

        $sources =
            is_array(
                $snapshot['sources']
                ?? null
            )
                ? $snapshot['sources']
                : [];

        $allowed = [];

        foreach ($sources as $source) {
            if (
                is_array($source)
                && isset(
                    $source['source_asset_id']
                )
            ) {
                $allowed[] =
                    (int) $source[
                        'source_asset_id'
                    ];
            }
        }

        $invalid =
            array_values(
                array_diff(
                    $sourceIds,
                    $allowed
                )
            );

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'source_ids' => [
                    'Una o más fuentes no pertenecen a la evidencia diagnóstica de esta evaluación.',
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

        $value =
            trim(
                (string) $value
            );

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @param list<int> $sourceIds
     */
    private function audit(
        string $event,
        DataTransformationBiEvaluationFinding $finding,
        DataTransformationBiEvaluation $evaluation,
        User $actor,
        array $sourceIds
    ): void {
        AuditService::log(
            $event,
            $finding,
            [
                'company_id' =>
                    (int) $evaluation->company_id,

                'evaluation_id' =>
                    (int) $evaluation->getKey(),

                'intake_session_id' =>
                    (int) $evaluation
                        ->data_transformation_bi_intake_session_id,

                'finding_type' =>
                    (string) $finding->finding_type,

                'priority' =>
                    $finding->priority,

                'evidence_version' =>
                    (int) $finding
                        ->evidence_version,

                'source_asset_ids' =>
                    $sourceIds,

                'actor_user_id' =>
                    (int) $actor->getKey(),

                'automatic_score' =>
                    false,

                'implementation_started' =>
                    false,
            ]
        );
    }
}
