<?php

namespace App\Services\Diagnosis;

use Illuminate\Validation\Rule;

class DiagnosisBusinessProfileService
{
    public const FIELDS = [
        'business_activity_type',
        'business_sector',
        'business_sector_other',
        'customer_market',
        'sales_channels',
        'sales_channel_other',
        'logistics_operation_types',
        'logistics_operation_other',
        'business_activity_description',
    ];

    public function options(): array
    {
        return config('lauda360_business_profile', []);
    }

    public function normalize(array $input): array
    {
        $activityType = $this->cleanString(
            $input['business_activity_type'] ?? null
        );

        $sector = $this->cleanString(
            $input['business_sector'] ?? null
        );

        $market = $this->cleanString(
            $input['customer_market'] ?? null
        );

        $salesChannels = $this->cleanList(
            $input['sales_channels'] ?? []
        );

        $logisticsOperations = $this->cleanList(
            $input['logistics_operation_types'] ?? []
        );

        return [
            'business_activity_type' => $activityType,
            'business_sector' => $sector,
            'business_sector_other' => $sector === 'other'
                ? $this->cleanString(
                    $input['business_sector_other'] ?? null
                )
                : null,
            'customer_market' => $market,
            'sales_channels' => $salesChannels,
            'sales_channel_other' => in_array(
                'other',
                $salesChannels,
                true
            )
                ? $this->cleanString(
                    $input['sales_channel_other'] ?? null
                )
                : null,
            'logistics_operation_types' => $sector === 'logistics'
                ? $logisticsOperations
                : [],
            'logistics_operation_other' => (
                $sector === 'logistics'
                && in_array('other', $logisticsOperations, true)
            )
                ? $this->cleanString(
                    $input['logistics_operation_other'] ?? null
                )
                : null,
            'business_activity_description' => $this->cleanString(
                $input['business_activity_description'] ?? null
            ),
        ];
    }

    public function rules(array $input): array
    {
        $sector = $input['business_sector'] ?? null;

        $salesChannels = is_array(
            $input['sales_channels'] ?? null
        )
            ? $input['sales_channels']
            : [];

        $logisticsOperations = is_array(
            $input['logistics_operation_types'] ?? null
        )
            ? $input['logistics_operation_types']
            : [];

        return [
            'business_activity_type' => [
                'required',
                'string',
                Rule::in(array_keys(
                    config(
                        'lauda360_business_profile.activity_types',
                        []
                    )
                )),
            ],
            'business_sector' => [
                'required',
                'string',
                Rule::in(array_keys(
                    config(
                        'lauda360_business_profile.sectors',
                        []
                    )
                )),
            ],
            'business_sector_other' => [
                Rule::requiredIf($sector === 'other'),
                'nullable',
                'string',
                'max:120',
            ],
            'customer_market' => [
                'required',
                'string',
                Rule::in(array_keys(
                    config(
                        'lauda360_business_profile.customer_markets',
                        []
                    )
                )),
            ],
            'sales_channels' => [
                'required',
                'array',
                'min:1',
                'max:8',
            ],
            'sales_channels.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_keys(
                    config(
                        'lauda360_business_profile.sales_channels',
                        []
                    )
                )),
            ],
            'sales_channel_other' => [
                Rule::requiredIf(
                    in_array('other', $salesChannels, true)
                ),
                'nullable',
                'string',
                'max:120',
            ],
            'logistics_operation_types' => [
                Rule::requiredIf($sector === 'logistics'),
                'array',
                'max:11',
            ],
            'logistics_operation_types.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_keys(
                    config(
                        'lauda360_business_profile.logistics_operation_types',
                        []
                    )
                )),
            ],
            'logistics_operation_other' => [
                Rule::requiredIf(
                    $sector === 'logistics'
                    && in_array(
                        'other',
                        $logisticsOperations,
                        true
                    )
                ),
                'nullable',
                'string',
                'max:120',
            ],
            'business_activity_description' => [
                'required',
                'string',
                'min:20',
                'max:2000',
            ],
        ];
    }

    public function extract(array $validated): array
    {
        $profile = [];

        foreach (self::FIELDS as $field) {
            $profile[$field] = $validated[$field] ?? null;
        }

        $profile['sales_channels'] = array_values(
            $profile['sales_channels'] ?? []
        );

        $profile['logistics_operation_types'] = array_values(
            $profile['logistics_operation_types'] ?? []
        );

        return $profile;
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function cleanList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                fn ($item) => $this->cleanString($item),
                $value
            ),
            fn ($item) => $item !== null
        )));
    }
}
