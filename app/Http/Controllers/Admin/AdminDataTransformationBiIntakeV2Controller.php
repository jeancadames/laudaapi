<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiIntakeV2DomainDeliveryService;
use App\Services\Diagnosis\DataTransformationBiSourceDomainRegistry;
use App\Services\Diagnosis\DataTransformationBiSourceDomainUploadService;
use App\Services\Diagnosis\DataTransformationBiSqlServerExtractionAssistant;
use App\Services\Diagnosis\DataTransformationBiIntakeV2SessionResolutionService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2SessionService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2StagingMaterializationService;
use App\Services\Diagnosis\DataTransformationBiIntakeV2StateService;
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
}
