<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiSourceAssetStructureService
{
    public const MAX_STRUCTURE_LENGTH = 50000;

    /**
     * Persist one client-native structural definition.
     *
     * The supplied structure is treated strictly as inert text.
     * This service never executes SQL and never connects to
     * the client's source system.
     */
    public function save(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        string $structureFormat,
        string $structureText,
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

        $format =
            trim(
                $structureFormat
            );

        $text =
            trim(
                $structureText
            );

        if (
            ! in_array(
                $format,
                [
                    DataTransformationBiSourceAsset
                        ::STRUCTURE_FORMAT_FIELD_TYPE_LIST,

                    DataTransformationBiSourceAsset
                        ::STRUCTURE_FORMAT_SQL_SERVER_DDL,

                    DataTransformationBiSourceAsset
                        ::STRUCTURE_FORMAT_OTHER,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'structure_format' => [
                    'Selecciona un formato de estructura válido.',
                ],
            ]);
        }

        if ($text === '') {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'Indica la estructura de la tabla o archivo.',
                ],
            ]);
        }

        if (
            mb_strlen(
                $text
            )
            > self::MAX_STRUCTURE_LENGTH
        ) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'La estructura excede el máximo permitido de 50,000 caracteres.',
                ],
            ]);
        }

        $lineCount =
            preg_match_all(
                '/\R/u',
                $text
            );

        if ($lineCount === false) {
            $lineCount = 0;
        }

        return DB::transaction(
            function () use (
                $asset,
                $actor,
                $format,
                $text,
                $lineCount
            ): DataTransformationBiSourceAsset {
                $asset->forceFill([
                    'structure_format' =>
                        $format,

                    'structure_text' =>
                        $text,

                    'structure_status' =>
                        DataTransformationBiSourceAsset
                            ::STRUCTURE_PROVIDED,

                    'structure_snapshot' => [
                        'format' =>
                            $format,

                        'character_count' =>
                            mb_strlen(
                                $text
                            ),

                        'line_count' =>
                            $lineCount + 1,
                    ],

                    'structure_analyzed_at' =>
                        null,

                    'status' =>
                        (string) $asset->status
                            === DataTransformationBiSourceAsset
                                ::STATUS_DRAFT
                            ? DataTransformationBiSourceAsset
                                ::STATUS_ACTIVE
                            : $asset->status,

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ]);

                $asset->save();

                return $asset->fresh()
                    ?? $asset;
            }
        );
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
