<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivationRequest;
use App\Mail\ActivationConfirmationMail;
use App\Mail\ActivationInternalNotificationMail;
use App\Models\ActivationRequest;
use App\Models\ContactRequest;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivationRequestController extends Controller
{
    public function store(StoreActivationRequest $request)
    {
        $wasExisting = false;

        try {
            /** @var ActivationRequest $activation */
            $activation = DB::transaction(function () use ($request, &$wasExisting) {

                $payload = $request->validated();

                // Normalizar email
                $payload['email'] = strtolower(trim($payload['email']));

                /**
                 * ✅ Anti-duplicados: SOLO 1 activa por email
                 * Activa = pending | accepted | trialing
                 *
                 * ⚠️ FIX IMPORTANTE:
                 * si existe pending pero su link expiró (24h), marcarla expired y permitir crear una nueva.
                 */
                $existingActive = ActivationRequest::query()
                    ->where('email', $payload['email'])
                    ->whereIn('status', [
                        ActivationRequest::STATUS_PENDING,
                        ActivationRequest::STATUS_ACCEPTED,
                        ActivationRequest::STATUS_TRIALING,
                    ])
                    ->latest('id')
                    ->first();

                if ($existingActive) {
                    // ✅ Si es pending, validar expiración real (24h)
                    if ($existingActive->status === ActivationRequest::STATUS_PENDING) {
                        $meta = $existingActive->metadata ?? [];

                        $expiresAt = !empty($meta['activation_email_expires_at'])
                            ? Carbon::parse($meta['activation_email_expires_at'])
                            : $existingActive->created_at->copy()->addHours(24);

                        // ✅ Si expiró: marcar como expired y permitir crear una nueva solicitud
                        if ($expiresAt->isPast()) {
                            $existingActive->update(['status' => ActivationRequest::STATUS_EXPIRED]);

                            AuditService::log('activation_expired_auto', $existingActive, [
                                'email' => $existingActive->email,
                                'activation_request_id' => $existingActive->id,
                                'expired_at' => now()->toISOString(),
                            ]);

                            $existingActive = null;
                        }
                    }

                    if ($existingActive) {
                        $wasExisting = true;
                        return $existingActive;
                    }
                }

                // Buscar usuario
                $user = User::where('email', $payload['email'])->first();

                // Permite subscriber y user; bloquea otros roles
                if ($user && !in_array($user->role, ['subscriber', 'user'], true)) {
                    throw ValidationException::withMessages([
                        'email' => ['Este correo ya está registrado con otro tipo de cuenta.'],
                    ]);
                }

                // Crear subscriber si no existe
                if (!$user) {
                    $user = User::create([
                        'name' => $payload['name'] ?? 'Usuario LaudaAPI',
                        'email' => $payload['email'],
                        'password' => Hash::make(Str::random(32)),
                        'must_change_password' => true,
                        'password_changed_at' => null,
                        'role' => 'subscriber',
                    ]);
                }

                // Promover user -> subscriber
                if ($user->role === 'user') {
                    $user->update(['role' => 'subscriber']);
                }

                // Auto-link contact_request_id
                $payload['contact_request_id'] ??= ContactRequest::where('email', $payload['email'])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->latest('id')
                    ->value('id');

                // Vincular con user
                $payload['user_id'] = $user->id;

                // Status inicial
                $payload['status'] = ActivationRequest::STATUS_PENDING;

                $activation = ActivationRequest::create($payload);

                AuditService::log('activation_created', $activation, [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'email' => $activation->email,
                    'company' => $activation->company,
                    'status' => $activation->status,
                ]);

                return $activation;
            });
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error creando activation request: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.',
            ], 500);
        }

        /**
         * ✅ Respuesta estable para frontend
         */
        $status = strtolower((string) $activation->status);

        $nextUrl = '';
        $nextLabel = '';

        if ($status === ActivationRequest::STATUS_ACCEPTED) {
            $nextUrl = '/subscriber/activation';
            $nextLabel = 'Ir a iniciar trial';
        }

        if ($status === ActivationRequest::STATUS_TRIALING || $status === ActivationRequest::STATUS_CONVERTED) {
            $nextUrl = '/subscriber';
            $nextLabel = 'Ir al dashboard';
        }

        if ($status === ActivationRequest::STATUS_EXPIRED || $status === ActivationRequest::STATUS_DISCARDED) {
            $nextUrl = '';
            $nextLabel = '';
        }

        /**
         * ✅ Correos fuera de la transacción (solo si está pending)
         */
        try {
            if ($status === ActivationRequest::STATUS_PENDING) {
                $meta = $activation->metadata ?? [];

                $expiresAt = !empty($meta['activation_email_expires_at'])
                    ? Carbon::parse($meta['activation_email_expires_at'])
                    : $activation->created_at->copy()->addHours(24);

                // ✅ Si expiró, marcar expired para no bloquear nuevas solicitudes
                if ($expiresAt->isPast()) {
                    $activation->update(['status' => ActivationRequest::STATUS_EXPIRED]);

                    return response()->json([
                        'success' => true,
                        'status' => ActivationRequest::STATUS_EXPIRED,
                        'message' => 'Tu solicitud anterior expiró. Por favor, vuelve a solicitar activación.',
                        'next_url' => '',
                        'next_label' => '',
                        'activation_id' => $activation->id,
                    ]);
                }

                $activationUrl = URL::temporarySignedRoute(
                    'activations.accept',
                    $expiresAt,
                    ['activation' => $activation->id]
                );

                Mail::to($activation->email)->queue(
                    new ActivationConfirmationMail($activation, $activationUrl)
                );

                Mail::to('contacto@laudaapi.com')->queue(
                    new ActivationInternalNotificationMail($activation)
                );

                $meta['activation_email_sent_at'] = now()->toISOString();
                $meta['activation_email_expires_at'] = $expiresAt->toISOString();
                $meta['activation_email_send_count'] = (int) ($meta['activation_email_send_count'] ?? 0) + 1;
                $meta['last_email_type'] = $wasExisting ? 'activation_confirmation_resend' : 'activation_confirmation_initial';
                $meta['last_email_source'] = 'http:ActivationRequestController@store';

                $activation->update(['metadata' => $meta]);
            }
        } catch (\Throwable $e) {
            Log::warning('ActivationRequest creada/recuperada pero falló el envío/encolado de correos: ' . $e->getMessage(), [
                'activation_request_id' => $activation->id,
                'was_existing' => $wasExisting,
                'mailer' => config('mail.default'),
                'queue_connection' => config('queue.default'),
                'exception' => $e,
            ]);
        }

        // Mensaje coherente por estado
        $message = $wasExisting
            ? 'Ya tenías una solicitud activa. Te indicamos el siguiente paso.'
            : 'Solicitud recibida. Revisa tu correo para confirmar la activación.';

        if ($status === ActivationRequest::STATUS_ACCEPTED) {
            $message = 'Tu correo ya está confirmado. Ahora puedes iniciar tu trial desde el panel.';
        } elseif ($status === ActivationRequest::STATUS_TRIALING) {
            $message = 'Tu trial ya está activo. Puedes entrar al dashboard.';
        } elseif ($status === ActivationRequest::STATUS_CONVERTED) {
            $message = 'Tu cuenta ya está activa. Puedes entrar al dashboard.';
        } elseif ($status === ActivationRequest::STATUS_DISCARDED) {
            $message = 'Esta solicitud fue descartada. Debes crear una nueva.';
        } elseif ($status === ActivationRequest::STATUS_EXPIRED) {
            $message = 'Esta solicitud expiró. Debes crear una nueva.';
        }

        return response()->json([
            'success' => true,
            'status' => $activation->status,
            'message' => $message,
            'next_url' => $nextUrl,
            'next_label' => $nextLabel,
            'activation_id' => $activation->id,
        ]);
    }
}
