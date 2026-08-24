<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Entregables LAUDA 360
    |--------------------------------------------------------------------------
    |
    | Estos valores representan la oferta comercial base.
    | Cada Informe Ampliado guarda su propio snapshot monetario, por lo que
    | cambios futuros en esta configuración no alteran entregables existentes.
    |
    */

    'expanded_report' => [
        'code' => 'lauda360_expanded_report',
        'name' => 'Informe Ampliado LAUDA 360',
        'currency' => 'DOP',
        'subtotal' => 29900.00,
        'tax_rate' => 18.000,
    ],

    'detailed_roadmap' => [
        'code' => 'lauda360_detailed_roadmap',
        'name' => 'Roadmap Detallado LAUDA 360',
        'currency' => 'DOP',
        'subtotal' => 95000.00,
        'tax_rate' => 18.000,

        /*
         * Política comercial acordada:
         * el Informe Ampliado puede acreditarse completamente al Roadmap
         * dentro de la ventana comercial definida.
         */
        'expanded_report_credit' => 29900.00,
        'expanded_report_credit_window_days' => 30,
    ],
];
