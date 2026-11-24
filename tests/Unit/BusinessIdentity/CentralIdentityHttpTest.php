<?php

namespace Tests\Unit\BusinessIdentity;

use App\Models\Company;
use App\Models\User;
use App\Services\BusinessIdentity\IdentityToken;
use App\Services\Ecosystem\EcosystemHubService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\BusinessIdentityApplication;

final class CentralIdentityHttpTest extends TestCase
{
    private string $runtime;

    private IdentityToken $codec;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business identity tests only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central', [
            'enabled' => true, 'issuer' => 'https://hub.example',
            'peers' => ['bys' => ['url' => 'https://bys.example', 'secret' => str_repeat('business-test-', 4)]],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessIdentityApplication::centralSchema();
        $this->withoutVite();
        $this->codec = new IdentityToken(config('business_identity.peers.bys.secret'));
        DB::table('users')->insert(['id' => 1, 'name' => 'Test owner', 'email' => 'owner@business.invalid', 'password' => Hash::make('synthetic-password'), 'email_verified_at' => now(), 'role' => 'subscriber']);
        DB::table('subscribers')->insert(['id' => 1, 'name' => 'Synthetic subscriber', 'slug' => 'identity-test']);
        DB::table('subscriber_user')->insert(['subscriber_id' => 1, 'user_id' => 1, 'role' => 'owner', 'active' => true]);
        DB::table('companies')->insert(['id' => 1, 'subscriber_id' => 1, 'name' => 'Test company', 'slug' => 'identity-test']);
        DB::table('services')->insert(['id' => 1, 'title' => 'BYS', 'slug' => 'bys', 'service_key' => 'bys']);
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'business-csrf-test'])->withHeader('X-CSRF-TOKEN', 'business-csrf-test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function start(bool $restart = false): array
    {
        return $this->postJson('/business/identity/companies/1/bys/requests', ['link_company' => true, 'restart' => $restart])->assertOk()->json();
    }

    private function proof(array $request, array $changes = []): string
    {
        $claims = $this->codec->decode($request['request_token'], 'https://hub.example', 'bys', 'link-request', now()->timestamp);
        $data = [...$claims['data'], 'local_company_id' => 21, 'local_user_id' => 31, 'consent' => $claims['nonce'], 'local_company_name' => 'BYS company', 'local_user_name' => 'BYS person', 'local_user_email' => 'bys@business.invalid', ...$changes];

        return $this->codec->encode('bys', 'https://hub.example', 'link-proof', $data, now()->timestamp);
    }

    private function confirm(string $proof): array
    {
        return $this->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertOk()->json();
    }

    private function ready(array $activation): string
    {
        $data = $this->codec->decode($activation['activation_token'], 'https://hub.example', 'bys', 'link-activation', now()->timestamp)['data'];

        return $this->codec->encode('bys', 'https://hub.example', 'link-ready', $data, now()->timestamp);
    }

    private function grantCommercialAccess(): void
    {
        DB::table('subscriptions')->insert(['id' => 1, 'subscriber_id' => 1, 'status' => 'active']);
        DB::table('subscription_items')->insert(['subscription_id' => 1, 'service_id' => 1, 'status' => 'active']);
    }

    public static function businessServices(): array
    {
        return array_combine(IdentityToken::SERVICES, array_map(fn ($service) => [$service], IdentityToken::SERVICES));
    }

    private function configureLegacyLaunch(string $service): void
    {
        config()->set([
            'lauda_sso.secret' => str_repeat('legacy-synthetic-', 4),
            'lauda_sso.issuer' => 'https://hub.example',
            'ecosystem_hub.groups' => ['business' => ['solutions' => [$service => [
                'service_key' => $service, 'launchable' => true, 'integration' => 'external',
                'target_url' => "https://$service.example", 'target_launch_path' => '/launch',
            ]]]],
        ]);
        DB::table('services')->where('id', 1)->update([
            'slug' => $service, 'service_key' => $service, 'launch_mode' => 'external',
            'external_url' => "https://$service.example", 'launch_path' => '/launch', 'integration_mode' => 'sso',
        ]);
        $this->grantCommercialAccess();
    }

    private function disableBusinessWithoutIdentityTables(): void
    {
        config()->set('business_identity', ['enabled' => false]);
        foreach (['2026_11_06_000003_add_business_identity_handoffs', '2026_11_06_000002_create_business_identity_registry'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->down();
        }
    }

    #[DataProvider('businessServices')]
    public function test_disabled_business_preserves_legacy_launch_without_identity_tables(string $service): void
    {
        $this->configureLegacyLaunch($service);
        $this->disableBusinessWithoutIdentityTables();

        $solution = app(EcosystemHubService::class)->groupsFor(User::findOrFail(1), Company::findOrFail(1))[0]['solutions'][0];
        $this->assertTrue($solution['integration_ready']);
        $this->assertSame("/erp/services/open/$service", $solution['launch_url']);
        $response = $this->get($solution['launch_url'])->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith("https://$service.example/launch?token=", $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $cipher = new Encrypter(hash('sha256', config('lauda_sso.secret'), true), 'AES-256-CBC');
        $claims = json_decode($cipher->decryptString($query['token']), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $claims['v']);
        $this->assertSame($service, $claims['aud']);
        $this->assertSame('https://hub.example', $claims['iss']);
        $this->assertSame(1, $claims['company']['id']);
        $this->assertSame(1, $claims['user']['id']);
    }

    public function test_disabled_identity_page_and_request_do_not_require_identity_tables(): void
    {
        $this->disableBusinessWithoutIdentityTables();
        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page
            ->where('identity.enabled', false)->has('identity.companies', 0));
        $this->postJson('/business/identity/companies/1/bys/requests', ['link_company' => true])->assertStatus(503);
    }

    public function test_legacy_launch_still_enforces_commercial_access_and_membership(): void
    {
        $this->configureLegacyLaunch('bys');
        config()->set('business_identity.enabled', false);
        DB::table('subscription_items')->update(['status' => 'cancelled']);
        $this->getJson('/erp/services/open/bys')->assertForbidden();
        DB::table('subscription_items')->update(['status' => 'active']);
        DB::table('subscriber_user')->update(['role' => 'member']);
        $this->getJson('/erp/services/open/bys')->assertRedirect();
        DB::table('subscriber_user')->update(['active' => false]);
        $this->getJson('/erp/services/open/bys')->assertForbidden();
    }

    public function test_enabled_business_never_falls_back_to_legacy_when_configuration_or_link_is_missing(): void
    {
        $this->configureLegacyLaunch('bys');
        config()->set('business_identity.peers.bys.secret', '');
        $this->getJson('/erp/services/open/bys')->assertStatus(503)->assertHeaderMissing('Location');
        config()->set('business_identity.peers.bys.secret', str_repeat('business-test-', 4));
        $this->getJson('/erp/services/open/bys')->assertConflict()->assertHeaderMissing('Location');
        $this->assertSame(0, DB::table('business_identity_links')->count());
    }

    public function test_start_confirm_activation_and_ready_are_recoverable_and_launch_requires_ready(): void
    {
        $request = $this->start();
        $this->assertSame($request['request_token'], $this->start()['request_token']);
        $this->assertStringStartsWith('https://bys.example/business/identity#', $request['continue_url']);
        $proof = $this->proof($request);
        $this->postJson('/business/identity/bys/preview', ['proof_token' => $proof])->assertOk()->assertJsonPath('local_company_name', 'BYS company');
        $this->assertFalse((bool) DB::table('business_identity_links')->first()->active);
        $activation = $this->confirm($proof);
        $this->assertSame($activation, $this->confirm($proof));
        $this->assertSame($activation['activation_token'], $this->start()['activation_token']);
        $this->grantCommercialAccess();
        $response = $this->getJson('/business/identity/companies/1/services/bys/launch');
        $this->assertSame(409, $response->status(), $response->json('message') ?? '');
        $ready = $this->ready($activation);
        $this->postJson('/business/identity/bys/ready', ['ready_token' => $ready])->assertOk();
        $this->postJson('/business/identity/bys/ready', ['ready_token' => $ready])->assertOk();
        $response = $this->get('/business/identity/companies/1/services/bys/launch')->assertRedirect();
        $this->assertStringStartsWith('https://bys.example/business/identity#stage=launch', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('business_identity_events')->where('event', 'consented')->count());
        $this->assertSame(1, User::count());
    }

    public function test_restart_and_cancel_reject_stale_proofs_before_permanent_binding(): void
    {
        $request = $this->start();
        $old = $this->proof($request);
        $new = $this->start(true);
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $old])->assertConflict();
        $claims = $this->codec->decode($new['request_token'], 'https://hub.example', 'bys', 'link-request', now()->timestamp);
        $this->deleteJson('/business/identity/requests/'.$claims['nonce'])->assertOk();
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $this->proof($new)])->assertConflict();
        $this->assertSame(0, DB::table('business_identity_accounts')->count());
        $this->assertNull(DB::table('business_identity_links')->first()->local_company_id);
        $this->confirm($this->proof($this->start(true), ['local_company_id' => 22]));
        $this->assertSame(22, DB::table('business_identity_links')->first()->local_company_id);
    }

    public function test_revocation_cannot_be_undone_by_completed_proof_or_ready_replay(): void
    {
        $proof = $this->proof($this->start());
        $activation = $this->confirm($proof);
        $this->deleteJson('/business/identity/companies/1/bys', ['whole_company' => true])->assertOk();
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertConflict();
        $this->postJson('/business/identity/bys/ready', ['ready_token' => $this->ready($activation)])->assertConflict();
        $this->assertFalse((bool) DB::table('business_identity_links')->first()->active);
    }

    public function test_membership_removed_between_request_and_confirm_is_denied(): void
    {
        $proof = $this->proof($this->start());
        DB::table('subscriber_user')->update(['active' => false]);
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertForbidden();
        $this->assertSame(0, DB::table('business_identity_accounts')->count());
    }

    public function test_company_binding_and_billing_are_independent_authorizations(): void
    {
        DB::table('subscriber_user')->update(['role' => 'member']);
        $this->postJson('/business/identity/companies/1/bys/requests', ['link_company' => true])->assertForbidden();
        DB::table('subscriber_user')->update(['role' => 'owner']);
        $activation = $this->confirm($this->proof($this->start()));
        $this->postJson('/business/identity/bys/ready', ['ready_token' => $this->ready($activation)])->assertOk();
        $this->getJson('/business/identity/companies/1/services/bys/launch')->assertForbidden();
    }

    public function test_confirmation_requires_the_original_actor_and_exact_proof(): void
    {
        $request = $this->start();
        $proof = $this->proof($request);
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $this->proof($request, ['central_user_id' => 2])])->assertForbidden();
        $this->confirm($proof);
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $this->proof($request, ['local_company_id' => 22])])->assertConflict();
    }

    public function test_real_csrf_and_validation_protect_requests_and_do_not_flash_tokens(): void
    {
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson('/business/identity/companies/1/bys/requests', ['link_company' => true])->assertStatus(419)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->withHeader('X-CSRF-TOKEN', 'business-csrf-test')->post('/business/identity/bys/confirm', ['proof_token' => ['opaque']])->assertUnprocessable();
        $this->assertFalse(session()->has('_old_input'));
        $this->assertSame(0, DB::table('business_identity_links')->count());
    }

    public function test_page_is_no_store_contains_only_authorized_companies_and_no_tokens(): void
    {
        $this->start();
        DB::table('subscribers')->insert(['id' => 2, 'name' => 'Foreign subscriber', 'slug' => 'foreign']);
        DB::table('companies')->insert(['id' => 2, 'subscriber_id' => 2, 'name' => 'Foreign company', 'slug' => 'foreign']);
        $response = $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')->has('identity.companies', 1)->missing('identity.request_token'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        auth()->logout();
        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->where('identity.account', null)->has('identity.companies', 0));
    }

    public function test_client_page_shares_only_the_portal_sidebar_context(): void
    {
        (require base_path('database/migrations/2026_01_24_123011_company_tax_profiles_table.php'))->up();
        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')
            ->where('auth.user.tenant_access.mode', 'subscriber.admin')->where('auth.user.role', 'subscriber')
            ->where('activeCompany.name', 'Test company')->has('activeCompany.tax_id')->where('businessIdentityEnabled', true)
            ->missing('activeCompany.id')->missing('activeCompany.slug')->missing('activeCompany.ws_subdomain')
            ->missing('auth.user.tenant_access.can_manage_billing')
            ->missing('auth.user.password')->missing('identity.request_token'));
    }

    public function test_sidebar_context_keeps_the_active_lauda360_capabilities(): void
    {
        (require base_path('database/migrations/2026_01_24_123011_company_tax_profiles_table.php'))->up();
        (require base_path('database/migrations/2026_08_30_190000_create_transformation_capability_activations_table.php'))->up();
        Schema::withoutForeignKeyConstraints(fn () => DB::table('transformation_capability_activations')->insert([
            'company_id' => 1, 'diagnosis_assessment_id' => 1, 'capability_key' => 'branding_identity',
            'source_type' => 'synthetic', 'source_id' => 1, 'status' => 'activated',
        ]));

        // «Branding e identidad» no desaparece del menú al entrar en Conexiones.
        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')
            ->where('lauda360.company_id', 1)->where('lauda360.active_capabilities', ['branding_identity']));
    }

    public function test_sidebar_context_is_not_shared_with_an_unverified_account(): void
    {
        (require base_path('database/migrations/2026_01_24_123011_company_tax_profiles_table.php'))->up();
        DB::table('users')->where('id', 1)->update(['email_verified_at' => null]);
        $this->actingAs(User::findOrFail(1));

        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')
            ->missing('activeCompany')->missing('auth.user.tenant_access')->missing('lauda360')->has('identity.companies', 0));
    }

    public function test_sidebar_context_is_not_shared_without_an_active_membership(): void
    {
        (require base_path('database/migrations/2026_01_24_123011_company_tax_profiles_table.php'))->up();
        // La empresa sigue siendo suya (owner_user_id), pero la membresía ya no está activa.
        DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]);
        DB::table('companies')->where('id', 1)->update(['owner_user_id' => 1]);
        $this->actingAs(User::findOrFail(1));

        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')
            ->missing('activeCompany')->missing('auth.user.tenant_access')->missing('lauda360')->has('identity.companies', 0));
    }

    public function test_client_page_falls_back_to_its_own_frame_when_sidebar_context_fails(): void
    {
        // Sin la tabla de perfiles fiscales el contexto del menú no se puede leer: la página no se cae.
        $this->get('/business/identity')->assertOk()->assertInertia(fn ($page) => $page->component('Business/Identity')
            ->missing('auth.user.tenant_access')->missing('activeCompany')->has('identity.companies', 1));
    }

    public function test_login_returns_to_the_fixed_identity_page_and_never_to_an_arbitrary_origin(): void
    {
        auth()->logout();
        $this->get('/business/identity')->assertOk();
        $this->post('/login', ['email' => 'owner@business.invalid', 'password' => 'synthetic-password'])->assertRedirect(route('business.identity.index'));
    }

    public function test_pending_identity_never_skips_the_local_two_factor_challenge(): void
    {
        auth()->logout();
        DB::table('users')->where('id', 1)->update([
            'two_factor_secret' => Crypt::encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->get('/business/identity')->assertOk();
        $this->post('/login', ['email' => 'owner@business.invalid', 'password' => 'synthetic-password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->postJson('/business/identity/companies/1/bys/requests', ['link_company' => true])->assertUnauthorized();
        $code = (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('business.identity.index'));
        $this->assertAuthenticatedAs(User::findOrFail(1));
    }
}
