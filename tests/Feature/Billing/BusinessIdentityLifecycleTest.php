<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\BusinessIdentity\CentralIdentityService;
use App\Services\BusinessIdentity\IdentityToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** MySQL DEV only, after the identity migration. Remote proofs are signed fixtures. */
final class BusinessIdentityLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        if (getenv('LAUDA_BUSINESS_MYSQL') !== '1') {
            return parent::createApplication();
        }
        $app = require dirname(__DIR__, 2).'/Fixtures/BusinessIdentity/bootstrap.php';
        $app->instance('env', 'testing');
        $app['config']->set('app.env', 'testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertTrue(app()->environment(['local', 'testing']));
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        $this->withoutVite();
        config()->set(['business_identity.enabled' => true, 'business_identity.issuer' => 'https://hub.example',
            'business_identity.peers.bys' => ['url' => 'https://bys.example', 'secret' => str_repeat('identity-test-', 4)]]);
    }

    private function fixture(string $role = 'owner'): array
    {
        $user = User::factory()->create(['role' => 'subscriber']);
        $subscriber = Subscriber::create(['name' => 'Identity QA', 'slug' => 'identity-qa-'.Str::uuid(), 'active' => true, 'currency' => 'DOP']);
        $subscriber->users()->attach($user->id, ['role' => $role, 'active' => true]);
        $company = Company::withoutEvents(fn () => Company::create(['name' => 'Identity QA', 'slug' => 'identity-qa-'.Str::uuid(),
            'subscriber_id' => $subscriber->id, 'active' => true, 'currency' => 'DOP']));
        $service = Service::firstOrCreate(['service_key' => 'bys'], ['slug' => 'identity-qa-'.Str::uuid(), 'title' => 'BYS QA', 'currency' => 'DOP']);
        $service->update(['active' => true]);

        return [$user, $subscriber, $company];
    }

    private function proof(User $user, Company $company): string
    {
        $request = app(CentralIdentityService::class)->start($user, $company, 'bys', true, true);
        $codec = new IdentityToken(config('business_identity.peers.bys.secret'));
        $claims = $codec->decode($request['request_token'], 'https://hub.example', 'bys', 'link-request', now()->timestamp);
        $data = [...$claims['data'], 'local_company_id' => 900000 + $company->id, 'local_user_id' => 900000 + $user->id, 'consent' => $claims['nonce']];

        return $codec->encode('bys', 'https://hub.example', 'link-proof', $data, now()->timestamp);
    }

    public function test_confirmation_is_idempotent_and_does_not_provision_or_bill(): void
    {
        [$user, , $company] = $this->fixture();
        $before = [User::count(), Company::count(), DB::table('subscriptions')->count(), DB::table('invoices')->count()];
        $proof = $this->proof($user, $company);
        $identity = app(CentralIdentityService::class);
        $one = $identity->complete($user, 'bys', $proof);
        $two = $identity->complete($user, 'bys', $proof);
        $this->assertSame($one, $two);
        $this->assertSame($before, [User::count(), Company::count(), DB::table('subscriptions')->count(), DB::table('invoices')->count()]);
        $this->assertSame(1, DB::table('business_identity_members')->where('central_user_id', $user->id)->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_revoked_membership_between_request_and_confirmation_is_rejected(): void
    {
        [$user, $subscriber, $company] = $this->fixture();
        $proof = $this->proof($user, $company);
        $subscriber->users()->updateExistingPivot($user->id, ['active' => false]);
        $this->actingAs($user)->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertForbidden();
        $this->assertDatabaseMissing('business_identity_links', ['central_company_id' => $company->id, 'active' => true]);
    }

    public function test_different_actor_and_member_without_management_permission_cannot_link_a_company(): void
    {
        [$user, , $company] = $this->fixture();
        $proof = $this->proof($user, $company);
        [$other] = $this->fixture('member');
        $this->actingAs($other)->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertForbidden();
        $this->postJson('/business/identity/companies/'.$company->id.'/bys/requests', ['link_company' => true])->assertForbidden();
    }

    public function test_revocation_cancels_pending_requests_and_cannot_be_undone_by_replay(): void
    {
        [$user, , $company] = $this->fixture();
        $proof = $this->proof($user, $company);
        $identity = app(CentralIdentityService::class);
        $identity->complete($user, 'bys', $proof);
        $pending = $this->proof($user, $company);
        $identity->revoke($user, $company, 'bys', true);
        $this->actingAs($user)->postJson('/business/identity/bys/confirm', ['proof_token' => $proof])->assertConflict();
        $this->postJson('/business/identity/bys/confirm', ['proof_token' => $pending])->assertConflict();
    }

    public function test_remote_origin_configuration_and_disabled_rollout_fail_closed(): void
    {
        [$user, , $company] = $this->fixture();
        config()->set('business_identity.peers.bys.url', 'https://user:password@bys.example');
        $this->actingAs($user)->postJson('/business/identity/companies/'.$company->id.'/bys/requests', ['link_company' => true])->assertStatus(503);
        config()->set('business_identity.enabled', false);
        $this->postJson('/business/identity/companies/'.$company->id.'/bys/requests', ['link_company' => true])->assertStatus(503);
    }

    public function test_explicit_company_launcher_does_not_grant_commercial_access_from_linking_alone(): void
    {
        [$user, , $company] = $this->fixture();
        app(CentralIdentityService::class)->complete($user, 'bys', $this->proof($user, $company));
        $service = Service::where('service_key', 'bys')->firstOrFail();
        $url = route('business.identity.launch', ['company' => $company->id, 'service' => $service->slug], false);
        $this->actingAs($user)->getJson($url)->assertForbidden();
        [$other] = $this->fixture();
        $this->actingAs($other)->getJson($url)->assertForbidden();
    }
}
