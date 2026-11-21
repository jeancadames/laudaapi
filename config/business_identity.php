<?php

return [
    'enabled' => (bool) env('LAUDA_BUSINESS_IDENTITY_ENABLED', false),
    'issuer' => env('LAUDA_BUSINESS_ISSUER', config('app.url')),
    // Independent keys per receiver and environment; never fall back to APP_KEY or v1 SSO.
    'peers' => [
        'ecf' => ['url' => env('LAUDA_BUSINESS_ECF_URL'), 'secret' => env('LAUDA_BUSINESS_ECF_SECRET')],
        'pos' => ['url' => env('LAUDA_BUSINESS_POS_URL'), 'secret' => env('LAUDA_BUSINESS_POS_SECRET')],
        'bys' => ['url' => env('LAUDA_BUSINESS_BYS_URL'), 'secret' => env('LAUDA_BUSINESS_BYS_SECRET')],
        'cumplimiento' => ['url' => env('LAUDA_BUSINESS_CUMPLIMIENTO_URL'), 'secret' => env('LAUDA_BUSINESS_CUMPLIMIENTO_SECRET')],
    ],
];
