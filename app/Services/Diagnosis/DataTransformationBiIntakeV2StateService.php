<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeDomainDelivery;
use App\Models\DataTransformationBiSourceDomainFile;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use RuntimeException;

final class DataTransformationBiIntakeV2StateService
{
    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver
            $usableDatasetResolver
    ) {
    }

    /**
     * Read-only HTTP/UI projection for Intake v2.
     *
     * It deliberately exposes no storage path and performs no lifecycle
     * mutation, staging, profiling or normalization.
     *
     * @return array<string,mixed>
     */
    public function forRequest(
        TransformationImplementationRequest $request
    ): array {
        $this->assertRequest(
            $request
        );

        $companyId =
            (int) $request->company_id;

        $requestId =
            (int) $request->getKey();

        $domainDefinitions =
            DataTransformationBiStandardIntakeSchema::domains();

        $domainCatalog = [];

        foreach (
            $domainDefinitions
            as $domainKey => $definition
        ) {
            $domainCatalog[] = [
                'key' =>
                    (string) $domainKey,

                'label' =>
                    (string) (
                        $definition['label']
                        ?? $domainKey
                    ),

                'description' =>
                    (string) (
                        $definition['description']
                        ?? ''
                    ),
            ];
        }

        $session =
            DataTransformationBiIntakeSession::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $requestId
                )
                ->orderByDesc('id')
                ->first();

        $sessionPayload =
            $session === null
                ? null
                : $this->sessionPayload(
                    $session,
                    $domainDefinitions
                );

        $usableDataset =
            $this
                ->usableDatasetResolver
                ->forRequest(
                    $companyId,
                    $requestId
                );

        return [
            'version' =>
                2,

            'schema_version' =>
                DataTransformationBiStandardIntakeSchema::VERSION,

            'domains' =>
                $domainCatalog,

            'session' =>
                $sessionPayload,

            'usable_dataset' =>
                $usableDataset,

            'actions' =>
                $this->actions(
                    $sessionPayload
                ),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $domainDefinitions
     * @return array<string,mixed>
     */
    private function sessionPayload(
        DataTransformationBiIntakeSession $session,
        array $domainDefinitions
    ): array {
        $deliveryRows =
            DataTransformationBiIntakeDomainDelivery::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->where(
                    'company_id',
                    (int) $session->company_id
                )
                ->get()
                ->keyBy(
                    'domain_key'
                );

        $sourceFiles =
            DataTransformationBiSourceDomainFile::query()
                ->where(
                    'company_id',
                    (int) $session->company_id
                )
                ->whereIn(
                    'data_transformation_bi_intake_domain_delivery_id',
                    $deliveryRows
                        ->pluck('id')
                        ->all()
                )
                ->get()
                ->keyBy(
                    'data_transformation_bi_intake_domain_delivery_id'
                );

        $deliveries = [];

        $validCount = 0;
        $pendingCount = 0;
        $invalidCount = 0;

        foreach (
            $domainDefinitions
            as $domainKey => $definition
        ) {
            /** @var DataTransformationBiIntakeDomainDelivery|null $delivery */
            $delivery =
                $deliveryRows->get(
                    $domainKey
                );

            /** @var DataTransformationBiSourceDomainFile|null $sourceFile */
            $sourceFile =
                $delivery === null
                    ? null
                    : $sourceFiles->get(
                        (int) $delivery->getKey()
                    );

            if ($delivery === null) {
                $pendingCount++;

                $deliveries[] = [
                    'domain_key' =>
                        (string) $domainKey,

                    'label' =>
                        (string) (
                            $definition['label']
                            ?? $domainKey
                        ),

                    'delivery_mode' =>
                        null,

                    'status' =>
                        DataTransformationBiIntakeDomainDelivery
                            ::STATUS_PENDING,

                    'original_filename' =>
                        null,

                    'source_format' =>
                        null,

                    'source_size_bytes' =>
                        null,

                    'source_row_count' =>
                        0,

                    'accepted_row_count' =>
                        0,

                    'carry_forward_processing_run_id' =>
                        null,

                    'carry_forward_intake_batch_id' =>
                        null,

                    'validated_at' =>
                        null,

                    'validation_feedback' =>
                        null,

                    'source_native_supported' =>
                        DataTransformationBiSourceDomainRegistry
                            ::supports(
                                (string) $domainKey
                            ),

                    'source_native' =>
                        null,
                ];

                continue;
            }

            if (
                $delivery->status
                === DataTransformationBiIntakeDomainDelivery
                    ::STATUS_VALID
            ) {
                $validCount++;
            } elseif (
                $delivery->status
                === DataTransformationBiIntakeDomainDelivery
                    ::STATUS_INVALID
            ) {
                $invalidCount++;
            } else {
                $pendingCount++;
            }

            $deliveries[] = [
                'domain_key' =>
                    (string) $domainKey,

                'label' =>
                    (string) (
                        $definition['label']
                        ?? $domainKey
                    ),

                'delivery_mode' =>
                    $delivery->delivery_mode !== null
                        ? (string) $delivery->delivery_mode
                        : null,

                'status' =>
                    (string) $delivery->status,

                'original_filename' =>
                    $delivery->original_filename !== null
                        ? (string) $delivery->original_filename
                        : null,

                'source_format' =>
                    $delivery->source_format !== null
                        ? (string) $delivery->source_format
                        : null,

                'source_size_bytes' =>
                    $delivery->source_size_bytes !== null
                        ? (int) $delivery->source_size_bytes
                        : null,

                'source_row_count' =>
                    (int) $delivery->source_row_count,

                'accepted_row_count' =>
                    (int) $delivery->accepted_row_count,

                'carry_forward_processing_run_id' =>
                    $delivery->carry_forward_processing_run_id !== null
                        ? (int) $delivery
                            ->carry_forward_processing_run_id
                        : null,

                'carry_forward_intake_batch_id' =>
                    $delivery->carry_forward_intake_batch_id !== null
                        ? (int) $delivery
                            ->carry_forward_intake_batch_id
                        : null,

                'validated_at' =>
                    $delivery->validated_at?->toISOString(),

                'validation_feedback' =>
                    $this->validationFeedback(
                        $delivery->validation_snapshot
                    ),

                'source_native_supported' =>
                    DataTransformationBiSourceDomainRegistry
                        ::supports(
                            (string) $domainKey
                        ),

                'source_native' =>
                    $sourceFile === null
                        ? null
                        : [
                            'id' =>
                                (int) $sourceFile->getKey(),

                            'status' =>
                                (string) $sourceFile->status,

                            'original_filename' =>
                                (string) $sourceFile->original_filename,

                            'source_format' =>
                                (string) $sourceFile->source_format,

                            'source_size_bytes' =>
                                (int) $sourceFile->source_size_bytes,

                            'source_row_count' =>
                                (int) $sourceFile->source_row_count,

                            'reader_configuration' =>
                                $sourceFile->reader_configuration,

                            'source_structure_snapshot' =>
                                $sourceFile->source_structure_snapshot,

                            'uploaded_at' =>
                                $sourceFile->uploaded_at?->toISOString(),
                        ],
            ];
        }

        $relationSnapshot =
            is_array(
                $session->relational_validation_snapshot
            )
                ? $session->relational_validation_snapshot
                : null;

        $relationSummary =
            $relationSnapshot === null
                ? null
                : [
                    'valid' =>
                        ($relationSnapshot['valid'] ?? false)
                        === true,

                    'error_count' =>
                        is_array(
                            $relationSnapshot['errors']
                            ?? null
                        )
                            ? count(
                                $relationSnapshot['errors']
                            )
                            : 0,

                    'warning_count' =>
                        is_array(
                            $relationSnapshot['warnings']
                            ?? null
                        )
                            ? count(
                                $relationSnapshot['warnings']
                            )
                            : 0,

                    /*
                     * D15F_SAFE_ERROR_PROJECTION
                     *
                     * UI receives only bounded human-readable messages.
                     * Never expose the complete internal snapshot,
                     * source paths, hashes, manifests or row payloads.
                     */
                    'errors' =>
                        $this->safeMessages(
                            $relationSnapshot['errors']
                            ?? []
                        ),

                    'warnings' =>
                        $this->safeMessages(
                            $relationSnapshot['warnings']
                            ?? []
                        ),
                ];

        return [
            'id' =>
                (int) $session->getKey(),

            'status' =>
                (string) $session->status,

            'schema_version' =>
                (int) $session->schema_version,

            'definition_id' =>
                $session
                    ->transformation_implementation_definition_id
                    !== null
                        ? (int) $session
                            ->transformation_implementation_definition_id
                        : null,

            'definition_version' =>
                $session->definition_version !== null
                    ? (int) $session->definition_version
                    : null,

            'resulting_intake_batch_id' =>
                $session->resulting_intake_batch_id !== null
                    ? (int) $session->resulting_intake_batch_id
                    : null,

            'resolved_manifest_sha256' =>
                $session->resolved_manifest_sha256 !== null
                    ? (string) $session
                        ->resolved_manifest_sha256
                    : null,

            'relational_validation' =>
                $relationSummary,

            'failure_code' =>
                $session->failure_code !== null
                    ? (string) $session->failure_code
                    : null,

            'failure_message' =>
                $session->failure_message !== null
                    ? (string) $session->failure_message
                    : null,

            'started_at' =>
                $session->started_at?->toISOString(),

            'ready_at' =>
                $session->ready_at?->toISOString(),

            'finalized_at' =>
                $session->finalized_at?->toISOString(),

            'cancelled_at' =>
                $session->cancelled_at?->toISOString(),

            'delivery_count' =>
                count($deliveries),

            'valid_delivery_count' =>
                $validCount,

            'pending_delivery_count' =>
                $pendingCount,

            'invalid_delivery_count' =>
                $invalidCount,

            'all_logical_decisions_resolved' =>
                count($deliveries)
                    === count($domainDefinitions)
                && $validCount
                    === count($domainDefinitions),

            'deliveries' =>
                $deliveries,
        ];
    }

    /**
     * @param array<string,mixed>|null $session
     * @return array<string,bool>
     */
    /**
     * D15F_SAFE_ERROR_PROJECTION
     *
     * @return array{
     *     valid:bool,
     *     error_count:int,
     *     warning_count:int,
     *     errors:array<int,string>,
     *     warnings:array<int,string>
     * }|null
     */
    private function validationFeedback(
        mixed $snapshot
    ): ?array {
        if (! is_array($snapshot)) {
            return null;
        }

        $rawErrors =
            $snapshot['errors']
            ?? [];

        $rawWarnings =
            $snapshot['warnings']
            ?? [];

        return [
            'valid' =>
                ($snapshot['valid'] ?? false)
                === true,

            'error_count' =>
                is_array($rawErrors)
                    ? count($rawErrors)
                    : 0,

            'warning_count' =>
                is_array($rawWarnings)
                    ? count($rawWarnings)
                    : 0,

            'errors' =>
                $this->safeMessages(
                    $rawErrors
                ),

            'warnings' =>
                $this->safeMessages(
                    $rawWarnings
                ),
        ];
    }

    /**
     * Bound UI feedback to avoid exposing arbitrary internal payloads
     * or creating an unbounded HTTP/Inertia response.
     *
     * @return array<int,string>
     */
    private function safeMessages(
        mixed $messages
    ): array {
        if (! is_array($messages)) {
            return [];
        }

        $safe = [];

        foreach ($messages as $message) {
            if (! is_string($message)) {
                continue;
            }

            $message =
                trim(
                    $message
                );

            if ($message === '') {
                continue;
            }

            $safe[] =
                strlen($message) > 1000
                    ? substr(
                        $message,
                        0,
                        997
                    ).'...'
                    : $message;

            if (count($safe) >= 20) {
                break;
            }
        }

        return array_values(
            array_unique(
                $safe
            )
        );
    }

    private function actions(
        ?array $session
    ): array {
        if ($session === null) {
            return [
                'can_start_or_resume' =>
                    true,

                'can_start_new_session' =>
                    true,

                'can_edit_domains' =>
                    false,

                'can_resolve' =>
                    false,

                'can_materialize' =>
                    false,
            ];
        }

        $status =
            (string) (
                $session['status']
                ?? ''
            );

        $allResolved =
            ($session[
                'all_logical_decisions_resolved'
            ] ?? false)
            === true;

        $relationValid =
            (
                $session[
                    'relational_validation'
                ]['valid']
                ?? false
            )
            === true;

        return [
            'can_start_or_resume' =>
                $status
                !== DataTransformationBiIntakeSession
                    ::STATUS_FINALIZING,

            'can_start_new_session' =>
                in_array(
                    $status,
                    [
                        DataTransformationBiIntakeSession
                            ::STATUS_FINALIZED,

                        DataTransformationBiIntakeSession
                            ::STATUS_FAILED,

                        DataTransformationBiIntakeSession
                            ::STATUS_CANCELLED,
                    ],
                    true
                ),

            'can_edit_domains' =>
                in_array(
                    $status,
                    [
                        DataTransformationBiIntakeSession
                            ::STATUS_DRAFT,

                        DataTransformationBiIntakeSession
                            ::STATUS_READY,
                    ],
                    true
                ),

            'can_resolve' =>
                $status
                    === DataTransformationBiIntakeSession
                        ::STATUS_DRAFT
                && $allResolved,

            'can_materialize' =>
                $status
                    === DataTransformationBiIntakeSession
                        ::STATUS_READY
                && $relationValid,
        ];
    }

    private function assertRequest(
        TransformationImplementationRequest $request
    ): void {
        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
            || (int) $request->company_id <= 0
            || (string) $request->capability_key
                !== 'data_transformation_bi'
        ) {
            throw new RuntimeException(
                'La solicitud no pertenece a Transformación de Datos para BI.'
            );
        }
    }
}
