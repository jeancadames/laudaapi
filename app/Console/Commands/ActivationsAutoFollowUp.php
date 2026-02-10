<?php

namespace App\Console\Commands;

use App\Mail\ActivationDiscardedMail;
use App\Mail\ActivationPendingReminderMail;
use App\Models\ActivationRequest;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ActivationsAutoFollowUp extends Command
{
    protected $signature = 'activations:auto-followup {--dry-run : No envía correos ni actualiza DB, solo simula}';
    protected $description = 'Envía recordatorio automático a las 12h (pending) y descarta a las 24h (pending).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'pending_12h_candidates' => 0,
            'pending_12h_processed' => 0,
            'pending_24h_candidates' => 0,
            'pending_24h_processed' => 0,
        ];

        $this->send12hPendingReminder($dryRun, $stats);
        $this->send24hFinalAndDiscard($dryRun, $stats);

        $this->info('ActivationsAutoFollowUp finished' . ($dryRun ? ' (dry-run)' : '') . '.');
        $this->line('12h candidates: ' . $stats['pending_12h_candidates']);
        $this->line('12h processed:  ' . $stats['pending_12h_processed']);
        $this->line('24h candidates: ' . $stats['pending_24h_candidates']);
        $this->line('24h processed:  ' . $stats['pending_24h_processed']);

        return self::SUCCESS;
    }

    /**
     * ✅ 12h recordatorio SOLO para PENDING:
     * - Reenvía link firmado (sin extender expiración)
     * - Usa activation_email_expires_at guardado en metadata
     *
     * ✅ Mail y Audit FUERA de la transacción (evita inconsistencias)
     */
    private function send12hPendingReminder(bool $dryRun, array &$stats): void
    {
        $now = now();
        $min = $now->copy()->subHours(24);
        $max = $now->copy()->subHours(12);

        $query = ActivationRequest::query()
            ->where('status', ActivationRequest::STATUS_PENDING)
            ->whereBetween('created_at', [$min, $max])
            ->select(['id']);

        $stats['pending_12h_candidates'] = (clone $query)->count();

        $query->orderBy('id')->chunkById(200, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $mailToSend = null;     // ['to' => ..., 'mailable' => ...]
                $auditPayload = null;   // ['event' => ..., 'model' => ..., 'data' => ...]

                try {
                    DB::transaction(function () use ($row, $dryRun, &$stats, &$mailToSend, &$auditPayload) {
                        $activation = ActivationRequest::whereKey($row->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($activation->status !== ActivationRequest::STATUS_PENDING) {
                            return;
                        }

                        $meta = $activation->metadata ?? [];

                        // idempotencia
                        if (!empty($meta['auto_reminder_12h_sent_at'])) {
                            return;
                        }

                        // NO extender expiración: usar expires_at guardado, o fallback created_at+24h
                        $expiresAt = !empty($meta['activation_email_expires_at'])
                            ? Carbon::parse($meta['activation_email_expires_at'])
                            : $activation->created_at->copy()->addHours(24);

                        // si ya expiró, no enviar
                        if ($expiresAt->isPast()) {
                            return;
                        }

                        $activationUrl = URL::temporarySignedRoute(
                            'activations.accept',
                            $expiresAt,
                            ['activation' => $activation->id]
                        );

                        if ($dryRun) {
                            $stats['pending_12h_processed']++;
                            return;
                        }

                        // ✅ actualizar metadata primero (consistencia DB)
                        $meta['auto_reminder_12h_sent_at'] = now()->toISOString();
                        $meta['activation_email_send_count'] = (int) ($meta['activation_email_send_count'] ?? 1) + 1; // 1 = email inicial
                        $meta['last_email_type'] = 'auto_reminder_12h';
                        $meta['last_email_source'] = 'console:activations:auto-followup';

                        $activation->update(['metadata' => $meta]);

                        // ✅ preparar envío fuera de la transacción
                        $mailToSend = [
                            'to' => $activation->email,
                            'mailable' => new ActivationPendingReminderMail($activation->fresh(), $activationUrl),
                        ];

                        $auditPayload = [
                            'event' => 'activation_auto_reminder_12h_sent',
                            'model' => $activation->fresh(),
                            'data' => [
                                'email' => $activation->email,
                                'expires_at' => $expiresAt->toISOString(),
                                'send_count' => $meta['activation_email_send_count'],
                                'source' => 'console:activations:auto-followup',
                            ],
                        ];

                        $stats['pending_12h_processed']++;
                    });

                    // ✅ enviar mail fuera de transacción
                    if (!$dryRun && $mailToSend) {
                        Mail::to($mailToSend['to'])->queue($mailToSend['mailable']);
                    }

                    // ✅ audit fuera de transacción
                    if (!$dryRun && $auditPayload) {
                        try {
                            AuditService::log($auditPayload['event'], $auditPayload['model'], $auditPayload['data']);
                        } catch (\Throwable $e) {
                            Log::warning('Audit log failed (12h reminder): ' . $e->getMessage(), [
                                'activation_id' => $row->id,
                                'exception' => $e,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Auto 12h pending reminder failed: ' . $e->getMessage(), [
                        'activation_id' => $row->id,
                        'exception' => $e,
                    ]);
                }
            }
        });
    }

    /**
     * ✅ 24h: si sigue PENDING => email final + status DISCARDED
     *
     * ✅ Mail y Audit FUERA de la transacción (evita inconsistencias)
     */
    private function send24hFinalAndDiscard(bool $dryRun, array &$stats): void
    {
        $now = now();
        $cut = $now->copy()->subHours(24);

        $query = ActivationRequest::query()
            ->where('status', ActivationRequest::STATUS_PENDING)
            ->where('created_at', '<=', $cut)
            ->select(['id']);

        $stats['pending_24h_candidates'] = (clone $query)->count();

        $query->orderBy('id')->chunkById(200, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $mailToSend = null;
                $auditPayload = null;

                try {
                    DB::transaction(function () use ($row, $dryRun, &$stats, &$mailToSend, &$auditPayload) {
                        $activation = ActivationRequest::whereKey($row->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($activation->status !== ActivationRequest::STATUS_PENDING) {
                            return;
                        }

                        $meta = $activation->metadata ?? [];

                        // idempotencia
                        if (!empty($meta['auto_final_24h_sent_at'])) {
                            return;
                        }

                        if ($dryRun) {
                            $stats['pending_24h_processed']++;
                            return;
                        }

                        // ✅ primero: actualizar DB (status + metadata)
                        $meta['auto_final_24h_sent_at'] = now()->toISOString();
                        $meta['last_email_type'] = 'auto_final_24h_discard';
                        $meta['last_email_source'] = 'console:activations:auto-followup';

                        $activation->update([
                            'status' => ActivationRequest::STATUS_DISCARDED,
                            'metadata' => $meta,
                        ]);

                        // ✅ preparar envío fuera de transacción
                        $mailToSend = [
                            'to' => $activation->email,
                            'mailable' => new ActivationDiscardedMail($activation->fresh()),
                        ];

                        $auditPayload = [
                            'event' => 'activation_auto_discarded_24h',
                            'model' => $activation->fresh(),
                            'data' => [
                                'email' => $activation->email,
                                'source' => 'console:activations:auto-followup',
                            ],
                        ];

                        $stats['pending_24h_processed']++;
                    });

                    if (!$dryRun && $mailToSend) {
                        Mail::to($mailToSend['to'])->queue($mailToSend['mailable']);
                    }

                    if (!$dryRun && $auditPayload) {
                        try {
                            AuditService::log($auditPayload['event'], $auditPayload['model'], $auditPayload['data']);
                        } catch (\Throwable $e) {
                            Log::warning('Audit log failed (24h discard): ' . $e->getMessage(), [
                                'activation_id' => $row->id,
                                'exception' => $e,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Auto 24h discard failed: ' . $e->getMessage(), [
                        'activation_id' => $row->id,
                        'exception' => $e,
                    ]);
                }
            }
        });
    }
}
