<?php

namespace Tests\Feature\AppHub;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Ecosystem\EcosystemHubService;
use App\Services\LaudaErp\ServiceAccessResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialEntitlementLaunchE2ETest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Subscriber $subscriber;
    private Company $company;
    private Service $social;
    private Subscription $subscription;
    private SubscriptionItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->social = Service::query()
            ->where('service_key', 'social')
            ->firstOrFail();

        $this->assertFalse(
            (bool) $this->social->active
        );

        $this->assertFalse(
            (bool) $this->social->billable
        );

        /*
         * Activación exclusivamente transaccional.
         * Al terminar el test vuelve a active=false.
         */
        $this->social->forceFill([
            'active' => true,
        ])->save();

        $this->user = User::query()->create([
            'name' => 'Social Entitlement E2E',
            'email' =>
                'social-entitlement-'
                .Str::uuid()
                .'@example.invalid',
            'password' => bcrypt(
                Str::random(40)
            ),
            'role' => 'subscriber',
            'email_verified_at' => now(),
        ]);

        $this->subscriber =
            Subscriber::query()->create([
                'name' => 'Social E2E Subscriber',
                'slug' =>
                    'social-e2e-'.Str::uuid(),
                'country_code' => 'DO',
                'currency' => 'DOP',
                'timezone' =>
                    'America/Santo_Domingo',
                'active' => true,
                'provider' => 'internal',
                'provider_mode' => 'test',
            ]);

        $pivot = [
            'user_id' => $this->user->id,
            'subscriber_id' =>
                $this->subscriber->id,
            'active' => true,
        ];

        if (
            Schema::hasColumn(
                'subscriber_user',
                'created_at'
            )
        ) {
            $pivot['created_at'] = now();
        }

        if (
            Schema::hasColumn(
                'subscriber_user',
                'updated_at'
            )
        ) {
            $pivot['updated_at'] = now();
        }

        DB::table('subscriber_user')
            ->insert($pivot);

        $this->company =
            Company::query()->create([
                'name' => 'Social E2E Company',
                'slug' =>
                    'social-e2e-company-'
                    .Str::uuid(),
                'currency' => 'DOP',
                'timezone' =>
                    'America/Santo_Domingo',
                'owner_user_id' =>
                    $this->user->id,
                'subscriber_id' =>
                    $this->subscriber->id,
                'active' => true,
            ]);

        $this->subscription =
            Subscription::query()->create([
                'subscriber_id' =>
                    $this->subscriber->id,
                'created_by_user_id' =>
                    $this->user->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'currency' => 'DOP',
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'trial_ends_at' => null,
                'current_period_start' =>
                    now(),
                'current_period_end' =>
                    now()->addMonth(),
                'starts_at' => now(),
                'ends_at' => null,
                'cancelled_at' => null,
                'provider' => 'internal',
                'provider_subscription_id' =>
                    null,
                'meta' => [
                    'fixture' =>
                        'app_hub_social_s4',
                    'non_commercial' => true,
                ],
            ]);

        $this->item =
            SubscriptionItem::query()->create([
                'subscription_id' =>
                    $this->subscription->id,
                'service_id' =>
                    $this->social->id,
                'status' => 'pending',
                'billing_model' => 'flat',
                'quantity' => 1,
                'unit_price' => 0,
                'amount' => 0,
                'currency' => 'DOP',
                'block_size' => null,
                'unit_name' => null,
                'included_units' => null,
                'overage_unit_price' => null,
                'meta' => [
                    'fixture' =>
                        'app_hub_social_s4',
                    'non_commercial' => true,
                ],
            ]);
    }

    public function test_pending_item_does_not_grant_hub_or_launcher_access(): void
    {
        $resolver = app(
            ServiceAccessResolver::class
        );

        $this->assertFalse(
            $resolver->userCanAccess(
                $this->user,
                $this->company,
                $this->social
            )
        );

        $social = $this->socialFromHub();

        $this->assertFalse(
            $social['entitled']
        );

        $this->assertSame(
            'available',
            $social['state']
        );

        $this->assertNull(
            $social['launch_url']
        );

        $response = $this
            ->actingAs($this->user)
            ->get(
                route(
                    'erp.services.open',
                    $this->social
                )
            );

        $response->assertForbidden();
    }

    public function test_active_item_grants_hub_and_launcher_then_cancel_revokes_immediately(): void
    {
        $this->item->forceFill([
            'status' => 'active',
        ])->save();

        $resolver = app(
            ServiceAccessResolver::class
        );

        $this->assertTrue(
            $resolver->userCanAccess(
                $this->user,
                $this->company,
                $this->social
            )
        );

        $social = $this->socialFromHub();

        $this->assertTrue(
            $social['entitled']
        );

        $this->assertTrue(
            $social['integration_ready']
        );

        $this->assertSame(
            'active',
            $social['state']
        );

        $this->assertNotNull(
            $social['launch_url']
        );

        $response = $this
            ->actingAs($this->user)
            ->get(
                route(
                    'erp.services.open',
                    $this->social
                )
            );

        $response->assertRedirect();

        $location = (string) $response
            ->headers
            ->get('Location');

        $this->assertStringStartsWith(
            'https://social.laudaapi.com/launch?token=',
            $location
        );

        $token = urldecode(
            (string) parse_url(
                $location,
                PHP_URL_QUERY
            )
        );

        $this->assertStringContainsString(
            'token=',
            $token
        );

        /*
         * Revocación inmediata:
         * ServiceAccessResolver no cachea
         * entitlement entre requests.
         */
        $this->item->forceFill([
            'status' => 'cancelled',
        ])->save();

        $this->assertFalse(
            $resolver->userCanAccess(
                $this->user,
                $this->company,
                $this->social
            )
        );

        $revoked = $this->socialFromHub();

        $this->assertFalse(
            $revoked['entitled']
        );

        $this->assertSame(
            'available',
            $revoked['state']
        );

        $this->assertNull(
            $revoked['launch_url']
        );

        $this
            ->actingAs($this->user)
            ->get(
                route(
                    'erp.services.open',
                    $this->social
                )
            )
            ->assertForbidden();
    }

    private function socialFromHub(): array
    {
        $groups = app(
            EcosystemHubService::class
        )->groupsFor(
            $this->user,
            $this->company
        );

        foreach ($groups as $group) {
            foreach (
                $group['solutions'] as
                $solution
            ) {
                if (
                    $solution['key']
                    === 'social'
                ) {
                    return $solution;
                }
            }
        }

        $this->fail(
            'Social no apareció en EcosystemHubService.'
        );
    }
}
