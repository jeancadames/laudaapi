<?php

namespace Tests\Unit\BusinessIdentity;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\BusinessIdentity\CentralMembership;
use App\Services\LaudaErp\ServiceAccessResolver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CentralMembershipTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);
        parent::tearDown();
    }

    public static function accessCases(): array
    {
        return [
            'owner' => ['owner', true, true], 'admin' => ['admin', true, true],
            'member cannot manage' => ['member', true, false], 'member can launch' => ['member', false, true],
            'inactive or missing membership' => [null, false, false],
        ];
    }

    #[DataProvider('accessCases')]
    public function test_requires_active_membership_and_company_role(?string $role, bool $manage, bool $allowed): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 7, 'email_verified_at' => '2026-01-01 00:00:00']);
        $company = new Company(['id' => 8, 'active' => true, 'subscriber_id' => 9]);
        $relation = Mockery::mock(BelongsToMany::class);
        $user->shouldReceive('subscribers')->once()->andReturn($relation);
        $relation->shouldReceive('where')->once()->with('subscribers.id', 9)->andReturnSelf();
        $relation->shouldReceive('where')->once()->with('subscribers.active', true)->andReturnSelf();
        $relation->shouldReceive('wherePivot')->once()->with('active', true)->andReturnSelf();
        $subscriber = new Subscriber;
        $subscriber->setRelation('pivot', new Pivot(['role' => $role]));
        $relation->shouldReceive('first')->once()->andReturn($role === null ? null : $subscriber);
        if (! $allowed) {
            $this->expectException(HttpException::class);
        }
        $this->assertSame($role, (new CentralMembership)->authorize($user, $company, $manage));
    }

    public function test_inactive_company_and_unverified_user_are_denied_before_querying_membership(): void
    {
        $user = new User;
        $user->forceFill(['id' => 7]);
        $company = new Company(['id' => 8, 'active' => false, 'subscriber_id' => 9]);
        $this->assertNull((new CentralMembership)->role($user, $company));
        $company->active = true;
        $this->assertNull((new CentralMembership)->role($user, $company));
    }

    public function test_service_resolver_rejects_foreign_subscriber_before_looking_up_entitlements(): void
    {
        $container = new Container;
        $container->instance(CentralMembership::class, new CentralMembership);
        Container::setInstance($container);
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 7, 'email_verified_at' => '2026-01-01 00:00:00']);
        $company = new Company(['id' => 8, 'active' => true, 'subscriber_id' => 9]);
        $service = new Service(['active' => true, 'slug' => 'bys']);
        $relation = Mockery::mock(BelongsToMany::class);
        $user->shouldReceive('subscribers')->twice()->andReturn($relation);
        $relation->shouldReceive('where')->twice()->with('subscribers.id', 9)->andReturnSelf();
        $relation->shouldReceive('where')->twice()->with('subscribers.active', true)->andReturnSelf();
        $relation->shouldReceive('wherePivot')->twice()->with('active', true)->andReturnSelf();
        $relation->shouldReceive('first')->twice()->andReturnNull();
        $resolver = new ServiceAccessResolver;
        $this->assertFalse($resolver->userCanAccess($user, $company, $service));
        $this->assertFalse($resolver->canAccessAny($user, $company, ['bys']));
    }
}
