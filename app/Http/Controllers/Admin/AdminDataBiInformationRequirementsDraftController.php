<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataBiInformationRequest;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationRequestDraftService;
use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * D2F-T2C3: GET borrador actual / POST guardar versión revisada.
 * Aislado del flujo de Definition V1, entrega de archivos, ETL y comercial.
 * Sin tabla de persistencia, GET funciona en modo preview y POST falla cerrado (503).
 */
final class AdminDataBiInformationRequirementsDraftController extends Controller
{
    public function show(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        $this->assertScope($http, $implementationRequest);
        $snapshot = $implementationRequest->source_snapshot;
        $draft = DataBiInformationRequirementDraftGenerator::preview(is_array($snapshot) ? $snapshot : []);
        $tableReady = Schema::hasTable('data_bi_information_requests');
        $saved = null;
        $version = 0;
        $hasLegacy = false;

        if ($tableReady) {
            // La lectura también se limita por solicitud y empresa (no hay lista cross-tenant).
            $latest = DataBiInformationRequest::query()
                ->where('transformation_implementation_request_id', (int) $implementationRequest->getKey())
                ->where('company_id', (int) $implementationRequest->company_id)
                ->orderByDesc('version')
                ->first();
            if ($latest !== null) {
                $version = (int) $latest->version;
                $saved = $latest->review_snapshot;
            }
            $hasLegacy = TransformationImplementationDefinition::query()
                ->where('transformation_implementation_request_id', (int) $implementationRequest->getKey())
                ->exists();
        }

        $statusOk = in_array((string) $implementationRequest->status,
            ['requested', 'under_lauda_review', 'definition_preparation'], true);
        // Sin tabla o sin objetivos reconocidos, el formulario queda en solo lectura.
        $canSave = $tableReady && $statusOk && ! $hasLegacy
            && count($draft['requirements']) > 0;

        $reason = null;
        if (! $tableReady) {
            $reason = 'La tabla de borradores todavía no está migrada. Guardado deshabilitado.';
        } elseif ($hasLegacy) {
            $reason = 'Esta solicitud tiene una definición V1. Requiere transición individual autorizada.';
        } elseif (! $statusOk) {
            $reason = 'El estado actual de la solicitud no permite guardar requerimientos.';
        } elseif ($draft['requirements'] === []) {
            $reason = 'No hay requerimientos generados a partir de los objetivos del Tenant.';
        }

        return $this->response([
            'request_id' => (int) $implementationRequest->getKey(),
            'draft' => $draft,
            'saved' => is_array($saved) ? $saved : null,
            'version' => $version,
            'persistence_available' => $tableReady,
            'can_save' => $canSave,
            'can_save_reason' => $reason,
            'can_send' => false,
        ]);
    }

    public function save(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiInformationRequestDraftService $service
    ): JsonResponse {
        $this->assertScope($http, $implementationRequest);
        if (! Schema::hasTable('data_bi_information_requests')) {
            return $this->response([
                'message' => 'El guardado no está disponible hasta autorizar y ejecutar la migración en DEV.',
            ], 503);
        }

        // No aceptar IDs de empresa, vínculos entre objetivos ni un snapshot externo.
        $validated = $http->validate([
            'expected_version' => ['required', 'integer', 'min:0', 'max:9999'],
            'requirements' => ['required', 'array', 'min:1', 'max:100'],
            'requirements.*.key' => ['required', 'string', 'max:120'],
            'requirements.*.included' => ['required', 'boolean'],
            'requirements.*.label' => ['required', 'string', 'max:191'],
            'requirements.*.guidance' => ['required', 'string', 'max:800'],
        ]);
        $saved = $service->saveDraft(
            $implementationRequest,
            $http->user(),
            ['requirements' => $validated['requirements']],
            (int) $validated['expected_version']
        );

        return $this->response([
            'request_id' => (int) $implementationRequest->getKey(),
            'version' => (int) $saved->version,
            'content_sha256' => (string) $saved->content_sha256,
            'status' => (string) $saved->status,
            'saved' => $saved->review_snapshot,
            'sent' => false,
        ]);
    }

    private function assertScope(Request $http, TransformationImplementationRequest $request): void
    {
        // Defensa adicional a middleware auth+verified+role:admin.
        abort_unless($http->user() !== null && (string) $http->user()->role === 'admin', 403);
        abort_unless((string) $request->capability_key === 'data_transformation_bi', 404);
    }

    private function response(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
