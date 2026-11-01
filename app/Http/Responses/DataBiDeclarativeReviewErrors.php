<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Services\Diagnosis\DataBiClarificationContract;
use App\Services\Diagnosis\DataBiClarificationTransitionPolicy;
use App\Services\Diagnosis\DataBiDeclarativeReviewClosureContract;
use App\Services\Diagnosis\DataBiDeclarativeReviewContext;
use App\Services\Diagnosis\DataBiDeclarativeReviewPersistencePolicy;
use App\Services\Diagnosis\DataBiDeclarativeReviewService;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use App\Services\Diagnosis\DataBiTenantDeclarationPersistencePolicy;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/** Adaptador HTTP de excepciones B2 no tipadas; no modifica sus contratos aprobados. */
final class DataBiDeclarativeReviewErrors
{
    public static function infrastructure(QueryException $error): array
    {
        return [in_array($error->errorInfo[0] ?? '', ['42S02', '42S22'], true) ? 'schema_unavailable' : 'service_unavailable',
            'El servicio de revisión declarativa no está disponible.', 503];
    }

    /** @return array{0:string,1:string,2:int}|null null conserva el error inesperado del handler. */
    public static function domain(InvalidArgumentException $error, string $phase): ?array
    {
        $frame = $error->getTrace()[0] ?? [];
        $class = $frame['class'] ?? '';
        $method = $frame['function'] ?? '';
        $known = [DataBiDeclarativeReviewService::class, DataBiDeclarativeReviewContext::class,
            DataBiDeclarativeReviewPersistencePolicy::class, DataBiClarificationContract::class,
            DataBiDeclarativeReviewClosureContract::class, DataBiClarificationTransitionPolicy::class,
            DataBiInformationTenantDeclarationContract::class, DataBiTenantDeclarationPersistencePolicy::class];
        if (! in_array($class, $known, true)) {
            return null;
        }
        if ($class === DataBiDeclarativeReviewContext::class && $method === 'assertCurrent') {
            return ['questionnaire_replaced', 'El cuestionario publicado cambió. Consulta la versión vigente.', 409];
        }
        if ($phase === 'read') {
            return match ($error->getMessage()) {
                'Se requiere una declaración enviada válida.' => ['declaration_not_submitted', 'Todavía no existe una declaración enviada válida para esta versión.', 409],
                'No existe un cuestionario publicado.' => ['questionnaire_unavailable', 'Todavía no existe un cuestionario publicado.', 409],
                'La versión publicada no está disponible.' => ['not_found', 'Recurso no disponible.', 404],
                default => self::integrity(),
            };
        }
        // B2 agrupa varias causas en los mismos tipos/métodos: mapa exacto y acotado a su origen.
        $explicit = match ($error->getMessage()) {
            'Se requiere una declaración enviada válida.' => ['declaration_not_submitted', 'Todavía no existe una declaración enviada válida para esta versión.', 409],
            'No existe un cuestionario publicado.' => ['questionnaire_unavailable', 'Todavía no existe un cuestionario publicado.', 409],
            'La versión publicada no está disponible.' => ['not_found', 'Recurso no disponible.', 404],
            'Conflicto: la aclaración tiene otra revisión.', 'Conflicto: una aclaración nueva requiere revisión cero.',
            'Conflicto: todavía no existe un cierre.' => ['revision_conflict', 'La revisión cambió. Consulta el estado vigente antes de reintentar.', 409],
            'Solicitud duplicada o revisión incompatible.', 'Solicitud de aclaración duplicada.' => ['duplicate_clarification', 'La pregunta ya existe o la revisión no corresponde a esa operación.', 409],
            'Aclaración o acción no disponible en esta declaración.' => ['not_found', 'Recurso no disponible.', 404],
            'El cierre existente es inmutable.', 'El cierre declarativo histórico es inmutable.',
            'La revisión ya tiene un cierre; su historia no puede reabrirse.' => ['review_closed', 'La revisión declarativa ya está cerrada.', 409],
            'Existen aclaraciones abiertas para esta declaración.' => ['clarifications_open', 'Resuelve o cancela las aclaraciones abiertas antes de cerrar.', 409],
            'Identificador o cantidad de aclaraciones inválidos.' => ['clarification_limit_reached', 'Se alcanzó el límite de aclaraciones de esta declaración.', 409],
            default => null,
        };
        if ($explicit !== null) {
            return $explicit;
        }
        if ($class === DataBiClarificationTransitionPolicy::class) {
            return ['clarification_state_conflict', 'El estado de la aclaración no permite esta operación.', 409];
        }
        if (($class === DataBiDeclarativeReviewContext::class && in_array($method, ['text', 'keys', 'requirement'], true))
            || ($class === DataBiInformationTenantDeclarationContract::class && $method === 'text')
            || ($class === DataBiDeclarativeReviewPersistencePolicy::class && $method === 'input')) {
            return ['validation_failed', 'Revisa el requerimiento, los textos y la revisión indicados.', 422];
        }
        if ($class === DataBiInformationTenantDeclarationContract::class) {
            return self::integrity();
        }
        if (in_array($method, ['__construct', 'columns', 'storedClarification', 'storedClosure', 'validate',
            'collection', 'history', 'instant', 'publishedRequirements', 'storedSnapshot', 'assertPublished', 'loadState', 'snapshot'], true)) {
            return self::integrity();
        }

        return null;
    }

    private static function integrity(): array
    {
        return ['declarative_integrity_conflict', 'La revisión declarativa contiene una inconsistencia. Solicita revisión a LAUDA.', 409];
    }
}
