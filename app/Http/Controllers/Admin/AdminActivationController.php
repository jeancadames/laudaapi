<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ActivationReminderMail;
use App\Models\ActivationRequest;
use App\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminActivationController extends Controller
{
    public function discard(ActivationRequest $activation)
    {
        try {
            DB::transaction(function () use ($activation) {
                $activation = ActivationRequest::whereKey($activation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // No tiene sentido descartar si ya está trialing/converted
                if (in_array($activation->status, [ActivationRequest::STATUS_TRIALING, 'converted'], true)) {
                    abort(422, 'No puedes descartar una activación ya iniciada/convertida.');
                }

                $activation->update([
                    'status' => 'discarded',
                ]);

                AuditService::log('activation_discarded', $activation, [
                    'email' => $activation->email,
                    'status' => $activation->status,
                ]);
            });

            return back()->with('success', 'Solicitud descartada.');
        } catch (\Throwable $e) {
            Log::error('Error descartando activation request: ' . $e->getMessage(), [
                'activation_id' => $activation->id,
                'exception' => $e,
            ]);

            return back()->with('error', 'No se pudo descartar la solicitud.');
        }
    }

    public function remind(ActivationRequest $activation)
    {
        try {
            DB::transaction(function () use ($activation) {
                $activation = ActivationRequest::whereKey($activation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // ✅ Solo permitido cuando está accepted
                if ($activation->status !== ActivationRequest::STATUS_ACCEPTED) {
                    abort(422, 'El recordatorio solo está disponible cuando la solicitud está aceptada.');
                }

                // Anti-spam simple: 1 recordatorio cada 12h (usando metadata)
                $meta = $activation->metadata ?? [];
                $last = $meta['reminder_sent_at'] ?? null;

                if ($last) {
                    $lastAt = Carbon::parse($last);
                    if ($lastAt->diffInHours(now()) < 12) {
                        abort(422, 'Ya se envió un recordatorio recientemente.');
                    }
                }

                // ✅ Email de recordatorio (sin link firmado)
                Mail::to($activation->email)->queue(
                    new ActivationReminderMail($activation)
                );

                // Guardar rastro en metadata
                $meta['reminder_sent_at'] = now()->toISOString();
                $meta['reminder_count'] = ($meta['reminder_count'] ?? 0) + 1;

                $activation->update([
                    'metadata' => $meta,
                ]);

                AuditService::log('activation_reminder_sent', $activation, [
                    'email' => $activation->email,
                    'reminder_count' => $meta['reminder_count'],
                ]);
            });

            return back()->with('success', 'Recordatorio enviado.');
        } catch (\Throwable $e) {
            Log::error('Error enviando recordatorio: ' . $e->getMessage(), [
                'activation_id' => $activation->id,
                'exception' => $e,
            ]);

            return back()->with('error', 'No se pudo enviar el recordatorio.');
        }
    }
}
