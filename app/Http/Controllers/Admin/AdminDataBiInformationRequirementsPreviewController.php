<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * D2F-T2A: vista previa, exclusivamente para Admin LAUDA.
 * GET sin escritura: ni Definition V1, ni fuentes, ni estado de solicitud.
 * No permite emitir, enviar ni completar el formulario del Tenant.
 */
final class AdminDataBiInformationRequirementsPreviewController extends Controller
{
    public function show(Request $request, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        // Defensa en profundidad: el grupo de rutas también exige auth+verified+role:admin.
        abort_unless($request->user() !== null, 403);
        abort_unless(
            (string) $implementationRequest->capability_key === 'data_transformation_bi',
            404
        );

        $snapshot = $implementationRequest->source_snapshot;
        $draft = DataBiInformationRequirementDraftGenerator::preview(
            is_array($snapshot) ? $snapshot : []
        );

        return response()->json([
            'request_id' => (int) $implementationRequest->getKey(),
            'request_status' => (string) $implementationRequest->status,
            'draft' => $draft,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
