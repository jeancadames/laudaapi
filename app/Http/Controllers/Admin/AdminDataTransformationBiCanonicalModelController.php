<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiCanonicalEntity;
use App\Models\DataTransformationBiCanonicalRegistryVersion;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiCanonicalModelService;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AdminDataTransformationBiCanonicalModelController
    extends Controller
{
    /**
     * Read-only Admin LAUDA workspace.
     *
     * GET never creates a registry, draft, entity, field,
     * relationship or mapping.
     */
    public function workspace(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        return $this->workspaceResponse(
            $implementationRequest,
            $actor,
            $service
        );
    }

    /**
     * Explicitly create or reuse the mutable draft version.
     */
    public function prepareDraft(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        try {
            $registry =
                $service->prepareDraft(
                    $implementationRequest,
                    $actor
                );
        } catch (ValidationException $exception) {
            return $this->validationError(
                $exception,
                'No se pudo preparar el modelo canónico.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Modelo canónico preparado correctamente.',

            'registry_id' =>
                (int) $registry->getKey(),

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
    }

    /**
     * Add one dynamic entity to a draft registry.
     */
    public function createEntity(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $registryId,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $registry =
            $this->scopedRegistry(
                $implementationRequest,
                $registryId
            );

        $validated =
            $request->validate([
                'entity_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                ],

                'label' => [
                    'required',
                    'string',
                    'max:191',
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        try {
            $entity =
                $service->createEntity(
                    $implementationRequest,
                    $registry,
                    $validated,
                    $actor
                );
        } catch (ValidationException $exception) {
            return $this->validationError(
                $exception,
                'No se pudo crear la entidad canónica.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Entidad canónica creada correctamente.',

            'entity_id' =>
                (int) $entity->getKey(),

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
    }

    /**
     * Replace the complete field definition of one draft entity.
     */
    public function replaceFields(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $registryId,
        int $entityId,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $registry =
            $this->scopedRegistry(
                $implementationRequest,
                $registryId
            );

        $entity =
            $this->scopedEntity(
                $implementationRequest,
                $registry,
                $entityId
            );

        $validated =
            $request->validate([
                'fields' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'fields.*' => [
                    'required',
                    'array',
                ],

                'fields.*.field_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                    'distinct',
                ],

                'fields.*.label' => [
                    'required',
                    'string',
                    'max:191',
                ],

                'fields.*.data_type' => [
                    'required',
                    'string',
                    'in:text,integer,decimal,boolean,date,datetime',
                ],

                'fields.*.required' => [
                    'required',
                    'boolean',
                ],

                'fields.*.is_identity' => [
                    'required',
                    'boolean',
                ],

                'fields.*.description' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        try {
            $service->replaceFields(
                $implementationRequest,
                $registry,
                $entity,
                $validated['fields'],
                $actor
            );
        } catch (ValidationException $exception) {
            return $this->validationError(
                $exception,
                'No se pudieron reemplazar los campos canónicos.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Campos canónicos actualizados correctamente.',

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
    }

    /**
     * Replace all relationships of the draft registry.
     *
     * An empty array is valid and explicitly removes all relationships.
     */
    public function replaceRelationships(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $registryId,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $registry =
            $this->scopedRegistry(
                $implementationRequest,
                $registryId
            );

        $validated =
            $request->validate([
                'relationships' => [
                    'present',
                    'array',
                ],

                'relationships.*' => [
                    'required',
                    'array',
                ],

                'relationships.*.from_entity_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                ],

                'relationships.*.from_field_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                ],

                'relationships.*.to_entity_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                ],

                'relationships.*.to_field_key' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z][a-z0-9_]{0,99}$/',
                ],

                'relationships.*.relationship_type' => [
                    'required',
                    'string',
                    'in:one_to_one,one_to_many,many_to_one,many_to_many',
                ],

                'relationships.*.label' => [
                    'nullable',
                    'string',
                    'max:191',
                ],

                'relationships.*.description' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        try {
            $service->replaceRelationships(
                $implementationRequest,
                $registry,
                $validated['relationships'],
                $actor
            );
        } catch (ValidationException $exception) {
            return $this->validationError(
                $exception,
                'No se pudieron reemplazar las relaciones canónicas.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Relaciones canónicas actualizadas correctamente.',

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
    }

    /**
     * Publish one immutable canonical registry version.
     */
    public function publish(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $registryId,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $this->assertRequest(
            $implementationRequest
        );

        $registry =
            $this->scopedRegistry(
                $implementationRequest,
                $registryId
            );

        try {
            $published =
                $service->publish(
                    $implementationRequest,
                    $registry,
                    $actor
                );
        } catch (ValidationException $exception) {
            return $this->validationError(
                $exception,
                'No se pudo publicar el modelo canónico.'
            );
        }

        return response()->json([
            'ok' =>
                true,

            'message' =>
                'Modelo canónico publicado correctamente.',

            'registry_id' =>
                (int) $published->getKey(),

            'version' =>
                (int) $published->version,

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
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

    /**
     * Request is only an operational authorization context.
     *
     * Canonical Registry ownership is Company-level.
     */
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

        app(
            DataTransformationBiTenantSourceWorkspaceGate::class
        )->assertCanManage(
            $request
        );
    }

    /**
     * Registry is scoped by Company, not by the request that created it.
     */
    private function scopedRegistry(
        TransformationImplementationRequest $implementationRequest,
        int $registryId
    ): DataTransformationBiCanonicalRegistryVersion {
        $registry =
            DataTransformationBiCanonicalRegistryVersion::query()
                ->whereKey(
                    $registryId
                )
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->first();

        abort_unless(
            $registry !== null,
            404
        );

        return $registry;
    }

    private function scopedEntity(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiCanonicalRegistryVersion $registry,
        int $entityId
    ): DataTransformationBiCanonicalEntity {
        $entity =
            DataTransformationBiCanonicalEntity::query()
                ->whereKey(
                    $entityId
                )
                ->where(
                    'canonical_registry_version_id',
                    (int) $registry->getKey()
                )
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->first();

        abort_unless(
            $entity !== null,
            404
        );

        return $entity;
    }

    private function workspaceResponse(
        TransformationImplementationRequest $implementationRequest,
        User $actor,
        DataTransformationBiCanonicalModelService $service
    ): JsonResponse {
        return response()->json([
            'ok' =>
                true,

            'workspace' =>
                $service->workspace(
                    $implementationRequest,
                    $actor
                ),
        ]);
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
}
