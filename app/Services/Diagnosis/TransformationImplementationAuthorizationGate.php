<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationAuthorization;
use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use Illuminate\Auth\Access\AuthorizationException;

final class TransformationImplementationAuthorizationGate
{
    /**
     * Resolve the single active modern implementation authorization
     * for one exact Request.
     *
     * This service is an assertion/read boundary only.
     *
     * It does NOT:
     * - create or revoke authorizations;
     * - mutate Request;
     * - mutate Definition;
     * - mutate Commercial Engagement;
     * - set ready_for_execution;
     * - set execution_started;
     * - invoke historical execution;
     * - mutate Canonical or Mapping.
     */
    public function assertActiveForRequest(
        TransformationImplementationRequest $request,
        bool $lockForUpdate = false
    ): TransformationImplementationAuthorization {
        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
        ) {
            throw new AuthorizationException(
                'La autorización requiere una solicitud persistida.'
            );
        }

        if (
            (string) $request->capability_key
            !== 'data_transformation_bi'
        ) {
            throw new AuthorizationException(
                'La autorización no pertenece a Transformación de Datos para BI.'
            );
        }

        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

            || $request->ready_for_commercial_at
            === null
        ) {
            throw new AuthorizationException(
                'La solicitud no conserva el boundary ready_for_commercial requerido.'
            );
        }

        $query =
            TransformationImplementationAuthorization::query()
                ->where(
                    'transformation_implementation_request_id',
                    $request->getKey()
                )
                ->where(
                    'company_id',
                    $request->company_id
                )
                ->where(
                    'transformation_implementation_phase_capability_id',
                    $request
                        ->transformation_implementation_phase_capability_id
                )
                ->where(
                    'capability_key',
                    $request->capability_key
                )
                ->where(
                    'status',
                    TransformationImplementationAuthorization::STATUS_AUTHORIZED
                )
                ->whereNotNull(
                    'authorized_at'
                )
                ->whereNull(
                    'revoked_at'
                )
                ->with([
                    'commercialEngagement',
                    'definition',
                ]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $authorizations =
            $query->get();

        /*
         * Never choose "latest" when authorization is ambiguous.
         *
         * Exactly one active authorization must govern one Request
         * before implementation writes are allowed.
         */
        if ($authorizations->count() !== 1) {
            throw new AuthorizationException(
                $authorizations->isEmpty()
                    ? 'La implementación requiere una autorización activa.'
                    : 'La solicitud posee más de una autorización activa y no puede continuar hasta resolver la ambigüedad.'
            );
        }

        /** @var TransformationImplementationAuthorization $authorization */
        $authorization =
            $authorizations->first();

        if (! $authorization->isActive()) {
            throw new AuthorizationException(
                'La autorización de implementación no está activa.'
            );
        }

        $engagement =
            $authorization->commercialEngagement;

        $definition =
            $authorization->definition;

        $this->assertEngagement(
            $request,
            $authorization,
            $engagement
        );

        $this->assertDefinition(
            $request,
            $authorization,
            $definition
        );

        $this->assertAuthorizationSnapshot(
            $request,
            $authorization,
            $engagement,
            $definition
        );

        return $authorization;
    }

    private function assertEngagement(
        TransformationImplementationRequest $request,
        TransformationImplementationAuthorization $authorization,
        ?TransformationImplementationCommercialEngagement $engagement
    ): void {
        if (
            $engagement === null

            || (int) $engagement->getKey()
            !== (int) $authorization
                ->transformation_implementation_commercial_engagement_id

            || $engagement->status
            !== TransformationImplementationCommercialEngagement::STATUS_ACCEPTED

            || $engagement->accepted_at
            === null

            || $engagement->accepted_by_user_id
            === null

            || $engagement->presented_at
            === null

            || $engagement->presented_by_user_id
            === null

            || (int) $engagement
                ->transformation_implementation_request_id
            !== (int) $request->getKey()

            || (int) $engagement->company_id
            !== (int) $request->company_id

            || (int) $engagement
                ->transformation_implementation_phase_capability_id
            !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) $engagement->capability_key
            ) !== trim(
                (string) $request->capability_key
            )
        ) {
            throw new AuthorizationException(
                'La autorización activa no corresponde a una aceptación comercial válida para esta solicitud.'
            );
        }
    }

    private function assertDefinition(
        TransformationImplementationRequest $request,
        TransformationImplementationAuthorization $authorization,
        ?TransformationImplementationDefinition $definition
    ): void {
        if (
            $definition === null

            || (int) $definition->getKey()
            !== (int) $authorization
                ->transformation_implementation_definition_id

            || (int) $definition
                ->transformation_implementation_request_id
            !== (int) $request->getKey()

            || (int) $definition->company_id
            !== (int) $request->company_id

            || (int) $definition
                ->transformation_implementation_plan_id
            !== (int) $request
                ->transformation_implementation_plan_id

            || (int) $definition
                ->transformation_implementation_phase_capability_id
            !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) $definition->capability_key
            ) !== trim(
                (string) $request->capability_key
            )

            || $definition->status
            !== TransformationImplementationDefinition::STATUS_READY

            || data_get(
                $definition->readiness,
                'state'
            ) !== 'ready'

            || data_get(
                $definition->readiness,
                'definition_ready'
            ) !== true

            || data_get(
                $definition->readiness,
                'technical_readiness'
            ) !== true

            || data_get(
                $definition->readiness,
                'ready_for_execution'
            ) !== false

            || data_get(
                $definition->readiness,
                'execution_started'
            ) !== false

            || $definition->ready_at
            === null
        ) {
            throw new AuthorizationException(
                'La autorización activa no corresponde a la Definition funcional vigente de esta solicitud.'
            );
        }
    }

    private function assertAuthorizationSnapshot(
        TransformationImplementationRequest $request,
        TransformationImplementationAuthorization $authorization,
        TransformationImplementationCommercialEngagement $engagement,
        TransformationImplementationDefinition $definition
    ): void {
        $snapshot =
            is_array(
                $authorization->authorization_snapshot
            )
                ? $authorization->authorization_snapshot
                : [];

        if (
            data_get(
                $snapshot,
                'request.id'
            ) !== (int) $request->getKey()

            || data_get(
                $snapshot,
                'request.status'
            ) !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

            || data_get(
                $snapshot,
                'definition.id'
            ) !== (int) $definition->getKey()

            || data_get(
                $snapshot,
                'definition.version'
            ) !== (int) $definition->version

            || data_get(
                $snapshot,
                'definition.definition_ready'
            ) !== true

            || data_get(
                $snapshot,
                'definition.technical_readiness'
            ) !== true

            || data_get(
                $snapshot,
                'definition.ready_for_execution'
            ) !== false

            || data_get(
                $snapshot,
                'definition.execution_started'
            ) !== false

            || data_get(
                $snapshot,
                'commercial_engagement.id'
            ) !== (int) $engagement->getKey()

            || data_get(
                $snapshot,
                'commercial_engagement.version'
            ) !== (int) $engagement->version

            || data_get(
                $snapshot,
                'commercial_engagement.status'
            ) !== TransformationImplementationCommercialEngagement::STATUS_ACCEPTED

            || data_get(
                $snapshot,
                'commercial_engagement.accepted_by_user_id'
            ) !== (int) $engagement->accepted_by_user_id

            || data_get(
                $snapshot,
                'scope.company_id'
            ) !== (int) $request->company_id

            || data_get(
                $snapshot,
                'scope.phase_capability_id'
            ) !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) data_get(
                    $snapshot,
                    'scope.capability_key',
                    ''
                )
            ) !== trim(
                (string) $request->capability_key
            )

            || data_get(
                $snapshot,
                'authorization_boundary.commercial_acceptance'
            ) !== true

            || data_get(
                $snapshot,
                'authorization_boundary.implementation_authorized'
            ) !== true

            || data_get(
                $snapshot,
                'authorization_boundary.ready_for_execution_mutated'
            ) !== false

            || data_get(
                $snapshot,
                'authorization_boundary.execution_started'
            ) !== false

            || data_get(
                $snapshot,
                'authorization_boundary.canonical_write'
            ) !== false
        ) {
            throw new AuthorizationException(
                'La evidencia congelada de autorización no corresponde al alcance actual de implementación.'
            );
        }
    }
}
