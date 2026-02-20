<?php

namespace App\Services\Dgii;

use App\Models\DgiiDocumentSequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DgiiDocumentSequenceService
{
    /**
     * Reserva el próximo número de una secuencia DGII.
     * ✅ Concurrencia segura: lockForUpdate() dentro de transacción.
     *
     * Retorna:
     * [
     *   'sequence_id' => int,
     *   'next_number' => int,
     *   'formatted'   => string,
     *   'digits'      => int,
     * ]
     */
    public function reserveNext(
        int $companyId,
        string $environment,   // precert|cert|prod
        string $documentClass, // NCF|ECF
        string $documentType,  // B01|E31|...
        string $series = ''    // '' normalmente
    ): array {
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($companyId, $environment, $documentClass, $documentType, $series, $now) {

            /** @var DgiiDocumentSequence|null $seq */
            $seq = DgiiDocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('environment', $environment)
                ->where('document_class', $documentClass)
                ->where('document_type', $documentType)
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                throw new RuntimeException("No existe secuencia DGII para {$documentClass} {$documentType} [{$environment}] series='{$series}'.");
            }

            if ($seq->status !== 'active') {
                throw new RuntimeException("Secuencia DGII no activa (status={$seq->status}).");
            }

            if ($seq->expires_at && $now->greaterThanOrEqualTo($seq->expires_at)) {
                // cierra para que no se siga usando
                $seq->status = 'closed';
                $seq->save();

                throw new RuntimeException("Secuencia DGII expirada (expires_at={$seq->expires_at}).");
            }

            $next = max(((int)$seq->last_number) + 1, (int)$seq->start_number);

            if (!is_null($seq->end_number) && $next > (int)$seq->end_number) {
                $seq->status = 'exhausted';
                $seq->save();

                throw new RuntimeException("Secuencia DGII agotada (end_number={$seq->end_number}).");
            }

            $digits = $this->resolveDigits($seq);

            // ✅ persistir
            $seq->last_number = $next;
            $seq->lock_version = ((int)$seq->lock_version) + 1;
            $seq->last_issued_at = $now;
            $seq->save();

            // ✅ Formato: document_type + series + secuencial padded
            // - Para DO: NCF => 8 dígitos, e-CF => 10 dígitos (por default)
            $prefix = (string) $seq->document_type;
            if ((string)$seq->series !== '') {
                $prefix .= (string) $seq->series;
            }

            $formatted = $prefix . str_pad((string)$next, $digits, '0', STR_PAD_LEFT);

            return [
                'sequence_id' => (int) $seq->id,
                'next_number' => (int) $next,
                'formatted'   => $formatted,
                'digits'      => (int) $digits,
            ];
        }, 3);
    }

    private function resolveDigits(DgiiDocumentSequence $seq): int
    {
        // ✅ override opcional por meta: { "digits": 8|10|... }
        $meta = is_array($seq->meta) ? $seq->meta : (is_string($seq->meta) ? json_decode($seq->meta, true) : null);
        if (is_array($meta) && isset($meta['digits']) && is_numeric($meta['digits'])) {
            return (int) $meta['digits'];
        }

        return strtoupper((string)$seq->document_class) === 'ECF' ? 10 : 8;
    }
}
