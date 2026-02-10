<?php

namespace App\Http\Controllers;

use App\Models\ActivationRequest;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivationController extends Controller
{
    public function accept(ActivationRequest $activation)
    {
        try {
            DB::transaction(function () use (&$activation) {
                $activation = ActivationRequest::whereKey($activation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // ✅ Solo permitimos usar el link si está pending o accepted
                // (accepted = ya confirmó correo y puede entrar al dashboard)
                if (!in_array($activation->status, [
                    ActivationRequest::STATUS_PENDING,
                    ActivationRequest::STATUS_ACCEPTED,
                ], true)) {
                    abort(422, 'Esta activación ya no está disponible.');
                }

                $user = $activation->user;

                // ✅ Primera vez: pending -> accepted
                if ($activation->status === ActivationRequest::STATUS_PENDING) {
                    $activation->update([
                        'status' => ActivationRequest::STATUS_ACCEPTED,
                    ]);

                    AuditService::log('activation_accepted', $activation, [
                        'user_id' => $activation->user_id,
                        'email' => $activation->email,
                        'company' => $activation->company,
                    ]);
                }

                // ✅ Marcar email verificado (si aplica)
                if ($user && is_null($user->email_verified_at)) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            });

            // ✅ Loguear y mandar a subscriber
            if ($activation->user_id) {
                Auth::loginUsingId($activation->user_id);

                // Mensaje coherente con tu semántica:
                // accepted = acceso concedido, trial se inicia luego desde dashboard
                return redirect()->route('subscriber')
                    ->with('success', 'Acceso concedido. Ya puedes iniciar tu prueba desde el dashboard.');
            }

            return redirect('/login')
                ->with('info', 'Correo confirmado. Inicia sesión para continuar.');
        } catch (\Throwable $e) {
            Log::error('Error al aceptar activación: ' . $e->getMessage(), [
                'activation_id' => $activation->id ?? null,
                'exception' => $e,
            ]);

            return redirect('/login')
                ->with('error', 'Ocurrió un error al confirmar la activación. Intenta más tarde.');
        }
    }
}
