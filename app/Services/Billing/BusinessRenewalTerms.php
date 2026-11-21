<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Services\Fiscal\FiscalDocumentTotals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** A new, explicit agreement for ONE period; no inferred promotion or debit mandate. */
final class BusinessRenewalTerms
{
    public const POLICIES = [
        'renewal_mode' => 'explicit_single_period',
        'discount_duration' => 'this_period_only',
        'price_changes' => 'new_acceptance_required',
        'tax_changes' => 'new_acceptance_required',
        'unpaid_coverage' => 'no_coverage',
    ];

    public static function hash(array $value): string
    {
        $canonical = function (array $value) use (&$canonical): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $canonical($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function boundary(BusinessContract $contract, int $offset): CarbonImmutable
    {
        abort_unless($offset >= 0 && $offset <= 1200, 409, 'Período fuera del calendario permitido.');
        $anchor = $contract->activated_at->setTimezone($contract->timezone);

        return ($contract->snapshot['billing_cycle'] === 'yearly'
            ? $anchor->addYearsNoOverflow($offset) : $anchor->addMonthsNoOverflow($offset))->utc();
    }

    public function make(BusinessContract $contract, int $number, array $input): array
    {
        if (is_string($input['commercial_note'] ?? null)) {
            $input['commercial_note'] = trim($input['commercial_note']);
        }
        $rules = ['base_cents' => 'required|integer|min:0|max:9999999999', 'discount_cents' => 'required|integer|min:0|lte:base_cents',
            'tax_bps' => 'required|integer|min:0|max:10000|multiple_of:10', 'commercial_note' => 'required|string|min:20|max:4000'];
        foreach (self::POLICIES as $field => $policy) {
            $rules[$field] = ['required', Rule::in([$policy])];
        }
        $data = Validator::make($input, $rules)->validate();
        $base = (int) $data['base_cents'];
        $discount = (int) $data['discount_cents'];
        $taxBps = (int) $data['tax_bps'];
        $amounts = app(BusinessQuoteInvoiceAmounts::class);
        $line = app(FiscalDocumentTotals::class)->computeLine([
            'quantity' => 1, 'unit_price' => ($base - $discount) / 100, 'tax_rate' => $taxBps / 10000,
        ], $taxBps / 10000);

        return ['schema_version' => 1, 'contract_id' => $contract->id, 'contract_hash' => $contract->snapshot_hash,
            'company_id' => $contract->company_id, 'subscriber_id' => $contract->subscriber_id,
            'currency' => $contract->snapshot['currency'], 'timezone' => $contract->timezone,
            'billing_cycle' => $contract->snapshot['billing_cycle'], 'period_number' => $number,
            'period_starts_at' => self::boundary($contract, $number - 1)->toIso8601String(),
            'period_ends_at' => self::boundary($contract, $number)->toIso8601String(),
            'calendar' => 'activation_anchor_no_overflow', 'base_cents' => $base, 'discount_cents' => $discount,
            'subtotal_cents' => $base - $discount, 'tax_bps' => $taxBps, 'tax_cents' => $amounts->cents($line['tax_amount']),
            'total_cents' => $amounts->cents($line['line_total']), 'one_time_cents' => 0,
            ...self::POLICIES, 'commercial_note' => trim($data['commercial_note']),
            'automatic_debit' => false, 'consent' => 'business_renewal_single_period_v1'];
    }
}
