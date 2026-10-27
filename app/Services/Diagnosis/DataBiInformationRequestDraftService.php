<?php

namespace App\Services\Diagnosis;

use App\Models\DataBiInformationRequest;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Servicio de persistencia PREPARADO, todavía sin ruta ni UI de guardado.
 * Cada revisión guardada crea versión inmutable. No envía ni toca Definition V1.
 */
final class DataBiInformationRequestDraftService
{
    /** @param array<string,mixed> $submitted */
    public function saveDraft(
        TransformationImplementationRequest $request,
        User $actor,
        array $submitted,
        int $expectedVersion
    ): DataBiInformationRequest {
        if (!$actor->exists || (int) $actor->getKey() <= 0 || (string) $actor->role !== 'admin') {
            throw ValidationException::withMessages(['actor' => 'Se requiere Admin LAUDA autorizado.']);
        }
        if (!$request->exists || (int) $request->company_id <= 0
            || (string) $request->capability_key !== 'data_transformation_bi') {
            throw ValidationException::withMessages(['implementation_request' => 'Solicitud BI inválida.']);
        }

        return DB::transaction(function () use ($request, $actor, $submitted, $expectedVersion): DataBiInformationRequest {
            // Lock de la solicitud original serializa incluso cuando aún no existe fila de borrador.
            $locked = TransformationImplementationRequest::query()
                ->whereKey((int) $request->getKey())
                ->where('company_id', (int) $request->company_id)
                ->where('capability_key', 'data_transformation_bi')
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array((string) $locked->status, ['requested', 'under_lauda_review', 'definition_preparation'], true)) {
                throw ValidationException::withMessages(['implementation_request' => 'Estado incompatible con el borrador declarativo.']);
            }
            // Protección explícita de definiciones V1 legacy, incluida solicitud PROD #5.
            // Su transición deberá aprobarse de manera individual, no automática.
            if (TransformationImplementationDefinition::query()
                ->where('transformation_implementation_request_id', (int) $locked->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['implementation_request' => 'La solicitud conserva una Definition V1. No se habilitó su transición.']);
            }

            $snapshot = $locked->source_snapshot;
            $generated = DataBiInformationRequirementDraftGenerator::preview(is_array($snapshot) ? $snapshot : []);
            try {
                $review = DataBiInformationRequirementReviewContract::normalize($generated, $submitted);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['requirements' => $e->getMessage()]);
            }
            $latest = DataBiInformationRequest::query()
                ->where('company_id', (int) $locked->company_id)
                ->where('transformation_implementation_request_id', (int) $locked->getKey())
                ->orderByDesc('version')->first();
            try {
                $decision = DataBiInformationRequestVersionPolicy::decide(
                    $latest ? [
                        'status' => (string) $latest->status,
                        'version' => (int) $latest->version,
                        'content_sha256' => (string) $latest->content_sha256,
                    ] : null,
                    $review['content_sha256'],
                    $expectedVersion
                );
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['version' => $e->getMessage()]);
            }
            if ($decision['action'] === 'unchanged') {
                return $latest;
            }
            return DataBiInformationRequest::query()->create([
                'transformation_implementation_request_id' => (int) $locked->getKey(),
                'company_id' => (int) $locked->company_id,
                'version' => $decision['version'],
                'status' => DataBiInformationRequest::STATUS_DRAFT,
                'review_snapshot' => $review,
                'content_sha256' => $review['content_sha256'],
                'created_by_user_id' => (int) $actor->getKey(),
            ]);
        }, 3);
    }
}
