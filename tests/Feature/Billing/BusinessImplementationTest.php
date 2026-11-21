<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\BusinessImplementationEvent;
use App\Models\BusinessImplementationOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCheckoutService;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessImplementationFixtures;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessImplementationTest extends TestCase
{
    private string $runtime;

    private ServiceCommercialQuote $quote;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business implementation tests only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessImplementationFixtures::schema();
        Mail::fake();
        Notification::fake();
        $this->quote = BusinessQuoteCheckoutFixtures::seed();
        config()->set('business_commercial.implementation_enabled', true);
        $this->withoutVite();
        $this->asUser(1);
        $actor = User::findOrFail(1);
        $hash = BusinessQuoteAcceptanceService::fingerprint($this->quote);
        app(BusinessQuoteAcceptanceService::class)->accept($actor, $this->quote->public_id, $hash, (string) Str::uuid());
        app(BusinessQuoteCheckoutService::class)->checkout($actor, $this->quote->public_id, $hash, (string) Str::uuid());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function asUser(int $id): void
    {
        $this->actingAs(User::findOrFail($id))->withSession(['_token' => 'implementation-csrf'])->withHeader('X-CSRF-TOKEN', 'implementation-csrf');
    }

    private function url(bool $admin = false): string
    {
        return $admin ? '/admin/business-implementations/'.$this->quote->public_id : '/app/business/quotes/'.$this->quote->public_id.'/implementation';
    }

    private function payload(string $action = 'request'): array
    {
        return ['confirmed' => true, 'action' => $action, 'version' => BusinessImplementationOrder::first()?->lock_version ?? 0,
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($this->quote), 'idempotency_key' => (string) Str::uuid(), 'note' => 'Evidencia sintética del trabajo realizado.'];
    }

    private function requestOrder(): void
    {
        $this->postJson($this->url(), $this->payload())->assertOk();
    }

    private function pay(string $amount = '11210.00', bool $confirmed = true): Payment
    {
        return Payment::create(['company_id' => 1, 'invoice_id' => Invoice::sole()->id, 'method' => 'bank_transfer',
            'currency' => 'DOP', 'amount' => $amount, 'paid_at' => $confirmed ? now() : null]);
    }

    private function adminAction(string $action): void
    {
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload($action))->assertOk();
    }

    public function test_review_is_private_and_does_not_create_work(): void
    {
        $response = $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Implementation/Show')->where('implementation.order', null)->where('implementation.actions', ['request']));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(0, BusinessImplementationOrder::count());
    }

    public function test_request_freezes_scope_and_links_invoice_without_other_effects(): void
    {
        $this->requestOrder();
        $order = BusinessImplementationOrder::sole();
        $this->assertSame($this->quote->snapshot, $order->scope_snapshot);
        $this->assertSame(BusinessQuoteAcceptanceService::fingerprint($this->quote), $order->snapshot_hash);
        $this->assertSame(Invoice::sole()->id, $order->checkout->invoice_id);
        $this->assertSame('queued', $order->status);
        $this->assertSame(1, BusinessImplementationEvent::count());
        $this->assertSame(1, AuditLog::where('event', 'business.implementation.request')->count());
        foreach (['payments', 'subscriptions', 'subscription_items', 'business_identity_links', 'diagnosis_assessments', 'transformation_capability_activations'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_lost_response_retries_are_idempotent_and_changed_payload_is_rejected(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url(), $payload)->assertOk();
        $this->postJson($this->url(), $payload)->assertOk();
        $this->postJson($this->url(), [...$payload, 'note' => 'Cambió la nota'])->assertConflict();
        $this->requestOrder();
        $this->assertSame(1, BusinessImplementationOrder::count());
        $this->assertSame(1, BusinessImplementationEvent::count());
        $this->pay();
        $this->asUser(4);
        $start = $this->payload('start');
        $this->postJson($this->url(true), $start)->assertOk();
        $this->postJson($this->url(true), $start)->assertOk();
        $this->assertSame(2, BusinessImplementationEvent::count());
        $this->assertSame(2, BusinessImplementationOrder::sole()->lock_version);
    }

    public function test_full_execution_requires_customer_conformity_and_retains_history(): void
    {
        $this->requestOrder();
        $this->pay();
        $this->adminAction('start');
        $this->adminAction('block');
        $this->adminAction('resume');
        $this->adminAction('deliver');
        $this->postJson($this->url(true), $this->payload('complete'))->assertForbidden();
        $this->asUser(1);
        $this->postJson($this->url(), $this->payload('request_changes'))->assertOk()->assertJsonPath('implementation.order.status', 'blocked');
        $this->adminAction('resume');
        $this->adminAction('deliver');
        $this->asUser(1);
        $this->postJson($this->url(), $this->payload('complete'))->assertOk()->assertJsonPath('implementation.order.status', 'completed');
        $this->assertSame(9, BusinessImplementationEvent::count());
        $this->assertSame(4, BusinessImplementationOrder::sole()->assigned_to);
        $this->assertSame(0, DB::table('subscriptions')->count());
        $this->assertNull(Invoice::sole()->subscription_id);
    }

    public static function payments(): array
    {
        return [['0.00', true], ['11209.99', true], ['11210.00', false]];
    }

    #[DataProvider('payments')]
    public function test_unpaid_partial_and_unconfirmed_payments_cannot_start(string $amount, bool $confirmed): void
    {
        $this->requestOrder();
        if ($amount !== '0.00') {
            $this->pay($amount, $confirmed);
        }
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload('start'))->assertConflict();
        $this->assertSame('queued', BusinessImplementationOrder::sole()->status);
    }

    public function test_reversal_blocks_delivery_and_conformity_but_preserves_history(): void
    {
        $this->requestOrder();
        $payment = $this->pay();
        $this->adminAction('start');
        $payment->update(['paid_at' => null]);
        $this->postJson($this->url(true), $this->payload('deliver'))->assertConflict();
        $this->get($this->url(true))->assertOk()->assertInertia(fn ($page) => $page->where('implementation.actions', ['block', 'cancel']));
        $payment->update(['paid_at' => now()]);
        $this->adminAction('deliver');
        $payment->update(['paid_at' => null]);
        $this->asUser(1);
        $this->postJson($this->url(), $this->payload('complete'))->assertConflict();
        $this->assertSame('delivered', BusinessImplementationOrder::sole()->status);
    }

    public function test_forged_paid_flag_and_stale_payment_total_do_not_authorize_work(): void
    {
        $this->requestOrder();
        DB::table('invoices')->update(['status' => 'paid', 'amount_paid' => '11210.00']);
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload('start'))->assertConflict();
    }

    public function test_outdated_version_and_invalid_transitions_are_rejected(): void
    {
        $this->requestOrder();
        $this->pay();
        $this->asUser(4);
        $stale = $this->payload('start');
        $this->adminAction('start');
        $this->postJson($this->url(true), $stale)->assertConflict();
        $this->postJson($this->url(true), $this->payload('resume'))->assertConflict();
        $this->assertSame(2, BusinessImplementationEvent::count());
    }

    public static function deniedUsers(): array
    {
        return [[2], [3], [4]];
    }

    #[DataProvider('deniedUsers')]
    public function test_foreign_member_and_platform_admin_cannot_act_as_customer(int $id): void
    {
        $this->asUser($id);
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
    }

    public function test_customer_cannot_execute_and_staff_cannot_request_work(): void
    {
        $this->requestOrder();
        $this->postJson($this->url(), $this->payload('start'))->assertForbidden();
        $this->get('/admin/business-implementations')->assertForbidden();
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload())->assertForbidden();
        $this->get('/admin/business-implementations')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Implementation/Index')->has('orders.data', 1)->where('orders.data.0.company_name', 'Comercial Rivera'));
    }

    public function test_revoked_membership_is_checked_before_retry(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url(), $payload)->assertOk();
        DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]);
        $this->postJson($this->url(), $payload)->assertNotFound();
    }

    public function test_unverified_user_and_guest_are_denied(): void
    {
        DB::table('users')->where('id', 1)->update(['email_verified_at' => null]);
        $this->asUser(1);
        $this->getJson($this->url())->assertForbidden();
        auth()->logout();
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_reassigned_company_cannot_start_and_customer_loses_access(): void
    {
        $this->requestOrder();
        $this->pay();
        DB::table('companies')->where('id', 2)->update(['subscriber_id' => null]);
        DB::table('companies')->where('id', 1)->update(['subscriber_id' => 2]);
        $this->get($this->url())->assertNotFound();
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload('start'))->assertConflict();
    }

    public function test_flag_and_missing_migration_fail_closed_without_creating_tables(): void
    {
        config()->set('business_commercial.implementation_enabled', false);
        $this->get($this->url())->assertNotFound();
        config()->set('business_commercial.implementation_enabled', true);
        Schema::drop('business_implementation_events');
        $this->get($this->url())->assertStatus(503);
        $this->assertFalse(Schema::hasTable('business_implementation_events'));
    }

    public function test_confirmation_note_hash_and_csrf_are_required(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url(), [...$payload, 'confirmed' => false])->assertUnprocessable();
        $this->postJson($this->url(), [...$payload, 'note' => ' '])->assertUnprocessable();
        $this->postJson($this->url(), [...$payload, 'snapshot_hash' => str_repeat('a', 64)])->assertConflict();
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->url(), $payload)->assertStatus(419);
        $this->assertSame(0, BusinessImplementationOrder::count());
    }

    public function test_changed_invoice_or_scope_is_rejected(): void
    {
        $this->requestOrder();
        DB::table('invoices')->update(['total' => '1.00']);
        $this->get($this->url())->assertConflict();
        DB::table('invoices')->update(['total' => '11210.00']);
        DB::table('business_implementation_orders')->update(['scope_snapshot' => json_encode(['tampered' => true])]);
        $this->get($this->url())->assertConflict();
    }

    public function test_cancel_preserves_invoice_and_cannot_be_reopened(): void
    {
        $this->requestOrder();
        $this->adminAction('cancel');
        $this->postJson($this->url(true), $this->payload('start'))->assertConflict();
        $this->assertSame('issued', Invoice::sole()->status);
        $this->assertSame('cancelled', BusinessImplementationOrder::sole()->status);
    }

    public function test_audit_failure_rolls_back_order_and_event(): void
    {
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.implementation.request') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->postJson($this->url(), $this->payload())->assertStatus(500);
        $this->assertSame(0, BusinessImplementationOrder::count());
        $this->assertSame(0, BusinessImplementationEvent::count());
    }

    public function test_history_and_scope_are_immutable_and_rollback_preserves_them(): void
    {
        $migration = require base_path('database/migrations/2026_11_06_000006_create_business_implementation_orders.php');
        $migration->down();
        $migration->up();
        $this->requestOrder();
        foreach ([fn () => BusinessImplementationOrder::sole()->update(['scope_snapshot' => []]),
            fn () => BusinessImplementationOrder::sole()->delete(), fn () => BusinessImplementationEvent::sole()->update(['note' => 'changed']),
            fn () => BusinessImplementationEvent::sole()->delete(), fn () => $migration->down()] as $operation) {
            try {
                $operation();
                $this->fail('History must be retained');
            } catch (\LogicException|\RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $this->assertSame(1, BusinessImplementationOrder::count());
    }

    public function test_zero_price_guided_scope_can_proceed_without_fabricating_payment(): void
    {
        $this->quote = BusinessQuoteFixtures::issue(companyId: 2);
        $snapshot = $this->quote->snapshot;
        foreach (['recurring', 'one_time'] as $kind) {
            $snapshot[$kind.'_cents'] = 0;
            $snapshot['taxes'][$kind] = ['taxable_base' => 0, 'tax_amount' => 0, 'line_total' => 0];
        }
        $snapshot['implementation'] = ['name' => 'Guiado', 'hours' => 0, 'scope' => 'Documentación y asistentes.', 'price_cents' => 0];
        // Synthetic issued quote, before acceptance; no operational/history rewrite.
        DB::table('service_commercial_quotes')->where('id', $this->quote->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->quote->refresh();
        $this->asUser(2);
        $actor = User::findOrFail(2);
        $hash = BusinessQuoteAcceptanceService::fingerprint($this->quote);
        app(BusinessQuoteAcceptanceService::class)->accept($actor, $this->quote->public_id, $hash, (string) Str::uuid());
        app(BusinessQuoteCheckoutService::class)->checkout($actor, $this->quote->public_id, $hash, (string) Str::uuid());
        $this->requestOrder();
        $this->adminAction('start');
        $this->adminAction('deliver');
        $this->asUser(2);
        $this->postJson($this->url(), $this->payload('complete'))->assertOk();
        $this->assertSame(0, Payment::count());
        $this->assertSame('completed', BusinessImplementationOrder::sole()->status);
        $this->assertSame(0, BusinessImplementationOrder::sole()->scope_snapshot['implementation']['hours']);
    }

    public function test_checkout_and_acceptance_are_required_before_request(): void
    {
        $this->quote = BusinessQuoteFixtures::issue();
        $this->get($this->url())->assertConflict();
        app(BusinessQuoteAcceptanceService::class)->accept(User::findOrFail(1), $this->quote->public_id,
            BusinessQuoteAcceptanceService::fingerprint($this->quote), (string) Str::uuid());
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(0, BusinessImplementationOrder::count());
    }

    public function test_void_invoice_prevents_request_and_progress_without_erasing_work(): void
    {
        DB::table('invoices')->update(['status' => 'void']);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        DB::table('invoices')->update(['status' => 'issued']);
        $this->requestOrder();
        $this->pay();
        DB::table('invoices')->update(['status' => 'void']);
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload('start'))->assertConflict();
        $this->assertSame('queued', BusinessImplementationOrder::sole()->status);
    }

    public function test_database_rejects_duplicate_order_and_event_version(): void
    {
        $this->requestOrder();
        $order = BusinessImplementationOrder::sole();
        try {
            DB::table('business_implementation_orders')->insert(collect($order->getAttributes())->except('id')->all());
            $this->fail('Duplicate checkout must fail');
        } catch (QueryException $error) {
            $this->assertStringContainsString('UNIQUE', $error->getMessage());
        }
        $event = collect(BusinessImplementationEvent::sole()->getAttributes())->except('id')->all();
        $event['idempotency_key'] = (string) Str::uuid();
        try {
            DB::table('business_implementation_events')->insert($event);
            $this->fail('Duplicate version must fail');
        } catch (QueryException $error) {
            $this->assertStringContainsString('UNIQUE', $error->getMessage());
        }
    }

    public function test_transition_audit_failure_rolls_back_status_and_assignee(): void
    {
        $this->requestOrder();
        $this->pay();
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.implementation.start') {
                throw new \RuntimeException('Synthetic transition audit failure');
            }
        });
        $this->asUser(4);
        $this->postJson($this->url(true), $this->payload('start'))->assertStatus(500);
        $this->assertSame('queued', BusinessImplementationOrder::sole()->status);
        $this->assertNull(BusinessImplementationOrder::sole()->assigned_to);
        $this->assertSame(1, BusinessImplementationEvent::count());
    }
}
