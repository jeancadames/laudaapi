<?php

namespace App\Services\Diagnosis;

use App\Models\DataBiInformationRequest;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * D2F-T3B.
 *
 * Envía una versión previamente guardada.
 *
 * - No crea nuevas solicitudes.
 * - No modifica Definition V1.
 * - No recibe archivos ni datos reales.
 * - No altera el estado comercial.
 * - No envía notificaciones.
 * - Todavía no tiene endpoint HTTP.
 */
final class DataBiInformationRequestSendService
{
    public static function eligible(
        string $capability,
        string $status,
        bool $hasLegacyDefinition
    ): bool {
        return $capability === 'data_transformation_bi'
            && in_array(
                $status,
                [
                    'requested',
                    'under_lauda_review',
                    'definition_preparation',
                ],
                true
            )
            && !$hasLegacyDefinition;
    }

    public function send(
        TransformationImplementationRequest $request,
        User $actor,
        int $expectedVersion
    ): DataBiInformationRequest {

        // Solo Admin LAUDA.
        if (
            !$actor->exists ||
            (int) $actor->getKey() <= 0 ||
            (string) $actor->role !== 'admin'
        ) {
            throw ValidationException::withMessages([
                'actor' => 'Se requiere Admin LAUDA autorizado.',
            ]);
        }

        // Solicitud existente y de la capacidad BI.
        if (
            !$request->exists ||
            (int) $request->getKey() <= 0 ||
            (int) $request->company_id <= 0 ||
            (string) $request->capability_key !==
                'data_transformation_bi'
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => 'Solicitud BI inválida.',
            ]);
        }

        if (
            $expectedVersion < 1 ||
            $expectedVersion > 9999
        ) {
            throw ValidationException::withMessages([
                'version' => 'Versión de envío inválida.',
            ]);
        }

        // Fallar cerrado mientras la migración no se ejecute.
        if (
            !Schema::hasTable('data_bi_information_requests') ||
            !Schema::hasColumn(
                'data_bi_information_requests',
                'sent_by_user_id'
            )
        ) {
            throw ValidationException::withMessages([
                'schema' => 'Migración de auditoría T3B pendiente.',
            ]);
        }

        return DB::transaction(
            function () use (
                $request,
                $actor,
                $expectedVersion
            ): DataBiInformationRequest {

                // Serializa operaciones sobre esta solicitud.
                $locked = TransformationImplementationRequest::query()
                    ->whereKey((int) $request->getKey())
                    ->where(
                        'company_id',
                        (int) $request->company_id
                    )
                    ->where(
                        'capability_key',
                        'data_transformation_bi'
                    )
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw ValidationException::withMessages([
                        'implementation_request' =>
                            'Solicitud no disponible.',
                    ]);
                }

                // Bloquear solicitudes con definición heredada.
                $hasLegacy = TransformationImplementationDefinition::query()
                    ->where(
                        'transformation_implementation_request_id',
                        (int) $locked->getKey()
                    )
                    ->exists();

                if (
                    !self::eligible(
                        (string) $locked->capability_key,
                        (string) $locked->status,
                        $hasLegacy
                    )
                ) {
                    throw ValidationException::withMessages([
                        'implementation_request' =>
                            'Solicitud no elegible. La Definition V1 no se puede convertir automáticamente.',
                    ]);
                }

                // Obtener exclusivamente la última versión.
                $latest = DataBiInformationRequest::query()
                    ->where(
                        'transformation_implementation_request_id',
                        (int) $locked->getKey()
                    )
                    ->where(
                        'company_id',
                        (int) $locked->company_id
                    )
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                if ($latest === null) {
                    throw ValidationException::withMessages([
                        'version' =>
                            'Primero guarde un borrador revisado.',
                    ]);
                }

                // Aplicar la política T3A.
                try {
                    $decision = DataBiInformationRequestSendPolicy::decide(
                        [
                            'version' => (int) $latest->version,
                            'status' => (string) $latest->status,
                            'content_sha256' =>
                                (string) $latest->content_sha256,
                            'review_snapshot' =>
                                $latest->review_snapshot,
                        ],
                        $expectedVersion
                    );
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'version' => $e->getMessage(),
                    ]);
                }

                // Reintento de una versión ya enviada.
                if ($decision['action'] === 'already_sent') {
                    if (
                        $latest->sent_at === null ||
                        (int) $latest->getAttribute(
                            'sent_by_user_id'
                        ) <= 0
                    ) {
                        throw ValidationException::withMessages([
                            'version' =>
                                'Envío anterior incompleto: requiere revisión.',
                        ]);
                    }

                    // No modificar fecha, autor ni contenido.
                    return $latest;
                }

                // Evitar publicaciones sin destinatario activo.
                // Un reintento ya enviado conserva su auditoría.
                if (!DataBiInformationPublicationGate::hasActiveRecipient(
                    (int) $locked->company_id
                )) {
                    throw ValidationException::withMessages([
                        'recipient' =>
                            'La empresa no tiene un propietario o administrador con membresía activa.',
                    ]);
                }

                // Impedir doble marcado o estados inconsistentes.
                if (
                    $latest->sent_at !== null ||
                    $latest->getAttribute(
                        'sent_by_user_id'
                    ) !== null
                ) {
                    throw ValidationException::withMessages([
                        'version' =>
                            'Borrador con marca de envío inconsistente.',
                    ]);
                }

                // Única mutación autorizada de esta etapa.
                $latest->setAttribute(
                    'status',
                    DataBiInformationRequest::STATUS_SENT
                );

                $latest->setAttribute(
                    'sent_at',
                    now()
                );

                $latest->setAttribute(
                    'sent_by_user_id',
                    (int) $actor->getKey()
                );

                $latest->save();

                return $latest;
            },
            3
        );
    }
}
