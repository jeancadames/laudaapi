<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertFiscalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'document_type_code' => ['sometimes', 'string', 'max:32'], // requerido en create; opcional en update
            'buyer_party_id' => ['nullable', 'integer'],
            'external_ref' => ['nullable', 'string', 'max:80'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],

            'payload' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],

            'lines' => ['nullable', 'array'],
            'lines.*.sku' => ['nullable', 'string', 'max:60'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['nullable', 'numeric'],
            'lines.*.discount' => ['nullable', 'numeric'],
            'lines.*.tax_rate' => ['nullable', 'numeric'],
            'lines.*.taxes' => ['nullable', 'array'],
            'lines.*.taxes.*.type' => ['nullable', 'string', 'max:30'],
            'lines.*.taxes.*.rate' => ['nullable', 'numeric'],
            'lines.*.taxes.*.amount' => ['nullable', 'numeric'],
            'lines.*.meta' => ['nullable', 'array'],
        ];
    }
}
