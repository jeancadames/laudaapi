<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LAUDA Ecosystem SSO
    |--------------------------------------------------------------------------
    |
    | Secret exclusivo para launch entre app.laudaapi.com y las soluciones
    | independientes. Nunca utilizar APP_KEY como secreto compartido.
    |
    */
    'secret' => env('LAUDA_SSO_SECRET'),

    'issuer' => env(
        'LAUDA_SSO_ISSUER',
        config('app.url')
    ),

    'ttl_minutes' => (int) env(
        'LAUDA_SSO_TTL_MINUTES',
        5
    ),
];
