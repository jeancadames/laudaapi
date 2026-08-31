<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;

final class TransformationCapabilityNeedService
{
    public const SOURCE_CAPABILITY_CATALOG =
        'capability_activation_catalog';

    public function syncForActivation(
        TransformationCapabilityActivation $activation
    ): Collection {
        $definitions =
            TransformationCapabilityNeedCatalog::forCapability(
                (string) $activation->capability_key
            );

        if ($definitions === []) {
            return new Collection();
        }

        $snapshot = is_array($activation->source_snapshot)
            ? $activation->source_snapshot
            : [];

        $roadmap = is_array($snapshot['roadmap'] ?? null)
            ? $snapshot['roadmap']
            : [];

        $createdKeys = [];

        foreach (
            array_values($definitions)
            as $index => $definition
        ) {
            $needKey = trim(
                (string) (
                    $definition['need_key']
                    ?? ''
                )
            );

            if ($needKey === '') {
                continue;
            }

            $need =
                TransformationCapabilityNeed::query()
                    ->firstOrNew([
                        'transformation_capability_activation_id' =>
                            $activation->id,
                        'need_key' =>
                            $needKey,
                    ]);

            $isNew = ! $need->exists;

            $need->forceFill([
                'sequence' =>
                    $index + 1,

                'title' =>
                    (string) (
                        $definition['title']
                        ?? $needKey
                    ),

                'description' =>
                    $definition['description']
                    ?? null,

                'source_type' =>
                    self::SOURCE_CAPABILITY_CATALOG,

                'source_snapshot' => [
                    'catalog_version' =>
                        TransformationCapabilityNeedCatalog::versionFor(
                            (string) $activation->capability_key
                        ),

                    'capability_key' =>
                        (string) $activation->capability_key,

                    'activation_source' => [
                        'type' =>
                            $activation->source_type,
                        'id' =>
                            $activation->source_id !== null
                                ? (int) $activation->source_id
                                : null,
                        'version' =>
                            $activation->source_version,
                    ],

                    'roadmap_recommendation' => [
                        'recommended' =>
                            (bool) (
                                $roadmap['recommended']
                                ?? false
                            ),

                        'basis' =>
                            $roadmap['recommendation_basis']
                            ?? null,
                    ],

                    'free_activation' =>
                        true,
                ],
            ]);

            if ($isNew) {
                $need->forceFill([
                    'status' =>
                        TransformationCapabilityNeed::STATUS_IDENTIFIED,

                    'identified_at' =>
                        now(),
                ]);

                $createdKeys[] =
                    $needKey;
            }

            $need->save();

            TransformationCapabilityNeedEvaluation::query()
                ->firstOrCreate(
                    [
                        'transformation_capability_need_id' =>
                            $need->id,
                    ],
                    [
                        'status' =>
                            TransformationCapabilityNeedEvaluation::STATUS_PENDING,
                        'generation_version' =>
                            0,
                    ]
                );
        }

        if ($createdKeys !== []) {
            AuditService::log(
                'transformation_capability_needs_identified',
                $activation,
                [
                    'company_id' =>
                        $activation->company_id,
                    'assessment_id' =>
                        $activation->diagnosis_assessment_id,
                    'capability_key' =>
                        $activation->capability_key,
                    'need_keys' =>
                        array_values($createdKeys),
                    'need_count' =>
                        count($createdKeys),
                    'commercial_acceptance' =>
                        false,
                ]
            );
        }

        return $activation
            ->needs()
            ->get();
    }
}
