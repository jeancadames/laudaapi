<?php

return [
    'version' => '1.0',

    'steps' => 11,

    'dimensions' => [
        'strategy' => [
            'weight' => 15,
            'questions' => ['STR-01', 'STR-02', 'STR-03', 'STR-04', 'STR-05'],
        ],
        'people' => [
            'weight' => 10,
            'questions' => ['PEO-01', 'PEO-02', 'PEO-03', 'PEO-04', 'PEO-05'],
        ],
        'presence' => [
            'weight' => 10,
            'questions' => ['PRE-01', 'PRE-02', 'PRE-03', 'PRE-04', 'PRE-05'],
        ],
        'commercial' => [
            'weight' => 15,
            'questions' => ['COM-01', 'COM-02', 'COM-03', 'COM-04', 'COM-05'],
        ],
        'operations' => [
            'weight' => 20,
            'questions' => ['OPE-01', 'OPE-02', 'OPE-03', 'OPE-04', 'OPE-05'],
        ],
        'technology' => [
            'weight' => 10,
            'questions' => ['TEC-01', 'TEC-02', 'TEC-03', 'TEC-04', 'TEC-05'],
        ],
        'data' => [
            'weight' => 15,
            'questions' => ['DAT-01', 'DAT-02', 'DAT-03', 'DAT-04', 'DAT-05'],
        ],
        'governance' => [
            'weight' => 5,
            'questions' => ['GOV-01', 'GOV-02', 'GOV-03', 'GOV-04', 'GOV-05'],
        ],
    ],

    'capacity_questions' => [
        'CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06',
    ],

    'urgency_questions' => [
        'URG-01', 'URG-02', 'URG-03', 'URG-04', 'URG-05',
    ],

    'maturity_levels' => [
        ['min' => 0, 'max' => 20, 'label' => 'Empresa Tradicional'],
        ['min' => 21, 'max' => 40, 'label' => 'Digitalización Inicial'],
        ['min' => 41, 'max' => 60, 'label' => 'Empresa Digital'],
        ['min' => 61, 'max' => 80, 'label' => 'Empresa Conectada'],
        ['min' => 81, 'max' => 100, 'label' => 'Empresa Inteligente'],
    ],

    'capacity_recommendations' => [
        [
            'min' => 70,
            'max' => 100,
            'modality' => 'guided',
            'label' => 'LAUDA 360 Guiado',
            'note' => 'Autoservicio con metodología, plantillas y soporte principalmente por email.',
        ],
        [
            'min' => 40,
            'max' => 69,
            'modality' => 'assisted',
            'label' => 'LAUDA 360 Asistido',
            'note' => 'Ejecución compartida entre LAUDA y el equipo del cliente.',
        ],
        [
            'min' => 0,
            'max' => 39,
            'modality' => 'managed',
            'label' => 'LAUDA 360 Gestionado',
            'note' => 'LAUDA lidera y coordina la transformación con participación ejecutiva del cliente.',
        ],
    ],

    'urgency_levels' => [
        ['min' => 0, 'max' => 24, 'label' => 'Baja'],
        ['min' => 25, 'max' => 49, 'label' => 'Media'],
        ['min' => 50, 'max' => 74, 'label' => 'Alta'],
        ['min' => 75, 'max' => 100, 'label' => 'Crítica'],
    ],

    // Una respuesta 1 en cualquiera de estas preguntas obliga revisión humana.
    'critical_review_questions' => [
        'GOV-01', 'GOV-02', 'GOV-03', 'GOV-04', 'GOV-05',
    ],
];
