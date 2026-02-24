<?php

return [
    'node_bin' => env('DGII_NODE_BIN', '/usr/local/bin/node'),
    'node_sign_timeout' => (int) env('DGII_NODE_SIGN_TIMEOUT', 25),
];