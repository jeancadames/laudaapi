<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeRowValidator;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;

function qa2I15ValidRows(): array
{
    return [
        'customers' => [
            [
                'customer_id' => 'C001',
                'customer_name' => 'Cliente Uno',
                'tax_id' => '101000001',
                'customer_type' => 'empresa',
                'city' => 'Santo Domingo',
                'country' => 'DO',
                'credit_limit' => '1000.50',
                'created_at' => '2026-01-01 10:30:00',
                'active' => 'true',
            ],
        ],

        'products' => [
            [
                'product_id' => 'P001',
                'product_name' => 'Producto Uno',
                'sku' => 'SKU-001',
                'category' => 'General',
                'brand' => 'Marca',
                'unit_of_measure' => 'unidad',
                'unit_cost' => '10.25',
                'unit_price' => '20.50',
                'active' => true,
            ],
        ],

        'inventory' => [
            [
                'snapshot_date' => '2026-09-14',
                'product_id' => 'P001',
                'quantity_on_hand' => '10',
                'branch' => 'Principal',
                'warehouse' => 'Almacén 1',
                'quantity_available' => '8',
                'quantity_committed' => '2',
                'unit_cost' => '10.25',
            ],
        ],

        'sales' => [
            [
                'document_id' => 'V001',
                'line_number' => '1',
                'document_date' => '2026-09-14',
                'customer_id' => 'C001',
                'product_id' => 'P001',
                'quantity' => '2',
                'unit_price' => '20.50',
                'discount_amount' => '0',
                'tax_amount' => '7.38',
                'cost_amount' => '20.50',
                'net_amount' => '48.38',
                'currency' => 'DOP',
            ],
        ],

        'accounts_receivable' => [
            [
                'document_id' => 'AR001',
                'customer_id' => 'C001',
                'issue_date' => '2026-09-01',
                'due_date' => '2026-09-30',
                'original_amount' => '100.00',
                'outstanding_amount' => '50.00',
                'document_type' => 'invoice',
                'currency' => 'DOP',
                'status' => 'open',
            ],
        ],

        'suppliers' => [
            [
                'supplier_id' => 'S001',
                'supplier_name' => 'Suplidor Uno',
                'tax_id' => '101000002',
                'city' => 'Santo Domingo',
                'country' => 'DO',
                'payment_terms_days' => '30',
                'active' => 'false',
            ],
        ],

        'accounts_payable' => [
            [
                'document_id' => 'AP001',
                'supplier_id' => 'S001',
                'issue_date' => '2026-09-01',
                'due_date' => '2026-09-30',
                'original_amount' => '200.00',
                'outstanding_amount' => '125.00',
                'document_type' => 'invoice',
                'currency' => 'DOP',
                'status' => 'open',
            ],
        ],
    ];
}

it(
    'schema v1 exposes machine readable identity and relationship rules',
    function () {
        expect(
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()
        )
            ->toHaveKeys([
                'customers',
                'products',
                'inventory',
                'sales',
                'accounts_receivable',
                'suppliers',
                'accounts_payable',
            ]);

        expect(
            DataTransformationBiStandardIntakeSchema
                ::relationships()
        )
            ->toHaveCount(5)
            ->and(
                DataTransformationBiStandardIntakeSchema
                    ::VERSION
            )
            ->toBe(1);
    }
);

it(
    'accepts a valid canonical row set across all seven domains',
    function () {
        $validator =
            new DataTransformationBiStandardIntakeRowValidator();

        $result =
            $validator->validate(
                qa2I15ValidRows()
            );

        expect($result['valid'])
            ->toBeTrue()
            ->and($result['errors'])
            ->toBe([])
            ->and($result['schema_version'])
            ->toBe(1);

        foreach (
            DataTransformationBiStandardIntakeSchema
                ::domainKeys()
            as $domain
        ) {
            expect(
                $result['domains'][$domain]
                    ['row_count']
            )->toBe(1);
        }
    }
);

it(
    'rejects empty required values',
    function () {
        $rows =
            qa2I15ValidRows();

        $rows['customers'][0]
            ['customer_name'] = '';

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        expect($result['valid'])
            ->toBeFalse()
            ->and(
                implode(
                    ' ',
                    $result['errors']
                )
            )
            ->toContain(
                'customer_name es obligatorio'
            );
    }
);

it(
    'enforces canonical value types and formats',
    function () {
        $rows =
            qa2I15ValidRows();

        $rows['customers'][0]
            ['customer_id'] = 123;

        $rows['customers'][0]
            ['credit_limit'] = '1,000.50';

        $rows['customers'][0]
            ['created_at'] = '14/09/2026 10:30';

        $rows['customers'][0]
            ['active'] = 'yes';

        $rows['inventory'][0]
            ['snapshot_date'] = '14/09/2026';

        $rows['suppliers'][0]
            ['payment_terms_days'] = '30.5';

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        $errors =
            implode(
                "\n",
                $result['errors']
            );

        expect($result['valid'])
            ->toBeFalse()
            ->and($errors)
            ->toContain(
                'customer_id debe ser texto'
            )
            ->toContain(
                'credit_limit debe ser un decimal'
            )
            ->toContain(
                'created_at debe usar el formato YYYY-MM-DD HH:MM:SS'
            )
            ->toContain(
                'active debe usar true o false'
            )
            ->toContain(
                'snapshot_date debe usar el formato YYYY-MM-DD'
            )
            ->toContain(
                'payment_terms_days debe ser un entero'
            );
    }
);

it(
    'rejects duplicate canonical row identities',
    function () {
        $rows =
            qa2I15ValidRows();

        $duplicate =
            $rows['customers'][0];

        $duplicate['customer_name'] =
            'Otro nombre';

        $rows['customers'][] =
            $duplicate;

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        expect($result['valid'])
            ->toBeFalse()
            ->and(
                $result['domains']
                    ['customers']
                    ['duplicate_keys']
            )
            ->toHaveCount(1)
            ->and(
                implode(
                    ' ',
                    $result['errors']
                )
            )
            ->toContain(
                'clave duplicada'
            );
    }
);

it(
    'rejects sales references to unknown customers and products',
    function () {
        $rows =
            qa2I15ValidRows();

        $rows['sales'][0]
            ['customer_id'] = 'C999';

        $rows['sales'][0]
            ['product_id'] = 'P999';

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        $relations =
            implode(
                "\n",
                $result['domains']
                    ['sales']
                    ['relation_errors']
            );

        expect($result['valid'])
            ->toBeFalse()
            ->and($relations)
            ->toContain(
                'customer_id referencia un valor inexistente'
            )
            ->toContain(
                'product_id referencia un valor inexistente'
            );
    }
);

it(
    'enforces product customer and supplier relationships',
    function () {
        $rows =
            qa2I15ValidRows();

        $rows['inventory'][0]
            ['product_id'] = 'P404';

        $rows['accounts_receivable'][0]
            ['customer_id'] = 'C404';

        $rows['accounts_payable'][0]
            ['supplier_id'] = 'S404';

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        expect($result['valid'])
            ->toBeFalse()
            ->and(
                $result['domains']
                    ['inventory']
                    ['relation_errors']
            )
            ->toHaveCount(1)
            ->and(
                $result['domains']
                    ['accounts_receivable']
                    ['relation_errors']
            )
            ->toHaveCount(1)
            ->and(
                $result['domains']
                    ['accounts_payable']
                    ['relation_errors']
            )
            ->toHaveCount(1);
    }
);

it(
    'ignores fully empty rows',
    function () {
        $rows =
            qa2I15ValidRows();

        $rows['customers'][] = [
            'customer_id' => '',
            'customer_name' => '',
        ];

        $result =
            (new DataTransformationBiStandardIntakeRowValidator())
                ->validate($rows);

        expect($result['valid'])
            ->toBeTrue()
            ->and(
                $result['domains']
                    ['customers']
                    ['row_count']
            )
            ->toBe(1);
    }
);
