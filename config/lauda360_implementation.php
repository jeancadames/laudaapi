<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transformación Digital 360 · Matriz comercial de implementación
    |--------------------------------------------------------------------------
    |
    | Esta configuración NO corresponde a las tarifas recurrentes de las
    | soluciones LAUDAAPI.
    |
    | Calcula únicamente el trabajo de implementación del Plan de
    | Transformación 360.
    |
    | Los valores permanecen NULL hasta que exista una decisión comercial.
    | El motor debe considerarse NOT READY mientras falte cualquier valor
    | requerido por un Plan.
    |
    */

    'version' => 'commercial_matrix_v1',

    'currency' => 'DOP',

    'duration_unit' => 'days',

    'modalities' => [
        'guided' => [
            'initiative_effort' => [
                'low' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'medium' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'high' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],

            'professional_capabilities' => [
                'procedures_guide' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'branding_identity' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],
        ],

        'assisted' => [
            'initiative_effort' => [
                'low' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'medium' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'high' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],

            'professional_capabilities' => [
                'procedures_guide' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'branding_identity' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],
        ],

        'managed' => [
            'initiative_effort' => [
                'low' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'medium' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'high' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],

            'professional_capabilities' => [
                'procedures_guide' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],

                'branding_identity' => [
                    'price_amount' => null,
                    'duration_days' => null,
                ],
            ],
        ],
    ],
];
