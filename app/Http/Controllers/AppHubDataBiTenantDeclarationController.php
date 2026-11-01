<?php

namespace App\Http\Controllers;

use App\Http\Middleware\DataBiTenantDeclarationJson;
use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataBiInformationTenantAccessPolicy;
use App\Services\Diagnosis\DataBiTenantDeclarationPersistencePolicy;
use App\Services\Diagnosis\DataBiTenantDeclarationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

/** D2F-T4D. Adaptación HTTP; publicación, autorización y escrituras pertenecen a T4C. */
final class AppHubDataBiTenantDeclarationController extends Controller
{
    public function show(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiTenantDeclarationService $service
    ): JsonResponse {
        $this->authorizeTenant($http, $implementationRequest);
        try {
            if ($http->all() !== []) {
                throw ValidationException::withMessages(['payload' => 'La consulta no acepta atributos.']);
            }
            // T4C exige una versión para consultar. Esta lectura solo la descubre;
            // findExisting vuelve a autorizar, bloquear y validar la publicación y su integridad.
            if (! Schema::hasTable('data_bi_information_requests')) {
                throw ValidationException::withMessages(['schema' => 'Esquema no disponible.']);
            }
            $version = (int) $this->published($implementationRequest)->max('version');
            $declaration = $service->findExisting($implementationRequest, $version);

            return $this->state($implementationRequest, $version, $declaration);
        } catch (ValidationException $e) {
            return $this->validationError($e, $implementationRequest, $version ?? null);
        }
    }

    public function saveDraft(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiTenantDeclarationService $service
    ): JsonResponse {
        return $this->write($http, $implementationRequest, $service, false);
    }

    public function submit(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiTenantDeclarationService $service
    ): JsonResponse {
        return $this->write($http, $implementationRequest, $service, true);
    }

    private function write(
        Request $http,
        TransformationImplementationRequest $implementationRequest,
        DataBiTenantDeclarationService $service,
        bool $submit
    ): JsonResponse {
        $this->authorizeTenant($http, $implementationRequest);
        if (! $http->isJson()) {
            return DataBiTenantDeclarationJson::error('unsupported_media_type', 'Se requiere un cuerpo JSON.', 415);
        }
        try {
            $input = $this->input($http);
            $expectedRevision = $input['expected_revision'];
            // Nunca pasar expected_revision (ni el token de transporte) al normalizador T4B.
            $payload = ['version' => $input['version'], 'declarations' => $input['declarations']];
            $result = $submit
                ? $service->submit($implementationRequest, $payload, $expectedRevision)
                : $service->saveDraft($implementationRequest, $payload, $expectedRevision);

            // 200 tanto para la primera operación como para su reintento idéntico.
            return $this->state($implementationRequest, $payload['version'], $result);
        } catch (ValidationException $e) {
            return $this->validationError($e, $implementationRequest, $input['version'] ?? null);
        }
    }

    private function authorizeTenant(Request $http, TransformationImplementationRequest $request): void
    {
        abort_unless($http->user() !== null, 401);
        abort_unless((string) $request->capability_key === 'data_transformation_bi', 404);
        // Preflight antes de validar el cuerpo: una solicitud ajena y una inexistente
        // responden el mismo 404 incluso con JSON inválido. T4C reautoriza bajo lock.
        $scope = DB::table('companies')
            ->join('subscribers', 'subscribers.id', '=', 'companies.subscriber_id')
            ->join('subscriber_user', 'subscriber_user.subscriber_id', '=', 'subscribers.id')
            ->where('companies.id', (int) $request->company_id)
            ->where('companies.active', 1)->where('subscribers.active', 1)
            ->where('subscriber_user.user_id', (int) $http->user()->getAuthIdentifier())
            ->where('subscriber_user.active', 1)
            ->first(['companies.owner_user_id', 'subscriber_user.role']);
        abort_unless($scope !== null && DataBiInformationTenantAccessPolicy::mayRead(
            (int) $http->user()->getAuthIdentifier(), (int) $scope->owner_user_id, $scope->role, true
        ), 404);
    }

    private function input(Request $http): array
    {
        try {
            // Conservar los tipos, espacios y cadenas vacías de T4B: los middleware
            // globales TrimStrings/ConvertEmptyStringsToNull alteran el InputBag web.
            $body = json_decode($http->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => 'JSON inválido.']);
        }
        if (! $body instanceof stdClass || $http->query->all() !== [] || $http->allFiles() !== []
            || array_diff(array_keys((array) $body), ['version', 'declarations', 'expected_revision', '_token']) !== []) {
            throw ValidationException::withMessages(['payload' => 'Solo se admiten version, declarations y expected_revision.']);
        }
        $errors = [];
        if (! is_int($body->version ?? null) || $body->version < 1 || $body->version > 9999) {
            $errors['version'] = 'Se requiere la versión entera publicada del cuestionario.';
        }
        if (! is_int($body->expected_revision ?? null) || $body->expected_revision < 0
            || $body->expected_revision > DataBiTenantDeclarationPersistencePolicy::MAX_REVISION) {
            $errors['expected_revision'] = 'Se requiere una revisión entera válida; 0 para crear.';
        }
        if (! is_array($body->declarations ?? null)) {
            $errors['declarations'] = 'Se requiere una lista de declaraciones.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->jsonValue($body);
    }

    private function jsonValue(mixed $value): mixed
    {
        // {} no debe convertirse en [] y eludir los tipos de lista de T4B.
        if ($value instanceof stdClass && get_object_vars($value) !== []) {
            $value = (array) $value;
        }

        return is_array($value) ? array_map($this->jsonValue(...), $value) : $value;
    }

    private function published(TransformationImplementationRequest $request): Builder
    {
        return DataBiInformationRequest::query()
            ->where('transformation_implementation_request_id', (int) $request->getKey())
            ->where('company_id', (int) $request->company_id)
            ->where('status', DataBiInformationRequest::STATUS_SENT);
    }

    private function state(
        TransformationImplementationRequest $request,
        int $version,
        ?DataBiTenantDeclaration $declaration
    ): JsonResponse {
        // Proyección explícita: no serializar el modelo ni el snapshot interno completo.
        return new JsonResponse(['data' => [
            'implementation_request_id' => (int) $request->getKey(),
            'version' => $version,
            'declaration' => $declaration === null ? null : [
                'id' => (int) $declaration->getKey(),
                'status' => (string) $declaration->status,
                'revision' => (int) $declaration->revision,
                'version' => (int) $declaration->declaration_snapshot['version'],
                // La columna MySQL guarda segundos; mantener la misma fecha en los reintentos.
                'submitted_at' => $declaration->submitted_at?->toIso8601String(),
                'declarations' => $declaration->declaration_snapshot['declarations'],
            ],
        ]]);
    }

    private function validationError(
        ValidationException $exception,
        TransformationImplementationRequest $request,
        ?int $version
    ): JsonResponse {
        $errors = $exception->errors();
        if (isset($errors['schema'])) {
            return DataBiTenantDeclarationJson::error('schema_unavailable', 'Esquema de declaraciones no disponible.', 503);
        }
        $message = $errors['declaration'][0] ?? null;
        // T4C expone ValidationException, sin códigos tipados. Adaptar solo sus
        // mensajes conocidos; no reinterpretar excepciones inesperadas como éxitos.
        if ($message === 'No existe un cuestionario publicado.') {
            return DataBiTenantDeclarationJson::error('questionnaire_not_published', 'Cuestionario publicado no disponible.', 404);
        }
        if ($message === 'Cuestionario no publicado, ajeno o versión publicada sustituida.') {
            $current = $this->published($request)->orderByDesc('version')->first();
            if ($current !== null && $current->sent_at !== null && $current->sent_by_user_id !== null
                && (int) $current->version !== $version) {
                return DataBiTenantDeclarationJson::error('questionnaire_replaced', 'El cuestionario fue sustituido. Consulta la versión vigente.', 409);
            }

            return DataBiTenantDeclarationJson::error('questionnaire_unavailable', 'Cuestionario publicado no disponible.', 503);
        }
        if (in_array($message, ['Conflicto: todavía no existe esa revisión.',
            'Conflicto: la declaración tiene una revisión más reciente.', 'Se alcanzó el límite de revisiones.'], true)) {
            return DataBiTenantDeclarationJson::error('revision_conflict', 'La revisión cambió. Consulta la declaración vigente.', 409);
        }
        if ($message === 'La declaración enviada es inmutable.') {
            return DataBiTenantDeclarationJson::error('declaration_submitted', 'La declaración ya fue enviada y no admite cambios.', 409);
        }
        if (in_array($message, ['Se requiere una versión publicada válida.', 'Snapshot declarativo inválido.',
            'Objetivo publicado inválido.', 'Requerimiento publicado inválido.', 'Requerimientos publicados duplicados o sin objetivos.',
            'El cuestionario publicado está vacío.', 'El hash no corresponde al contenido del cuestionario.',
            'Clave publicada duplicada.', 'Texto publicado inválido.', 'Snapshot o auditoría de la declaración inválidos.',
            'Revisión persistida inválida.', 'Auditoría de envío inconsistente.',
            'Estado persistido de la declaración inválido.',
            'La declaración guardada no corresponde al cuestionario o fue alterada.'], true)) {
            return DataBiTenantDeclarationJson::error('declaration_unavailable', 'La declaración o su cuestionario no están disponibles.', 503);
        }

        return DataBiTenantDeclarationJson::error('validation_failed', 'Revisa los datos declarados.', 422, $errors);
    }
}
