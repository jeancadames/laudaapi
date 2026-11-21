<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
$runtime = $argv[1] ?? getenv('LAUDA_BUSINESS_RUNTIME');
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime ?: '') || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => 'ecf', 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers']['ecf']['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/ecflaudaapi', $runtime.'/ecf', $identity, $identity['issuer']);
config()->set(['business_commercial_emission.bys_handoff_enabled' => true, 'business_commercial_bridge.enabled' => true, 'business_commercial_emission.pos_handoff_enabled' => true]);
if (is_file($runtime.'/direct-fiscal-enabled')) {
    config()->set(['business_commercial_emission.direct_enabled' => true, 'business_commercial_outbox.enabled' => true]);
}
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
Queue::fake();
Mail::fake();

if (is_file($runtime.'/facil-browser-enabled')) {
    config()->set('business_commercial_emission.facil_enabled', true);
}
