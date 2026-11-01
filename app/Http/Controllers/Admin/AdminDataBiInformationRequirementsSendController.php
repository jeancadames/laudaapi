<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationRequestSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D2F-T3C1.
 *
 * QA HTTP del marcado de envío.
 * No entrega el cuestionario al Tenant.
 * Solo operativo en APP_ENV=local y laudaapi_dev.
 */
final class AdminDataBiInformationRequirementsSendController extends Controller
{
    public function send(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiInformationRequestSendService $service
    ): JsonResponse {

        // Defensa adicional al middleware Admin LAUDA.
        abort_unless(
            $http->user() !== null &&
            (string) $http->user()->role === 'admin',
            403
        );

        abort_unless(
            (string) $implementationRequest->capability_key ===
                'data_transformation_bi',
            404
        );

        // Publicación únicamente si el entorno, esquema
        // y ruta del Tenant están disponibles.
        abort_unless(
            \App\Services\Diagnosis\DataBiInformationPublicationGate::enabled(),
            503,
            'Publicación no disponible en este ambiente o esquema.'
        );

        // El cliente solamente indica la versión.
        // No puede enviar identidad, fuentes ni contenido arbitrario.
        $extra = array_diff(
            array_keys($http->all()),
            ['expected_version', '_token']
        );

        if ($extra !== []) {
            throw ValidationException::withMessages([
                'payload' => 'El envío solo acepta expected_version.',
            ]);
        }

        $validated = $http->validate([
            'expected_version' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
            ],
        ]);

        $result = $service->send(
            $implementationRequest,
            $http->user(),
            (int) $validated['expected_version']
        );

        return response()->json([
            'request_id' => (int) $implementationRequest->getKey(),
            'version' => (int) $result->version,
            'status' => (string) $result->status,
            'sent_at' => $result->sent_at?->toISOString(),
            'sent_by_user_id' => (int) $result->getAttribute(
                'sent_by_user_id'
            ),
            // Entrega = publicación en el portal, no correo.
            'delivered_to_tenant' => true,
            'tenant_access_enabled' => true,
            'published_to_tenant' => true,
            'publication_channel' => 'tenant_portal',
            'notification_sent' => false,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
