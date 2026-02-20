<?php

namespace App\Services\Fiscal;

class FiscalDocumentTotals
{
    /**
     * Normaliza y calcula una línea:
     * - taxable_base
     * - tax_amount (a partir de taxes[] o tax_rate)
     * - line_total
     */
    public function computeLine(array $line, float $defaultTaxRate = 0.18): array
    {
        $qty = (float) ($line['quantity'] ?? 1);
        $unit = (float) ($line['unit_price'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);

        $gross = $qty * $unit;
        $taxable = max($gross - $discount, 0);

        $taxes = $line['taxes'] ?? null;

        // Permite formato flexible:
        // taxes: [{type, rate, amount}]
        // o tax_rate: 0.18 / 18
        $taxTotal = 0.0;

        if (is_array($taxes)) {
            $out = [];
            foreach ($taxes as $t) {
                $rate = isset($t['rate']) ? (float) $t['rate'] : null;
                if ($rate !== null && $rate > 1) $rate = $rate / 100;

                $amount = isset($t['amount']) ? (float) $t['amount'] : null;
                if ($amount === null && $rate !== null) {
                    $amount = $taxable * $rate;
                }
                $amount ??= 0.0;

                $taxTotal += $amount;

                $out[] = [
                    'type' => (string) ($t['type'] ?? 'tax'),
                    'rate' => $rate,
                    'amount' => $this->round2($amount),
                ];
            }
            $taxes = $out;
        } else {
            $rate = $line['tax_rate'] ?? $defaultTaxRate;
            $rate = (float) $rate;
            if ($rate > 1) $rate = $rate / 100;

            $amount = $taxable * $rate;
            $taxTotal = $amount;

            $taxes = [[
                'type' => 'itbis',
                'rate' => $rate,
                'amount' => $this->round2($amount),
            ]];
        }

        $lineTotal = $taxable + $taxTotal;

        return [
            'quantity' => $qty,
            'unit_price' => $unit,
            'discount' => $this->round2($discount),
            'taxable_base' => $this->round2($taxable),
            'tax_amount' => $this->round2($taxTotal),
            'line_total' => $this->round2($lineTotal),
            'taxes' => $taxes,
        ];
    }

    public function computeDocumentTotals(array $lines): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $grand = 0.0;

        foreach ($lines as $l) {
            $subtotal += (float) ($l['taxable_base'] ?? 0);
            $discountTotal += (float) ($l['discount'] ?? 0);
            $taxTotal += (float) ($l['tax_amount'] ?? 0);
            $grand += (float) ($l['line_total'] ?? 0);
        }

        return [
            'subtotal' => $this->round2($subtotal),
            'discount_total' => $this->round2($discountTotal),
            'tax_total' => $this->round2($taxTotal),
            'grand_total' => $this->round2($grand),
            'balance_due' => $this->round2($grand),
        ];
    }

    private function round2(float $v): float
    {
        return round($v, 2);
    }
}
