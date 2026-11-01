<?php

namespace App\Http\Controllers;

use App\Models\DataBiInformationRequest;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationTenantAccessPolicy;
use App\Services\Diagnosis\DataBiTenantDeclarationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

final class AppHubDataBiInformationRequirementsController extends Controller
{
    public function __invoke(
        Request $http,
        TransformationImplementationRequest $implementationRequest
    ): JsonResponse {
        $actor = $http->user();

        abort_unless($actor !== null, 401);

        abort_unless(
            (string) $implementationRequest->capability_key
                === 'data_transformation_bi',
            404
        );

        // La empresa se obtiene de la solicitud persistida,
        // nunca de un company_id proporcionado por el navegador.
        $company = DB::table('companies')
            ->where(
                'id',
                (int) $implementationRequest->company_id
            )
            ->first([
                'id',
                'subscriber_id',
                'owner_user_id',
            ]);

        abort_unless(
            $company && (int) $company->subscriber_id > 0,
            404
        );

        $membership = DB::table('subscriber_user')
            ->where(
                'subscriber_id',
                (int) $company->subscriber_id
            )
            ->where(
                'user_id',
                (int) $actor->getKey()
            )
            ->where('active', 1)
            ->first(['role']);

        $canRead = DataBiInformationTenantAccessPolicy::mayRead(
            (int) $actor->getKey(),
            (int) ($company->owner_user_id ?? 0),
            $membership ? (string) $membership->role : null,
            $membership !== null
        );

        // 404 evita revelar la existencia de solicitudes ajenas.
        abort_unless($canRead, 404);

        $sent = DataBiInformationRequest::query()
            ->where(
                'transformation_implementation_request_id',
                (int) $implementationRequest->getKey()
            )
            ->where('company_id', (int) $company->id)
            ->where(
                'status',
                DataBiInformationRequest::STATUS_SENT
            )
            ->whereNotNull('sent_at')
            ->whereNotNull('sent_by_user_id')
            ->orderByDesc('version')
            ->first();

        abort_unless($sent !== null, 404);

        $snapshot = $sent->review_snapshot;

        abort_unless(
            is_array($snapshot)
            && ($snapshot['schema_version'] ?? null) === 1
            && ($snapshot['purpose'] ?? null)
                === 'information_source_declaration'
            && ($snapshot['data_inspected'] ?? null) === false
            && is_array($snapshot['requirements'] ?? null)
            && array_is_list($snapshot['requirements'])
            && count($snapshot['requirements']) > 0
            && count($snapshot['requirements']) <= 100,
            404
        );

        // Proyección explícita: excluir metadatos internos,
        // credenciales, snapshots completos y datos comerciales.
        $requirements = [];

        foreach ($snapshot['requirements'] as $item) {
            abort_unless(
                is_array($item)
                && is_string($item['key'] ?? null)
                && is_string($item['label'] ?? null)
                && is_string($item['guidance'] ?? null)
                && is_array($item['objective_keys'] ?? null)
                && array_is_list($item['objective_keys']),
                404
            );

            $requirements[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'guidance' => $item['guidance'],
                'objective_keys' => array_values(
                    $item['objective_keys']
                ),
            ];
        }

        $needs = [];

        if (is_array($snapshot['selected_needs'] ?? null)) {
            foreach ($snapshot['selected_needs'] as $need) {
                if (
                    is_array($need)
                    && is_string($need['key'] ?? null)
                    && is_string($need['label'] ?? null)
                ) {
                    $needs[] = [
                        'key' => $need['key'],
                        'label' => $need['label'],
                    ];
                }
            }
        }

        return response()->json([
            'implementation_request_id'
                => (int) $implementationRequest->getKey(),
            'version' => (int) $sent->version,
            'sent_at' => $sent->sent_at?->toISOString(),
            'objectives' => $needs,
            'requirements' => $requirements,
            'declaration_only' => true,
            'can_respond' => $this->canRespond($implementationRequest, (int) $sent->version),
            'attachments_required' => false,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function canRespond(TransformationImplementationRequest $request, int $version): bool
    {
        if (! class_exists(DataBiTenantDeclarationService::class)) {
            return false;
        }
        foreach (['show', 'save_draft', 'submit'] as $action) {
            if (! Route::has('app.transformation.data_bi.information_requirements.declaration.'.$action)) {
                return false;
            }
        }
        try {
            // T4E: consulta sin DML. Reutiliza las comprobaciones completas de T4C:
            // esquema/columnas, ámbito activo, membresía, publicación e integridad.
            app(DataBiTenantDeclarationService::class)->findExisting($request, $version);

            return true;
        } catch (AuthenticationException|AuthorizationException|ValidationException) {
            return false;
        } catch (QueryException $exception) {
            // También fallar cerrado si faltan columnas/tablas del cuestionario.
            // No ocultar otros errores inesperados de conexión o del servidor.
            if (in_array($exception->errorInfo[0] ?? null, ['42S02', '42S22'], true)) {
                return false;
            }

            throw $exception;
        }
    }
}
