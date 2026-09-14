<?php

use App\Services\Diagnosis\TransformationImplementationDefinitionValidationEvidence;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test(
    'validated standard intake is file based and does not require technical source type',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',

                        'source_role' =>
                            'primary',

                        'delivery_format' =>
                            'csv',

                        'extraction_assistance_required' =>
                            false,

                        'data_domains' => [
                            'clientes',
                            'productos',
                            'ventas',
                        ],

                        'owner' =>
                            'Administración',

                        'historical_coverage' =>
                            '2022 a la fecha',

                        'granularity' =>
                            'transacción / línea',

                        'status' =>
                            'validated',

                        'notes' =>
                            null,
                    ],
                ],

                'accesses' => [],
            ]);

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('source_type');

        expect(
            $evidence['inputs'][0]['delivery_format']
        )->toBe('csv');

        expect(
            $evidence['inputs'][0]['status']
        )->toBe('validated');
    }
);

test(
    'standard intake supports multiple csv and xlsx deliveries',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',

                        'source_role' =>
                            'primary',

                        'delivery_format' =>
                            'csv',

                        'extraction_assistance_required' =>
                            false,

                        'data_domains' => [
                            'clientes',
                            'productos',
                            'ventas',
                        ],

                        'owner' =>
                            null,

                        'historical_coverage' =>
                            null,

                        'granularity' =>
                            null,

                        'status' =>
                            'pending',

                        'notes' =>
                            null,
                    ],

                    [
                        'source_name' =>
                            'Histórico legado',

                        'source_role' =>
                            'historical',

                        'delivery_format' =>
                            null,

                        'extraction_assistance_required' =>
                            true,

                        'data_domains' => [
                            'ventas históricas',
                        ],

                        'owner' =>
                            null,

                        'historical_coverage' =>
                            null,

                        'granularity' =>
                            null,

                        'status' =>
                            'pending',

                        'notes' =>
                            'Pendiente asistencia de extracción.',
                    ],

                    [
                        'source_name' =>
                            'Sistema contable',

                        'source_role' =>
                            'complementary',

                        'delivery_format' =>
                            'xlsx',

                        'extraction_assistance_required' =>
                            false,

                        'data_domains' => [
                            'cuentas por pagar',
                            'contabilidad',
                        ],

                        'owner' =>
                            null,

                        'historical_coverage' =>
                            null,

                        'granularity' =>
                            null,

                        'status' =>
                            'pending',

                        'notes' =>
                            null,
                    ],
                ],

                'accesses' => [],
            ]);

        expect($evidence['inputs'])
            ->toHaveCount(3);

        foreach ($evidence['inputs'] as $input) {
            expect($input)
                ->not
                ->toHaveKey('source_type');
        }

        expect(
            $evidence['inputs'][1]['source_role']
        )->toBe('historical');

        expect(
            $evidence['inputs'][1][
                'extraction_assistance_required'
            ]
        )->toBeTrue();

        expect(
            $evidence['inputs'][2]['delivery_format']
        )->toBe('xlsx');
    }
);

test(
    'validated standard intake requires csv or xlsx delivery format',
    function () {
        try {
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',

                        'source_role' =>
                            'primary',

                        'delivery_format' =>
                            null,

                        'extraction_assistance_required' =>
                            false,

                        'data_domains' => [
                            'clientes',
                            'ventas',
                        ],

                        'owner' =>
                            'Administración',

                        'historical_coverage' =>
                            '2022 a la fecha',

                        'granularity' =>
                            'transacción / línea',

                        'status' =>
                            'validated',

                        'notes' =>
                            null,
                    ],
                ],

                'accesses' => [],
            ]);

            $this->fail(
                'Expected delivery format validation.'
            );
        } catch (ValidationException $exception) {
            expect($exception->errors())
                ->toHaveKey(
                    'readiness.validation_evidence.inputs.0.delivery_format'
                );
        }
    }
);

test(
    'standard intake rejects non standard delivery formats',
    function () {
        expect(
            fn () =>
                TransformationImplementationDefinitionValidationEvidence::normalize([
                    'inputs' => [
                        [
                            'source_name' =>
                                'ERP operativo',

                            'source_role' =>
                                'primary',

                            'delivery_format' =>
                                'json',

                            'extraction_assistance_required' =>
                                false,

                            'data_domains' => [
                                'clientes',
                            ],

                            'owner' =>
                                null,

                            'historical_coverage' =>
                                null,

                            'granularity' =>
                                null,

                            'status' =>
                                'pending',

                            'notes' =>
                                null,
                        ],
                    ],

                    'accesses' => [],
                ])
        )->toThrow(
            ValidationException::class
        );
    }
);

test(
    'extraction assistance keeps standard delivery from being validated',
    function () {
        try {
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'Sistema contable',

                        'source_role' =>
                            'complementary',

                        'delivery_format' =>
                            'xlsx',

                        'extraction_assistance_required' =>
                            true,

                        'data_domains' => [
                            'cuentas por pagar',
                        ],

                        'owner' =>
                            'Contabilidad',

                        'historical_coverage' =>
                            '2024 a la fecha',

                        'granularity' =>
                            'documento',

                        'status' =>
                            'validated',

                        'notes' =>
                            'Requiere apoyo para preparar la exportación.',
                    ],
                ],

                'accesses' => [],
            ]);

            $this->fail(
                'Expected extraction assistance validation.'
            );
        } catch (ValidationException $exception) {
            expect($exception->errors())
                ->toHaveKey(
                    'readiness.validation_evidence.inputs.0.extraction_assistance_required'
                );
        }
    }
);

test(
    'legacy technical source type remains readable and preserved',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'SQL Server - Base operativa',

                        'source_type' =>
                            'sql_server',

                        'data_domains' => [
                            'clientes',
                            'ventas',
                        ],

                        'owner' =>
                            null,

                        'historical_coverage' =>
                            null,

                        'granularity' =>
                            null,

                        'status' =>
                            'pending',

                        'notes' =>
                            'Evidencia histórica.',
                    ],
                ],

                'accesses' => [],
            ]);

        expect(
            $evidence['inputs'][0]['source_type']
        )->toBe('sql_server');

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('delivery_format');

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('source_role');
    }
);

test(
    'legacy evidence remains readable without migration',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'Fuente legado',

                        'data_domains' => [
                            'clientes',
                        ],

                        'owner' =>
                            'Sistemas',

                        'historical_coverage' =>
                            '2020 a la fecha',

                        'granularity' =>
                            'registro',

                        'status' =>
                            'validated',

                        'notes' =>
                            null,
                    ],
                ],

                'accesses' => [],
            ]);

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('source_type');

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('source_role');

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey('delivery_format');

        expect($evidence['inputs'][0])
            ->not
            ->toHaveKey(
                'extraction_assistance_required'
            );
    }
);
