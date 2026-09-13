<?php

use App\Services\Diagnosis\TransformationImplementationDefinitionValidationEvidence;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test(
    'standard intake supports multiple heterogeneous origin sources',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',
                        'source_type' =>
                            'sql_server',
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
                        'owner' => null,
                        'historical_coverage' => null,
                        'granularity' => null,
                        'status' =>
                            'pending',
                        'notes' => null,
                    ],
                    [
                        'source_name' =>
                            'Histórico legado',
                        'source_type' =>
                            'dbf',
                        'source_role' =>
                            'historical',
                        'delivery_format' =>
                            null,
                        'extraction_assistance_required' =>
                            true,
                        'data_domains' => [
                            'ventas históricas',
                        ],
                        'owner' => null,
                        'historical_coverage' => null,
                        'granularity' => null,
                        'status' =>
                            'pending',
                        'notes' =>
                            'Pendiente asistencia de extracción.',
                    ],
                    [
                        'source_name' =>
                            'QuickBooks',
                        'source_type' =>
                            'quickbooks',
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
                        'owner' => null,
                        'historical_coverage' => null,
                        'granularity' => null,
                        'status' =>
                            'pending',
                        'notes' => null,
                    ],
                ],
                'accesses' => [],
            ]);

        expect($evidence['inputs'])
            ->toHaveCount(3);

        expect(
            $evidence['inputs'][0]['source_type']
        )->toBe('sql_server');

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
    'validated standard intake requires source metadata and delivery format',
    function () {
        try {
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',
                        'source_type' =>
                            'sql_server',
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
                        'notes' => null,
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
    'extraction assistance keeps source from being validated',
    function () {
        try {
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'QuickBooks',
                        'source_type' =>
                            'quickbooks',
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
                            'Requiere apoyo para exportación.',
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
    'validated standard intake accepts csv without direct source connection',
    function () {
        $evidence =
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' =>
                            'ERP operativo',
                        'source_type' =>
                            'sql_server',
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
                        'notes' => null,
                    ],
                ],
                'accesses' => [],
            ]);

        expect(
            $evidence['inputs'][0]['status']
        )->toBe('validated');

        expect(
            $evidence['inputs'][0]['delivery_format']
        )->toBe('csv');

        expect(
            $evidence['inputs'][0][
                'extraction_assistance_required'
            ]
        )->toBeFalse();
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
                        'notes' => null,
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
