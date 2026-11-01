<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use App\Models\DataBiDeclarationClarification;
use App\Models\DataBiDeclarationClarificationEvent;
use App\Models\DataBiDeclarativeReviewClosure;
use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * T4F-B2. Sin HTTP ni efectos comerciales. Todas las lecturas y decisiones se revalidan bajo lock.
 * request solo aporta su ID persistido; actor, empresa, subscriber, auditoría e historial son del servidor.
 * Devuelve snapshots B1 para uso interno; un futuro controlador debe proyectar sus campos públicos.
 */
final class DataBiDeclarativeReviewService
{
    public function requestClarification(TransformationImplementationRequest $request, array $input, int $expectedRevision = 0): array
    {
        return $this->withReview($request, $this->version($input), 'admin', function ($context, $actor, $state) use ($input, $expectedRevision): array {
            $decision = DataBiDeclarativeReviewPersistencePolicy::request($context, $actor, $input, $expectedRevision,
                random_int(1, 9007199254740991), $this->now(), $state['clarifications'], $state['closure']);

            return $this->persistClarification($context, $decision, $state);
        });
    }

    public function answerClarification(TransformationImplementationRequest $request, int $clarificationId, array $input, int $expectedRevision): array
    {
        return $this->transition($request, $clarificationId, $input, $expectedRevision, 'answer', 'tenant');
    }

    public function resolveClarification(TransformationImplementationRequest $request, int $clarificationId, array $input, int $expectedRevision): array
    {
        return $this->transition($request, $clarificationId, $input, $expectedRevision, 'resolve', 'admin');
    }

    public function cancelClarification(TransformationImplementationRequest $request, int $clarificationId, array $input, int $expectedRevision): array
    {
        return $this->transition($request, $clarificationId, $input, $expectedRevision, 'cancel', 'admin');
    }

    public function closeReview(TransformationImplementationRequest $request, array $input, int $expectedRevision = 0): array
    {
        return $this->withReview($request, $this->version($input), 'admin', function ($context, $actor, $state) use ($input, $expectedRevision): array {
            $decision = DataBiDeclarativeReviewPersistencePolicy::close($context, $actor, $input, $expectedRevision,
                $this->now(), $state['clarifications'], $state['closure']);
            if ($decision['action'] === 'unchanged') {
                return $decision['snapshot'];
            }
            $row = new DataBiDeclarativeReviewClosure;
            $row->forceFill(DataBiDeclarativeReviewPersistencePolicy::closureRow($decision['snapshot']))->save();

            return $this->loadState($context)['closure'];
        });
    }

    /** null selecciona la última publicación; una versión explícita conserva su ámbito histórico. */
    public function review(TransformationImplementationRequest $request, ?int $version = null): array
    {
        return $this->withReview($request, $version, 'read', static fn ($context, $actor, $state): array => [
            'reference' => $context->reference(), 'is_current' => $context->isCurrent(),
            'clarifications' => $state['clarifications'], 'closure' => $state['closure'],
        ]);
    }

    private function transition(TransformationImplementationRequest $request, int $id, array $input, int $expected, string $action, string $mode): array
    {
        return $this->withReview($request, $this->version($input), $mode, function ($context, $actor, $state) use ($id, $input, $expected, $action): array {
            $decision = DataBiDeclarativeReviewPersistencePolicy::transition($context, $actor, $state['clarifications'],
                $id, $action, $input, $expected, $this->now(), $state['closure']);

            return $this->persistClarification($context, $decision, $state);
        });
    }

    private function persistClarification(DataBiDeclarativeReviewContext $context, array $decision, array $loaded): array
    {
        $snapshot = $decision['snapshot'];
        if ($decision['action'] === 'unchanged') {
            return $snapshot; // Ningún save/touch/INSERT de eventos en un reintento.
        }
        $row = $decision['action'] === 'create' ? new DataBiDeclarationClarification : $loaded['models'][$snapshot['id']];
        $row->forceFill(DataBiDeclarativeReviewPersistencePolicy::clarificationRow($snapshot))->save();
        $events = DataBiDeclarativeReviewPersistencePolicy::eventRows($context, $snapshot);
        $event = new DataBiDeclarationClarificationEvent;
        $event->forceFill($events[count($events) - 1])->save();
        // Leer de nuevo: si la persistencia no coincide, toda la operación se revierte.
        foreach ($this->loadState($context)['clarifications'] as $state) {
            if ($state['id'] === $snapshot['id']) {
                return $state;
            }
        }
        throw new InvalidArgumentException('La aclaración guardada no está disponible.');
    }

    private function withReview(TransformationImplementationRequest $request, ?int $version, string $mode, callable $operation): array
    {
        $authenticated = Auth::user();
        if (! $authenticated instanceof User || ! $authenticated->exists || (int) $authenticated->getKey() < 1) {
            throw new AuthenticationException('Se requiere un usuario autenticado.');
        }
        if (! $request->exists || (int) $request->getKey() < 1 || ($version !== null && ($version < 1 || $version > 9999))) {
            throw new InvalidArgumentException('Solicitud o versión no disponible.');
        }
        $this->requireSchema();

        return DB::transaction(function () use ($request, $version, $mode, $operation, $authenticated): array {
            // Mismo primer lock de T4C/publicación: también serializa la ausencia inicial.
            $locked = TransformationImplementationRequest::query()->whereKey((int) $request->getKey())
                ->where('capability_key', 'data_transformation_bi')->lockForUpdate()->first();
            if ($locked === null) {
                throw new AuthorizationException('Solicitud no disponible.');
            }
            $actor = $this->actor((int) $authenticated->getKey(), (int) $locked->company_id, $mode);
            $published = DataBiInformationRequest::query()->where('transformation_implementation_request_id', (int) $locked->id)
                ->where('status', 'sent')->orderByDesc('version')->lockForUpdate()->first();
            if ($published === null) {
                throw new InvalidArgumentException('No existe un cuestionario publicado.');
            }
            $selected = $version === null || $version === (int) $published->version ? $published
                : DataBiInformationRequest::query()->where('transformation_implementation_request_id', (int) $locked->id)
                    ->where('status', 'sent')->where('version', $version)->lockForUpdate()->first();
            if ($selected === null) {
                throw new InvalidArgumentException('La versión publicada no está disponible.');
            }
            // No filtrar company/status para ocultar filas inconsistentes o una declaración draft.
            $declaration = DataBiTenantDeclaration::query()->where('data_bi_information_request_id', (int) $selected->id)
                ->lockForUpdate()->first();
            $context = new DataBiDeclarativeReviewContext([
                'id' => (int) $locked->id, 'company_id' => (int) $locked->company_id, 'capability_key' => $locked->capability_key,
            ], $this->publication($selected), $declaration?->attributesToArray(), $this->publication($published));
            $actor->assertScope($context);
            if ($mode !== 'read') {
                $context->assertCurrent($version);
            }
            $state = $this->loadState($context);

            return $operation($context, $actor, $state);
        }, 3);
    }

    private function actor(int $userId, int $companyId, string $mode): DataBiDeclarativeReviewActor
    {
        $company = DB::table('companies')->where('id', $companyId)->sharedLock()->first(['id', 'subscriber_id', 'owner_user_id', 'active']);
        if ($company === null) {
            throw new AuthorizationException('Empresa no disponible.');
        }
        $subscriber = DB::table('subscribers')->where('id', (int) $company->subscriber_id)->sharedLock()->first(['id', 'active']);
        $user = User::query()->whereKey($userId)->sharedLock()->first();
        if ($user === null) {
            throw new AuthenticationException('Usuario autenticado no disponible.');
        }
        try {
            if ($mode === 'admin' || ($mode === 'read' && $user->role === 'admin')) {
                return DataBiDeclarativeReviewActor::admin((int) $user->id, (string) $user->role, $user->hasVerifiedEmail());
            }
            $membership = DB::table('subscriber_user')->where('subscriber_id', (int) $company->subscriber_id)
                ->where('user_id', (int) $user->id)->where('active', 1)->sharedLock()->first(['role']);
            if ((int) $company->active !== 1 || $subscriber === null || (int) $subscriber->active !== 1) {
                throw new InvalidArgumentException('Empresa o subscriber no disponible.');
            }

            return DataBiDeclarativeReviewActor::tenant((int) $user->id, $user->hasVerifiedEmail(), (string) $user->role,
                (int) $company->id, (int) $company->owner_user_id, (int) $subscriber->id, (int) $company->subscriber_id,
                $membership?->role, $membership !== null);
        } catch (InvalidArgumentException $e) {
            throw new AuthorizationException($e->getMessage());
        }
    }

    private function loadState(DataBiDeclarativeReviewContext $context): array
    {
        $reference = $context->reference();
        $scope = static fn ($query) => $query->where('data_bi_tenant_declaration_id', $reference['declaration_id'])
            ->orWhere('data_bi_information_request_id', $reference['information_request_id']);
        $rows = DataBiDeclarationClarification::query()->where($scope)->orderBy('id')->lockForUpdate()->get();
        if ($rows->count() > DataBiClarificationContract::MAX_CLARIFICATIONS) {
            throw new InvalidArgumentException('Colección persistida fuera del límite B1.');
        }
        $events = $rows->isEmpty() ? collect() : DataBiDeclarationClarificationEvent::query()
            ->whereIn('data_bi_declaration_clarification_id', $rows->modelKeys())
            ->orderBy('data_bi_declaration_clarification_id')->orderBy('sequence')->lockForUpdate()->get();
        $byClarification = $events->groupBy('data_bi_declaration_clarification_id');
        $states = [];
        $models = [];
        foreach ($rows as $row) {
            $states[] = DataBiDeclarativeReviewPersistencePolicy::storedClarification($context, $row->attributesToArray(),
                ($byClarification->get($row->id) ?? collect())->map(fn ($event) => $event->attributesToArray())->values()->all());
            $models[$row->id] = $row;
        }
        $states = DataBiClarificationContract::collection($context, $states);
        $closures = DataBiDeclarativeReviewClosure::query()->where($scope)->orderBy('id')->lockForUpdate()->get();
        if ($closures->count() > 1) {
            throw new InvalidArgumentException('Cierres persistidos contradictorios.');
        }
        $closure = $closures->isEmpty() ? null : DataBiDeclarativeReviewPersistencePolicy::storedClosure(
            $context, $closures->first()->attributesToArray(), $states);

        return ['clarifications' => $states, 'closure' => $closure, 'models' => $models];
    }

    private function publication(DataBiInformationRequest $row): array
    {
        return [...$row->attributesToArray(), 'sent_by_user_id' => $row->sent_by_user_id === null ? null : (int) $row->sent_by_user_id];
    }

    private function version(array $input): int
    {
        if (! is_int($input['version'] ?? null) || $input['version'] < 1 || $input['version'] > 9999) {
            throw new InvalidArgumentException('Se requiere la versión entera publicada.');
        }

        return $input['version'];
    }

    private function now(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z');
    }

    private function requireSchema(): void
    {
        $columns = [
            'data_bi_information_requests' => ['id', 'company_id', 'transformation_implementation_request_id',
                'status', 'version', 'review_snapshot', 'content_sha256', 'sent_at', 'sent_by_user_id'],
            'data_bi_tenant_declarations' => ['id', 'company_id', 'data_bi_information_request_id', 'status', 'revision',
                'declaration_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id', 'submitted_at'],
            'data_bi_declaration_clarifications' => ['id', 'transformation_implementation_request_id', 'company_id',
                'data_bi_information_request_id', 'data_bi_tenant_declaration_id', 'requirement_key', 'question_sha256',
                'status', 'revision', 'clarification_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at'],
            'data_bi_declaration_clarification_events' => ['id', 'data_bi_declaration_clarification_id', 'sequence',
                'action', 'message', 'actor_user_id', 'actor_kind', 'occurred_at', 'previous_sha256', 'projection_sha256', 'content_sha256'],
            'data_bi_declarative_review_closures' => ['id', 'transformation_implementation_request_id', 'company_id',
                'data_bi_information_request_id', 'data_bi_tenant_declaration_id', 'conclusion', 'limitations',
                'declared_gaps', 'declared_limitations', 'clarification_references', 'closure_snapshot', 'content_sha256', 'closed_by_user_id', 'closed_at'],
        ];
        foreach ($columns as $table => $required) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $required)) {
                throw ValidationException::withMessages(['schema' => 'Esquema declarativo B2 no disponible; migraciones pendientes.']);
            }
        }
    }
}
