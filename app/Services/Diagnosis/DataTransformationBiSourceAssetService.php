<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiSourceAssetService
{
    private const MAX_DISPLAY_NAME_LENGTH = 191;
    private const MAX_SOURCE_OBJECT_NAME_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 4000;
    private const MAX_ORIGIN_SYSTEM_LENGTH = 191;
    private const MAX_OWNER_LENGTH = 191;
    private const MAX_BUSINESS_DOMAIN_LENGTH = 191;
    private const MAX_BUSINESS_DOMAINS = 50;
    private const MAX_STRUCTURE_FORMAT_LENGTH = 64;

    /**
     * Create one logical client-native source.
     *
     * This operation deliberately does NOT:
     * - assign a canonical LAUDA domain;
     * - connect to the source system;
     * - request or persist credentials;
     * - upload source data;
     * - create canonical deliveries;
     * - materialize staging rows.
     *
     * @param array<string,mixed> $input
     */
    public function create(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        array $input,
        User $actor
    ): DataTransformationBiSourceAsset {
        $this->assertCanManage(
            $implementationRequest,
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $payload =
            $this->validatedPayload(
                $input,
                true
            );

        return DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $payload,
                $actor
            ): DataTransformationBiSourceAsset {
                $this->lockEditableSession(
                    $implementationRequest,
                    $session
                );

                $nextSortOrder =
                    (
                        (int) (
                            DataTransformationBiSourceAsset::query()
                                ->where(
                                    'data_transformation_bi_intake_session_id',
                                    (int) $session->getKey()
                                )
                                ->where(
                                    'company_id',
                                    (int) $implementationRequest->company_id
                                )
                                ->max('sort_order')
                            ?? -1
                        )
                    ) + 1;

                $asset =
                    new DataTransformationBiSourceAsset();

                $asset->fill(
                    array_merge(
                        $payload,
                        [
                            'data_transformation_bi_intake_session_id' =>
                                (int) $session->getKey(),

                            'company_id' =>
                                (int) $implementationRequest->company_id,

                            'status' =>
                                DataTransformationBiSourceAsset
                                    ::STATUS_DRAFT,

                            'structure_status' =>
                                DataTransformationBiSourceAsset
                                    ::STRUCTURE_PENDING,

                            'data_status' =>
                                DataTransformationBiSourceAsset
                                    ::DATA_PENDING,

                            'sort_order' =>
                                $nextSortOrder,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]
                    )
                );

                $asset->save();

                return $asset->fresh()
                    ?? $asset;
            }
        );
    }

    /**
     * Edit source metadata only.
     *
     * Structure snapshots, uploaded files, profiling results and canonical
     * mappings are intentionally outside this method.
     *
     * @param array<string,mixed> $input
     */
    public function update(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        array $input,
        User $actor
    ): DataTransformationBiSourceAsset {
        $this->assertCanManage(
            $implementationRequest,
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $this->assertAssetBelongsToSession(
            $implementationRequest,
            $session,
            $asset
        );

        $this->assertNotArchived(
            $asset
        );

        $payload =
            $this->validatedPayload(
                $input,
                false
            );

        return DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $asset,
                $payload,
                $actor
            ): DataTransformationBiSourceAsset {
                $this->lockEditableSession(
                    $implementationRequest,
                    $session
                );

                $asset->fill(
                    array_merge(
                        $payload,
                        [
                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]
                    )
                );

                $asset->save();

                return $asset->fresh()
                    ?? $asset;
            }
        );
    }

    /**
     * Persist the horizontal workspace order.
     *
     * The supplied ids must represent every active source in the session,
     * exactly once. Archived sources are excluded from ordering.
     *
     * @param array<int,int|string> $orderedAssetIds
     */
    public function reorder(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        array $orderedAssetIds,
        User $actor
    ): void {
        $this->assertCanManage(
            $implementationRequest,
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $normalizedIds = [];

        foreach ($orderedAssetIds as $id) {
            $normalizedId =
                filter_var(
                    $id,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1,
                        ],
                    ]
                );

            if ($normalizedId === false) {
                throw ValidationException::withMessages([
                    'source_assets' => [
                        'El orden contiene una fuente inválida.',
                    ],
                ]);
            }

            $normalizedIds[] =
                (int) $normalizedId;
        }

        if (
            count(
                $normalizedIds
            )
            !== count(
                array_unique(
                    $normalizedIds
                )
            )
        ) {
            throw ValidationException::withMessages([
                'source_assets' => [
                    'Una misma fuente no puede aparecer más de una vez.',
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $normalizedIds,
                $actor
            ): void {
                $this->lockEditableSession(
                    $implementationRequest,
                    $session
                );

                $assets =
                    DataTransformationBiSourceAsset::query()
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $session->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->whereNull(
                            'archived_at'
                        )
                        ->lockForUpdate()
                        ->get([
                            'id',
                        ]);

                $existingIds =
                    $assets
                        ->pluck('id')
                        ->map(
                            static fn ($id): int =>
                                (int) $id
                        )
                        ->sort()
                        ->values()
                        ->all();

                $requestedIds =
                    collect(
                        $normalizedIds
                    )
                        ->sort()
                        ->values()
                        ->all();

                if ($existingIds !== $requestedIds) {
                    throw ValidationException::withMessages([
                        'source_assets' => [
                            'El orden debe incluir todas las fuentes activas de la sesión exactamente una vez.',
                        ],
                    ]);
                }

                foreach (
                    $normalizedIds
                    as $sortOrder => $assetId
                ) {
                    DataTransformationBiSourceAsset::query()
                        ->whereKey(
                            $assetId
                        )
                        ->update([
                            'sort_order' =>
                                $sortOrder,

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_at' =>
                                now(),
                        ]);
                }
            }
        );
    }

    /**
     * Archive without deleting historical source metadata.
     */
    public function archive(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        User $actor
    ): DataTransformationBiSourceAsset {
        $this->assertCanManage(
            $implementationRequest,
            $actor
        );

        $this->assertRequestAndSession(
            $implementationRequest,
            $session
        );

        $this->assertEditableSession(
            $session
        );

        $this->assertAssetBelongsToSession(
            $implementationRequest,
            $session,
            $asset
        );

        if ($asset->archived_at !== null) {
            return $asset;
        }

        return DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $asset,
                $actor
            ): DataTransformationBiSourceAsset {
                $this->lockEditableSession(
                    $implementationRequest,
                    $session
                );

                $asset->forceFill([
                    'status' =>
                        DataTransformationBiSourceAsset
                            ::STATUS_ARCHIVED,

                    'archived_at' =>
                        now(),

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ]);

                $asset->save();

                return $asset->fresh()
                    ?? $asset;
            }
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validatedPayload(
        array $input,
        bool $creating
    ): array {
        $displayName =
            trim(
                (string) (
                    $input['display_name']
                    ?? ''
                )
            );

        $sourceObjectName =
            trim(
                (string) (
                    $input['source_object_name']
                    ?? ''
                )
            );

        if (
            $creating
            || array_key_exists(
                'display_name',
                $input
            )
        ) {
            if ($displayName === '') {
                throw ValidationException::withMessages([
                    'display_name' => [
                        'Indica un nombre para identificar la fuente.',
                    ],
                ]);
            }

            if (
                mb_strlen(
                    $displayName
                )
                > self::MAX_DISPLAY_NAME_LENGTH
            ) {
                throw ValidationException::withMessages([
                    'display_name' => [
                        'El nombre de la fuente es demasiado largo.',
                    ],
                ]);
            }
        }

        if (
            $creating
            || array_key_exists(
                'source_object_name',
                $input
            )
        ) {
            if ($sourceObjectName === '') {
                throw ValidationException::withMessages([
                    'source_object_name' => [
                        'Indica el nombre de la tabla o archivo de origen.',
                    ],
                ]);
            }

            if (
                mb_strlen(
                    $sourceObjectName
                )
                > self::MAX_SOURCE_OBJECT_NAME_LENGTH
            ) {
                throw ValidationException::withMessages([
                    'source_object_name' => [
                        'El nombre de la tabla o archivo es demasiado largo.',
                    ],
                ]);
            }
        }

        $payload = [];

        if (
            $creating
            || array_key_exists(
                'display_name',
                $input
            )
        ) {
            $payload['display_name'] =
                $displayName;
        }

        if (
            $creating
            || array_key_exists(
                'source_object_name',
                $input
            )
        ) {
            $payload['source_object_name'] =
                $sourceObjectName;
        }

        if (
            array_key_exists(
                'description',
                $input
            )
        ) {
            $payload['description'] =
                $this->nullableText(
                    $input['description'],
                    self::MAX_DESCRIPTION_LENGTH,
                    'description',
                    'La descripción es demasiado larga.'
                );
        }

        if (
            array_key_exists(
                'origin_system',
                $input
            )
        ) {
            $payload['origin_system'] =
                $this->nullableText(
                    $input['origin_system'],
                    self::MAX_ORIGIN_SYSTEM_LENGTH,
                    'origin_system',
                    'El origen de los datos es demasiado largo.'
                );
        }

        if (
            array_key_exists(
                'owner',
                $input
            )
        ) {
            $payload['owner'] =
                $this->nullableText(
                    $input['owner'],
                    self::MAX_OWNER_LENGTH,
                    'owner',
                    'El responsable de la fuente es demasiado largo.'
                );
        }

        if (
            $creating
            || array_key_exists(
                'business_domains',
                $input
            )
        ) {
            $payload['business_domains'] =
                $this->normalizeBusinessDomains(
                    $input['business_domains']
                    ?? []
                );
        }

        if (
            array_key_exists(
                'structure_format',
                $input
            )
        ) {
            $payload['structure_format'] =
                $this->nullableText(
                    $input['structure_format'],
                    self::MAX_STRUCTURE_FORMAT_LENGTH,
                    'structure_format',
                    'El formato de estructura es demasiado largo.'
                );
        }

        if (
            array_key_exists(
                'delivery_format',
                $input
            )
        ) {
            $deliveryFormat =
                strtolower(
                    trim(
                        (string) (
                            $input['delivery_format']
                            ?? ''
                        )
                    )
                );

            if ($deliveryFormat === '') {
                $payload['delivery_format'] =
                    null;
            } elseif (
                ! in_array(
                    $deliveryFormat,
                    [
                        DataTransformationBiSourceAsset
                            ::DELIVERY_CSV,

                        DataTransformationBiSourceAsset
                            ::DELIVERY_XLSX,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'delivery_format' => [
                        'El formato de entrega debe ser CSV o XLSX.',
                    ],
                ]);
            } else {
                $payload['delivery_format'] =
                    $deliveryFormat;
            }
        }

        return $payload;
    }

    /**
     * Normalize tenant-declared business information domains.
     *
     * Domains remain completely dynamic. Only the three broad business
     * groups are controlled by LAUDA.
     *
     * This classification is descriptive diagnostic metadata only and
     * must never become a source-readiness gate or canonical-domain link.
     *
     * @return list<array{domain:string,group:string}>
     */
    private function normalizeBusinessDomains(
        mixed $value
    ): array {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'business_domains' => [
                    'La clasificación de dominios debe ser una lista.',
                ],
            ]);
        }

        if (
            count($value)
            > self::MAX_BUSINESS_DOMAINS
        ) {
            throw ValidationException::withMessages([
                'business_domains' => [
                    'La fuente contiene demasiados dominios de información.',
                ],
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach (
            array_values($value)
            as $index => $item
        ) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}" => [
                        'Cada dominio debe indicar su nombre y grupo.',
                    ],
                ]);
            }

            $domainValue =
                $item['domain']
                ?? null;

            $groupValue =
                $item['group']
                ?? null;

            if (! is_string($domainValue)) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.domain" => [
                        'El dominio de información debe ser texto.',
                    ],
                ]);
            }

            if (! is_string($groupValue)) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.group" => [
                        'Selecciona un grupo válido para el dominio.',
                    ],
                ]);
            }

            $domain =
                trim(
                    $domainValue
                );

            $group =
                strtolower(
                    trim(
                        $groupValue
                    )
                );

            if ($domain === '') {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.domain" => [
                        'Indica el nombre del dominio de información.',
                    ],
                ]);
            }

            if (
                mb_strlen(
                    $domain
                )
                > self::MAX_BUSINESS_DOMAIN_LENGTH
            ) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.domain" => [
                        'El nombre del dominio de información es demasiado largo.',
                    ],
                ]);
            }

            if (
                ! in_array(
                    $group,
                    DataTransformationBiSourceAsset::BUSINESS_GROUPS,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.group" => [
                        'El grupo debe ser Operaciones, Gestión o Finanzas.',
                    ],
                ]);
            }

            $domainKey =
                mb_strtolower(
                    $domain,
                    'UTF-8'
                );

            if (isset($seen[$domainKey])) {
                throw ValidationException::withMessages([
                    "business_domains.{$index}.domain" => [
                        'Un dominio de información no puede repetirse en la misma fuente.',
                    ],
                ]);
            }

            $seen[$domainKey] = true;

            $normalized[] = [
                'domain' =>
                    $domain,

                'group' =>
                    $group,
            ];
        }

        return $normalized;
    }

    private function nullableText(
        mixed $value,
        int $maxLength,
        string $field,
        string $message
    ): ?string {
        $text =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($text === '') {
            return null;
        }

        if (
            mb_strlen(
                $text
            )
            > $maxLength
        ) {
            throw ValidationException::withMessages([
                $field => [
                    $message,
                ],
            ]);
        }

        return $text;
    }

    private function assertCanManage(
        TransformationImplementationRequest $implementationRequest,
        User $actor
    ): void {
        app(
            DataTransformationBiIntakeActorAuthorizationService::class
        )->assertCanManage(
            $implementationRequest,
            $actor
        );
    }

    private function assertRequestAndSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            (int) $session->company_id
            !== (int) $implementationRequest->company_id
            || (int) $session->transformation_implementation_request_id
            !== (int) $implementationRequest->getKey()
        ) {
            throw new AuthorizationException(
                'La sesión no pertenece a esta solicitud de implementación.'
            );
        }
    }

    /**
     * Acquire the session lock that serializes tenant mutations
     * against evaluation submission.
     *
     * Lock order:
     * session -> source asset(s) -> source file/mapping rows.
     */
    private function lockEditableSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): DataTransformationBiIntakeSession {
        $lockedSession =
            DataTransformationBiIntakeSession::query()
                ->whereKey(
                    (int) $session->getKey()
                )
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    (int) $implementationRequest->getKey()
                )
                ->lockForUpdate()
                ->first();

        if ($lockedSession === null) {
            throw new AuthorizationException(
                'La sesión ya no pertenece a esta solicitud de implementación.'
            );
        }

        $this->assertEditableSession(
            $lockedSession
        );

        return $lockedSession;
    }

    private function assertEditableSession(
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            ! in_array(
                (string) $session->status,
                [
                    DataTransformationBiIntakeSession
                        ::STATUS_DRAFT,

                    DataTransformationBiIntakeSession
                        ::STATUS_READY,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'session' => [
                    'La sesión ya no permite modificar sus fuentes de datos.',
                ],
            ]);
        }
    }

    private function assertAssetBelongsToSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            (int) $asset->company_id
            !== (int) $implementationRequest->company_id
            || (int) $asset->data_transformation_bi_intake_session_id
            !== (int) $session->getKey()
        ) {
            throw new AuthorizationException(
                'La fuente no pertenece a esta sesión.'
            );
        }
    }

    private function assertNotArchived(
        DataTransformationBiSourceAsset $asset
    ): void {
        if (
            $asset->archived_at !== null
            || (string) $asset->status
                === DataTransformationBiSourceAsset
                    ::STATUS_ARCHIVED
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'La fuente está archivada y no puede modificarse.',
                ],
            ]);
        }
    }
}
