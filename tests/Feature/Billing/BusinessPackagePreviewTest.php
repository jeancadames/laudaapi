<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessCatalogService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessContractFixtures;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessPackagePreviewTest extends TestCase
{
    private const BASE = '/app/business/self-service';

    private string $runtime;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business package integration tests');
        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessQuoteCheckoutFixtures::schema();
        BusinessQuoteFixtures::seed(0);
        $this->withoutVite();
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'package-csrf'])->withHeader('X-CSRF-TOKEN', 'package-csrf');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function preview(array $products, string $cycle = 'monthly', string $level = 'initial')
    {
        return $this->postJson(self::BASE.'/v1/preview', compact('products', 'cycle', 'level'));
    }

    public function test_home_catalog_and_real_prices_share_the_owner_context(): void
    {
        config(['ecosystem_hub.groups' => []]);
        $this->get('/app')->assertOk()->assertInertia(fn ($page) => $page->component('App/Home')
            ->where('business_self_service.configurator_url', self::BASE));
        $catalog = $this->getJson(self::BASE.'/v1/catalog')->assertOk()->json();
        $this->assertSame(['ecf', 'cumplimiento', 'pos', 'bys'], array_column($catalog['products'], 'code'));
        $this->assertSame(['facil_ecf'], $catalog['products'][0]['inclusion_codes']);
        $this->get(self::BASE)->assertOk()->assertInertia(fn ($page) => $page->component('Business/Package/Build', false)
            ->where('configurator.catalog', $catalog)->where('configurator.confirm_url', null));
        $this->preview(['ecf', 'cumplimiento'])->assertOk()->assertJsonPath('components.0.offer_code', 'fiscal')
            ->assertJsonPath('due_today_cents', 200000)->assertJsonPath('due_today.tax_cents', 0)
            ->assertJsonPath('automatic_renewal', false);
        $custom = $this->preview(['ecf', 'bys', 'cumplimiento'])->assertOk()->assertJsonPath('due_today_cents', 300000)
            ->assertJsonPath('capacity.documents', 500)->json();
        $this->assertEqualsCanonicalizing(['bys', 'fiscal'], array_column($custom['components'], 'offer_code'));
        $this->preview(['pos', 'bys', 'cumplimiento', 'ecf'], 'yearly')->assertOk()->assertJsonPath('due_today_cents', 3780000)
            ->assertJsonPath('components.0.offer_code', 'business');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('service_commercial_quotes', 1); // Only the initial fixture.
    }

    public function test_compares_prices_for_the_selected_cycle_and_charges_guidance_once(): void
    {
        $service = app(BusinessCatalogService::class);
        $version = $service->current();
        $draft = $service->revise($version, $version->lock_version, 4)->refresh();
        $payload = $draft->payload;
        $payload['tax_rate'] = 0.1;
        $payload['offers']['ecf']['levels']['initial']['annual_cents'] = 600000;
        $payload['offers']['cumplimiento']['levels']['initial']['annual_cents'] = 300000;
        $payload['implementations']['guided']['price_cents'] = 10000;
        $draft = $service->save($draft, $draft->lock_version, $payload, 4);
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinute());
        $service->publish($draft, $draft->lock_version, null, 4);
        $this->preview(['cumplimiento', 'ecf'], 'yearly')->assertOk()->assertJsonCount(2, 'components')
            ->assertJsonPath('recurring.net_cents', 900000)->assertJsonPath('recurring.tax_cents', 90000)
            ->assertJsonPath('one_time.total_cents', 11000)->assertJsonPath('due_today_cents', 1001000);
    }

    public function test_all_initial_selections_use_the_cheapest_exact_cover(): void
    {
        $products = ['ecf', 'cumplimiento', 'pos', 'bys'];
        // Indexed by the subset of the four products above, including non-packaged combinations.
        $monthly = [1 => 1500, 1000, 2000, 1500, 2500, 2500, 3500, 1000, 2500, 2000, 3000, 2500, 3500, 3500, 3500];
        foreach ($monthly as $mask => $amount) {
            $selection = [];
            foreach ($products as $position => $product) {
                if ($mask & (1 << $position)) {
                    $selection[] = $product;
                }
            }
            foreach (['monthly' => $amount * 100, 'yearly' => $amount * 1080] as $cycle => $cents) {
                $proposal = $this->preview($selection, $cycle)->assertOk()->assertJsonPath('due_today_cents', $cents)->json();
                $covered = array_merge(...array_column($proposal['components'], 'products'));
                $this->assertEqualsCanonicalizing($selection, $covered);
            }
        }
    }

    public function test_invalid_combinations_and_retired_catalog_are_actionable(): void
    {
        $this->preview(['facil_ecf'])->assertUnprocessable()->assertJsonPath('code', 'invalid_selection');
        $this->preview(['ecf', 'ecf'])->assertUnprocessable();
        $this->preview(['ecf'], 'monthly', 'growth')->assertUnprocessable()->assertJsonPath('code', 'invalid_selection');
        DB::table('services')->where('service_key', 'cumplimiento')->update(['active' => false]);
        $this->preview(['ecf', 'cumplimiento'])->assertUnprocessable();
        $codes = array_column($this->getJson(self::BASE.'/v1/catalog')->json('products'), 'code');
        $this->assertNotContains('cumplimiento', $codes);
        ServiceCatalogVersion::query()->update(['status' => 'retired']);
        $this->getJson(self::BASE.'/v1/catalog')->assertStatus(503)->assertJsonPath('code', 'catalog_unavailable');
    }

    private function purchasePayload(array $products): array
    {
        config(['business_commercial.checkout_enabled' => true]);
        return ['accepted' => true, 'idempotency_key' => (string) Str::uuid(),
            'proposal_ref' => $this->preview($products)->assertOk()->json('proposal_ref')];
    }

    public function test_confirmation_freezes_exact_custom_package_and_recovers_after_catalog_retirement(): void
    {
        $payload = $this->purchasePayload(['cumplimiento', 'ecf', 'bys']);
        $url = self::BASE.'/v1/purchases';
        $result = $this->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('summary.due_today_cents', 300000)->assertJsonPath('balance_cents', 300000)
            ->assertJsonPath('payment.status', 'preparing')->json();
        $id = $result['purchase_id'];
        $quote = ServiceCommercialQuote::where('public_id', $id)->sole();
        $this->assertEqualsCanonicalizing(['ecf', 'bys', 'cumplimiento'], array_keys($quote->snapshot['products']));
        $this->assertSame($payload['proposal_ref'], $quote->snapshot['self_service']['proposal_ref']);
        $this->assertSame('3000.00', Invoice::sole()->total);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscription_items', 0);
        $this->getJson(self::BASE.'/v1/catalog')->assertJsonPath('resume_purchase_id', $id);
        $this->get(self::BASE)->assertInertia(fn ($p) => $p->component('Business/Package/Build', false)
            ->where('configurator.confirm_url', $url)->where('configurator.resume_purchase_id', $id));
        $this->postJson($url, array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))
            ->assertConflict()->assertJsonPath('code', 'existing_purchase')->assertJsonPath('purchase_id', $id);
        ServiceCatalogVersion::query()->update(['status' => 'retired']);
        config(['ecosystem_hub.groups' => []]);
        $this->get('/app')->assertOk()->assertInertia(fn ($p) => $p->component('App/Home')
            ->where('business_self_service.pending_purchase.id', $id)
            ->where('business_self_service.pending_purchase.next_action.url', self::BASE.'?compra='.$id));
        $this->get(self::BASE)->assertInertia(fn ($p) => $p->component('Business/Package/Build', false)
            ->where('configurator.catalog', null)->where('configurator.resume_purchase_id', $id));
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMonth());
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('purchase_id', $id);
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonPath('summary.due_today_cents', 300000);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('service_quote_acceptances', 1);
        $this->actingAs(User::findOrFail(2));
        $this->getJson($url.'/'.$id)->assertNotFound();
        $this->postJson($url, $payload)->assertConflict();
    }

    public function test_price_change_requires_fresh_consent_and_retries_do_not_overwrite_another_selection(): void
    {
        $url = self::BASE.'/v1/purchases';
        $first = $this->purchasePayload(['ecf', 'cumplimiento']);
        $catalog = app(BusinessCatalogService::class);
        $version = $catalog->current();
        $draft = $catalog->revise($version, $version->lock_version, 4)->refresh();
        $payload = $draft->payload;
        $payload['tax_rate'] = 0.1;
        $draft = $catalog->save($draft, $draft->lock_version, $payload, 4);
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinute());
        $catalog->publish($draft, $draft->lock_version, null, 4);
        $replacement = $this->postJson($url, $first)->assertConflict()->assertJsonPath('code', 'price_changed')
            ->assertJsonPath('replacement.due_today_cents', 220000)->json('replacement.proposal_ref');
        $this->assertDatabaseCount('invoices', 0);
        $next = array_replace($first, ['proposal_ref' => $replacement, 'idempotency_key' => (string) Str::uuid()]);
        $this->postJson($url, array_replace($next, ['accepted' => false]))->assertUnprocessable();
        $this->postJson($url, $next)->assertCreated();
        $this->postJson($url, array_replace($next, ['proposal_ref' => $first['proposal_ref']]))
            ->assertConflict()->assertJsonPath('code', 'key_conflict');
        $this->assertSame('200.00', Invoice::sole()->tax_total);
    }

    public function test_before_transfer_migration_payments_are_read_from_the_invoice_without_offering_an_external_link(): void
    {
        $url = self::BASE.'/v1/purchases';
        $response = $this->postJson($url, $this->purchasePayload(['ecf']))->assertCreated();
        $url .= '/'.$response->json('purchase_id');
        $invoice = Invoice::sole();
        $invoice->update(['payment_url' => 'https://payments.example.invalid/test']);
        $this->getJson($url)->assertOk()->assertJsonPath('payment.status', 'preparing')->assertJsonPath('payment.action', null);
        $payment = ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'method' => 'bank_transfer',
            'currency' => 'DOP', 'paid_at' => now()];
        Payment::create($payment + ['amount' => '500.00']);
        $this->getJson($url)->assertOk()->assertJsonPath('payment.status', 'processing')
            ->assertJsonPath('payment.action', null)->assertJsonPath('balance_cents', 100000);
        Payment::create($payment + ['amount' => '1000.00']);
        $this->getJson($url)->assertOk()->assertJsonPath('payment.status', 'paid')->assertJsonPath('balance_cents', 0)
            ->assertJsonPath('access.0.status', 'pending');
    }

    public function test_manual_transfer_is_private_until_review_and_retries_reconcile_only_once(): void
    {
        foreach (['2026_01_24_125845_create_payment_transactions_table', '2026_11_23_000001_create_business_transfer_proofs'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        config(['business_commercial.implementation_enabled' => true]);
        Storage::fake('private');
        $base = self::BASE.'/v1/purchases';
        $purchase = $this->postJson($base, $this->purchasePayload(['ecf']))->assertCreated()->assertJsonPath('payment.status', 'payable')->json();
        $url = $base.'/'.$purchase['purchase_id'];
        $checkout = $purchase['payment']['action']['url'];
        $returnUrl = self::BASE.'?compra='.$purchase['purchase_id'];
        $this->get($checkout)->assertInertia(fn ($page) => $page->where('checkout.transfer.submit_url', $url.'/proof')
            ->where('checkout.purchase_url', $returnUrl));
        $proof = fn () => UploadedFile::fake()->createWithContent('transfer.pdf', "%PDF-1.4\nSynthetic receipt\n%%EOF");
        $payload = ['reference' => 'QA-001', 'submission_key' => (string) Str::uuid()];
        $submit = fn () => $this->post($url.'/proof', $payload + ['proof' => $proof()], ['Accept' => 'application/json']);
        $this->actingAs(User::findOrFail(2));
        $submit()->assertNotFound();
        $this->actingAs(User::findOrFail(1));
        $this->post($url.'/proof', $payload + ['proof' => UploadedFile::fake()->createWithContent('bad.html', '<html/>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $submit()->assertOk();
        $submit()->assertOk();
        $this->assertDatabaseCount('business_transfer_proofs', 1);
        $this->assertCount(1, Storage::disk('private')->allFiles());
        $this->get($checkout)->assertInertia(fn ($page) => $page->where('checkout.transfer.status', 'pending')->where('checkout.implementation_url', null)
            ->where('checkout.purchase_url', $returnUrl));
        $this->get($returnUrl)->assertOk()->assertInertia(fn ($page) => $page->component('Business/Package/Build', false)
            ->where('configurator.resume_purchase_id', $purchase['purchase_id']));
        $this->getJson($url)->assertJsonPath('payment.status', 'processing')->assertJsonPath('balance_cents', 150000)
            ->assertJsonPath('access.0.message', 'Pendiente de confirmación del pago.');
        $this->assertDatabaseCount('payments', 0);
        $review = '/admin/business-transfer-proofs/1/review';
        $file = '/admin/business-transfer-proofs/1/file';
        $approve = ['decision' => 'approved', 'amount_cents' => 150000];
        $this->postJson($review, $approve)->assertForbidden();
        $this->get($file)->assertForbidden();
        $this->actingAs(User::findOrFail(4));
        $this->get('/admin/payments')->assertOk()->assertInertia(fn ($page) => $page->where('transferProofs.data.0.reference', 'QA-001'));
        $this->get($file)->assertDownload('comprobante-1.pdf');
        $this->postJson($review, ['decision' => 'rejected'])->assertUnprocessable();
        $this->postJson($review, ['decision' => 'rejected', 'reason' => 'La referencia no coincide.'])->assertOk();
        $this->postJson($review, $approve)->assertConflict();
        $this->actingAs(User::findOrFail(1));
        $this->getJson($url)->assertJsonPath('payment.status', 'failed')->assertJsonPath('payment.message', 'La referencia no coincide.')
            ->assertJsonPath('access.0.message', 'Revisa el estado del pago.');
        $payload = ['reference' => 'QA-002', 'submission_key' => (string) Str::uuid()];
        $this->post($url.'/proof', $payload + ['proof' => $proof()], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs(User::findOrFail(4));
        $review = '/admin/business-transfer-proofs/2/review';
        $this->postJson($review, ['decision' => 'approved', 'amount_cents' => 160000])->assertUnprocessable();
        $partial = ['decision' => 'approved', 'amount_cents' => 50000];
        $this->postJson($review, $partial)->assertOk();
        $this->postJson($review, $partial)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->actingAs(User::findOrFail(1));
        $this->getJson($url)->assertJsonPath('balance_cents', 100000)->assertJsonPath('payment.status', 'payable')
            ->assertJsonPath('access.0.message', 'Esperando el pago.');
        $this->get($checkout)->assertInertia(fn ($page) => $page->where('checkout.implementation_url', null));
        $this->post($url.'/proof', ['reference' => 'QA-002', 'submission_key' => (string) Str::uuid(), 'proof' => $proof()],
            ['Accept' => 'application/json'])->assertOk();
        $this->actingAs(User::findOrFail(4));
        $duplicate = '/admin/business-transfer-proofs/3/review';
        $this->postJson($duplicate, $partial)->assertUnprocessable();
        $this->postJson($duplicate, ['decision' => 'rejected', 'reason' => 'Ya se aplicó este abono.'])->assertOk();
        $this->actingAs(User::findOrFail(1));
        $this->post($url.'/proof', ['reference' => 'QA-003', 'submission_key' => (string) Str::uuid(), 'proof' => $proof()],
            ['Accept' => 'application/json'])->assertOk();
        $this->actingAs(User::findOrFail(4));
        $review = '/admin/business-transfer-proofs/4/review';
        $final = ['decision' => 'approved', 'amount_cents' => 100000];
        $this->postJson($review, $final)->assertOk();
        $this->postJson($review, $final)->assertOk();
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertDatabaseHas('business_transfer_proofs', ['id' => 2, 'reviewed_by' => 4, 'status' => 'approved']);
        $this->actingAs(User::findOrFail(1));
        $this->getJson($url)->assertJsonPath('balance_cents', 0)->assertJsonPath('payment.status', 'paid')->assertJsonPath('access.0.status', 'pending');
        $this->get($checkout)->assertInertia(fn ($page) => $page->where('checkout.invoice.status', 'paid')
            ->where('checkout.implementation_url', '/app/business/quotes/'.$purchase['purchase_id'].'/implementation')
            ->where('checkout.purchase_url', $returnUrl));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('subscription_items', 0);
    }

    public function test_paid_purchase_reports_personal_link_consent_active_coverage_and_revocation(): void
    {
        foreach (['2026_11_06_000006_create_business_implementation_orders', '2026_08_24_190000_create_service_bundle_discount_rules_and_applications',
            '2026_11_06_000007_create_business_contracts'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        BusinessContractFixtures::configure();
        $url = self::BASE.'/v1/purchases';
        $id = $this->postJson($url, $this->purchasePayload(['ecf']))->assertCreated()->json('purchase_id');
        $url .= '/'.$id;
        $quote = ServiceCommercialQuote::where('public_id', $id)->sole();
        $invoice = Invoice::sole();
        Payment::create(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'amount' => $invoice->total,
            'currency' => $invoice->currency, 'method' => 'bank_transfer', 'paid_at' => now()]);
        $this->getJson($url)->assertOk()->assertJsonPath('access.0.status', 'link_required');
        $scope = BusinessQuoteAcceptanceService::fingerprint($quote);
        $consentUrl = '/app/business/quotes/'.$id.'/implementation';
        $consent = ['action' => 'complete', 'confirmed' => true, 'version' => 0, 'note' => 'Revisé mi configuración de prueba.',
            'snapshot_hash' => $scope, 'idempotency_key' => (string) Str::uuid()];
        $this->postJson($consentUrl, $consent)->assertConflict();
        $this->assertDatabaseCount('business_implementation_orders', 0);
        BusinessContractFixtures::links($quote);
        $this->getJson($url)->assertOk()->assertJsonPath('access.0.status', 'consent_required')->assertJsonPath('access.0.action.url', $consentUrl);
        $this->get($consentUrl)->assertOk()->assertInertia(fn ($page) => $page->where('implementation.actions.0', 'complete'));
        $this->postJson($consentUrl, $consent)->assertOk()->assertJsonPath('implementation.order.status', 'completed');
        $this->postJson($consentUrl, $consent)->assertOk();
        $this->assertDatabaseCount('business_contracts', 1);
        $this->assertDatabaseCount('business_implementation_events', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->getJson($url)->assertOk()->assertJsonPath('access.0.status', 'ready')->assertJsonPath('access.0.action.url', '/business/identity/companies/1/services/ecf/launch');
        config(['ecosystem_hub.groups' => []]);
        $lastPurchaseUrl = self::BASE.'?compra='.$id;
        $this->get('/app')->assertInertia(fn ($page) => $page->where('business_self_service.pending_purchase', null)
            ->where('business_self_service.last_purchase_url', $lastPurchaseUrl));
        $this->get($lastPurchaseUrl)->assertInertia(fn ($page) => $page->component('Business/Package/Build', false)
            ->where('configurator.resume_purchase_id', $id));
        $this->get(self::BASE.'?purchase='.$id)->assertOk()->assertInertia(fn ($page) => $page->component('Business/Package/Build', false)
            ->where('configurator.resume_purchase_id', $id));
        DB::table('business_identity_members')->update(['active' => false]);
        $this->getJson($url)->assertOk()->assertJsonPath('access.0.status', 'link_required');
        Payment::sole()->update(['paid_at' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('payment.status', 'reversed')->assertJsonPath('current_period.access_until', null)
            ->assertJsonPath('access.0.status', 'pending')
            ->assertJsonPath('access.0.message', 'El pago se revirtió.')->assertJsonPath('access.0.action', null);
        $this->actingAs(User::findOrFail(2));
        $this->get('/app')->assertInertia(fn ($page) => $page->where('business_self_service.last_purchase_url', null));
        $this->getJson($lastPurchaseUrl)->assertNotFound();
    }

    private function activeManagedPackage(string $cycle = 'monthly'): array
    {
        foreach (['2026_11_06_000006_create_business_implementation_orders', '2026_08_24_190000_create_service_bundle_discount_rules_and_applications',
            '2026_11_06_000007_create_business_contracts', '2026_11_23_000002_create_business_package_changes'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        BusinessContractFixtures::configure();
        $proposal = $this->preview(['ecf', 'cumplimiento', 'bys'], $cycle)->assertOk()->json();
        $id = $this->postJson(self::BASE.'/v1/purchases', ['accepted' => true, 'idempotency_key' => (string) Str::uuid(),
            'proposal_ref' => $proposal['proposal_ref']])->assertCreated()->json('purchase_id');
        $quote = ServiceCommercialQuote::where('public_id', $id)->sole();
        $invoice = Invoice::sole();
        Payment::create(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'amount' => $invoice->total,
            'currency' => $invoice->currency, 'method' => 'bank_transfer', 'paid_at' => now()]);
        BusinessContractFixtures::links($quote);
        $data = ['action' => 'complete', 'confirmed' => true, 'version' => 0, 'note' => 'Configuración verificada.',
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/app/business/quotes/'.$id.'/implementation', $data)->assertOk();
        $url = self::BASE.'/'.$id.'/manage';
        $this->getJson(self::BASE.'/v1/purchases/'.$id)->assertJsonPath('manage_url', $url);
        $this->get($url)->assertInertia(fn ($page) => $page->component('Business/Package/Manage', false)->where('plan.change', null));

        return [$url, $id];
    }

    public function test_cancel_package_at_paid_boundary_without_an_invoice_or_early_loss_of_access(): void
    {
        [$url, $id] = $this->activeManagedPackage();
        $this->actingAs(User::findOrFail(2));
        $this->postJson($url.'/preview', ['products' => []])->assertNotFound();
        $this->actingAs(User::findOrFail(3));
        $this->postJson($url.'/preview', ['products' => []])->assertForbidden();
        $this->actingAs(User::findOrFail(1));
        $review = $this->postJson($url.'/preview', ['products' => []])->assertOk()->assertJsonPath('total_cents', 0)->json();
        $submit = ['products' => [], 'accepted' => true, 'terms_hash' => $review['terms_hash']];
        $this->postJson($url.'/confirm', array_replace($submit, ['terms_hash' => str_repeat('a', 64)]))->assertConflict();
        $this->postJson($url.'/confirm', $submit)->assertOk()->assertJsonPath('purchase_url', null);
        $this->postJson($url.'/confirm', $submit)->assertOk();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('business_package_changes', 1);
        $item = DB::table('business_contract_items')->value('subscription_item_id');
        CarbonImmutable::setTestNow(CarbonImmutable::parse($review['effective_at'])->subSecond());
        $this->assertTrue(\App\Services\Entitlements\BusinessContractCoverage::itemIsCovered($item));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($review['effective_at']));
        $this->assertFalse(\App\Services\Entitlements\BusinessContractCoverage::itemIsCovered($item));
        \App\Services\Billing\BusinessPackageChanges::finishDue();
        \App\Services\Billing\BusinessPackageChanges::finishDue();
        $this->assertDatabaseHas('subscriptions', ['status' => 'expired']);
        $this->assertDatabaseHas('subscription_items', ['id' => $item, 'status' => 'cancelled']);
    }

    public function test_annual_change_requires_review_again_if_the_published_price_changes(): void
    {
        [$url] = $this->activeManagedPackage('yearly');
        $review = $this->postJson($url.'/preview', ['products' => ['pos']])->assertOk()->assertJsonPath('total_cents', 1620000)->json();
        $catalog = app(BusinessCatalogService::class);
        $current = $catalog->current();
        $draft = $catalog->revise($current, $current->lock_version, 4)->refresh();
        $payload = $draft->payload;
        $payload['offers']['pos']['levels']['initial']['annual_cents'] = 1400000;
        $draft = $catalog->save($draft, $draft->lock_version, $payload, 4);
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinute());
        $catalog->publish($draft, $draft->lock_version, null, 4);
        $this->postJson($url.'/confirm', ['products' => ['pos'], 'accepted' => true, 'terms_hash' => $review['terms_hash']])->assertConflict();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('business_package_changes', 0);
        $this->postJson($url.'/preview', ['products' => ['pos']])->assertOk()->assertJsonPath('total_cents', 1400000)
            ->assertJsonPath('effective_at', $review['effective_at']);
    }

    public function test_cancellation_respects_an_accepted_and_prepaid_renewal(): void
    {
        [$url, $id] = $this->activeManagedPackage();
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        config(['business_commercial.renewal_enabled' => true]);
        $contract = \App\Models\BusinessContract::sole();
        $service = app(\App\Services\Billing\BusinessRenewalService::class);
        $admin = User::findOrFail(4);
        $owner = User::findOrFail(1);
        $offer = $service->propose($admin, $id, ['confirmed' => true, 'idempotency_key' => (string) Str::uuid(),
            'period_number' => 2, 'expected_revision' => 0, 'valid_until' => $contract->period_ends_at->subDay()->toIso8601String(),
            'base_cents' => 240000, 'discount_cents' => 0, 'tax_bps' => 0, 'commercial_note' => 'Renovación de prueba de un período.',
            ...\App\Services\Billing\BusinessRenewalTerms::POLICIES]);
        $consent = ['offer_id' => $offer->id, 'terms_hash' => $offer->terms_hash, 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()];
        $service->accept($owner, $id, $consent);
        $this->postJson($url.'/preview', ['products' => []])->assertConflict();
        $period = $service->issue($admin, $id, $consent);
        $detailUrl = self::BASE.'/v1/purchases/'.$id;
        $this->getJson($detailUrl)->assertOk()->assertJsonPath('current_period.total_cents', 300000)
            ->assertJsonPath('current_period.access_until', $contract->period_ends_at->toIso8601String())
            ->assertJsonPath('scheduled_change', null);
        $this->postJson($url.'/preview', ['products' => []])->assertConflict();
        Payment::create(['invoice_id' => $period->invoice_id, 'company_id' => $contract->company_id, 'amount' => $period->total,
            'currency' => 'DOP', 'method' => 'bank_transfer', 'paid_at' => now()]);
        $this->getJson($detailUrl)->assertOk()->assertJsonPath('current_period.total_cents', 300000)
            ->assertJsonPath('current_period.access_until', $period->period_ends_at->toIso8601String());
        $review = $this->postJson($url.'/preview', ['products' => []])->assertOk()
            ->assertJsonPath('effective_at', $period->period_ends_at->toIso8601String())->json();
        $this->postJson($url.'/confirm', ['products' => [], 'accepted' => true, 'terms_hash' => $review['terms_hash']])->assertOk();
        CarbonImmutable::setTestNow($period->period_starts_at->addDay());
        $this->getJson($detailUrl)->assertOk()->assertJsonPath('current_period.total_cents', 240000)
            ->assertJsonPath('summary.recurring.total_cents', 300000)
            ->assertJsonPath('scheduled_change', ['effective_at' => $review['effective_at'], 'products' => [], 'manage_url' => $url]);
        $this->get($url)->assertInertia(fn ($page) => $page->where('plan.current.total_cents', 240000)
            ->where('plan.current.access_until', $period->period_ends_at->toIso8601String()));
        $item = DB::table('business_contract_items')->value('subscription_item_id');
        $this->assertTrue(\App\Services\Entitlements\BusinessContractCoverage::itemIsCovered($item));
        CarbonImmutable::setTestNow($period->period_ends_at);
        $this->getJson($detailUrl)->assertOk()->assertJsonPath('current_period.total_cents', null)
            ->assertJsonPath('current_period.access_until', $review['effective_at'])->assertJsonPath('scheduled_change', null);
        $this->assertFalse(\App\Services\Entitlements\BusinessContractCoverage::itemIsCovered($item));
    }

    public function test_replacement_prices_selected_products_and_uses_the_new_invoice_only_after_consent(): void
    {
        [$url, $oldId] = $this->activeManagedPackage();
        $review = $this->postJson($url.'/preview', ['products' => ['ecf', 'pos']])->assertOk()->assertJsonPath('total_cents', 250000)->json();
        $this->assertDatabaseCount('invoices', 1);
        $params = ['products' => ['pos', 'ecf'], 'accepted' => true, 'terms_hash' => $review['terms_hash']];
        $next = $this->postJson($url.'/confirm', $params)->assertOk()->json('purchase_url');
        $this->postJson($url.'/confirm', $params)->assertOk()->assertJsonPath('purchase_url', $next);
        $this->assertDatabaseCount('invoices', 2);
        $id = Str::after($next, '?compra=');
        $this->getJson(self::BASE.'/v1/purchases/'.$oldId)->assertOk()
            ->assertJsonPath('scheduled_change.products', ['ecf', 'pos'])->assertJsonPath('scheduled_change.effective_at', $review['effective_at'])
            ->assertJsonPath('scheduled_change.manage_url', $url);
        $this->getJson(self::BASE.'/v1/purchases/'.$id)->assertOk()->assertJsonPath('scheduled_change', null)
            ->assertJsonPath('current_period', null);
        $quote = ServiceCommercialQuote::where('public_id', $id)->sole();
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals(2500, $invoice->total);
        $this->assertSame(0, $quote->snapshot['one_time_cents']);
        $this->assertDatabaseCount('business_contracts', 1);
        Payment::create(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'amount' => $invoice->total,
            'currency' => $invoice->currency, 'method' => 'bank_transfer', 'paid_at' => now()]);
        $pos = $quote->replicate();
        $pos->snapshot = ['products' => ['pos' => $quote->snapshot['products']['pos']]];
        BusinessContractFixtures::links($pos);
        $consent = ['action' => 'complete', 'confirmed' => true, 'version' => 0, 'note' => 'Configuración revisada.',
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/app/business/quotes/'.$id.'/implementation', $consent)->assertConflict();
        CarbonImmutable::setTestNow(CarbonImmutable::parse($review['effective_at'])->addSecond());
        $this->postJson('/app/business/quotes/'.$id.'/implementation', $consent)->assertOk();
        $this->assertDatabaseCount('business_contracts', 2);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseHas('subscriptions', ['status' => 'expired']);
        $this->getJson(self::BASE.'/v1/purchases/'.$id)->assertJsonPath('access.0.status', 'ready')->assertJsonPath('access.1.status', 'ready');
        $this->getJson(self::BASE.'/v1/purchases/'.$oldId)->assertJsonPath('access.0.status', 'unavailable');
    }

    public function test_expired_reference_disabled_checkout_and_revoked_membership_cannot_invoice(): void
    {
        $payload = $this->purchasePayload(['ecf']);
        $url = self::BASE.'/v1/purchases';
        config(['business_commercial.checkout_enabled' => false]);
        $this->postJson($url, $payload)->assertNotFound();
        config(['business_commercial.checkout_enabled' => true]);
        $this->postJson($url, array_replace($payload, ['proposal_ref' => 'unrecognized']))->assertConflict();
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMonth());
        $this->postJson($url, $payload)->assertConflict()->assertJsonPath('code', 'proposal_expired');
        $this->actingAs(User::findOrFail(3));
        $this->postJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_member_cannot_open_or_calculate_and_company_is_derived_from_membership(): void
    {
        $this->actingAs(User::findOrFail(2));
        $this->getJson(self::BASE.'/v1/catalog?company_id=1')->assertOk()->assertJsonPath('company_name', 'Empresa ajena');
        $this->actingAs(User::findOrFail(3));
        config(['ecosystem_hub.groups' => []]);
        $this->get('/app')->assertOk()->assertInertia(fn ($page) => $page->missing('business_self_service'));
        $this->getJson(self::BASE.'/v1/catalog')->assertForbidden();
        $this->preview(['ecf'])->assertForbidden()->assertJsonPath('code', 'forbidden');
    }
}
