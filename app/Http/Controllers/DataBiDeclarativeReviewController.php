<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\DataBiDeclarativeReviewJson;
use App\Http\Responses\DataBiDeclarativeReviewErrors;
use App\Http\Responses\DataBiDeclarativeReviewProjection;
use App\Models\DataBiDeclarationClarification;
use App\Models\DataBiInformationRequest;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataBiDeclarativeReviewActor;
use App\Services\Diagnosis\DataBiDeclarativeReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use stdClass;

/** B3: adaptación HTTP. B1/B2 conservan todas las decisiones de negocio y persistencia. */
final class DataBiDeclarativeReviewController extends Controller
{
    public function __construct(private DataBiDeclarativeReviewService $service) {}

    public function adminShow(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        return $this->handle($http, $implementationRequest, true);
    }

    public function tenantShow(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        return $this->handle($http, $implementationRequest, false);
    }

    public function requestClarification(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        return $this->handle($http, $implementationRequest, true, 'request');
    }

    public function answerClarification(Request $http, TransformationImplementationRequest $implementationRequest, string $clarification): JsonResponse
    {
        return $this->handle($http, $implementationRequest, false, 'answer', $clarification);
    }

    public function resolveClarification(Request $http, TransformationImplementationRequest $implementationRequest, string $clarification): JsonResponse
    {
        return $this->handle($http, $implementationRequest, true, 'resolve', $clarification);
    }

    public function cancelClarification(Request $http, TransformationImplementationRequest $implementationRequest, string $clarification): JsonResponse
    {
        return $this->handle($http, $implementationRequest, true, 'cancel', $clarification);
    }

    public function closeReview(Request $http, TransformationImplementationRequest $implementationRequest): JsonResponse
    {
        return $this->handle($http, $implementationRequest, true, 'close');
    }

    private function handle(Request $http, TransformationImplementationRequest $bound, bool $admin, ?string $action = null, ?string $clarification = null): JsonResponse
    {
        $phase = 'read';
        try {
            // Mismo primer lock que B2. Mantenerlo hasta proyectar evita responder con acciones de otra revisión.
            return DB::transaction(function () use ($http, $bound, $admin, $action, $clarification, &$phase): JsonResponse {
                $request = TransformationImplementationRequest::query()->whereKey($bound->getKey())
                    ->where('capability_key', 'data_transformation_bi')->lockForUpdate()->first();
                if ($request === null) {
                    $this->fail('not_found', 'Recurso no disponible.', 404);
                }
                $this->authorizeActor($http, $request, $admin);
                $id = null;
                $input = null;
                $expected = null;
                if ($clarification !== null) {
                    $id = filter_var($clarification, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false) {
                        $this->fail('not_found', 'Recurso no disponible.', 404);
                    }
                    // No binding global de aclaración: identificarla siempre mediante la solicitud autorizada.
                    if (! Schema::hasTable('data_bi_declaration_clarifications')) {
                        $this->fail('schema_unavailable', 'El esquema de revisión declarativa no está disponible.', 503);
                    }
                    $row = DataBiDeclarationClarification::query()->whereKey($id)
                        ->where('transformation_implementation_request_id', (int) $request->id)->first();
                    if ($row === null) {
                        $this->fail('not_found', 'Recurso no disponible.', 404);
                    }
                    $publication = DataBiInformationRequest::query()->whereKey($row->data_bi_information_request_id)
                        ->where('transformation_implementation_request_id', (int) $request->id)->first();
                    if ($publication === null) {
                        $this->fail('not_found', 'Recurso no disponible.', 404);
                    }
                    $version = (int) $publication->version;
                } else {
                    if ($action === null) {
                        $version = $this->queryVersion($http);
                    } else {
                        // Sin ID de aclaración, el ámbito ya está autorizado: conservar la versión B1 solicitada.
                        // Un cuestionario nuevo sin respuesta no debe ocultar el conflicto de una operación antigua.
                        [$input, $expected] = $this->payload($http, $action);
                        $version = $input['version'];
                    }
                }
                // B2 verifica contexto e historial completos; con ID de aclaración, antes del payload.
                $review = $this->service->review($request, $version);
                if ($id !== null && ! in_array($id, array_column($review['clarifications'], 'id'), true)) {
                    $this->fail('not_found', 'Recurso no disponible.', 404);
                }
                if ($action !== null) {
                    if ($input === null) {
                        [$input, $expected] = $this->payload($http, $action);
                    }
                    $phase = 'write';
                    match ($action) {
                        'request' => $this->service->requestClarification($request, $input, $expected),
                        'answer' => $this->service->answerClarification($request, $id, $input, $expected),
                        'resolve' => $this->service->resolveClarification($request, $id, $input, $expected),
                        'cancel' => $this->service->cancelClarification($request, $id, $input, $expected),
                        'close' => $this->service->closeReview($request, $input, $expected),
                    };
                    $phase = 'read';
                    $review = $this->service->review($request, $input['version']);
                }

                return new JsonResponse(['data' => DataBiDeclarativeReviewProjection::make($review, $admin)]);
            }, 3);
        } catch (AuthenticationException) {
            return DataBiDeclarativeReviewJson::error('unauthenticated', 'Se requiere una sesión autenticada.', 401);
        } catch (AuthorizationException) {
            return DataBiDeclarativeReviewJson::error($admin ? 'forbidden' : 'not_found',
                $admin ? 'No tienes permiso para esta operación.' : 'Recurso no disponible.', $admin ? 403 : 404);
        } catch (ValidationException $e) {
            if (array_key_exists('schema', $e->errors())) {
                return DataBiDeclarativeReviewJson::error('schema_unavailable', 'El esquema de revisión declarativa no está disponible.', 503);
            }

            return DataBiDeclarativeReviewJson::error('validation_failed', 'El contenido de la solicitud no es válido.', 422);
        } catch (InvalidArgumentException $e) {
            $mapped = DataBiDeclarativeReviewErrors::domain($e, $phase);
            if ($mapped === null) {
                throw $e;
            }
            [$code, $message, $status] = $mapped;

            return DataBiDeclarativeReviewJson::error($code, $message, $status);
        } catch (QueryException $e) {
            report($e);
            [$code, $message, $status] = DataBiDeclarativeReviewErrors::infrastructure($e);

            return DataBiDeclarativeReviewJson::error($code, $message, $status);
        }
    }

    private function authorizeActor(Request $http, TransformationImplementationRequest $request, bool $admin): void
    {
        // Recursos y orden de locks T4C/B2. Nunca workspace_company_id ni identidades del payload.
        $company = DB::table('companies')->where('id', (int) $request->company_id)->sharedLock()->first(['id', 'subscriber_id', 'owner_user_id', 'active']);
        if ($company === null) {
            $this->fail('not_found', 'Recurso no disponible.', 404);
        }
        $subscriber = DB::table('subscribers')->where('id', (int) $company->subscriber_id)->sharedLock()->first(['id', 'active']);
        $user = User::query()->whereKey($http->user()->getAuthIdentifier())->sharedLock()->first();
        if ($user === null) {
            $this->fail('unauthenticated', 'Se requiere una sesión autenticada.', 401);
        }
        if (! $user->hasVerifiedEmail()) {
            $this->fail('email_unverified', 'Debes verificar tu correo.', 403);
        }
        try {
            if ($admin) {
                DataBiDeclarativeReviewActor::admin((int) $user->id, (string) $user->role, true);
            } else {
                $membership = DB::table('subscriber_user')->where('subscriber_id', (int) $company->subscriber_id)
                    ->where('user_id', (int) $user->id)->where('active', 1)->sharedLock()->first(['role']);
                if ((int) $company->active !== 1 || $subscriber === null || (int) $subscriber->active !== 1) {
                    $this->fail('not_found', 'Recurso no disponible.', 404);
                }
                DataBiDeclarativeReviewActor::tenant((int) $user->id, true, (string) $user->role, (int) $company->id,
                    (int) $company->owner_user_id, (int) $subscriber->id, (int) $company->subscriber_id, $membership?->role, $membership !== null);
            }
        } catch (InvalidArgumentException) {
            $this->fail($admin ? 'forbidden' : 'not_found', $admin ? 'No tienes permiso para esta operación.' : 'Recurso no disponible.', $admin ? 403 : 404);
        }
    }

    private function queryVersion(Request $http): ?int
    {
        if ($http->getContent() !== '' || array_diff(array_keys($http->query->all()), ['version']) !== []) {
            $this->fail('validation_failed', 'La consulta solo admite version.', 422);
        }
        $value = $http->query('version');
        if ($value === null && ! array_key_exists('version', $http->query->all())) {
            return null;
        }
        $version = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]) : false;
        if ($version === false) {
            $this->fail('validation_failed', 'La versión consultada no es válida.', 422);
        }

        return $version;
    }

    /** El normalizador recibe exclusivamente B1; expected_revision y CSRF quedan fuera. */
    private function payload(Request $http, string $action): array
    {
        if (! $http->isJson()) {
            $this->fail('unsupported_media_type', 'Se requiere un cuerpo JSON.', 415);
        }
        try {
            $body = json_decode($http->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('validation_failed', 'El cuerpo JSON no es válido.', 422);
        }
        $fields = match ($action) {
            'request' => ['version', 'requirement_key', 'message'],
            'close' => ['version', 'conclusion', 'limitations'],
            default => ['version', 'message'],
        };
        if (! $body instanceof stdClass || $http->query->all() !== [] || $http->allFiles() !== []
            || array_diff(array_keys((array) $body), [...$fields, 'expected_revision', '_token'])
            || array_diff([...$fields, 'expected_revision'], array_keys((array) $body))) {
            $this->fail('validation_failed', 'Hay atributos desconocidos o faltan atributos requeridos.', 422);
        }
        if (! is_int($body->version) || $body->version < 1 || $body->version > 9999
            || ! is_int($body->expected_revision) || $body->expected_revision < 0 || $body->expected_revision > 3) {
            $this->fail('validation_failed', 'La versión y la revisión deben ser enteros válidos.', 422);
        }
        $input = [];
        foreach ($fields as $field) {
            $input[$field] = $body->{$field};
        }

        return [$input, $body->expected_revision];
    }

    private function fail(string $code, string $message, int $status): never
    {
        throw new HttpResponseException(DataBiDeclarativeReviewJson::error($code, $message, $status));
    }
}
