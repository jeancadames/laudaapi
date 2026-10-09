<?php

use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationCommercialProjection;
use Illuminate\Support\Facades\DB;

function r116AssertMySql(): void
{
    $connection = DB::connection();

    expect(app()->environment())->toBe('testing');
    expect($connection->getDriverName())->toBe('mysql');
    expect($connection->getDatabaseName())->toBe('laudaapi_dev');
}

function r116Projection(): TransformationImplementationCommercialProjection
{
    return app(TransformationImplementationCommercialProjection::class);
}

function r116Request(): TransformationImplementationRequest
{
    // Read an existing relationally valid reference.
    // Never update the source Request.
    $base = DB::table(
        'transformation_implementation_requests as r'
    )->join(
        'transformation_implementation_definitions as d',
        'd.transformation_implementation_request_id',
        '=',
        'r.id'
    )->where(
        'r.capability_key',
        'data_transformation_bi'
    )->select(
        'r.id'
    )->orderByDesc(
        'r.id'
    )->first();

    expect($base)->not->toBeNull();

    $source = DB::table(
        'transformation_implementation_requests'
    )->where(
        'id',
        $base->id
    )->first();

    expect($source)->not->toBeNull();

    $row = (array) $source;
    unset($row['id']);

    $maximumAttempt = DB::table(
        'transformation_implementation_requests'
    )->where(
        'company_id',
        $source->company_id
    )->where(
        'transformation_implementation_plan_id',
        $source->transformation_implementation_plan_id
    )->where(
        'transformation_implementation_phase_capability_id',
        $source->transformation_implementation_phase_capability_id
    )->max('attempt');

    expect((int) $maximumAttempt)->toBeLessThan(65535);

    $row['attempt'] = (int) $maximumAttempt + 1;
    $row['status'] = 'ready_for_commercial';
    $row['ready_for_commercial_at'] = now();
    $row['created_at'] = now();
    $row['updated_at'] = now();

    $id = DB::table(
        'transformation_implementation_requests'
    )->insertGetId($row);

    return TransformationImplementationRequest::findOrFail($id);
}

function r116DefinitionId(): int
{
    $id = DB::table(
        'transformation_implementation_definitions'
    )->where(
        'capability_key',
        'data_transformation_bi'
    )->orderByDesc(
        'id'
    )->value('id');

    expect($id)->not->toBeNull();

    return (int) $id;
}

function r116Engagement(
    TransformationImplementationRequest $request,
    string $status,
    int $version = 1
): int {
    return DB::table(
        'transformation_implementation_commercial_engagements'
    )->insertGetId([
        'transformation_implementation_request_id' => $request->id,
        'transformation_implementation_definition_id'
            => r116DefinitionId(),
        'company_id' => $request->company_id,
        'transformation_implementation_phase_capability_id'
            => $request->transformation_implementation_phase_capability_id,
        'capability_key' => 'data_transformation_bi',
        'version' => $version,
        'status' => $status,
        'currency' => 'DOP',
        'price_amount' => 15000,
        'duration_days' => 30,
        'scope_snapshot' => json_encode([
            'purpose' => 'R116 QA',
        ]),
        'deliverables_snapshot' => json_encode([
            ['name' => 'Fixture QA'],
        ]),
        'commercial_terms_snapshot' => json_encode([
            'terms_text' => 'R116 QA',
        ]),
        'internal_notes' => 'R116_SECRET_INTERNAL_NOTES',
        'presented_at' => now(),
        'accepted_at' => $status === 'accepted' ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function r116Authorization(
    TransformationImplementationRequest $request,
    int $engagementId,
    bool $revoked = false
): void {
    $definitionId = DB::table(
        'transformation_implementation_commercial_engagements'
    )->where(
        'id',
        $engagementId
    )->value('transformation_implementation_definition_id');

    DB::table(
        'transformation_implementation_authorizations'
    )->insert([
        'transformation_implementation_commercial_engagement_id'
            => $engagementId,
        'transformation_implementation_request_id' => $request->id,
        'transformation_implementation_definition_id' => $definitionId,
        'company_id' => $request->company_id,
        'transformation_implementation_phase_capability_id'
            => $request->transformation_implementation_phase_capability_id,
        'capability_key' => 'data_transformation_bi',
        'status' => $revoked ? 'revoked' : 'authorized',
        'authorization_snapshot' => json_encode([
            'r116_qa' => true,
        ]),
        'authorized_at' => now(),
        'revoked_at' => $revoked ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    // Guard BEFORE any fixture write.
    r116AssertMySql();

    DB::beginTransaction();

    $this->r116InitialTransactionLevel =
        DB::transactionLevel();
});

afterEach(function () {
    // Always roll back the temporary fixture transaction.
    if (
        isset($this->r116InitialTransactionLevel)
        && DB::transactionLevel() >= $this->r116InitialTransactionLevel
    ) {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('R116 tenant never receives a commercial draft', function () {
    $request = r116Request();
    r116Engagement($request, 'draft');

    $tenant = r116Projection()->forTenant(
        (int) $request->company_id
    );

    expect($tenant)->toBeNull();

    $admin = r116Projection()->forAdmin($request);

    expect($admin['engagement']['status'])->toBe('draft')
        ->and($admin['actions']['can_present'])->toBeTrue();
});

test('R116 presented proposal is visible without internal notes', function () {
    $request = r116Request();
    r116Engagement($request, 'presented');

    $tenant = r116Projection()->forTenant(
        (int) $request->company_id
    );

    expect($tenant)->not->toBeNull()
        ->and($tenant['engagement']['status'])->toBe('presented')
        ->and($tenant['actions']['can_accept'])->toBeTrue();

    $serialized = json_encode($tenant);

    expect($serialized)->not->toContain(
        'R116_SECRET_INTERNAL_NOTES'
    )->not->toContain('internal_notes');
});

test('R116 accepted proposal has no second acceptance action', function () {
    $request = r116Request();
    r116Engagement($request, 'accepted');

    $tenant = r116Projection()->forTenant(
        (int) $request->company_id
    );

    expect($tenant['engagement']['status'])->toBe('accepted')
        ->and($tenant['actions']['can_accept'])->toBeFalse()
        ->and($tenant['actions']['accept_endpoint'])->toBeNull();
});

test('R116 authorization stays separate from acceptance', function () {
    $request = r116Request();
    $engagement = r116Engagement($request, 'accepted');

    $before = r116Projection()->forAdmin($request);

    expect($before['authorization'])->toBeNull()
        ->and($before['actions']['can_authorize'])->toBeTrue();

    r116Authorization($request, $engagement);

    $tenant = r116Projection()->forTenant(
        (int) $request->company_id
    );
    $admin = r116Projection()->forAdmin($request);

    expect($tenant['authorization']['active'])->toBeTrue()
        ->and($tenant['authorization']['status'])->toBe('authorized')
        ->and($admin['actions']['can_authorize'])->toBeFalse();
});

test('R116 revoked authorization cannot be reauthorized', function () {
    $request = r116Request();
    $engagement = r116Engagement($request, 'accepted');

    r116Authorization($request, $engagement, true);

    $tenant = r116Projection()->forTenant(
        (int) $request->company_id
    );
    $admin = r116Projection()->forAdmin($request);

    expect($tenant['authorization']['status'])->toBe('revoked')
        ->and($tenant['authorization']['active'])->toBeFalse()
        ->and($admin['actions']['can_authorize'])->toBeFalse()
        ->and($admin['actions']['authorize_endpoint'])->toBeNull();
});

test('R116 latest version controls the commercial projection', function () {
    $request = r116Request();

    $old = r116Engagement($request, 'accepted', 1);
    r116Authorization($request, $old);

    $latest = r116Engagement($request, 'draft', 2);

    $admin = r116Projection()->forAdmin($request);

    expect($admin['engagement']['id'])->toBe($latest)
        ->and($admin['engagement']['version'])->toBe(2)
        ->and($admin['authorization'])->toBeNull()
        ->and($admin['actions']['can_present'])->toBeTrue();

    expect(r116Projection()->forTenant(
        (int) $request->company_id
    ))->toBeNull();
});

test('R116 company isolation prevents cross-tenant visibility', function () {
    $request = r116Request();
    r116Engagement($request, 'presented');

    $anotherCompanyId = (int) (
        DB::table('companies')
            ->where('id', '!=', $request->company_id)
            ->orderBy('id')
            ->value('id') ?? 0
    );

    if ($anotherCompanyId > 0) {
        $other = r116Projection()->forTenant($anotherCompanyId);

        if ($other !== null) {
            expect(
                $other['engagement']['id']
            )->not->toBe(
                r116Projection()->forTenant(
                    (int) $request->company_id
                )['engagement']['id']
            );
        }
    }

    $own = r116Projection()->forTenant(
        (int) $request->company_id
    );

    expect($own)->not->toBeNull();
});

test('R116 projections do not mutate commercial records', function () {
    $request = r116Request();
    $engagementId = r116Engagement($request, 'presented');

    $before = DB::table(
        'transformation_implementation_commercial_engagements'
    )->where('id', $engagementId)->first();

    r116Projection()->forAdmin($request);
    r116Projection()->forTenant((int) $request->company_id);

    $after = DB::table(
        'transformation_implementation_commercial_engagements'
    )->where('id', $engagementId)->first();

    expect($after)->toEqual($before);
});


// R116_E4G_LATEST_ATTEMPT_REGRESSION
test('R116 newer request prevents stale commercial offer', function () {
    r116AssertMySql();

    $oldRequest = r116Request();

    $oldEngagement = r116Engagement(
        $oldRequest,
        'presented'
    );

    $row = (array) DB::table(
        'transformation_implementation_requests'
    )->where('id', $oldRequest->id)->first();

    unset($row['id']);

    $row['attempt'] = (int) $oldRequest->attempt + 1;
    $row['status'] = 'requested';
    $row['ready_for_commercial_at'] = null;
    $row['requested_at'] = now();
    $row['created_at'] = now();
    $row['updated_at'] = now();

    $newId = DB::table(
        'transformation_implementation_requests'
    )->insertGetId($row);

    expect($newId)->toBeGreaterThan((int) $oldRequest->id);

    $actual = r116Projection()->forTenant(
        (int) $oldRequest->company_id
    );

    // The new Request has not reached the commercial gate.
    // The old offer must not reappear.
    expect($actual)->toBeNull();

    // The historical engagement remains intact.
    expect(
        DB::table(
            'transformation_implementation_commercial_engagements'
        )->where('id', $oldEngagement)->value('status')
    )->toBe('presented');
});

// R116_E4J_STALE_HTTP_ACCEPTANCE
test('R116 HTTP rejects acceptance from an older request', function () {
    r116AssertMySql();

    // Existing reference is cloned; original business data is not changed.
    $oldRequest = r116Request();
    $engagementId = r116Engagement($oldRequest, 'presented');

    $company = DB::table('companies')
        ->where('id', $oldRequest->company_id)
        ->first();

    expect($company)->not->toBeNull();
    expect((int) $company->subscriber_id)->toBeGreaterThan(0);

    $subscriberId = (int) $company->subscriber_id;

    // New user and membership exist only inside this test transaction.
    $user = \App\Models\User::factory()->create([
        'name' => 'R116 HTTP Tenant QA',
        'email' => 'r116-http-'
            .\Illuminate\Support\Str::lower(
                \Illuminate\Support\Str::random(20)
            )
            .'@example.test',
        'role' => 'subscriber',
        'email_verified_at' => now(),
    ]);

    DB::table('subscriber_user')->insert([
        'subscriber_id' => $subscriberId,
        'user_id' => $user->id,
        'role' => 'owner',
        'active' => 1,
    ]);

    $userContext = [];

    if (\Illuminate\Support\Facades\Schema::hasColumn(
        'users',
        'subscriber_id'
    )) {
        $userContext['subscriber_id'] = $subscriberId;
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn(
        'users',
        'company_id'
    )) {
        $userContext['company_id'] = (int) $company->id;
    }

    if ($userContext !== []) {
        DB::table('users')
            ->where('id', $user->id)
            ->update($userContext);
    }

    $user->refresh();

    // Verify the real production resolvers before issuing the HTTP call.
    $resolvedSubscriber = app(
        \App\Services\Subscribers\SubscriberResolver::class
    )->resolve($user);

    expect((int) $resolvedSubscriber)->toBe($subscriberId);

    $tenantAccess = app(
        \App\Services\Subscribers\TenantAccessService::class
    )->resolve($user, $subscriberId);

    expect($tenantAccess['mode'] ?? null)
        ->toBe(
            \App\Services\Subscribers\TenantAccessService::SUBSCRIBER_ADMIN
        )
        ->and($tenantAccess['tenant_admin'] ?? false)
        ->toBeTrue();

    $resolvedCompany = app(
        \App\Services\Subscribers\CompanyContextResolver::class
    )->resolve($user, $subscriberId);

    expect($resolvedCompany)->not->toBeNull()
        ->and((int) $resolvedCompany->id)
        ->toBe((int) $oldRequest->company_id);

    // A more recent request is not yet commercially ready.
    $row = (array) DB::table(
        'transformation_implementation_requests'
    )->where('id', $oldRequest->id)->first();

    unset($row['id']);

    $row['attempt'] = (int) $oldRequest->attempt + 1;
    $row['status'] = 'requested';
    $row['ready_for_commercial_at'] = null;
    $row['requested_at'] = now();
    $row['created_at'] = now();
    $row['updated_at'] = now();

    $newRequestId = DB::table(
        'transformation_implementation_requests'
    )->insertGetId($row);

    expect($newRequestId)->toBeGreaterThan((int) $oldRequest->id);

    $authorizationsBefore = DB::table(
        'transformation_implementation_authorizations'
    )->count();

    $engagementBefore = DB::table(
        'transformation_implementation_commercial_engagements'
    )->where('id', $engagementId)->first();

    // Execute the real HTTP route with real authorization resolvers.
    $this->actingAs($user)
        ->post(
            route(
                'app.transformation.data_bi.commercial_engagement.accept'
            )
        )
        ->assertNotFound();

    $engagementAfter = DB::table(
        'transformation_implementation_commercial_engagements'
    )->where('id', $engagementId)->first();

    expect($engagementAfter)->toEqual($engagementBefore)
        ->and($engagementAfter->status)->toBe('presented')
        ->and($engagementAfter->accepted_at)->toBeNull();

    expect(
        DB::table(
            'transformation_implementation_authorizations'
        )->count()
    )->toBe($authorizationsBefore);

    // The new request remains unchanged.
    expect(
        DB::table('transformation_implementation_requests')
            ->where('id', $newRequestId)
            ->value('status')
    )->toBe('requested');
});


// R116_E4L1_HISTORY_TESTS

test('R116 admin retains prior authorized version in history', function () {
    r116AssertMySql();

    $request = r116Request();

    $old = r116Engagement($request, 'accepted', 1);
    r116Authorization($request, $old);

    $current = r116Engagement($request, 'draft', 2);

    $admin = r116Projection()->forAdmin($request);

    expect($admin['engagement']['id'])->toBe($current)
        ->and($admin['authorization'])->toBeNull()
        ->and($admin['actions']['can_present'])->toBeTrue()
        ->and($admin['history'])->toHaveCount(1);

    $historical = $admin['history'][0];

    expect($historical['engagement']['id'])->toBe($old)
        ->and($historical['engagement']['version'])->toBe(1)
        ->and($historical['engagement']['status'])->toBe('accepted')
        ->and($historical['authorization']['status'])->toBe('authorized')
        ->and($historical['authorization']['active'])->toBeTrue();

    expect(array_key_exists('actions', $historical))->toBeFalse();

    expect(r116Projection()->forTenant(
        (int) $request->company_id
    ))->toBeNull();
});

test('R116 admin history preserves revoked authorization', function () {
    r116AssertMySql();

    $request = r116Request();

    $old = r116Engagement($request, 'accepted', 1);
    r116Authorization($request, $old, true);

    $current = r116Engagement($request, 'presented', 2);

    $before = DB::table(
        'transformation_implementation_authorizations'
    )->where(
        'transformation_implementation_commercial_engagement_id',
        $old
    )->first();

    $admin = r116Projection()->forAdmin($request);

    expect($admin['engagement']['id'])->toBe($current)
        ->and($admin['history'])->toHaveCount(1)
        ->and($admin['history'][0]['authorization']['status'])
            ->toBe('revoked')
        ->and($admin['history'][0]['authorization']['active'])
            ->toBeFalse();

    expect(array_key_exists(
        'actions',
        $admin['history'][0]
    ))->toBeFalse();

    $after = DB::table(
        'transformation_implementation_authorizations'
    )->where(
        'transformation_implementation_commercial_engagement_id',
        $old
    )->first();

    expect($after)->toEqual($before);
});

// R116_E5C_HTTP_INERTIA

test('R116 Admin GET exposes exact commercial history and authorization', function () {
    r116AssertMySql();

    $request = r116Request();

    $previous = r116Engagement($request, 'accepted', 1);
    r116Authorization($request, $previous, true);

    $current = r116Engagement($request, 'draft', 2);

    $admin = \App\Models\User::factory()->create([
        'name' => 'R116 Inertia Admin QA',
        'email' => 'r116-admin-'
            .\Illuminate\Support\Str::lower(
                \Illuminate\Support\Str::random(16)
            )
            .'@example.test',
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->withoutVite();

    $this->actingAs($admin)
        ->get(route(
            'admin.transformation360.implementation_requests.show',
            ['implementationRequest' => $request->id]
        ))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) =>
                $page
                    ->component(
                        'Admin/Transformation360/ImplementationRequests/Show'
                    )
                    ->where('modern_commercial.engagement.id', $current)
                    ->where('modern_commercial.engagement.version', 2)
                    ->where('modern_commercial.authorization', null)
                    ->has('modern_commercial.history', 1)
                    ->where(
                        'modern_commercial.history.0.engagement.id',
                        $previous
                    )
                    ->where(
                        'modern_commercial.history.0.authorization.status',
                        'revoked'
                    )
                    ->where(
                        'modern_commercial.history.0.authorization.active',
                        false
                    )
                    ->missing('modern_commercial.history.0.actions')
                    ->etc()
        );

    expect(
        DB::table('transformation_implementation_commercial_engagements')
            ->where('id', $previous)
            ->value('status')
    )->toBe('accepted');
});

test('R116 Tenant GET excludes internal commercial data', function () {
    r116AssertMySql();

    $request = r116Request();
    $engagementId = r116Engagement($request, 'presented');

    $company = DB::table('companies')
        ->where('id', $request->company_id)
        ->first();

    expect($company)->not->toBeNull();

    $subscriberId = (int) $company->subscriber_id;

    expect($subscriberId)->toBeGreaterThan(0);

    $user = \App\Models\User::factory()->create([
        'name' => 'R116 Inertia Tenant QA',
        'email' => 'r116-tenant-'
            .\Illuminate\Support\Str::lower(
                \Illuminate\Support\Str::random(16)
            )
            .'@example.test',
        'role' => 'subscriber',
        'email_verified_at' => now(),
    ]);

    DB::table('subscriber_user')->insert([
        'subscriber_id' => $subscriberId,
        'user_id' => $user->id,
        'role' => 'owner',
        'active' => 1,
    ]);

    $updates = [];

    if (\Illuminate\Support\Facades\Schema::hasColumn(
        'users',
        'subscriber_id'
    )) {
        $updates['subscriber_id'] = $subscriberId;
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn(
        'users',
        'company_id'
    )) {
        $updates['company_id'] = (int) $company->id;
    }

    if ($updates !== []) {
        DB::table('users')
            ->where('id', $user->id)
            ->update($updates);
    }

    $user->refresh();

    $resolvedSubscriber = app(
        \App\Services\Subscribers\SubscriberResolver::class
    )->resolve($user);

    expect((int) $resolvedSubscriber)->toBe($subscriberId);

    $resolvedCompany = app(
        \App\Services\Subscribers\CompanyContextResolver::class
    )->resolve($user, $subscriberId);

    expect($resolvedCompany)->not->toBeNull()
        ->and((int) $resolvedCompany->id)
        ->toBe((int) $request->company_id);

    $this->withoutVite();

    $this->actingAs($user)
        ->get(route('app.transformation.data_bi.show'))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) =>
                $page
                    ->component('App/DataTransformationBi')
                    ->where('company.id', (int) $company->id)
                    ->where(
                        'modern_commercial.engagement.id',
                        $engagementId
                    )
                    ->where(
                        'modern_commercial.engagement.status',
                        'presented'
                    )
                    ->where(
                        'modern_commercial.actions.can_accept',
                        true
                    )
                    ->missing('modern_commercial.history')
                    ->missing('modern_commercial.engagement.internal_notes')
                    ->missing('modern_commercial.engagement.definition_id')
                    ->etc()
        );

    expect(
        DB::table('transformation_implementation_commercial_engagements')
            ->where('id', $engagementId)
            ->value('status')
    )->toBe('presented');
});
