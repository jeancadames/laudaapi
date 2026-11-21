<?php

use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceRun;
use App\Models\User;
use App\Services\BusinessCommercial\RecurringFiscalContext;
use App\Services\Facil\RecurringInvoices;
use App\Support\Facil\InvoiceInput;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

define('BUSINESS_FISCAL_FIXTURE_CLI', true);
require __DIR__.'/ecf-router.php';
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set(['business_commercial_emission.facil_enabled' => true,
    'business_commercial_emission.recurring_enabled' => $input['enabled'] ?? true]);
$operation = $input['action'];
if (in_array($operation, ['store', 'update', 'action'], true)) {
    $session = app('session')->driver();
    $session->start();
    Auth::login(User::findOrFail(501));
    $csrf = $session->token();
    $session->save();
    $cookie = app('encrypter')->encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
    Auth::forgetGuards();
    $today = RecurringInvoices::today();
    $series = isset($input['series']) ? RecurringInvoice::withoutGlobalScopes()->findOrFail($input['series']) : null;
    $data = $operation === 'action' ? ['version' => $series->version] : ($series?->toArray() ?? [
        'id' => (string) Str::uuid(), 'version' => 0, 'name' => 'Servicio mensual sintético',
        'mode' => 'automatic', 'billing_day' => (int) substr($today, 8),
        'start_date' => $today, 'end_date' => '', 'due_days' => 15,
        'payload' => InvoiceInput::normalize(['tipo_ecf' => 'e31', 'tipo_pago' => '2', 'tipo_ingresos' => '01',
            'comprador_rnc' => '131880681', 'comprador_nombre' => 'Comprador sintético',
            'items' => [['nombre' => 'Servicio {periodo}', 'cantidad' => '1', 'precio_unitario' => '100',
                'descuento' => '0', 'indicador' => '1', 'indicador_bien_servicio' => '2']]]),
    ]);
    $data = array_replace($data, ['automatic_consent' => true], $input['payload'] ?? []);
    if ($input['last_period'] ?? false) {
        $data['end_date'] = $today;
    }
    $data['_token'] = $csrf;
    $request = Request::create(route('facil.recurring.'.$operation, $series ? ['recurrence' => $series->id] : []),
        $operation === 'update' ? 'PUT' : 'POST', $data, [config('session.cookie') => $cookie], [],
        ['HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on']);
    $kernel = app(Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true),
        'series' => $series?->id ?? $data['id']], JSON_THROW_ON_ERROR);
    exit;
}

// Exercise the native unattended entry with no HTTP route, authenticated guard or tenant.
Auth::forgetGuards();
app()->forgetInstance('tenant');
app()->instance('request', Request::create('/'));
Http::globalResponseMiddleware(function ($response) use (&$action, $input) {
    if ($action !== 'reserve') {
        return $response;
    }
    $run = RecurringInvoiceRun::withoutGlobalScopes()->where('status', 'processing')->sole();
    switch ($input['after_reserve'] ?? '') {
        case 'draft':
            $draft = DB::table('ecf_drafts')->where('id', $run->draft_id)->first();
            $payload = json_decode($draft->payload, true);
            $payload['items'][0]['precio_unitario'] = '999';
            DB::table('ecf_drafts')->where('id', $run->draft_id)->update(['payload' => json_encode($payload)]);
            break;
        case 'run_owner':
            $run->update(['user_id' => 999]);
            break;
        case 'run_date':
            $run->update(['scheduled_date' => '2000-01-01']);
            break;
        case 'run_status':
            $run->update(['status' => 'skipped']);
            break;
        case 'pause_series':
            RecurringInvoice::withoutGlobalScopes()->whereKey($run->recurring_invoice_id)->update(['status' => 'paused']);
            break;
        case 'reorder_json':
            $draft = DB::table('ecf_drafts')->where('id', $run->draft_id)->first();
            $canonical = function (array $value) use (&$canonical): array {
                foreach ($value as $key => $item) {
                    if (is_array($item)) {
                        $value[$key] = $canonical($item);
                    }
                }
                if (! array_is_list($value)) {
                    ksort($value);
                }

                return $value;
            };
            DB::table('ecf_drafts')->where('id', $run->draft_id)->update(['payload' => json_encode($canonical(json_decode($draft->payload, true)))]);
            break;
    }

    return $response;
});
$exit = 0;
if ($operation === 'prepare') {
    app(RecurringInvoices::class)->prepare($input['series']);
} elseif ($operation === 'emit') {
    app(RecurringInvoices::class)->emit(RecurringInvoiceRun::withoutGlobalScopes()->findOrFail($input['run']));
} else {
    $exit = Artisan::call('facil:generate-recurring');
}
echo json_encode(['exit' => $exit, 'runs' => RecurringInvoiceRun::withoutGlobalScopes()->orderBy('id')->get()->toArray(),
    'series' => RecurringInvoice::withoutGlobalScopes()->orderBy('id')->get()->toArray(),
    'context_clear' => RecurringFiscalContext::current() === null && ! app()->bound('tenant'),
    'unattended' => Auth::guest() && request()->route() === null
        && ! request()->attributes->has('business_facil_attempt') && ! request()->attributes->has('business_direct_generation')], JSON_THROW_ON_ERROR);
