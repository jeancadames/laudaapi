<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiIntakeV2DomainDeliveryService;
use App\Services\Diagnosis\DataTransformationBiSourceDomainRegistry;
use App\Services\Diagnosis\DataTransformationBiSourceAssetService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetStructureService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetDataUploadService;
use App\Services\Diagnosis\DataTransformationBiSourceAssetProfilingDispatchService;
use App\Services\Diagnosis\DataTransformationBiSourceDomainUploadService;
use App\Services\Diagnosis\DataTransformationBiSqlServerExtractionAssistant;
use App\Services\Diagnosis\DataTransformationBiIntakeV2SessionResolutionService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2SessionService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2StagingMaterializationService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2StateService;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminDataTransformationBiIntakeV2Controller extends Controller
{
    public function startSession(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeV2SessionService $sessionService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        try {
            $sessionService->startOrReuse(
                $implementationRequest,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo iniciar la sesión de intake.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Sesión de intake preparada correctamente.',

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function uploadDomain(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        string $domain,
        DataTransformationBiIntakeV2DomainDeliveryService $deliveryService,
        DataTransformationBiSourceDomainUploadService $sourceUploadService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $sourceNative =
            DataTransformationBiSourceDomainRegistry
                ::supports(
                    $domain
                );

        $validator =
            Validator::make(
                $request->all(),
                [
                    'file' => [
                        'required',
                        'file',
                        $sourceNative
                            ? 'max:'
                                .DataTransformationBiSourceDomainUploadService
                                    ::MAX_UPLOAD_KILOBYTES
                            : 'max:2048',
                    ],
                ]
            );

        if ($validator->fails()) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'No se pudo recibir el archivo del dominio.',

                    'errors' =>
                        $validator
                            ->errors()
                            ->toArray(),
                ],
                422
            );
        }

        $file =
            $request->file(
                'file'
            );

        if (
            $file === null
            || ! $file->isValid()
        ) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'El archivo recibido no es válido.',

                    'errors' => [
                        'file' => [
                            'El archivo recibido no es válido.',
                        ],
                    ],
                ],
                422
            );
        }

        $sourceFilePayload =
            null;

        try {
            if ($sourceNative) {
                $sourceFilePayload =
                    $sourceUploadService
                        ->persistSourceDomain(
                            $implementationRequest,
                            $session,
                            $domain,
                            $file,
                            $actor
                        );
            } else {
                $deliveryService
                    ->persistUploadedDomain(
                        $implementationRequest,
                        $session,
                        $domain,
                        $file,
                        $actor
                    );
            }
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                $sourceNative
                    ? 'No se pudo procesar el archivo fuente.'
                    : 'El dominio no superó la validación.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                $sourceNative
                    ? 'Archivo fuente recibido y analizado correctamente.'
                    : 'Dominio cargado y validado correctamente.',

            'source_file' =>
                $sourceFilePayload,

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function noDataDomain(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        string $domain,
        DataTransformationBiIntakeV2DomainDeliveryService $deliveryService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $deliveryService
                ->persistNoDataDomain(
                    $implementationRequest,
                    $session,
                    $domain,
                    $actor
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo marcar el dominio sin datos.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Dominio marcado como sin datos.',

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function carryForwardDomain(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        string $domain,
        DataTransformationBiIntakeV2DomainDeliveryService $deliveryService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $deliveryService
                ->persistCarryForwardDomain(
                    $implementationRequest,
                    $session,
                    $domain,
                    $actor
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo reutilizar el dominio del dataset anterior.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Dominio reutilizado desde el dataset canónico anterior.',

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function resolveSession(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiIntakeV2SessionResolutionService $resolutionService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $result =
                $resolutionService->resolve(
                    $implementationRequest,
                    $session,
                    $actor
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo resolver la sesión de intake.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                ($result['valid'] ?? false)
                === true,

            'message' =>
                ($result['valid'] ?? false)
                === true
                    ? 'Validación relacional completada correctamente.'
                    : 'La sesión presenta incidencias relacionales.',

            'resolution' =>
                $result,

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }

    public function materializeSession(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiIntakeV2StagingMaterializationService $materializationService,
        DataTransformationBiIntakeV2StateService $stateService
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $result =
                $materializationService
                    ->materialize(
                        $implementationRequest,
                        $session,
                        $actor
                    );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo materializar el intake en staging.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                ($result['reused'] ?? false)
                    ? 'El staging de esta sesión ya existía y fue reutilizado.'
                    : 'Sesión materializada correctamente en staging.',

            /*
             * Same logical shape expected by the existing post-staging
             * profiling UI: this is the completed intake batch summary.
             */
            'ingestion' =>
                $result,

            'state' =>
                $stateService->forRequest(
                    $implementationRequest
                ),
        ]);
    }


    /*
     * D15E_DOMAIN_TEMPLATE_CONTROLLER
     */
    public function createSourceAsset(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $validated =
            $request->validate([
                'display_name' => [
                    'required',
                    'string',
                    'max:191',
                ],
                'source_object_name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'description' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
                'origin_system' => [
                    'nullable',
                    'string',
                    'max:191',
                ],
                'owner' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:191',
                ],
                'structure_format' => [
                    'nullable',
                    'string',
                    'max:64',
                ],
                'delivery_format' => [
                    'nullable',
                    'string',
                    'in:csv,xlsx',
                ],
            ]);

        $asset =
            $service->create(
                $implementationRequest,
                $session,
                $validated,
                $actor
            );

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Fuente de datos creada correctamente.',

            'source_asset' =>
                $this->sourceAssetPayload(
                    $asset
                ),
        ]);
    }

    public function updateSourceAsset(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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
                'display_name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:191',
                ],
                'source_object_name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                ],
                'description' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:4000',
                ],
                'origin_system' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:191',
                ],
                'owner' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:191',
                ],
                'structure_format' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:64',
                ],
                'delivery_format' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'in:csv,xlsx',
                ],
            ]);

        $asset =
            $service->update(
                $implementationRequest,
                $session,
                $asset,
                $validated,
                $actor
            );

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Fuente de datos actualizada correctamente.',

            'source_asset' =>
                $this->sourceAssetPayload(
                    $asset
                ),
        ]);
    }

    public function reorderSourceAssets(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Orden de fuentes actualizado correctamente.',
        ]);
    }

    public function archiveSourceAsset(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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

        $asset =
            $service->archive(
                $implementationRequest,
                $session,
                $asset,
                $actor
            );

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Fuente de datos archivada correctamente.',

            'source_asset' =>
                $this->sourceAssetPayload(
                    $asset
                ),
        ]);
    }

    public function uploadSourceAssetData(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetDataUploadService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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

        $validator =
            Validator::make(
                $request->all(),
                [
                    'file' => [
                        'required',
                        'file',
                        'max:'
                            .DataTransformationBiSourceAssetDataUploadService
                                ::MAX_UPLOAD_KILOBYTES,
                    ],
                ]
            );

        if ($validator->fails()) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'No se pudo recibir el archivo de la fuente.',

                    'errors' =>
                        $validator
                            ->errors()
                            ->toArray(),
                ],
                422
            );
        }

        $file =
            $request->file(
                'file'
            );

        if (
            $file === null
            || ! $file->isValid()
        ) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'El archivo recibido no es válido.',

                    'errors' => [
                        'file' => [
                            'El archivo recibido no es válido.',
                        ],
                    ],
                ],
                422
            );
        }

        try {
            $result =
                $service->persist(
                    $implementationRequest,
                    $session,
                    $asset,
                    $file,
                    $actor
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo procesar el archivo de la fuente.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        $updatedAsset =
            $result['source_asset']
            ?? null;

        $dataFile =
            $result['data_file']
            ?? null;

        if (
            ! $updatedAsset
                instanceof DataTransformationBiSourceAsset
            || ! is_array(
                $dataFile
            )
        ) {
            return $this->runtimeError(
                new RuntimeException(
                    'El servicio de carga no devolvió un resultado válido.'
                )
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Archivo CSV/XLSX recibido y analizado correctamente.',

            'source_asset' =>
                $this->sourceAssetPayload(
                    $updatedAsset
                ),

            'data_file' =>
                $dataFile,
        ]);
    }

    /**
     * Execute LAUDA-owned technical profiling for one client-native source.
     *
     * This action deliberately does not assign a canonical domain and does
     * not perform mapping, normalization or staging materialization.
     */
    public function profileSourceAsset(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetProfilingDispatchService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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

        try {
            $queuedAsset =
                $service->dispatch(
                    $implementationRequest,
                    $session,
                    $asset,
                    $actor
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo iniciar el profiling técnico de la fuente.'
            );
        } catch (
            RuntimeException $exception
        ) {
            return $this->runtimeError(
                $exception
            );
        }

        return response()->json(
            [
                'ok' =>
                    true,

                'message' =>
                    'Profiling técnico encolado correctamente.',

                'source_asset' =>
                    $this->sourceAssetPayload(
                        $queuedAsset
                    ),
            ],
            202
        );
    }

    public function profileSourceAssetStatus(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId
    ): JsonResponse {
        $this->actor(
            $request
        );

        $this->assertRequest(
            $implementationRequest
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

        return response()->json([
            'ok' =>
                true,

            'source_asset' =>
                $this->sourceAssetPayload(
                    $asset->fresh()
                    ?? $asset
                ),
        ]);
    }


    public function updateSourceAssetStructure(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSourceAssetStructureService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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
                    'max:50000',
                ],
            ]);

        $asset =
            $service->save(
                $implementationRequest,
                $session,
                $asset,
                (string) $validated['structure_format'],
                (string) $validated['structure_text'],
                $actor
            );

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Estructura de la fuente guardada correctamente.',

            'source_asset' =>
                $this->sourceAssetPayload(
                    $asset
                ),
        ]);
    }

    public function previewSourceAssetSqlServerExtraction(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $sourceAssetId,
        DataTransformationBiSqlServerExtractionAssistant $assistant
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
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

        return response()->json([
            'ok' =>
                true,

            'source_asset_id' =>
                (int) $asset->getKey(),

            'preview' =>
                $preview,
        ]);
    }

    public function previewSqlServerExtraction(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        string $domain,
        DataTransformationBiSqlServerExtractionAssistant $assistant
    ): JsonResponse {
        $this->actor(
            $request
        );

        $this->assertRequest(
            $implementationRequest
        );

        abort_unless(
            DataTransformationBiSourceDomainRegistry
                ::supports(
                    $domain
                ),
            404
        );

        $validator =
            Validator::make(
                $request->all(),
                [
                    'schema_name' => [
                        'required',
                        'string',
                        'max:128',
                    ],

                    'table_name' => [
                        'required',
                        'string',
                        'max:128',
                    ],

                    'structure_text' => [
                        'required',
                        'string',
                        'max:'
                        .DataTransformationBiSqlServerExtractionAssistant
                            ::MAX_STRUCTURE_LENGTH,
                    ],
                ]
            );

        if ($validator->fails()) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'No se pudo interpretar la estructura de SQL Server.',

                    'errors' =>
                        $validator
                            ->errors()
                            ->toArray(),
                ],
                422
            );
        }

        try {
            $preview =
                $assistant->preview(
                    (string) $request->input(
                        'schema_name'
                    ),
                    (string) $request->input(
                        'table_name'
                    ),
                    (string) $request->input(
                        'structure_text'
                    )
                );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo generar la consulta de extracción.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Consulta de extracción preparada correctamente.',

            'domain' =>
                $domain,

            'preview' =>
                $preview,
        ]);
    }

    public function downloadDomainCsvTemplate(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        string $domain,
        \App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService $templates
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $this->actor(
            $request
        );

        $this->assertRequest(
            $implementationRequest
        );

        abort_unless(
            in_array(
                $domain,
                \App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            ),
            404
        );

        $path =
            $templates
                ->createDomainCsvTemporaryFile(
                    $domain
                );

        return response()
            ->download(
                $path,
                $templates
                    ->domainCsvFilename(
                        $domain
                    ),
                [
                    'Content-Type' =>
                        'text/csv; charset=UTF-8',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function downloadDomainXlsxTemplate(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        string $domain,
        \App\Services\Diagnosis\DataTransformationBiStandardIntakeTemplateService $templates
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $this->actor(
            $request
        );

        $this->assertRequest(
            $implementationRequest
        );

        abort_unless(
            in_array(
                $domain,
                \App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            ),
            404
        );

        $path =
            $templates
                ->createDomainXlsxTemporaryFile(
                    $domain
                );

        return response()
            ->download(
                $path,
                $templates
                    ->domainXlsxFilename(
                        $domain
                    ),
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    private function actor(
        Request $request
    ): User {
        $actor =
            $request->user();

        abort_unless(
            $actor instanceof User
            && (string) $actor->role === 'admin',
            403
        );

        return $actor;
    }

    private function assertRequest(
        TransformationImplementationRequest $request
    ): void {
        abort_unless(
            $request->exists
            && (int) $request->getKey() > 0
            && (int) $request->company_id > 0
            && (string) $request->capability_key
                === 'data_transformation_bi',
            404
        );

        /*
         * All Admin intake operations share the same lifecycle
         * boundary as Tenant Admin.
         *
         * This also protects controller-level operations that do
         * not delegate to one of the mutation services.
         */
        app(
            DataTransformationBiTenantSourceWorkspaceGate::class
        )->assertCanManage(
            $request
        );
    }

    private function scopedSession(
        TransformationImplementationRequest $request,
        int $sessionId
    ): DataTransformationBiIntakeSession {
        return DataTransformationBiIntakeSession::query()
            ->where(
                'id',
                $sessionId
            )
            ->where(
                'company_id',
                (int) $request->company_id
            )
            ->where(
                'transformation_implementation_request_id',
                (int) $request->getKey()
            )
            ->firstOrFail();
    }

    private function validationError(
        ValidationException $exception,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'ok' =>
                    false,

                'message' =>
                    $message,

                'errors' =>
                    $exception->errors(),
            ],
            422
        );
    }

    private function runtimeError(
        RuntimeException $exception
    ): JsonResponse {
        report(
            $exception
        );

        return response()->json(
            [
                'ok' =>
                    false,

                'message' =>
                    $exception->getMessage(),
            ],
            422
        );
    }

    private function scopedSourceAsset(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        int $sourceAssetId
    ): DataTransformationBiSourceAsset {
        $asset =
            DataTransformationBiSourceAsset::query()
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
                ->first();

        abort_unless(
            $asset !== null,
            404
        );

        return $asset;
    }

    /**
     * Safe HTTP representation of one dynamic source.
     *
     * @return array<string,mixed>
     */
    private function sourceAssetPayload(
        DataTransformationBiSourceAsset $asset
    ): array {
        return [
            'id' =>
                (int) $asset->getKey(),

            'display_name' =>
                (string) $asset->display_name,

            'source_object_name' =>
                (string) $asset->source_object_name,

            'description' =>
                $asset->description !== null
                    ? (string) $asset->description
                    : null,

            'origin_system' =>
                $asset->origin_system !== null
                    ? (string) $asset->origin_system
                    : null,


            'owner' =>
                $asset->owner !== null
                    ? (string) $asset->owner
                    : null,

'structure_format' =>
                $asset->structure_format !== null
                    ? (string) $asset->structure_format
                    : null,

            'structure_text' =>
                $asset->structure_text !== null
                    ? (string) $asset->structure_text
                    : null,

            'delivery_format' =>
                $asset->delivery_format !== null
                    ? (string) $asset->delivery_format
                    : null,

            'status' =>
                (string) $asset->status,

            'structure_status' =>
                (string) $asset->structure_status,

            'data_status' =>
                (string) $asset->data_status,

            'structure_snapshot' =>
                $asset->structure_snapshot,

            'profiling_snapshot' =>
                $asset->profiling_snapshot,

            'profiling_status' =>
                (string) (
                    $asset->profiling_status
                    ?? DataTransformationBiSourceAsset
                        ::PROFILING_IDLE
                ),

            'profiling_queued_at' =>
                $asset->profiling_queued_at
                    ?->toISOString(),

            'profiling_started_at' =>
                $asset->profiling_started_at
                    ?->toISOString(),

            'profiling_finished_at' =>
                $asset->profiling_finished_at
                    ?->toISOString(),

            'sort_order' =>
                (int) $asset->sort_order,

            'structure_analyzed_at' =>
                $asset->structure_analyzed_at
                    ?->toISOString(),

            'data_received_at' =>
                $asset->data_received_at
                    ?->toISOString(),

            'profiled_at' =>
                $asset->profiled_at
                    ?->toISOString(),

            'archived_at' =>
                $asset->archived_at
                    ?->toISOString(),

            'failure_code' =>
                $asset->failure_code !== null
                    ? (string) $asset->failure_code
                    : null,

            'failure_message' =>
                $asset->failure_message !== null
                    ? (string) $asset->failure_message
                    : null,

            'created_at' =>
                $asset->created_at
                    ?->toISOString(),

            'updated_at' =>
                $asset->updated_at
                    ?->toISOString(),
        ];
    }

}
