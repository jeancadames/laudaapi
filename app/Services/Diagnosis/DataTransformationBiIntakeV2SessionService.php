<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeDomainDelivery;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DataTransformationBiIntakeV2SessionService
{
    /**
     * Start one logical intake cut or reuse the currently open cut.
     *
     * The session is pre-staging. Creating it must not create:
     * - intake batches;
     * - staging rows;
     * - processing runs;
     * - normalized rows.
     */
    public function startOrReuse(
        TransformationImplementationRequest $implementationRequest,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        $this->assertPersistedRequest(
            $implementationRequest
        );

        return DB::transaction(
            function () use (
                $implementationRequest,
                $actor
            ): array {
                /*
                 * Stable lock order begins with the implementation request.
                 * This serializes creation of open intake sessions for the
                 * same request even though MySQL has no partial unique index
                 * for "only one draft/ready row".
                 */
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $implementationRequest->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedRequest === null) {
                    throw ValidationException::withMessages([
                        'implementation_request' => [
                            'La solicitud de implementación ya no existe.',
                        ],
                    ]);
                }

                $this->assertPersistedRequest(
                    $lockedRequest
                );

                $schemaVersion =
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION;

                $existing =
                    DataTransformationBiIntakeSession::query()
                        ->where(
                            'company_id',
                            (int) $lockedRequest->company_id
                        )
                        ->where(
                            'transformation_implementation_request_id',
                            (int) $lockedRequest->getKey()
                        )
                        ->where(
                            'schema_version',
                            $schemaVersion
                        )
                        ->whereIn(
                            'status',
                            [
                                DataTransformationBiIntakeSession
                                    ::STATUS_DRAFT,

                                DataTransformationBiIntakeSession
                                    ::STATUS_READY,

                                DataTransformationBiIntakeSession
                                    ::STATUS_FINALIZING,
                            ]
                        )
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                if (
                    $existing !== null
                    && $existing->status
                        === DataTransformationBiIntakeSession
                            ::STATUS_FINALIZING
                ) {
                    throw ValidationException::withMessages([
                        'intake_session' => [
                            'La sesión de intake se está finalizando '
                            .'y no puede modificarse.',
                        ],
                    ]);
                }

                if ($existing !== null) {
                    $this->ensureCanonicalSlots(
                        $existing,
                        $actor
                    );

                    return $this->result(
                        $existing->fresh(),
                        true
                    );
                }

                $definition =
                    $this->latestDefinitionFor(
                        $lockedRequest
                    );

                $session =
                    DataTransformationBiIntakeSession::query()
                        ->create([
                            'company_id' =>
                                (int) $lockedRequest->company_id,

                            'transformation_implementation_request_id' =>
                                (int) $lockedRequest->getKey(),

                            'transformation_implementation_definition_id' =>
                                $definition?->getKey(),

                            'definition_version' =>
                                $definition?->version,

                            'schema_version' =>
                                $schemaVersion,

                            'status' =>
                                DataTransformationBiIntakeSession
                                    ::STATUS_DRAFT,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'resulting_intake_batch_id' =>
                                null,

                            'resolved_manifest_sha256' =>
                                null,

                            'relational_validation_snapshot' =>
                                null,

                            'failure_code' =>
                                null,

                            'failure_message' =>
                                null,

                            'started_at' =>
                                now(),

                            'ready_at' =>
                                null,

                            'finalized_at' =>
                                null,

                            'cancelled_at' =>
                                null,
                        ]);

                $this->ensureCanonicalSlots(
                    $session,
                    $actor
                );

                return $this->result(
                    $session->fresh(),
                    false
                );
            }
        );
    }

    private function ensureCanonicalSlots(
        DataTransformationBiIntakeSession $session,
        User $actor
    ): void {
        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            DataTransformationBiIntakeDomainDelivery::query()
                ->firstOrCreate(
                    [
                        'data_transformation_bi_intake_session_id' =>
                            (int) $session->getKey(),

                        'domain_key' =>
                            $domain,
                    ],
                    [
                        'company_id' =>
                            (int) $session->company_id,

                        'delivery_mode' =>
                            null,

                        'status' =>
                            DataTransformationBiIntakeDomainDelivery
                                ::STATUS_PENDING,

                        'source_disk' =>
                            null,

                        'source_path' =>
                            null,

                        'original_filename' =>
                            null,

                        'source_format' =>
                            null,

                        'source_mime_type' =>
                            null,

                        'source_size_bytes' =>
                            null,

                        'source_sha256' =>
                            null,

                        'validation_snapshot' =>
                            null,

                        'source_row_count' =>
                            0,

                        'accepted_row_count' =>
                            0,

                        'carry_forward_processing_run_id' =>
                            null,

                        'carry_forward_intake_batch_id' =>
                            null,

                        'created_by_user_id' =>
                            (int) $actor->getKey(),

                        'validated_at' =>
                            null,
                    ]
                );
        }

        $actualDomains =
            DataTransformationBiIntakeDomainDelivery::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->pluck(
                    'domain_key'
                )
                ->all();

        $expectedDomains =
            DataTransformationBiStandardIntakeSchema
                ::domainKeys();

        sort(
            $actualDomains
        );

        sort(
            $expectedDomains
        );

        if (
            $actualDomains
            !== $expectedDomains
        ) {
            throw new RuntimeException(
                'La sesión de intake no contiene exactamente '
                .'los siete dominios canónicos.'
            );
        }
    }

    private function latestDefinitionFor(
        TransformationImplementationRequest $request
    ): ?TransformationImplementationDefinition {
        return TransformationImplementationDefinition::query()
            ->where(
                'transformation_implementation_request_id',
                (int) $request->getKey()
            )
            ->orderByDesc(
                'version'
            )
            ->orderByDesc(
                'id'
            )
            ->first();
    }

    private function result(
        DataTransformationBiIntakeSession $session,
        bool $reused
    ): array {
        $deliveries =
            DataTransformationBiIntakeDomainDelivery::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->get()
                ->keyBy(
                    'domain_key'
                );

        $domains = [];

        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            $delivery =
                $deliveries->get(
                    $domain
                );

            if (
                ! $delivery
                instanceof DataTransformationBiIntakeDomainDelivery
            ) {
                throw new RuntimeException(
                    "Falta el slot canónico {$domain}."
                );
            }

            $domains[$domain] = [
                'delivery_id' =>
                    (int) $delivery->getKey(),

                'domain' =>
                    $domain,

                'delivery_mode' =>
                    $delivery->delivery_mode,

                'status' =>
                    (string) $delivery->status,

                'format' =>
                    $delivery->source_format,

                'original_filename' =>
                    $delivery->original_filename,

                'source_row_count' =>
                    (int) $delivery->source_row_count,

                'accepted_row_count' =>
                    (int) $delivery->accepted_row_count,

                'validated_at' =>
                    $delivery->validated_at
                        ? $delivery->validated_at->toISOString()
                        : null,
            ];
        }

        return [
            'reused' =>
                $reused,

            'session_id' =>
                (int) $session->getKey(),

            'company_id' =>
                (int) $session->company_id,

            'implementation_request_id' =>
                (int) $session
                    ->transformation_implementation_request_id,

            'schema_version' =>
                (int) $session->schema_version,

            'status' =>
                (string) $session->status,

            'domains' =>
                $domains,
        ];
    }

    private function assertAdmin(
        User $actor
    ): void {
        if (
            (string) $actor->role
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'La gestión del intake requiere privilegios administrativos.'
            );
        }
    }

    private function assertPersistedRequest(
        TransformationImplementationRequest $request
    ): void {
        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud debe existir antes de iniciar el intake.',
                ],
            ]);
        }

        if (
            (string) $request->capability_key
            !== 'data_transformation_bi'
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud no corresponde a Transformación de Datos para BI.',
                ],
            ]);
        }

        if (
            (int) $request->company_id
            <= 0
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud no tiene una empresa válida.',
                ],
            ]);
        }
    }
}
