<?php

namespace App\Services\Subscribers;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TenantUserManagementService
{
    public const ROLES = [
        'owner',
        'admin',
        'member',
        'billing',
    ];

    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
        private readonly TenantAccessService $tenantAccessService,
    ) {
    }

    public function tenantAdminContext(User $actor): array
    {
        $subscriberId = (int) (
            $this->subscriberResolver->resolve($actor)
            ?? 0
        );

        abort_unless($subscriberId > 0, 403);

        $tenantAccess = $this->tenantAccessService->resolve(
            $actor,
            $subscriberId
        );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN,
            403
        );

        $subscriber = Subscriber::query()->findOrFail($subscriberId);

        return [
            'subscriber' => $subscriber,
            'subscriber_id' => $subscriberId,
            'tenant_access' => $tenantAccess,
        ];
    }

    public function listMembers(Subscriber $subscriber): array
    {
        return $subscriber
            ->users()
            ->orderByRaw("
                case subscriber_user.role
                    when 'owner' then 1
                    when 'admin' then 2
                    when 'billing' then 3
                    else 4
                end
            ")
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'users.email_verified_at',
                'users.created_at',
            ])
            ->map(
                fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'global_role' => (string) $user->role,
                    'role' => (string) $user->pivot->role,
                    'active' => (bool) $user->pivot->active,
                    'email_verified' => $user->email_verified_at !== null,
                    'created_at' => $user->created_at?->toIso8601String(),
                ]
            )
            ->values()
            ->all();
    }

    public function addMember(
        User $actor,
        Subscriber $subscriber,
        array $data
    ): array {
        $email = mb_strtolower(trim((string) $data['email']));
        $name = trim((string) $data['name']);
        $role = (string) $data['role'];

        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Rol tenant no válido.',
            ]);
        }

        $result = DB::transaction(function () use (
            $actor,
            $subscriber,
            $email,
            $name,
            $role
        ): array {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            $created = false;

            if ($user) {
                if (($user->role ?? null) === 'admin') {
                    throw ValidationException::withMessages([
                        'email' =>
                            'Este correo pertenece a un administrador global '
                            .'de LAUDAAPI y no puede agregarse como miembro tenant.',
                    ]);
                }

                if (($user->role ?? null) !== 'subscriber') {
                    throw ValidationException::withMessages([
                        'email' =>
                            'El usuario existente no pertenece al lane '
                            .'central de subscribers.',
                    ]);
                }
            } else {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(
                        Str::password(48)
                    ),
                    'role' => 'subscriber',
                    'must_change_password' => false,
                ]);

                $created = true;
            }

            $membership = DB::table('subscriber_user')
                ->where('subscriber_id', $subscriber->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership) {
                throw ValidationException::withMessages([
                    'email' =>
                        'Este usuario ya pertenece al tenant. '
                        .'Usa las acciones de rol o estado.',
                ]);
            }

            DB::table('subscriber_user')->insert([
                'subscriber_id' => $subscriber->id,
                'user_id' => $user->id,
                'role' => $role,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'user' => $user,
                'created' => $created,
                'actor_id' => (int) $actor->id,
            ];
        });

        $resetStatus = null;

        if ($result['created']) {
            try {
                $resetStatus = Password::sendResetLink([
                    'email' => $result['user']->email,
                ]);
            } catch (\Throwable $e) {
                report($e);
                $resetStatus = 'tenant_user_reset_delivery_failed';
            }
        }

        return [
            'user' => $result['user'],
            'created' => $result['created'],
            'reset_status' => $resetStatus,
        ];
    }

    public function updateRole(
        User $actor,
        Subscriber $subscriber,
        User $member,
        string $role
    ): void {
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Rol tenant no válido.',
            ]);
        }

        if ((int) $actor->id === (int) $member->id) {
            throw ValidationException::withMessages([
                'role' =>
                    'No puedes cambiar tu propio rol desde esta pantalla.',
            ]);
        }

        DB::transaction(function () use (
            $subscriber,
            $member,
            $role
        ): void {
            $membership = $this->membershipForUpdate(
                $subscriber,
                $member
            );

            $currentRole = (string) $membership->role;

            if (
                $currentRole === 'owner'
                && $role !== 'owner'
                && (bool) $membership->active
            ) {
                $this->assertAnotherActiveOwner(
                    $subscriber,
                    $member
                );
            }

            DB::table('subscriber_user')
                ->where('id', $membership->id)
                ->update([
                    'role' => $role,
                    'updated_at' => now(),
                ]);
        });
    }

    public function toggleActive(
        User $actor,
        Subscriber $subscriber,
        User $member
    ): bool {
        if ((int) $actor->id === (int) $member->id) {
            throw ValidationException::withMessages([
                'active' =>
                    'No puedes desactivar tu propio acceso desde esta pantalla.',
            ]);
        }

        return DB::transaction(function () use (
            $subscriber,
            $member
        ): bool {
            $membership = $this->membershipForUpdate(
                $subscriber,
                $member
            );

            $currentlyActive = (bool) $membership->active;

            if (
                $currentlyActive
                && (string) $membership->role === 'owner'
            ) {
                $this->assertAnotherActiveOwner(
                    $subscriber,
                    $member
                );
            }

            $next = ! $currentlyActive;

            DB::table('subscriber_user')
                ->where('id', $membership->id)
                ->update([
                    'active' => $next ? 1 : 0,
                    'updated_at' => now(),
                ]);

            return $next;
        });
    }

    public function resendAccess(
        Subscriber $subscriber,
        User $member
    ): string {
        $membership = DB::table('subscriber_user')
            ->where('subscriber_id', $subscriber->id)
            ->where('user_id', $member->id)
            ->first();

        abort_unless($membership, 404);

        try {
            return Password::sendResetLink([
                'email' => $member->email,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return 'tenant_user_reset_delivery_failed';
        }
    }

    private function membershipForUpdate(
        Subscriber $subscriber,
        User $member
    ): object {
        $membership = DB::table('subscriber_user')
            ->where('subscriber_id', $subscriber->id)
            ->where('user_id', $member->id)
            ->lockForUpdate()
            ->first();

        abort_unless($membership, 404);

        return $membership;
    }

    private function assertAnotherActiveOwner(
        Subscriber $subscriber,
        User $member
    ): void {
        $otherActiveOwners = DB::table('subscriber_user')
            ->where('subscriber_id', $subscriber->id)
            ->where('role', 'owner')
            ->where('active', 1)
            ->where('user_id', '!=', $member->id)
            ->count();

        if ($otherActiveOwners < 1) {
            throw ValidationException::withMessages([
                'role' =>
                    'Debe permanecer al menos un owner activo en el tenant.',
            ]);
        }
    }
}
