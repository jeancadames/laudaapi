<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use App\Services\Diagnosis\DataBiTenantDeclarationPersistencePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** T4F-A: lectura administrativa. No usa autorización Tenant ni métodos de escritura. */
final class AdminDataBiTenantDeclarationsController extends Controller
{
    public function show(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        abort_unless($http->user() !== null, 401);
        abort_unless($http->user()->role === 'admin' && $http->user()->hasVerifiedEmail(), 403);
        // Recargar la identidad persistida; no confiar en un modelo/empresa del navegador.
        $request = TransformationImplementationRequest::query()->whereKey($implementationRequest->getKey())
            ->where('capability_key', 'data_transformation_bi')->first();
        abort_unless($request !== null, 404);
        $company = DB::table('companies')->where('id', (int) $request->company_id)->first(['id', 'name']);
        abort_unless($company !== null, 404);

        try {
            if (array_diff(array_keys($http->all()), ['version', 'before_version']) !== [] || $http->getContent() !== '') {
                throw ValidationException::withMessages(['query' => 'Solo se admiten version y before_version en la consulta.']);
            }
            $input = Validator::make($http->query->all(), [
                'version' => ['sometimes', 'required', 'integer', 'min:1', 'max:9999'],
                'before_version' => ['sometimes', 'required', 'integer', 'min:1', 'max:9999'],
            ])->validate();
            if (! $this->schemaAvailable()) {
                return $this->error('schema_unavailable', 'El esquema de declaraciones no está disponible.', 503);
            }

            // No filtrar empresa para ocultar inconsistencias: el contrato debe detectarlas.
            $published = DataBiInformationRequest::query()
                ->where('transformation_implementation_request_id', (int) $request->getKey())
                ->where('status', DataBiInformationRequest::STATUS_SENT);
            $latest = (clone $published)->orderByDesc('version')->first();
            $selected = isset($input['version'])
                ? (clone $published)->where('version', (int) $input['version'])->first()
                : $latest;
            abort_if(isset($input['version']) && $selected === null, 404);
            $history = (clone $published)
                ->when(isset($input['before_version']), fn ($q) => $q->where('version', '<', (int) $input['before_version']))
                ->orderByDesc('version')->limit(21)->get();
            $hasMore = $history->count() > 20;
            $history = $history->take(20);
            $questionnaires = $history->concat(array_filter([$latest, $selected]))->unique('id');

            // Leer únicamente entregas formales. El contenido y la revisión de draft no se consultan.
            $submitted = DataBiTenantDeclaration::query()
                ->whereIn('data_bi_information_request_id', $questionnaires->modelKeys())
                ->where('status', DataBiTenantDeclaration::STATUS_SUBMITTED)->get()
                ->keyBy('data_bi_information_request_id');
            $views = [];
            foreach ($questionnaires as $questionnaire) {
                $view = $this->project($request, $questionnaire, $submitted->get($questionnaire->id));
                // Validar el historial completo de la página, conservando respuestas
                // detalladas en memoria únicamente para la versión seleccionada.
                $views[$questionnaire->id] = $questionnaire->id === $selected?->id ? $view : [
                    'version' => $view['version'], 'published_at' => $view['published_at'],
                    'declaration' => $view['declaration'] === null ? null : [
                        'revision' => $view['declaration']['revision'], 'submitted_at' => $view['declaration']['submitted_at'],
                    ],
                ];
            }

            return new JsonResponse(['data' => [
                'implementation_request_id' => (int) $request->getKey(),
                'company' => ['id' => (int) $company->id, 'name' => (string) $company->name],
                'current_version' => $latest?->version,
                'versions' => $history->map(function ($q) use ($views): array {
                    $view = $views[$q->id];

                    return ['version' => $view['version'], 'published_at' => $view['published_at'],
                        'response_status' => $view['declaration'] === null ? 'pending' : 'submitted',
                        'revision' => $view['declaration']['revision'] ?? null,
                        'submitted_at' => $view['declaration']['submitted_at'] ?? null];
                })->values()->all(),
                'next_before_version' => $hasMore ? (int) $history->last()->version : null,
                'selected' => $selected === null ? null : $views[$selected->id],
                'declaration_only' => true,
            ]]);
        } catch (ValidationException) {
            return $this->error('validation_failed', 'La versión o el cursor de consulta no son válidos.', 422);
        } catch (InvalidArgumentException) {
            return $this->error('declaration_integrity_conflict', 'La declaración o su publicación no superan las comprobaciones de integridad. No se muestran respuestas.', 409);
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['42S02', '42S22'], true)) {
                return $this->error('schema_unavailable', 'El esquema de declaraciones no está disponible.', 503);
            }
            throw $exception;
        }
    }

    private function project(
        TransformationImplementationRequest $request,
        DataBiInformationRequest $questionnaire,
        ?DataBiTenantDeclaration $declaration
    ): array {
        $sent = $questionnaire->attributesToArray();
        $sent['sent_by_user_id'] = $questionnaire->sent_by_user_id === null ? null : (int) $questionnaire->sent_by_user_id;
        DataBiTenantDeclarationPersistencePolicy::assertPublished([
            'id' => (int) $request->getKey(), 'company_id' => (int) $request->company_id,
            'capability_key' => (string) $request->capability_key,
        ], $sent, (int) $questionnaire->version);
        DataBiInformationTenantDeclarationContract::normalize($sent, ['version' => $sent['version'], 'declarations' => []], 'draft');
        $response = null;
        if ($declaration !== null) {
            if ($declaration->status !== DataBiTenantDeclaration::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Solo se proyectan entregas formales.');
            }
            $normalized = DataBiTenantDeclarationPersistencePolicy::storedSnapshot($sent, $declaration->attributesToArray());
            $response = ['status' => 'submitted', 'revision' => (int) $declaration->revision,
                'submitted_at' => $declaration->submitted_at->toIso8601String(),
                'declarations' => $normalized['declarations']];
        }
        $snapshot = $sent['review_snapshot'];

        return [
            'questionnaire_id' => (int) $questionnaire->getKey(),
            'version' => (int) $questionnaire->version,
            'published_at' => $questionnaire->sent_at->toIso8601String(),
            'objectives' => array_map(static fn (array $need): array => ['key' => $need['key'], 'label' => $need['label']], $snapshot['selected_needs']),
            'requirements' => array_map(static fn (array $r): array => [
                'key' => $r['key'], 'label' => $r['label'], 'guidance' => $r['guidance'], 'objective_keys' => $r['objective_keys'],
            ], $snapshot['requirements']),
            'declaration' => $response,
            'coverage' => $response === null ? null : $this->coverage($response['declarations']),
        ];
    }

    private function coverage(array $declarations): array
    {
        // Contar menciones por requerimiento: no afirmar unicidad técnica entre fuentes.
        $result = ['requested' => count($declarations), 'available' => 0, 'partial' => 0,
            'unavailable' => 0, 'unknown' => 0, 'sources' => 0, 'structures' => 0];
        foreach ($declarations as $declaration) {
            $result[$declaration['availability']]++;
            $result['sources'] += count($declaration['sources']);
            foreach ($declaration['sources'] as $source) {
                $result['structures'] += count($source['structures']);
            }
        }

        return $result;
    }

    private function schemaAvailable(): bool
    {
        return Schema::hasTable('data_bi_information_requests')
            && Schema::hasColumns('data_bi_information_requests', ['id', 'transformation_implementation_request_id', 'company_id',
                'version', 'status', 'review_snapshot', 'content_sha256', 'sent_at', 'sent_by_user_id'])
            && Schema::hasTable('data_bi_tenant_declarations')
            && Schema::hasColumns('data_bi_tenant_declarations', ['id', 'data_bi_information_request_id', 'company_id',
                'status', 'revision', 'declaration_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id',
                'submitted_by_user_id', 'submitted_at', 'created_at', 'updated_at']);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
