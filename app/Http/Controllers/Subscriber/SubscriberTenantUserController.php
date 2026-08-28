<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Subscribers\TenantUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

final class SubscriberTenantUserController extends Controller
{
    public function __construct(
        private readonly TenantUserManagementService $users
    ) {
    }

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor, 403);

        $context = $this->users->tenantAdminContext($actor);
        $subscriber = $context['subscriber'];
        $members = $this->users->listMembers($subscriber);

        $active = collect($members)
            ->where('active', true)
            ->count();

        $owners = collect($members)
            ->where('active', true)
            ->where('role', 'owner')
            ->count();

        $admins = collect($members)
            ->where('active', true)
            ->filter(
                fn (array $member): bool =>
                    in_array(
                        $member['role'],
                        ['owner', 'admin'],
                        true
                    )
            )
            ->count();

        return Inertia::render('Subscriber/Users/Index', [
            'subscriber' => [
                'id' => (int) $subscriber->id,
                'name' => (string) $subscriber->name,
            ],
            'members' => $members,
            'role_options' => [
                [
                    'value' => 'owner',
                    'label' => 'Owner',
                    'description' =>
                        'Control total central del tenant.',
                ],
                [
                    'value' => 'admin',
                    'label' => 'Administrador',
                    'description' =>
                        'Control Panel, App Store, empresa y facturación.',
                ],
                [
                    'value' => 'member',
                    'label' => 'Usuario',
                    'description' =>
                        'Solo Mis Apps y acceso a soluciones autorizadas.',
                ],
                [
                    'value' => 'billing',
                    'label' => 'Facturación',
                    'description' =>
                        'Mis Apps y funciones centrales de facturación.',
                ],
            ],
            'summary' => [
                'total' => count($members),
                'active' => $active,
                'admins' => $admins,
                'owners' => $owners,
            ],
            'current_user_id' => (int) $actor->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor, 403);

        $context = $this->users->tenantAdminContext($actor);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'role' => [
                'required',
                'in:owner,admin,member,billing',
            ],
        ]);

        $result = $this->users->addMember(
            $actor,
            $context['subscriber'],
            $data
        );

        if (! $result['created']) {
            return back()->with(
                'success',
                'Usuario existente agregado al tenant. '
                .'Puede iniciar sesión con sus credenciales actuales.'
            );
        }

        if ($result['reset_status'] === Password::RESET_LINK_SENT) {
            return back()->with(
                'success',
                'Usuario agregado. Enviamos un enlace seguro '
                .'para que defina su contraseña.'
            );
        }

        return back()
            ->with(
                'success',
                'Usuario agregado correctamente.'
            )
            ->with(
                'error',
                'No pudimos entregar el enlace de acceso. '
                .'Usa "Reenviar acceso" para intentarlo nuevamente.'
            );
    }

    public function updateRole(
        Request $request,
        User $member
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor, 403);

        $context = $this->users->tenantAdminContext($actor);

        $data = $request->validate([
            'role' => [
                'required',
                'in:owner,admin,member,billing',
            ],
        ]);

        $this->users->updateRole(
            $actor,
            $context['subscriber'],
            $member,
            (string) $data['role']
        );

        return back()->with(
            'success',
            'Rol actualizado correctamente.'
        );
    }

    public function toggleActive(
        Request $request,
        User $member
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor, 403);

        $context = $this->users->tenantAdminContext($actor);

        $active = $this->users->toggleActive(
            $actor,
            $context['subscriber'],
            $member
        );

        return back()->with(
            'success',
            $active
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.'
        );
    }

    public function resendAccess(
        Request $request,
        User $member
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor, 403);

        $context = $this->users->tenantAdminContext($actor);

        $status = $this->users->resendAccess(
            $context['subscriber'],
            $member
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with(
                'success',
                'Enlace de acceso enviado.'
            );
        }

        return back()->with(
            'error',
            'No se pudo enviar el enlace de acceso. '
            .'Revisa la configuración de correo e inténtalo nuevamente.'
        );
    }
}
