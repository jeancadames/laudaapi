<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invitación privada al Diagnóstico LAUDA 360
    |--------------------------------------------------------------------------
    |
    | Este vencimiento aplica únicamente al enlace inicial o reenviado.
    | Una vez aceptada la invitación, el acceso posterior se realiza mediante
    | autenticación normal y no queda limitado por estas horas.
    |
    */
    'invitation_ttl_hours' => (int) env(
        'LAUDA360_DIAGNOSIS_INVITATION_TTL_HOURS',
        72
    ),
];
