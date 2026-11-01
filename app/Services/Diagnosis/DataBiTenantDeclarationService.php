<?php

namespace App\Services\Diagnosis;

use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * D2F-T4C. Persistencia declarativa sin endpoints, archivos ni efectos comerciales.
 * El actor proviene del guard; request se recarga desde BD por su ID.
 * input conserva exactamente el contrato T4B: version + declarations.
 * expectedRevision es independiente: 0 para crear, revisión observada para editar/enviar.
 */
final class DataBiTenantDeclarationService
{
    public function saveDraft(
        TransformationImplementationRequest $request,
        array $input,
        int $expectedRevision
    ): DataBiTenantDeclaration {
        return $this->write($request, $input, $expectedRevision, 'draft');
    }

    public function submit(
        TransformationImplementationRequest $request,
        array $input,
        int $expectedRevision
    ): DataBiTenantDeclaration {
        return $this->write($request, $input, $expectedRevision, 'submit');
    }

    /** Consulta de la declaración vigente, sea draft o submitted; nunca crea una fila. */
    public function findExisting(
        TransformationImplementationRequest $request,
        int $version
    ): ?DataBiTenantDeclaration {
        return $this->withCurrent($request, $version, function (array $sent): ?DataBiTenantDeclaration {
            $current = $this->lockedDeclaration($sent['id']);
            if ($current !== null) {
                DataBiTenantDeclarationPersistencePolicy::storedSnapshot($sent, $current->attributesToArray());
            }

            return $current;
        });
    }

    private function write(
        TransformationImplementationRequest $request,
        array $input,
        int $expectedRevision,
        string $mode
    ): DataBiTenantDeclaration {
        if (!is_int($input['version'] ?? null)) {
            throw ValidationException::withMessages(['version' => 'Se requiere la versión entera de T4B.']);
        }

        return $this->withCurrent($request, $input['version'], function (array $sent, int $actorId) use (
            $input, $expectedRevision, $mode
        ): DataBiTenantDeclaration {
            $normalized = DataBiInformationTenantDeclarationContract::normalize($sent, $input, $mode);
            $current = $this->lockedDeclaration($sent['id']);
            $state = $current?->attributesToArray();
            if ($state !== null) {
                DataBiTenantDeclarationPersistencePolicy::storedSnapshot($sent, $state);
            }
            $decision = DataBiTenantDeclarationPersistencePolicy::decide(
                $state, $normalized['content_sha256'], $expectedRevision, $mode
            );
            if ($decision['action'] === 'unchanged') {
                return $current;
            }

            $declaration = $current ?? new DataBiTenantDeclaration;
            if ($current === null) {
                $declaration->forceFill([
                    'data_bi_information_request_id' => $sent['id'],
                    'company_id' => $sent['company_id'],
                    'created_by_user_id' => $actorId,
                ]);
            }
            $declaration->forceFill([
                'status' => $decision['status'],
                'revision' => $decision['revision'],
                'declaration_snapshot' => $normalized,
                'content_sha256' => $normalized['content_sha256'],
                'updated_by_user_id' => $actorId,
                'submitted_by_user_id' => $decision['status'] === 'submitted' ? $actorId : null,
                'submitted_at' => $decision['status'] === 'submitted' ? now() : null,
            ]);
            $declaration->save();

            return $declaration;
        });
    }

    private function lockedDeclaration(int $questionnaireId): ?DataBiTenantDeclaration
    {
        // No filtrar por company: detectar una fila inconsistente, no ocultarla como inexistente.
        return DataBiTenantDeclaration::query()
            ->where('data_bi_information_request_id', $questionnaireId)
            ->lockForUpdate()->first();
    }

    private function withCurrent(TransformationImplementationRequest $request, int $version, callable $operation): mixed
    {
        $actor = Auth::user();
        if (!$actor instanceof User || !$actor->exists || (int) $actor->getKey() < 1) {
            throw new AuthenticationException('Se requiere un usuario autenticado.');
        }
        if (!$request->exists || (int) $request->getKey() < 1) {
            throw ValidationException::withMessages(['implementation_request' => 'Solicitud no disponible.']);
        }
        $this->requireSchema();

        try {
            return DB::transaction(function () use ($request, $version, $operation, $actor): mixed {
                // Mismo primer lock que publicación y revisión: serializa incluso la primera inserción.
                $locked = TransformationImplementationRequest::query()
                    ->whereKey((int) $request->getKey())
                    ->where('capability_key', 'data_transformation_bi')
                    ->lockForUpdate()->first();
                if ($locked === null) {
                    throw new AuthorizationException('Solicitud no disponible.');
                }
                $actorId = (int) $actor->getKey();
                $company = DB::table('companies')->where('id', (int) $locked->company_id)
                    ->where('active', 1)->sharedLock()->first(['id', 'subscriber_id', 'owner_user_id']);
                if ($company === null || (int) $company->subscriber_id < 1) {
                    throw new AuthorizationException('Empresa no disponible.');
                }
                $subscriber = DB::table('subscribers')->where('id', (int) $company->subscriber_id)
                    ->where('active', 1)->sharedLock()->first(['id']);
                $user = DB::table('users')->where('id', $actorId)->sharedLock()->first(['id']);
                $membership = DB::table('subscriber_user')
                    ->where('subscriber_id', (int) $company->subscriber_id)
                    ->where('user_id', $actorId)->where('active', 1)
                    ->sharedLock()->first(['role']);
                if ($subscriber === null || $user === null
                    || !DataBiInformationTenantAccessPolicy::mayRead(
                        $actorId, (int) ($company->owner_user_id ?? 0),
                        $membership?->role, $membership !== null
                    )) {
                    throw new AuthorizationException('Se requiere owner/admin con membresía activa de la empresa.');
                }

                // Un borrador LAUDA más nuevo no sustituye la última versión realmente enviada.
                // No saltar un sent con auditoría rota: debe fallar cerrado.
                $published = DataBiInformationRequest::query()
                    ->where('transformation_implementation_request_id', (int) $locked->getKey())
                    ->where('company_id', (int) $company->id)
                    ->where('status', DataBiInformationRequest::STATUS_SENT)
                    ->orderByDesc('version')->lockForUpdate()->first();
                if ($published === null) {
                    throw new InvalidArgumentException('No existe un cuestionario publicado.');
                }
                $sent = $published->attributesToArray();
                $sent['id'] = (int) $published->getKey();
                $sent['transformation_implementation_request_id'] = (int) $published->transformation_implementation_request_id;
                $sent['sent_by_user_id'] = $published->getAttribute('sent_by_user_id') === null
                    ? null : (int) $published->getAttribute('sent_by_user_id');
                DataBiTenantDeclarationPersistencePolicy::assertPublished([
                    'id' => (int) $locked->getKey(), 'company_id' => (int) $company->id,
                    'capability_key' => (string) $locked->capability_key,
                ], $sent, $version);
                // También para consultas sin declaraciones: verificar siempre snapshot y hash originales.
                DataBiInformationTenantDeclarationContract::normalize($sent, [
                    'version' => $version, 'declarations' => [],
                ], 'draft');

                return $operation($sent, $actorId);
            }, 3);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['declaration' => $e->getMessage()]);
        }
    }

    private function requireSchema(): void
    {
        if (!Schema::hasTable('data_bi_information_requests')
            || !Schema::hasColumn('data_bi_information_requests', 'sent_by_user_id')
            || !Schema::hasTable('data_bi_tenant_declarations')
            || !Schema::hasColumns('data_bi_tenant_declarations', [
                'id', 'data_bi_information_request_id', 'company_id', 'status', 'revision',
                'declaration_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id',
                'submitted_by_user_id', 'submitted_at', 'created_at', 'updated_at',
            ])) {
            throw ValidationException::withMessages(['schema' => 'Migraciones de publicación o declaraciones pendientes.']);
        }
    }
}
