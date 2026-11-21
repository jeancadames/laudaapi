<?php

use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$base = getenv('LAUDA_BUSINESS_RUNTIME');
$project = getenv('LAUDA_BUSINESS_PROJECT');
$repos = ['central' => 'laudaapi', 'ecf' => 'ecflaudaapi', 'pos' => 'poslaudaapi', 'bys' => 'byslaudaapi', 'cumplimiento' => 'cumplimientolaudaapi'];
if (! isset($repos[$project]) || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $base ?: '')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$ports = ['central' => 9440, 'ecf' => 9441, 'pos' => 9442, 'bys' => 9443, 'cumplimiento' => 9444];
$peers = [];
foreach (['ecf', 'pos', 'bys', 'cumplimiento'] as $key) {
    $peers[$key] = ['url' => 'https://127.0.0.1:'.$ports[$key], 'secret' => str_repeat($key.'-synthetic-', 8)];
}
$identity = ['enabled' => true, 'issuer' => 'https://127.0.0.1:9440', 'peers' => $peers,
    'service' => $project, 'secret' => $peers[$project]['secret'] ?? ''];

return BusinessIdentityApplication::create(dirname(__DIR__, 4).'/'.$repos[$project], $base.'/'.$project, $identity, 'https://127.0.0.1:'.$ports[$project]);
