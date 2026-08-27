<?php

return [
    'groups' => [
        'identity_relationship' => [
            'title' => 'Identidad y Relación Digital',
            'description' =>
                'Presencia, conversación y relación comercial conectadas.',
            'sort_order' => 10,
            'solutions' => [
                'digital_presence' => [
                    'title' => 'Presencia Digital',
                    'description' =>
                        'Presencia digital operativa, consistente y medible.',
                    'service_key' => 'digital_presence',
                    'integration' => 'managed',
                    'launchable' => false,
                    'target_url' => null,
                    'first_wave' => false,
                    'sort_order' => 10,
                ],
                'social' => [
                    'title' => 'Social',
                    'description' =>
                        'Contenido, interacciones, inbox, leads y analítica social.',
                    'service_key' => 'social',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://social.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => true,
                    'sort_order' => 20,
                ],
                'crm' => [
                    'title' => 'CRM',
                    'description' =>
                        'Clientes, leads, oportunidades y seguimiento comercial.',
                    'service_key' => 'crm',
                    'legacy_service_key' => 'erp_crm',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://crm.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => true,
                    'sort_order' => 30,
                ],
            ],
        ],

        'sales_operations' => [
            'title' => 'Ventas y Operación',
            'description' =>
                'Ejecución comercial y operación transaccional.',
            'sort_order' => 20,
            'solutions' => [
                'pos' => [
                    'title' => 'POS',
                    'description' =>
                        'Ventas, inventario, crédito, despacho y operación.',
                    'service_key' => 'pos',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://pos.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => false,
                    'sort_order' => 10,
                ],
            ],
        ],

        'procurement_supply' => [
            'title' => 'Compras y Abastecimiento',
            'description' =>
                'Compras, suplidores y abastecimiento conectado.',
            'sort_order' => 30,
            'solutions' => [
                'bys' => [
                    'title' => 'BYS',
                    'description' =>
                        'Compras, suplidores e integración de abastecimiento.',
                    'service_key' => 'bys',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://bys.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => false,
                    'sort_order' => 10,
                ],
            ],
        ],

        'fiscal_compliance' => [
            'title' => 'Fiscal y Cumplimiento',
            'description' =>
                'Facturación electrónica y control de obligaciones fiscales.',
            'sort_order' => 40,
            'solutions' => [
                'ecf' => [
                    'title' => 'e-CF',
                    'description' =>
                        'Facturación electrónica y servicios fiscales DGII.',
                    'service_key' => 'api_facturacion_electronica',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://ecf.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => false,
                    'sort_order' => 10,
                ],
                'cumplimiento' => [
                    'title' => 'Cumplimiento',
                    'description' =>
                        'Obligaciones, calendario y seguimiento fiscal.',
                    'service_key' => 'cumplimiento_fiscal',
                    'integration' => 'external',
                    'launchable' => true,
                    'target_url' => 'https://cumplimiento.laudaapi.com',
                    'target_launch_path' => '/launch',
                    'first_wave' => false,
                    'sort_order' => 20,
                ],
            ],
        ],
    ],
];
