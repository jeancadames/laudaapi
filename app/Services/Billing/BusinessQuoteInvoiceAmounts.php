<?php

namespace App\Services\Billing;

/** Maps the accepted net amounts and saved taxes, never today's catalogue prices. */
final class BusinessQuoteInvoiceAmounts
{
    // invoices and invoice_items use DECIMAL(10,2).
    private const MAX_CENTS = 9999999999;

    public function fromSnapshot(array $snapshot): array
    {
        abort_unless(($snapshot['schema_version'] ?? null) === 1
            && ($snapshot['commercial_status'] ?? null) === 'quoted'
            && in_array($snapshot['billing_cycle'] ?? null, ['monthly', 'yearly'], true)
            && preg_match('/^[A-Z]{3}$/D', $snapshot['currency'] ?? '')
            && is_numeric($snapshot['tax_rate'] ?? null)
            && $snapshot['tax_rate'] >= 0 && $snapshot['tax_rate'] <= 1, 409, 'Las condiciones aceptadas requieren revisión.');

        $lines = [];
        $subtotal = $taxTotal = $total = 0;
        foreach (['recurring', 'one_time'] as $kind) {
            $net = $snapshot[$kind.'_cents'] ?? null;
            $taxes = $snapshot['taxes'][$kind] ?? [];
            abort_unless(is_int($net) && $net >= 0 && $net <= self::MAX_CENTS, 409, 'El importe supera el límite de facturación.');
            $tax = $this->cents($taxes['tax_amount'] ?? null);
            $lineTotal = $this->cents($taxes['line_total'] ?? null);
            abort_unless($this->cents($taxes['taxable_base'] ?? null) === $net && $lineTotal === $net + $tax,
                409, 'Los impuestos guardados no coinciden con el importe aceptado.');
            $lines[$kind] = ['subtotal_cents' => $net, 'tax_cents' => $tax, 'total_cents' => $lineTotal];
            $subtotal += $net;
            $taxTotal += $tax;
            $total += $lineTotal;
        }
        abort_if($total > self::MAX_CENTS, 409, 'El importe supera el límite de facturación.');

        return ['lines' => $lines, 'subtotal_cents' => $subtotal, 'tax_cents' => $taxTotal, 'total_cents' => $total];
    }

    public function cents(mixed $amount): int
    {
        abort_unless(is_numeric($amount) && is_finite((float) $amount) && $amount >= 0, 409, 'Importe contractual inválido.');
        $cents = round((float) $amount * 100);
        abort_if($cents > self::MAX_CENTS || abs((float) $amount * 100 - $cents) > 0.001, 409, 'Importe contractual fuera de precisión.');

        return (int) $cents;
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
