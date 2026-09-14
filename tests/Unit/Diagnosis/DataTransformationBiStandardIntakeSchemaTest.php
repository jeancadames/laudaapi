<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;

test(
    'standard intake schema v1 exposes the seven canonical base domains',
    function () {
        expect(
            DataTransformationBiStandardIntakeSchema::VERSION
        )->toBe(1);

        expect(
            DataTransformationBiStandardIntakeSchema::domainKeys()
        )->toBe([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ]);
    }
);

test(
    'canonical domains have unique explicit field contracts',
    function () {
        foreach (
            DataTransformationBiStandardIntakeSchema::domains()
            as $domainKey => $domain
        ) {
            expect($domain['fields'])
                ->not
                ->toBeEmpty();

            $names =
                array_column(
                    $domain['fields'],
                    'name'
                );

            expect(
                array_values(
                    array_unique($names)
                )
            )->toBe(
                array_values($names)
            );

            foreach ($domain['fields'] as $field) {
                expect($field)
                    ->toHaveKeys([
                        'name',
                        'required',
                        'type',
                        'description',
                    ]);

                expect($field['name'])
                    ->toBeString()
                    ->not
                    ->toBeEmpty();

                expect($field['required'])
                    ->toBeBool();

                expect($field['type'])
                    ->toBeString()
                    ->not
                    ->toBeEmpty();

                expect($field['description'])
                    ->toBeString()
                    ->not
                    ->toBeEmpty();
            }
        }
    }
);

test(
    'base relationships are represented through canonical identifiers',
    function () {
        $domains =
            DataTransformationBiStandardIntakeSchema::domains();

        $fieldNames =
            static fn (string $domain): array =>
                array_column(
                    $domains[$domain]['fields'],
                    'name'
                );

        expect(
            $fieldNames('inventory')
        )->toContain(
            'product_id'
        );

        expect(
            $fieldNames('sales')
        )
            ->toContain(
                'customer_id'
            )
            ->toContain(
                'product_id'
            );

        expect(
            $fieldNames('accounts_receivable')
        )->toContain(
            'customer_id'
        );

        expect(
            $fieldNames('accounts_payable')
        )->toContain(
            'supplier_id'
        );
    }
);

test(
    'future intelligence domains are deliberately outside schema v1',
    function () {
        $domains =
            DataTransformationBiStandardIntakeSchema::domainKeys();

        expect($domains)
            ->not
            ->toContain(
                'customer_segments'
            )
            ->not
            ->toContain(
                'product_raw_materials'
            )
            ->not
            ->toContain(
                'supplier_intelligence_inputs'
            );
    }
);

test(
    'format rules define the standard csv and data representation contract',
    function () {
        $rules =
            DataTransformationBiStandardIntakeSchema::formatRules();

        expect(
            $rules['dates']['value']
        )->toBe(
            'YYYY-MM-DD'
        );

        expect(
            $rules['decimal_separator']['value']
        )->toBe(
            '.'
        );

        expect(
            $rules['booleans']['value']
        )->toBe(
            'true|false'
        );

        expect(
            $rules['csv_encoding']['value']
        )->toBe(
            'UTF-8'
        );

        expect(
            $rules['csv_delimiter']['value']
        )->toBe(
            ','
        );
    }
);
