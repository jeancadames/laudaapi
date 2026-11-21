<?php

use Illuminate\Http\Request;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$runtime = getenv('LAUDA_BUSINESS_RUNTIME');
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime ?: '') || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$app = BusinessIdentityApplication::create(dirname(__DIR__, 3), $runtime.'/central', $fixture['identity'], $fixture['identity']['issuer']);
config()->set('business_commercial_bridge.enabled', true);
config()->set('business_commercial_bridge.capacities_enabled', $fixture['capacities'] ?? false);
config()->set('business_commercial.activation_enabled', true);
$_SERVER['HTTPS'] = 'on';
$app->handleRequest(Request::capture());
