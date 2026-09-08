<?php

use App\Services\Diagnosis\TransformationImplementationDefinitionValidationEvidence;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('starts with empty version-specific evidence', function () {
    expect(
        TransformationImplementationDefinitionValidationEvidence::empty()
    )->toBe([
        'inputs' => [],
        'accesses' => [],
    ]);
});

it('allows empty evidence while confirmations remain false', function () {
    $evidence =
        TransformationImplementationDefinitionValidationEvidence::normalize(
            null
        );

    TransformationImplementationDefinitionValidationEvidence::assertSupportsConfirmations(
        [
            'inputs_validated' => false,
            'accesses_validated' => false,
        ],
        $evidence
    );

    expect(true)->toBeTrue();
});

it('allows pending evidence while confirmation remains false', function () {
    $evidence =
        TransformationImplementationDefinitionValidationEvidence::normalize([
            'inputs' => [
                [
                    'source_name' => 'ERP',
                    'data_domains' => [
                        'clientes',
                    ],
                    'owner' => null,
                    'historical_coverage' => null,
                    'granularity' => null,
                    'status' => 'pending',
                    'notes' => 'Pendiente.',
                ],
            ],
            'accesses' => [],
        ]);

    TransformationImplementationDefinitionValidationEvidence::assertSupportsConfirmations(
        [
            'inputs_validated' => false,
            'accesses_validated' => false,
        ],
        $evidence
    );

    expect(
        $evidence['inputs']
    )->toHaveCount(1);
});

it('blocks inputs true without evidence', function () {
    expect(
        fn () =>
            TransformationImplementationDefinitionValidationEvidence::assertSupportsConfirmations(
                [
                    'inputs_validated' => true,
                    'accesses_validated' => false,
                ],
                TransformationImplementationDefinitionValidationEvidence::empty()
            )
    )->toThrow(
        ValidationException::class
    );
});

it('blocks accesses true without evidence', function () {
    expect(
        fn () =>
            TransformationImplementationDefinitionValidationEvidence::assertSupportsConfirmations(
                [
                    'inputs_validated' => false,
                    'accesses_validated' => true,
                ],
                TransformationImplementationDefinitionValidationEvidence::empty()
            )
    )->toThrow(
        ValidationException::class
    );
});

it('accepts complete input and access evidence', function () {
    $evidence =
        TransformationImplementationDefinitionValidationEvidence::normalize([
            'inputs' => [
                [
                    'source_name' => 'ERP',
                    'data_domains' => [
                        'clientes',
                        'ventas',
                    ],
                    'owner' => 'Administración',
                    'historical_coverage' => '2022 a la fecha',
                    'granularity' => 'transacción',
                    'status' => 'validated',
                    'notes' => null,
                ],
            ],
            'accesses' => [
                [
                    'source_name' => 'ERP',
                    'access_method' => 'lectura SQL',
                    'authorized' => true,
                    'verified' => true,
                    'status' => 'validated',
                    'notes' => null,
                ],
            ],
        ]);

    TransformationImplementationDefinitionValidationEvidence::assertSupportsConfirmations(
        [
            'inputs_validated' => true,
            'accesses_validated' => true,
        ],
        $evidence
    );

    expect($evidence['inputs'])->toHaveCount(1);
    expect($evidence['accesses'])->toHaveCount(1);
});

it('rejects fields outside the evidence schema', function () {
    expect(
        fn () =>
            TransformationImplementationDefinitionValidationEvidence::normalize([
                'inputs' => [
                    [
                        'source_name' => 'ERP',
                        'data_domains' => [
                            'clientes',
                        ],
                        'status' => 'pending',
                        'api_key' => 'NO-DEBE-PERSISTIRSE',
                    ],
                ],
                'accesses' => [],
            ])
    )->toThrow(
        ValidationException::class
    );
});
