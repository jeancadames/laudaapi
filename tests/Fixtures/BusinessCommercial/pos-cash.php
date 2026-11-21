<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PosFiscalConnection;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\ProductTax;
use App\Models\ProductTaxDetail;
use App\Models\ProductTaxType;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\Ecommerce\EcommerceOrderOperationalReleaseService;
use App\Services\Fiscal\FiscalDistributionApprovalService;
use App\Services\Fiscal\PosFiscalDocumentService;
use App\Services\Services\ServiceInvoicePostingService;
use App\Support\Fiscal\DocumentFiscalCalculator;
use App\Support\Fiscal\FiscalLineSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\BusinessIdentityApplication;

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';
[$script, $runtime] = $argv;
if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $runtime) || ! is_file($runtime.'/synthetic-only')) {
    throw new RuntimeException('Synthetic runtime required.');
}
$fixture = json_decode(file_get_contents($runtime.'/commercial-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$identity = ['enabled' => true, 'service' => 'pos', 'issuer' => $fixture['identity']['issuer'], 'secret' => $fixture['identity']['peers']['pos']['secret']];
$app = BusinessIdentityApplication::create(dirname(__DIR__, 4).'/poslaudaapi', $runtime.'/pos', $identity, 'https://pos.example');
config()->set(['business_commercial_bridge.enabled' => true, 'business_commercial_outbox.enabled' => true, 'business_commercial_sales.cash_enabled' => true]);
Http::allowStrayRequests([$identity['issuer'].'/*']);
Http::globalOptions(['verify' => $runtime.'/tls.pem', 'proxy' => '']);
DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', static fn (...$values) => max($values));

if (! Schema::hasTable('pos_business_cash_intents')) {
    // Setup only: avoid one fsync per migration; restore durability before native requests.
    DB::statement('PRAGMA synchronous = OFF');
    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
        if (basename($migration) === '2026_08_30_171500_make_payment_notice_case_nullable_for_integrations.php') {
            // Equivalent SQLite-only adaptation already used by POS LocalIntegration.
            Schema::table('accounts_receivable_payment_notices', fn ($table) => $table->unsignedBigInteger('accounts_receivable_case_id')->nullable()->change());
        } else {
            (require $migration)->up();
        }
    }
    $user = User::forceCreate(['id' => 501, 'name' => 'Responsable sintético', 'email' => 'cash@example.test', 'password' => bcrypt('Synthetic-only-2026!'),
        'email_verified_at' => now(), 'role' => 'owner', 'is_active' => true, 'is_admin' => false, 'onboarding_completed' => true, 'onboarding_completed_at' => now()]);
    $company = Company::forceCreate(['id' => 501, 'owner_id' => $user->id, 'name' => 'BUSINESS POS SINTETICO', 'tax_id' => '101010101', 'address' => 'Dirección sintética',
        'is_active' => true, 'onboarding_completed_at' => now(), 'catalog_mode' => 'products_only', 'scenario_version' => 1, 'scenario_activated_at' => now()]);
    $branch = Branch::forceCreate(['id' => 501, 'company_id' => $company->id, 'name' => 'Sucursal sintética', 'is_active' => true]);
    $team = Team::forceCreate(['name' => 'Equipo sintético', 'slug' => 'synthetic-only', 'is_personal' => false]);
    DB::table('team_members')->insert(['team_id' => $team->id, 'user_id' => 501, 'role' => 'owner']);
    $user->forceFill(['current_company_id' => 501, 'current_branch_id' => 501, 'current_team_id' => $team->id])->save();
    DB::table('branch_user')->insert(['user_id' => 501, 'branch_id' => 501, 'role' => 'owner', 'is_active' => true, 'can_create_sales' => true]);
    PosSetting::forceCreate(['company_id' => 501, 'branch_id' => 501, 'use_fiscal_rate_group_calculation' => true, 'require_cash_session' => false, 'allow_free_price' => true]);
    $type = ProductType::forceCreate(['company_id' => 501, 'name' => 'Producto sintético', 'slug' => 'synthetic', 'is_active' => true]);
    $tax = ProductTax::forceCreate(['company_id' => 501, 'code' => 'SYNTHETIC-18', 'tax' => 'ITBIS sintético', 'is_active' => true]);
    $taxType = ProductTaxType::forceCreate(['company_id' => 501, 'code' => 'SYNTHETIC-18', 'tax' => 'ITBIS sintético', 'tax_scope' => 'itbis', 'dgii_indicator' => 1, 'rate' => 18, 'is_exempt' => false, 'is_active' => true]);
    ProductTaxDetail::forceCreate(['product_tax_id' => $tax->id, 'product_tax_type_id' => $taxType->id, 'sort_order' => 1]);
    Product::forceCreate(['id' => 501, 'company_id' => 501, 'product_type_id' => $type->id, 'name' => 'Producto sintético', 'sku' => 'SYNTHETIC', 'product_kind' => 'good',
        'product_tax_id' => $tax->id, 'stock_tracking_mode' => 'product', 'sale_price' => 100, 'min_sale_price' => 50, 'max_sale_price' => 200, 'last_cost' => 40, 'average_cost' => 40, 'is_active' => true]);
    $warehouse = Warehouse::forceCreate(['company_id' => 501, 'branch_id' => 501, 'name' => 'Almacén sintético', 'code' => 'MAIN', 'type' => 'main',
        'is_main' => true, 'is_available_for_sale' => true, 'is_visible_to_sellers' => true, 'is_active' => true]);
    WarehouseProductStock::forceCreate(['company_id' => 501, 'branch_id' => 501, 'warehouse_id' => $warehouse->id, 'product_id' => 501,
        'quantity_on_hand' => 100, 'quantity_reserved' => 0, 'quantity_available' => 100]);
    PaymentMethod::forceCreate(['id' => 501, 'company_id' => 501, 'name' => 'Efectivo', 'code' => 'CASH', 'type' => 'cash', 'is_active' => true, 'is_cash_like' => true]);
    DB::table('business_identity_links')->insert($fixture['links']['pos']);
    DB::statement('PRAGMA synchronous = FULL');
}

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$mode = $input['mode'] ?? 'real';
if ($input['order'] ?? false) {
    config()->set('business_commercial_sales.orders_enabled', $mode !== 'orders_disabled');
    if (! Order::query()->whereKey(501)->exists()) {
        $order = Order::forceCreate(['id' => 501, 'company_id' => 501, 'branch_id' => 501, 'created_by' => 501,
            'order_number' => 'SYNTHETIC-ORDER', 'order_type' => Order::TYPE_COUNTER_ORDER,
            'source' => ($input['ecommerce'] ?? false) ? 'ecommerce' : 'counter', 'ecommerce_type' => ($input['ecommerce'] ?? false) ? 'b2c' : null,
            'ecommerce_checkout_status' => ($input['ecommerce'] ?? false) ? 'submitted' : null,
            'status' => Order::STATUS_APPROVED, 'flow_status' => ($input['ecommerce'] ?? false) ? Order::FLOW_STATUS_DRAFT : Order::FLOW_STATUS_SENT_TO_CASHIER,
            'payment_condition' => Order::PAYMENT_CONDITION_CASH, 'ecf_type' => '32',
            'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 18, 'total' => 118]);
        $line = ['product_id' => 501, 'sku' => 'SYNTHETIC', 'description' => 'Producto sintético', 'quantity' => 1,
            'unit_price' => 100, 'unit_cost' => 40, 'discount_amount' => 0, 'line_subtotal' => 100, 'tax_amount' => 18, 'line_total' => 118,
            'tax_breakdown' => [['dgii_indicator' => 1, 'rate' => 18, 'base_amount' => 100, 'amount' => 18, 'is_exempt' => false]]];
        $line['fiscal_snapshot'] = FiscalLineSnapshot::capture($line, 1, '32', $line['tax_breakdown']);
        $order->items()->create(app(DocumentFiscalCalculator::class)->calculate([$line])[0]);
        if ($input['ecommerce'] ?? false) {
            app(EcommerceOrderOperationalReleaseService::class)->release($order, User::findOrFail(501));
        }
        $distribution = app(FiscalDistributionApprovalService::class);
        $preview = $distribution->preview($order->fresh('items'), User::findOrFail(501));
        $distribution->approve($order->fresh('items'), User::findOrFail(501), $preview['id'], $preview['fingerprint'], 'Synthetic approved distribution');
    }
}

if ($fixture['fiscal'] ?? false) {
    config()->set(['business_commercial_sales.fiscal_handoff_enabled' => true, 'lauda_ecf.allowed_hosts' => ['127.0.0.1']]);
    if (! PosFiscalConnection::query()->exists()) {
        PosFiscalConnection::forceCreate(['company_id' => 501, 'base_url' => $identity['issuer'].'/v1', 'remote_tenant_id' => 501,
            'issuer_rnc' => '101010101', 'environment' => 'testecf', 'income_type' => '01', 'api_key' => 'synthetic-business-key',
            'api_secret' => 'synthetic-business-secret', 'is_enabled' => true, 'verified_at' => now(), 'fiscal_responsible_user_id' => 501]);
        Customer::forceCreate(['id' => 501, 'company_id' => 501, 'name' => 'Comprador sintético', 'rnc' => '131880681', 'is_active' => true]);
    }
}

if ($input['service_invoice'] ?? false) {
    require __DIR__.'/pos-service-setup.php';
}

if ($input['credit'] ?? false) {
    require __DIR__.'/pos-credit-setup.php';
}

if ($mode === 'fiscal_disabled') {
    config()->set(['business_commercial_sales.fiscal_handoff_enabled' => false, 'business_commercial_bridge.enabled' => false]);
}
$actions = [];
$action = null;
$reserveCount = 0;
$codec = new CommercialToken($identity['secret']);
Http::globalRequestMiddleware(function ($request) use (&$actions, &$action, $codec, $identity) {
    if ($request->getHeaderLine('Authorization') === '') {
        $action = 'fiscal';
        $actions[] = 'fiscal:'.$request->getMethod().':'.$request->getUri()->getPath();

        return $request;
    }
    $token = substr($request->getHeaderLine('Authorization'), 7);
    $action = $codec->decode($token, 'pos', $identity['issuer'], 'request', now()->timestamp)['action'];
    $actions[] = $action;

    return $request;
});
Http::globalResponseMiddleware(function ($response) use (&$action, &$reserveCount, $mode) {
    if ($action === 'reserve') {
        $reserveCount++;
        if ($mode === 'lost_second_reserve' && $reserveCount === 2) {
            throw new ConnectionException('Synthetic loss of second reservation.');
        }
        if ($mode === 'credit_changed') {
            DB::table('delivery_note_items')->where('delivery_note_id', 501)->update(['description' => 'Changed after reservation']);
        }
        if ($mode === 'credit_fenced') {
            DB::table('pos_business_credit_intents')->update(['lease_token' => '11111111-1111-4111-8111-111111111111', 'leased_until' => now()->addSeconds(90)]);
        }
    }
    if ($mode === 'lost_emission_reply' && $action === 'fiscal' && $response->getStatusCode() === 202) {
        throw new ConnectionException('Synthetic response loss after fiscal emission.');
    }
    if ($mode === 'lost_'.$action || ($mode === 'late_validation_lost_cancel' && $action === 'cancel')) {
        throw new ConnectionException('Synthetic response loss after central persistence.');
    }
    if ($action === 'reserve' && $mode === 'revoke_role') {
        DB::table('users')->where('id', 501)->update(['role' => 'seller']);
    }
    if ($action === 'reserve' && $mode === 'revoke_link') {
        DB::table('business_identity_links')->update(['active' => false]);
    }
    if ($action === 'reserve' && $mode === 'revoke_branch') {
        DB::table('users')->where('id', 501)->update(['role' => 'cashier']);
        DB::table('branch_user')->where('user_id', 501)->update(['can_create_sales' => false]);
    }
    if ($action === 'reserve' && $mode === 'service_changed') {
        DB::table('service_order_items')->where('service_order_id', 501)->update(['description' => 'Changed after reservation']);
    }
    if ($action === 'reserve' && $mode === 'service_cancelled') {
        DB::table('service_orders')->where('id', 501)->update(['status' => 'cancelled']);
    }
    if ($action === 'reserve' && $mode === 'service_membership_revoked') {
        DB::table('branch_user')->where('user_id', 501)->update(['is_active' => false]);
    }
    if ($action === 'reserve' && $mode === 'order_changed') {
        DB::table('order_items')->where('order_id', 501)->update(['description' => 'Changed after admission']);
    }
    if ($action === 'reserve' && $mode === 'order_cancelled') {
        DB::table('orders')->where('id', 501)->update(['status' => Order::STATUS_CANCELLED]);
    }
    if ($action === 'reserve' && $mode === 'revoke_collection') {
        DB::table('users')->where('id', 501)->update(['role' => 'cashier']);
        DB::table('branch_user')->where('user_id', 501)->update(['can_collect_payments' => false]);
    }
    if ($action === 'reserve' && $mode === 'fenced') {
        DB::table('pos_business_cash_intents')->update(['lease_token' => '11111111-1111-4111-8111-111111111111', 'leased_until' => now()->addSeconds(90)]);
    }

    return $response;
});
if ($mode === 'after_commit_failure') {
    Sale::created(fn () => DB::afterCommit(fn () => throw new RuntimeException('Synthetic afterCommit failure.')));
}
if (in_array($mode, ['lost_native_evidence', 'lost_native_evidence_validation'], true)) {
    Sale::created(fn () => DB::afterCommit(function () use ($mode): void {
        DB::table('pos_business_sale_documents')->delete();
        DB::table('pos_operation_receipts')->delete();
        if ($mode === 'lost_native_evidence_validation') {
            throw ValidationException::withMessages(['native' => 'Synthetic validation failure after evidence loss.']);
        }
        throw new RuntimeException('Synthetic loss of native evidence after commit.');
    }));
}
if (in_array($mode, ['after_commit_revoked', 'after_commit_generation'], true)) {
    Sale::created(fn () => DB::afterCommit(function () use ($mode): void {
        if ($mode === 'after_commit_revoked') {
            DB::table('users')->where('id', 501)->update(['role' => 'seller']);
        } else {
            DB::table('pos_business_cash_intents')->increment('generation');
        }
        throw new RuntimeException('Synthetic changed authorization or generation after commit.');
    }));
}
if (in_array($mode, ['late_validation', 'late_validation_lost_cancel'], true)) {
    Sale::created(fn () => throw ValidationException::withMessages(['payments' => 'Synthetic late native rejection.']));
}
if ($mode === 'map_failure') {
    DB::statement("CREATE TRIGGER fixture_map_failure BEFORE INSERT ON pos_business_sale_documents BEGIN SELECT RAISE(ABORT, 'synthetic evidence write failure'); END");
}
if (in_array($mode, ['expired_before_native', 'crash_after_begin'], true)) {
    DB::listen(function ($query) use ($mode): void {
        if (str_starts_with($query->sql, 'update "business_commercial_operations"') && in_array('executing', $query->bindings, true)) {
            DB::afterCommit(function () use ($mode): void {
                if ($mode === 'crash_after_begin') {
                    throw new RuntimeException('Synthetic process stopped after durable begin.');
                }
                CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(121));
                // Keep the local lease live to exercise the commercial deadline independently.
                DB::table('pos_business_cash_intents')->update(['leased_until' => CarbonImmutable::now()->addMinute()]);
            });
        }
    });
}
if ($mode === 'orphan_prepare') {
    DB::connection()->beforeExecuting(function ($sql, $bindings): void {
        if (str_starts_with($sql, 'update "pos_business_cash_intents" set "commercial_operation_id"')) {
            throw new RuntimeException('Synthetic process stopped before saving prepared operation id.');
        }
    });
}
if ($mode === 'disabled') {
    config()->set('business_commercial_sales.cash_enabled', false);
}
if ($mode === 'bridge_disabled') {
    config()->set('business_commercial_bridge.enabled', false);
}
if ($mode === 'unavailable') {
    Http::fake(['*' => Http::failedConnection()]);
}
if ($mode === 'due') {
    DB::table('business_commercial_operations')->update(['next_attempt_at' => null]);
    DB::table('pos_business_cash_intents')->update(['lease_token' => null, 'leased_until' => null]);
}

$output = [];
try {
    if (($input['action'] ?? 'sale') === 'reconcile') {
        $output = ['status' => 200, 'code' => Artisan::call(($input['credit'] ?? false) ? 'pos:business-credit-reconcile' : 'pos:business-cash-reconcile', $input['options'] ?? []), 'output' => Artisan::output()];
    } elseif (($input['action'] ?? 'sale') === 'setup') {
        $output = ['status' => 200];
    } elseif (($input['action'] ?? 'sale') === 'fiscal_reconcile') {
        app(PosFiscalDocumentService::class)->process(DB::table('pos_fiscal_documents')->sole()->id);
        $output = ['status' => 200];
    } elseif (($input['action'] ?? 'sale') === 'direct') {
        Sale::forceCreate(['company_id' => 501, 'branch_id' => 501, 'created_by' => 501, 'invoice_type' => 'cash']);
        $output = ['status' => 200];
    } elseif (($input['action'] ?? 'sale') === 'service') {
        app(ServiceInvoicePostingService::class)->invoice(new ServiceOrder(['company_id' => 501]), User::findOrFail(501));
        $output = ['status' => 200];
    } elseif (($input['action'] ?? 'sale') === 'down') {
        (require base_path('database/migrations/2026_11_07_000013_create_pos_business_cash_intents.php'))->down();
        $output = ['status' => 200];
    } else {
        $session = app('session')->driver();
        $session->start();
        Auth::login(User::findOrFail(501));
        $csrf = $session->token();
        $session->save();
        $cookie = app('encrypter')->encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
        Auth::forgetGuards();
        $payload = $input['payload'] ?? ['idempotency_key' => 'cash:1', 'items' => [['product_id' => 501, 'quantity' => 1, 'unit_price' => 100]],
            'payments' => [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118]]];
        if ($fixture['fiscal'] ?? false) {
            $payload += ['customer_id' => 501, 'ecf_type' => '31'];
        }
        if ($input['order'] ?? false) {
            $payload = $input['payload'] ?? ['idempotency_key' => 'order:1',
                'payments' => [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118]]];
        }
        if ($input['credit'] ?? false) {
            $payload = $input['payload'] ?? ['idempotency_key' => 'credit:1', 'notes' => null];
            if (($input['bulk'] ?? false) && ! isset($payload['invoices'])) {
                $payload['invoices'] = [['delivery_note_id' => 501], ['delivery_note_id' => 502]];
            }
        }
        $path = ($input['order'] ?? false) ? '/operaciones/pedidos/'.($input['order_id'] ?? 501).'/cobrar' : '/operaciones/ventas/contado';
        if ($input['credit'] ?? false) {
            $path = ($input['bulk'] ?? false) ? '/operaciones/conduces/facturar-credito-masivo' : '/operaciones/conduces/'.($input['note_id'] ?? 501).'/facturar-credito';
        }
        if ($input['service_invoice'] ?? false) {
            $path = '/operaciones/servicios/ordenes/'.($input['service_order_id'] ?? 501).'/facturar';
            $payload = $input['payload'] ?? ['idempotency_key' => 'service:1', 'payments' => ($input['service_credit'] ?? false) ? [] : [['payment_method_id' => 501, 'payment_type' => 'cash', 'amount' => 118]]];
        }
        if (($input['action'] ?? 'sale') === 'service_payment') {
            $saleId = DB::table('service_orders')->where('id', 501)->value('invoiced_sale_id');
            $path = ($input['service_credit'] ?? false) ? '/operaciones/cuentas-por-cobrar/pagos' : '/operaciones/servicios/facturas/'.$saleId.'/pagos';
            $payload = ($input['service_credit'] ?? false)
                ? ['account_receivable_id' => DB::table('accounts_receivable')->where('invoice_id', $saleId)->sole()->id, 'payment_method_id' => 501, 'amount' => 18, 'reference' => 'SERVICE-PAYMENT']
                : ['payment_method_id' => 501, 'amount' => 18];
        }
        if (($input['action'] ?? 'sale') === 'credit_payment') {
            $path = '/operaciones/cuentas-por-cobrar/pagos';
            $payload = ['account_receivable_id' => DB::table('accounts_receivable')->where('delivery_note_id', 501)->sole()->id,
                'payment_method_id' => 501, 'amount' => 18, 'reference' => 'SYNTHETIC-CREDIT-PAYMENT'];
        }
        $payload['_token'] = $csrf;
        $request = Request::create('https://pos.example'.$path, 'POST', $payload, [config('session.cookie') => $cookie], [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on']);
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $output = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'replayed' => $response->headers->get('Idempotency-Replayed')];
    }
} catch (Throwable $error) {
    $output = ['status' => $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500, 'message' => $error->getMessage()];
}
$output['native'] = ['sales' => DB::table('sales')->count(), 'payments' => DB::table('sale_payments')->count(), 'movements' => DB::table('stock_movements')->count(),
    'stock' => (float) DB::table('warehouse_product_stocks')->where('product_id', 501)->value('quantity_on_hand'), 'receipts' => DB::table('pos_operation_receipts')->count(),
    'maps' => DB::table('pos_business_sale_documents')->count(), 'intents' => DB::table('pos_business_cash_intents')->get(['state', 'generation'])->toArray(),
    'outbox' => DB::table('business_commercial_operations')->orderBy('created_at')->orderBy('id')->pluck('state')->all()];
if ($fixture['fiscal'] ?? false) {
    $output['fiscal'] = DB::table('pos_fiscal_documents')->get(['reference', 'status', 'encf', 'incident_code', 'payload'])->toArray();
}
if ($input['order'] ?? false) {
    $output['order'] = DB::table('orders')->where('id', 501)->first(['status', 'flow_status', 'sale_id', 'source', 'ecommerce_checkout_status']);
    $output['location'] = isset($response) ? $response->headers->get('Location') : null;
}
if ($input['credit'] ?? false) {
    $output['credit'] = ['intents' => DB::table('pos_business_credit_intents')->get(['state', 'generation'])->toArray(),
        'items' => DB::table('pos_business_credit_items')->get(['generation', 'delivery_note_id', 'sale_id', 'central_document_id'])->toArray(),
        'accounts' => DB::table('accounts_receivable')->count(), 'charges' => DB::table('customer_account_movements')->where('movement_type', 'charge')->count(),
        'payments' => DB::table('accounts_receivable_payments')->count(),
        'payment_movements' => DB::table('customer_account_movements')->where('movement_type', 'payment')->count(),
        'balances' => DB::table('accounts_receivable')->orderBy('delivery_note_id')->get(['delivery_note_id', 'original_amount', 'paid_amount', 'balance'])->toArray(),
        'notes' => DB::table('delivery_notes')->orderBy('id')->get(['id', 'status', 'invoice_id'])->toArray()];
}
if ($input['service_invoice'] ?? false) {
    $output['service'] = ['order' => DB::table('service_orders')->where('id', 501)->first(['status', 'invoiced_sale_id', 'account_receivable_id']),
        'accounts' => DB::table('accounts_receivable')->get(['original_amount', 'paid_amount', 'balance'])->toArray(),
        'charges' => DB::table('customer_account_movements')->where('movement_type', 'charge')->count(),
        'payments' => DB::table('accounts_receivable_payments')->count()];
}
$output['actions'] = $actions;
echo json_encode($output, JSON_THROW_ON_ERROR);
