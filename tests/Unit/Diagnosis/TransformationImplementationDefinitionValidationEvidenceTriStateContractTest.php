<?php

use App\Services\Diagnosis\TransformationImplementationDefinitionValidationEvidence;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

function standardIntakeTriStateEvidence(
    mixed $assistance,
    string $status = 'pending'
): array {
    return [
        'inputs' => [
            [
                'source_name' =>
                    'Sistema operativo de origen',

                'source_role' =>
                    $status === 'validated'
                        ? 'primary'
                        : null,

                'delivery_format' =>
                    $status === 'validated'
                        ? 'csv'
                        : null,

                'extraction_assistance_required' =>
                    $assistance,

                'data_domains' => [
                    'clientes',
                    'ventas',
                ],

                'owner' =>
                    'Administración / Sistemas',

                'historical_coverage' =>
                    '2022 a la fecha',

                'granularity' =>
                    'transacción',

                'status' =>
                    $status,

                'notes' =>
                    'Evidencia de prueba.',
            ],
        ],

        'accesses' => [],
    ];
}

it(
    'preserves unknown extraction assistance for pending standard intake evidence',
    function () {
        $normalized =
            TransformationImplementationDefinitionValidationEvidence::normalize(
                standardIntakeTriStateEvidence(
                    null,
                    'pending'
                )
            );

        $input =
            $normalized['inputs'][0];

        expect(
            array_key_exists(
                'extraction_assistance_required',
                $input
            )
        )->toBeTrue();

        expect(
            $input[
                'extraction_assistance_required'
            ]
        )->toBeNull();

        expect($input)
            ->not
            ->toHaveKey('source_type');
    }
);

it(
    'preserves explicit false extraction assistance',
    function () {
        $normalized =
            TransformationImplementationDefinitionValidationEvidence::normalize(
                standardIntakeTriStateEvidence(
                    false,
                    'pending'
                )
            );

        expect(
            $normalized['inputs'][0][
                'extraction_assistance_required'
            ]
        )->toBeFalse();
    }
);

it(
    'preserves explicit true extraction assistance while pending',
    function () {
        $normalized =
            TransformationImplementationDefinitionValidationEvidence::normalize(
                standardIntakeTriStateEvidence(
                    true,
                    'pending'
                )
            );

        expect(
            $normalized['inputs'][0][
                'extraction_assistance_required'
            ]
        )->toBeTrue();
    }
);

it(
    'requires explicit extraction assistance decision before validation',
    function () {
        expect(
            fn () =>
                TransformationImplementationDefinitionValidationEvidence::normalize(
                    standardIntakeTriStateEvidence(
                        null,
                        'validated'
                    )
                )
        )->toThrow(
            ValidationException::class
        );
    }
);

it(
    'allows validated standard intake when assistance is explicitly false',
    function () {
        $normalized =
            TransformationImplementationDefinitionValidationEvidence::normalize(
                standardIntakeTriStateEvidence(
                    false,
                    'validated'
                )
            );

        expect(
            $normalized['inputs'][0]['status']
        )->toBe('validated');

        expect(
            $normalized['inputs'][0][
                'extraction_assistance_required'
            ]
        )->toBeFalse();

        expect($normalized['inputs'][0])
            ->not
            ->toHaveKey('source_type');
    }
);

it(
    'blocks validated standard intake while extraction assistance is required',
    function () {
        expect(
            fn () =>
                TransformationImplementationDefinitionValidationEvidence::normalize(
                    standardIntakeTriStateEvidence(
                        true,
                        'validated'
                    )
                )
        )->toThrow(
            ValidationException::class
        );
    }
);
