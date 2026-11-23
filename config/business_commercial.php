<?php

return [
    'enabled' => (bool) env('LAUDA_BUSINESS_COMMERCIAL_ENABLED', false),
    'transfer_instructions' => env('LAUDA_BUSINESS_TRANSFER_INSTRUCTIONS'),
    'checkout_enabled' => (bool) env('LAUDA_BUSINESS_CHECKOUT_ENABLED', false),
    'activation_enabled' => (bool) env('LAUDA_BUSINESS_ACTIVATION_ENABLED', false),
    'renewal_enabled' => (bool) env('LAUDA_BUSINESS_RENEWAL_ENABLED', false),
    'transition_enabled' => (bool) env('LAUDA_BUSINESS_TRANSITION_ENABLED', false),
    'implementation_enabled' => (bool) env('LAUDA_BUSINESS_IMPLEMENTATION_ENABLED', false),
];
