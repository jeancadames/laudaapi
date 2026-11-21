<?php

use App\Models\EcfDraft;
use App\Models\User;
use App\Support\Facil\InvoiceInput;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

define('BUSINESS_FISCAL_FIXTURE_CLI', true);
require __DIR__.'/ecf-router.php';
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set('business_commercial_emission.facil_enabled', $input['enabled'] ?? true);
if (($input['action'] ?? '') === 'setup') {
    $payload = InvoiceInput::normalize($input['payload'] ?? ['tipo_ecf' => 'e31', 'fecha_emision' => now()->format('Y-m-d'),
        'tipo_pago' => '1', 'tipo_ingresos' => '01', 'comprador_rnc' => '131880681', 'comprador_nombre' => 'Comprador sintético',
        'items' => [['nombre' => 'Servicio sintético', 'cantidad' => '1', 'precio_unitario' => '100', 'descuento' => '0', 'indicador' => '1', 'indicador_bien_servicio' => '2']]]);
    $draft = EcfDraft::create(['id' => (string) Str::uuid(), 'user_id' => 501, 'tipo_ecf' => $payload['tipo_ecf'], 'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'version' => 1]);
    echo json_encode(['status' => 200, 'draft' => $draft->id]);
    exit;
}
$session = app('session')->driver();
$session->start();
if (! ($input['guest'] ?? false)) {
    Auth::login(User::findOrFail($input['user'] ?? 501));
}
$csrf = $session->token();
$session->save();
$cookie = app('encrypter')->encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
Auth::forgetGuards();
$action = $input['action'];
$url = route('facil.emission.'.$action, ['draft' => $input['draft']]);
$payload = $input['payload'] ?? ($action === 'review' ? ['version' => 1] : []);
$payload['_token'] = ($input['bad_csrf'] ?? false) ? 'invalid' : $csrf;
$request = Request::create($url, $action === 'status' ? 'GET' : 'POST', $payload, [config('session.cookie') => $cookie], [],
    ['HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on']);
$kernel = app(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
