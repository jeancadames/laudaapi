<?php

namespace Tests\Support;

use App\Models\BusinessContract;
use App\Models\BusinessImplementationOrder;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\BusinessCommercial\CommercialToken;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

abstract class BusinessCommercialTestCase extends TestCase
{
    protected string $runtime;

    protected ServiceCommercialQuote $quote;

    protected BusinessContract $contract;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business commercial tests only');

        return BusinessIdentityApplication::create(dirname(__DIR__, 2), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessContractFixtures::schema();
        (require base_path('database/migrations/2026_11_07_000010_create_business_document_usage.php'))->up();
        (require base_path('database/migrations/2026_11_07_000012_create_business_usage_cancellations.php'))->up();
        (require base_path('database/migrations/2026_11_07_000014_create_business_fiscal_handoffs.php'))->up();
        Mail::fake();
        Notification::fake();
        $this->quote = $this->seedQuote();
        config()->set('business_commercial_bridge.enabled', true);
        $this->contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $this->quote->public_id, [
            'confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($this->quote), 'idempotency_key' => (string) Str::uuid(),
            'implementation_version' => BusinessImplementationOrder::sole()->lock_version,
            'technical_evidence' => array_fill_keys(array_keys($this->quote->snapshot['products']), 'Preparación sintética verificada.'),
        ]);
    }

    protected function seedQuote(): ServiceCommercialQuote
    {
        return BusinessContractFixtures::seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    protected function data(string $service, string $action, array $data = []): array
    {
        $link = DB::table('business_identity_links')->where('service', $service)->sole();

        return ['action' => $action, 'link_id' => $link->id, 'local_company_id' => 501,
            'central_company_id' => 1, 'central_subscriber_id' => 1, ...$data];
    }

    protected function codec(string $service): CommercialToken
    {
        return new CommercialToken(config('business_identity.peers.'.$service.'.secret'));
    }

    protected function exchange(string $service, string $action, array $data = [], int $status = 200): array
    {
        $codec = $this->codec($service);
        $token = $codec->encode($service, config('business_identity.issuer'), 'request', $this->data($service, $action, $data), now()->timestamp);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/business/commercial/v1/'.$service)
            ->assertStatus($status)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $reply = $codec->decode($response->json('token'), config('business_identity.issuer'), $service, 'response', now()->timestamp);
        $this->assertSame(hash('sha256', $token), $reply['request_hash']);
        $this->assertSame($status, $reply['status']);

        return $reply['result'] ?? $reply;
    }

    protected function operation(string $reference = 'sale:1', array $extra = []): array
    {
        return ['operation_key' => (string) Str::uuid(), 'document' => ['source_reference' => $reference, ...$extra]];
    }
}
