<?php

namespace App\Http\Controllers;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiIntakeActorAuthorizationService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2SessionService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2StateService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetDataUploadService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetStructureService;
use App\Services\Diagnosis\DataTransformationBiSqlServerExtractionAssistant;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceProjection;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AppHubDataTransformationBiSourceWorkspaceController
    extends Controller
{
    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
        private readonly CompanyContextResolver $companyResolver,
        private readonly TenantAccessService $tenantAccessService,
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization,
        private readonly DataTransformationBiIntakeV2StateService
            $stateService
    ) {
    }

    /**
     * Prepare the tenant-owned source workspace.
     *
     * This may create or reuse the pre-staging intake session.
     * It does not materialize staging, profile, normalize or execute BI.
     */
    public function prepareWorkspace(
        Request $request,
        DataTransformationBiIntakeV2SessionService $sessions
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $result =
            $sessions->startOrReuse(
                $implementationRequest,
                $actor
            );

        return $this->tenantJson([
            'ok' =>
                true,

            'message' =>
                ($result['reused'] ?? false)
                    ? 'El workspace de fuentes existente fue reutilizado.'
                    : 'El workspace de fuentes fue preparado correctamente.',

            'state' =>
                $this->stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function createSourceAsset(
        Request $request,
        int $sessionId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        /*
         * Only client-owned source metadata is accepted.
         *
         * No company id, request id, session id, canonical domain,
         * credential or technical mapping can be supplied here.
         */
        $input =
            $request->only([
                'display_name',
                'source_object_name',
                'description',
                'origin_system',
                'owner',
                'delivery_format',
            ]);

        $service->create(
            $implementationRequest,
            $session,
            $input,
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Fuente de datos creada correctamente.'
        );
    }

    public function updateSourceAsset(
        Request $request,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $asset =
            $this->scopedSourceAsset(
                $implementationRequest,
                $session,
                $sourceAssetId
            );

        $input =
            $request->only([
                'display_name',
                'source_object_name',
                'description',
                'origin_system',
                'owner',
                'delivery_format',
            ]);

        $service->update(
            $implementationRequest,
            $session,
            $asset,
            $input,
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Fuente de datos actualizada correctamente.'
        );
    }

    public function reorderSourceAssets(
        Request $request,
        int $sessionId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $validated =
            $request->validate([
                'source_asset_ids' => [
                    'present',
                    'array',
                ],

                'source_asset_ids.*' => [
                    'integer',
                    'min:1',
                    'distinct',
                ],
            ]);

        $service->reorder(
            $implementationRequest,
            $session,
            $validated['source_asset_ids'],
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Orden de fuentes actualizado correctamente.'
        );
    }

    public function archiveSourceAsset(
        Request $request,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $asset =
            $this->scopedSourceAsset(
                $implementationRequest,
                $session,
                $sourceAssetId
            );

        $service->archive(
            $implementationRequest,
            $session,
            $asset,
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Fuente de datos archivada correctamente.'
        );
    }

    public function updateSourceAssetStructure(
        Request $request,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetStructureService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $asset =
            $this->scopedSourceAsset(
                $implementationRequest,
                $session,
                $sourceAssetId
            );

        $validated =
            $request->validate([
                'structure_format' => [
                    'required',
                    'string',
                    'in:field_type_list,sql_server_ddl,other',
                ],

                'structure_text' => [
                    'required',
                    'string',
                    'max:'
                        .DataTransformationBiSourceAssetStructureService
                            ::MAX_STRUCTURE_LENGTH,
                ],
            ]);

        $service->save(
            $implementationRequest,
            $session,
            $asset,
            (string) $validated['structure_format'],
            (string) $validated['structure_text'],
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Estructura de la fuente guardada correctamente.'
        );
    }

    public function uploadSourceAssetData(
        Request $request,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetDataUploadService $service
    ): JsonResponse {
        [
            'actor' => $actor,
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $asset =
            $this->scopedSourceAsset(
                $implementationRequest,
                $session,
                $sourceAssetId
            );

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'
                    .DataTransformationBiSourceAssetDataUploadService
                        ::MAX_UPLOAD_KILOBYTES,
            ],
        ]);

        $file =
            $request->file(
                'file'
            );

        if (
            $file === null
            || ! $file->isValid()
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'El archivo recibido no es válido.',
                ],
            ]);
        }

        $service->persist(
            $implementationRequest,
            $session,
            $asset,
            $file,
            $actor
        );

        return $this->stateResponse(
            $implementationRequest,
            'Archivo CSV/XLSX recibido y analizado correctamente.'
        );
    }

    public function previewSourceAssetSqlServerExtraction(
        Request $request,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSqlServerExtractionAssistant $assistant
    ): JsonResponse {
        [
            'implementation_request' => $implementationRequest,
        ] = $this->tenantContext(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $asset =
            $this->scopedSourceAsset(
                $implementationRequest,
                $session,
                $sourceAssetId
            );

        $structureText =
            trim(
                (string) (
                    $asset->structure_text
                    ?? ''
                )
            );

        if ($structureText === '') {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'Guarda primero la estructura de esta fuente.',
                ],
            ]);
        }

        $validated =
            $request->validate([
                'schema_name' => [
                    'required',
                    'string',
                    'max:128',
                ],

                'table_name' => [
                    'nullable',
                    'string',
                    'max:128',
                ],
            ]);

        $tableName =
            trim(
                (string) (
                    $validated['table_name']
                    ?? ''
                )
            );

        if ($tableName === '') {
            $tableName =
                trim(
                    (string) $asset->source_object_name
                );
        }

        if ($tableName === '') {
            throw ValidationException::withMessages([
                'table_name' => [
                    'Indica la tabla de origen antes de generar la consulta.',
                ],
            ]);
        }

        try {
            $preview =
                $assistant->preview(
                    trim(
                        (string) $validated['schema_name']
                    ),
                    $tableName,
                    $structureText
                );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    $exception->getMessage(),
                ],
            ]);
        }

        return $this->tenantJson([
            'ok' =>
                true,

            'preview' =>
                $preview,
        ]);
    }

    /**
     * Resolve the exact tenant/company/request context server-side.
     *
     * @return array{
     *     actor:User,
     *     implementation_request:TransformationImplementationRequest
     * }
     */
    private function tenantContext(
        Request $request
    ): array {
        $actor =
            $request->user();

        abort_unless(
            $actor instanceof User
            && (string) ($actor->role ?? '')
                === 'subscriber',
            403
        );

        $subscriberId =
            (int) (
                $this->subscriberResolver->resolve(
                    $actor
                )
                ?? 0
            );

        abort_unless(
            $subscriberId > 0,
            403
        );

        $tenantAccess =
            $this->tenantAccessService->resolve(
                $actor,
                $subscriberId
            );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            ),
            403
        );

        $company =
            $this->companyResolver->resolve(
                $actor,
                $subscriberId
            );

        abort_unless(
            $company !== null
            && (int) ($company->subscriber_id ?? 0)
                === $subscriberId,
            404
        );

        /*
         * The browser never supplies the implementation request id.
         *
         * The latest BI request for the resolved Company is selected
         * server-side, matching the App Hub read-model direction.
         */
        $implementationRequest =
            TransformationImplementationRequest::query()
                ->where(
                    'company_id',
                    (int) $company->getKey()
                )
                ->where(
                    'capability_key',
                    'data_transformation_bi'
                )
                ->orderByDesc(
                    'attempt'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $implementationRequest !== null,
            404
        );

        /*
         * Fail closed to the known lifecycle states in which the BI
         * implementation request still exists as a managed workflow.
         *
         * Cancelled requests cannot manage client source intake.
         */
        abort_unless(
            in_array(
                (string) $implementationRequest->status,
                [
                    TransformationImplementationRequestContract
                        ::STATUS_REQUESTED,

                    TransformationImplementationRequestContract
                        ::STATUS_UNDER_LAUDA_REVIEW,

                    TransformationImplementationRequestContract
                        ::STATUS_DEFINITION_PREPARATION,

                    TransformationImplementationRequestContract
                        ::STATUS_AWAITING_TENANT_REVIEW,

                    TransformationImplementationRequestContract
                        ::STATUS_CHANGES_REQUESTED,

                    TransformationImplementationRequestContract
                        ::STATUS_DEFINITION_AGREED,

                    TransformationImplementationRequestContract
                        ::STATUS_READY_FOR_COMMERCIAL,
                ],
                true
            ),
            409
        );

        /*
         * Defense in depth.
         *
         * The shared authorization boundary independently confirms the
         * exact subscriber-admin + Company relationship for this Request.
         */
        $this->authorization->assertCanManage(
            $implementationRequest,
            $actor
        );

        app(
            DataTransformationBiTenantSourceWorkspaceGate::class
        )->assertCanManage(
            $implementationRequest
        );

        return [
            'actor' =>
                $actor,

            'implementation_request' =>
                $implementationRequest,
        ];
    }

    private function scopedSession(
        TransformationImplementationRequest $implementationRequest,
        int $sessionId
    ): DataTransformationBiIntakeSession {
        return DataTransformationBiIntakeSession::query()
            ->whereKey(
                $sessionId
            )
            ->where(
                'company_id',
                (int) $implementationRequest->company_id
            )
            ->where(
                'transformation_implementation_request_id',
                (int) $implementationRequest->getKey()
            )
            ->firstOrFail();
    }

    private function scopedSourceAsset(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        int $sourceAssetId
    ): DataTransformationBiSourceAsset {
        return DataTransformationBiSourceAsset::query()
            ->whereKey(
                $sourceAssetId
            )
            ->where(
                'company_id',
                (int) $implementationRequest->company_id
            )
            ->where(
                'data_transformation_bi_intake_session_id',
                (int) $session->getKey()
            )
            ->firstOrFail();
    }

    private function stateResponse(
        TransformationImplementationRequest $implementationRequest,
        string $message
    ): JsonResponse {
        return $this->tenantJson([
            'ok' =>
                true,

            'message' =>
                $message,

            'state' =>
                $this->stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }


    /**
     * Apply the tenant-safe projection at the final JSON boundary.
     *
     * This protects initial state responses and mutation responses,
     * including nested service payloads.
     *
     * @param array<string,mixed> $payload
     */
    private function tenantJson(
        array $payload,
        int $status = 200,
        array $headers = [],
        int $options = 0
    ): JsonResponse {
        $payload =
            $this->sanitizeTenantPayload(
                $payload
            );

        return response()->json(
            $payload,
            $status,
            $headers,
            $options
        );
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>
     */
    private function sanitizeTenantPayload(
        array $payload
    ): array {
        $projection =
            app(
                DataTransformationBiTenantSourceWorkspaceProjection::class
            );

        foreach (
            $payload
            as $key => $value
        ) {
            if (
                $key === 'state'
                && is_array($value)
            ) {
                $payload[$key] =
                    $projection->workspaceState(
                        $value
                    );

                continue;
            }

            if ($key === 'source_asset') {
                $payload[$key] =
                    $projection->sourceAsset(
                        $value
                    );

                continue;
            }

            if ($key === 'source_assets') {
                $payload[$key] =
                    $projection->sourceAssets(
                        $value
                    );

                continue;
            }

            if ($key === 'data_file') {
                $payload[$key] =
                    $projection->dataFile(
                        $value
                    );

                continue;
            }

            if (is_array($value)) {
                $payload[$key] =
                    $this->sanitizeTenantPayload(
                        $value
                    );
            }
        }

        return $payload;
    }

}