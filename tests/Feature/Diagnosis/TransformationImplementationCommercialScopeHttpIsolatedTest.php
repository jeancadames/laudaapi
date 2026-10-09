<?php

use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\User;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Standalone, test-only relational subset built from D1F-F1 schema metadata.
 * All database connections are directed to SQLite :memory: before fixture writes.
 * HTTP requests use the registered Laravel routes and actual controllers/services.
 * Nothing is sourced from a real company, definition, or user.
 */
function d1f2IsolatedDatabase(): void
{
    if (app()->environment() !== 'testing'
        || config('database.default') !== 'sqlite'
        || DB::connection()->getDriverName() !== 'sqlite'
        || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('D1F2_DATABASE_ISOLATION_FAILED');
    }
    // Block even explicit calls to the conventional non-default MySQL connection.
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    foreach (['pgsql', 'sqlsrv', 'mariadb'] as $key) {
        config()->set('database.connections.'.$key, config('database.connections.sqlite'));
        DB::purge($key);
    }
    if (DB::connection('mysql')->getDriverName() !== 'sqlite') {
        throw new RuntimeException('D1F2_ALTERNATE_CONNECTION_GUARD_FAILED');
    }
}

function d1f2CreateSchema(): void
{
    foreach ([
        'transformation_implementation_authorizations',
        'transformation_implementation_commercial_engagements',
        'transformation_implementation_request_events',
        'transformation_implementation_definitions',
        'transformation_implementation_requests',
        'transformation_implementation_phase_capabilities',
        'subscriber_user', 'companies', 'subscribers', 'users', 'audit_logs',
    ] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('email')->unique();
        $t->timestamp('email_verified_at')->nullable(); $t->string('password');
        $t->string('role')->default('user'); $t->rememberToken(); $t->timestamps();
    });
    Schema::create('subscribers', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('slug')->unique();
        $t->boolean('active')->default(true); $t->timestamps();
    });
    Schema::create('companies', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('slug')->unique();
        $t->unsignedBigInteger('subscriber_id')->nullable();
        $t->unsignedBigInteger('owner_user_id')->nullable();
        $t->boolean('active')->default(true); $t->timestamps();
    });
    Schema::create('transformation_implementation_phase_capabilities', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('transformation_implementation_phase_id');
        $t->unsignedSmallInteger('sequence')->default(1);
        $t->string('capability_key'); $t->string('capability_label');
        $t->timestamps();
    });
    Schema::create('subscriber_user', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('subscriber_id');
        $t->unsignedBigInteger('user_id'); $t->string('role')->default('member');
        $t->boolean('active')->default(true); $t->timestamps();
        $t->unique(['subscriber_id','user_id']);
    });
    Schema::create('transformation_implementation_requests', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('company_id');
        $t->unsignedBigInteger('diagnosis_assessment_id');
        $t->unsignedBigInteger('transformation_implementation_plan_id');
        $t->unsignedBigInteger('transformation_implementation_phase_capability_id');
        $t->string('capability_key'); $t->unsignedSmallInteger('attempt')->default(1);
        $t->string('source_type')->default('tenant_admin');
        $t->string('status'); $t->json('source_snapshot');
        $t->timestamp('requested_at'); $t->timestamp('ready_for_commercial_at')->nullable();
        $t->timestamps();
    });
    Schema::create('transformation_implementation_request_events', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('transformation_implementation_request_id');
        $t->string('event_type'); $t->string('from_status')->nullable();
        $t->string('to_status'); $t->string('actor_type');
        $t->unsignedBigInteger('actor_user_id')->nullable();
        $t->json('metadata')->nullable(); $t->timestamp('occurred_at'); $t->timestamps();
    });
    Schema::create('transformation_implementation_definitions', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('transformation_implementation_plan_id');
        $t->unsignedBigInteger('diagnosis_assessment_id');
        $t->unsignedBigInteger('company_id');
        $t->unsignedBigInteger('transformation_implementation_request_id')->nullable();
        $t->unsignedBigInteger('transformation_implementation_phase_capability_id')->nullable();
        $t->string('capability_key')->nullable(); $t->unsignedInteger('version')->default(1);
        $t->string('status'); $t->json('source_snapshot');
        $t->json('implementation_scope')->nullable(); $t->json('deliverables')->nullable();
        $t->json('readiness')->nullable(); $t->timestamp('ready_at')->nullable(); $t->timestamps();
    });
    Schema::create('transformation_implementation_commercial_engagements', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('transformation_implementation_request_id');
        $t->unsignedBigInteger('transformation_implementation_definition_id');
        $t->unsignedBigInteger('company_id');
        $t->unsignedBigInteger('transformation_implementation_phase_capability_id');
        $t->string('capability_key'); $t->unsignedSmallInteger('version');
        $t->string('status')->default('draft'); $t->string('currency',3)->default('DOP');
        $t->decimal('price_amount',12,2)->nullable(); $t->unsignedInteger('duration_days')->nullable();
        $t->json('scope_snapshot')->nullable(); $t->json('deliverables_snapshot')->nullable();
        $t->json('commercial_terms_snapshot')->nullable(); $t->text('internal_notes')->nullable();
        foreach (['created_by_user_id','updated_by_user_id','presented_by_user_id',
            'accepted_by_user_id','rejected_by_user_id','cancelled_by_user_id'] as $column) {
            $t->unsignedBigInteger($column)->nullable();
        }
        foreach (['presented_at','accepted_at','rejected_at','superseded_at','cancelled_at'] as $column) {
            $t->timestamp($column)->nullable();
        }
        $t->text('rejection_reason')->nullable(); $t->text('cancellation_reason')->nullable();
        $t->timestamps(); $t->unique(['transformation_implementation_request_id','version']);
    });
    // Exercise the *real* new migration on the fixture table, not a handcrafted substitute.
    $migration = require base_path(
        'database/migrations/2026_10_09_220000_add_contracted_scope_snapshots_to_transformation_implementation_commercial_engagements.php'
    );
    $migration->up();
    Schema::create('transformation_implementation_authorizations', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('transformation_implementation_commercial_engagement_id');
        $t->unsignedBigInteger('transformation_implementation_request_id');
        $t->unsignedBigInteger('transformation_implementation_definition_id');
        $t->unsignedBigInteger('company_id');
        $t->unsignedBigInteger('transformation_implementation_phase_capability_id');
        $t->string('capability_key'); $t->string('status'); $t->json('authorization_snapshot');
        $t->unsignedBigInteger('authorized_by_user_id')->nullable();
        $t->unsignedBigInteger('revoked_by_user_id')->nullable();
        $t->timestamp('authorized_at'); $t->timestamp('revoked_at')->nullable();
        $t->timestamps(); $t->unique('transformation_implementation_commercial_engagement_id');
    });
    // The real AuditService determines whether additional fixture schema is needed.
    Schema::create('audit_logs', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('company_id')->nullable();
        $t->string('action')->nullable(); $t->string('event')->nullable();
        $t->string('auditable_type')->nullable(); $t->unsignedBigInteger('auditable_id')->nullable();
        $t->string('model_type')->nullable(); $t->unsignedBigInteger('model_id')->nullable();
        // Real AuditService inserts these fields during the commercial lifecycle.
        $t->json('data')->nullable();
        $t->string('ip')->nullable();
        $t->text('user_agent')->nullable();
        $t->json('metadata')->nullable(); $t->json('details')->nullable();
        $t->json('old_values')->nullable(); $t->json('new_values')->nullable();
        $t->timestamps();
    });
}

function d1f2Actor(string $role, string $name): User
{
    return User::query()->forceCreate([
        'name' => $name,
        'email' => strtolower(str_replace(' ','-', $name)).'@synthetic.test',
        'password' => 'unused-test-only', 'role' => $role,
        'email_verified_at' => now(),
    ]);
}

function d1f2Fixture(): array
{
    $admin = d1f2Actor('admin', 'D1F2 Platform');
    $owner = d1f2Actor('subscriber', 'D1F2 Tenant Owner');
    $member = d1f2Actor('subscriber', 'D1F2 Tenant Member');
    $other = d1f2Actor('subscriber', 'D1F2 Other Owner');
    $subscriberId = DB::table('subscribers')->insertGetId([
        'name'=>'D1F2 Synthetic Subscriber A','slug'=>'d1f2-subscriber-a',
        'active'=>1,'created_at'=>now(),'updated_at'=>now(),
    ]);
    $otherSubscriberId = DB::table('subscribers')->insertGetId([
        'name'=>'D1F2 Synthetic Subscriber B','slug'=>'d1f2-subscriber-b',
        'active'=>1,'created_at'=>now(),'updated_at'=>now(),
    ]);
    $companyId = DB::table('companies')->insertGetId([
        'name'=>'D1F2 Synthetic Company A','slug'=>'d1f2-company-a',
        'subscriber_id'=>$subscriberId,'owner_user_id'=>$owner->id,
        'active'=>1,'created_at'=>now(),'updated_at'=>now(),
    ]);
    $otherCompanyId = DB::table('companies')->insertGetId([
        'name'=>'D1F2 Synthetic Company B','slug'=>'d1f2-company-b',
        'subscriber_id'=>$otherSubscriberId,'owner_user_id'=>$other->id,
        'active'=>1,'created_at'=>now(),'updated_at'=>now(),
    ]);
    foreach ([[$subscriberId,$owner->id,'owner'],[$subscriberId,$member->id,'member'],
        [$otherSubscriberId,$other->id,'owner']] as [$sid,$uid,$role]) {
        DB::table('subscriber_user')->insert([
            'subscriber_id'=>$sid,'user_id'=>$uid,'role'=>$role,
            'active'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    $phaseCapabilityId=35;
    DB::table('transformation_implementation_phase_capabilities')->insert([
        'id'=>$phaseCapabilityId,'transformation_implementation_phase_id'=>31,
        'sequence'=>1,'capability_key'=>'data_transformation_bi',
        'capability_label'=>'D1F2 Synthetic Data BI',
        'created_at'=>now(),'updated_at'=>now(),
    ]);
    $planId=32;
    $assessmentId=30;
    $requestId = DB::table('transformation_implementation_requests')->insertGetId([
        'company_id'=>$companyId,'diagnosis_assessment_id'=>$assessmentId,
        'transformation_implementation_plan_id'=>$planId,
        'transformation_implementation_phase_capability_id'=>$phaseCapabilityId,
        'capability_key'=>'data_transformation_bi','attempt'=>1,
        'source_type'=>'tenant_admin','status'=>'ready_for_commercial',
        'source_snapshot'=>json_encode(['qa'=>'D1F2','synthetic'=>true]),
        'requested_at'=>now(),'ready_for_commercial_at'=>now(),
        'created_at'=>now(),'updated_at'=>now(),
    ]);
    $scope=['purpose'=>'D1F2 Synthetic Reference Only'];
    $deliverables=[
        ['deliverable'=>'D1F2 Synthetic Customer Source Profiling'],
        ['deliverable'=>'D1F2 Synthetic Product Normalization'],
        ['deliverable'=>'D1F2 Synthetic Reusable Data Model'],
    ];
    $definitionId = DB::table('transformation_implementation_definitions')->insertGetId([
        'transformation_implementation_plan_id'=>$planId,
        'diagnosis_assessment_id'=>$assessmentId,'company_id'=>$companyId,
        'transformation_implementation_request_id'=>$requestId,
        'transformation_implementation_phase_capability_id'=>$phaseCapabilityId,
        'capability_key'=>'data_transformation_bi','version'=>1,'status'=>'ready',
        'source_snapshot'=>json_encode(['synthetic'=>true]),
        'implementation_scope'=>json_encode($scope),
        'deliverables'=>json_encode($deliverables),
        'readiness'=>json_encode([
            'state'=>'ready','definition_ready'=>true,'technical_readiness'=>true,
            'ready_for_execution'=>false,'execution_started'=>false,
        ]),'ready_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
    ]);
    // Both observed readiness keys are set so the two independent evidence
    // resolvers can be exercised without guessing which upstream producer ran.
    $metadata=[
        'request_id'=>$requestId,'company_id'=>$companyId,
        'phase_capability_id'=>$phaseCapabilityId,
        'capability_key'=>'data_transformation_bi',
        'definition_id'=>$definitionId,'definition_version'=>1,
        'definition_ready'=>true,'functional_definition_ready'=>true,
        'technical_readiness'=>true,'commercial_acceptance'=>false,
        'ready_for_execution'=>false,'execution_started'=>false,
    ];
    DB::table('transformation_implementation_request_events')->insert([
        'transformation_implementation_request_id'=>$requestId,
        'event_type'=>'request_ready_for_commercial_by_lauda',
        'from_status'=>'definition_agreed','to_status'=>'ready_for_commercial',
        'actor_type'=>TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN,
        'actor_user_id'=>$admin->id,'metadata'=>json_encode($metadata),
        'occurred_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
    ]);
    return compact('admin','owner','member','other','companyId','otherCompanyId',
        'requestId','definitionId','scope','deliverables');
}

function d1f2DraftPayload(array $overrides=[]): array
{
    return array_replace_recursive([
        'currency'=>'DOP','price_amount'=>17000,'duration_days'=>21,
        'commercial_terms_snapshot'=>['terms_text'=>'Condiciones QA sintéticas'],
        'contract_scope_mode'=>'partial',
        'contract_deliverable_indices'=>[0,2],
        'contract_scope_terms'=>[
            'acceptance_criteria'=>'Entregables QA revisados y aceptados.',
            'dependencies'=>'Disponibilidad de archivos sintéticos.',
            'assumptions'=>'Sin datos reales.',
            'exclusions'=>'Entregable sintético 2 no incluido.',
        ],
        'internal_notes'=>'D1F2_PRIVATE_TEST_ONLY',
    ],$overrides);
}

function d1f2StoreUrl(int $requestId): string
{
    return route('admin.transformation360.implementation_requests.commercial_engagements.store',
        ['implementationRequest'=>$requestId]);
}
function d1f2PresentUrl(int $requestId,int $engagementId): string
{
    return route('admin.transformation360.implementation_requests.commercial_engagements.present',
        ['implementationRequest'=>$requestId,'engagement'=>$engagementId]);
}
function d1f2AuthorizeUrl(int $requestId,int $engagementId): string
{
    return route('admin.transformation360.implementation_requests.commercial_engagements.authorize',
        ['implementationRequest'=>$requestId,'engagement'=>$engagementId]);
}
function d1f2AcceptUrl(): string
{
    return route('app.transformation.data_bi.commercial_engagement.accept');
}

beforeEach(function () {
    d1f2IsolatedDatabase();
    d1f2CreateSchema();
    $this->fixture=d1f2Fixture();
});

test('D1F2 HTTP completes partial-scope draft presentation acceptance and authorization', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $engagement=TransformationImplementationCommercialEngagement::query()->sole();
    expect($engagement->status)->toBe('draft')
        ->and($engagement->contract_scope_schema_version)->toBe(1)
        ->and($engagement->contracted_scope_snapshot['mode'])->toBe('partial')
        ->and($engagement->contracted_scope_snapshot['selected_definition_indices'])->toBe([0,2]);
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$engagement->id))->assertRedirect();
    expect($engagement->fresh()->status)->toBe('presented');
    $this->actingAs($f['owner'])->post(d1f2AcceptUrl())->assertRedirect();
    expect($engagement->fresh()->status)->toBe('accepted');
    $this->actingAs($f['admin'])->post(d1f2AuthorizeUrl($f['requestId'],$engagement->id))->assertRedirect();
    $auth=DB::table('transformation_implementation_authorizations')->sole();
    $snapshot=json_decode($auth->authorization_snapshot,true,512,JSON_THROW_ON_ERROR);
    expect($snapshot['commercial_engagement']['contracted_scope_snapshot']['selected_definition_indices'])->toBe([0,2])
        ->and(count($snapshot['commercial_engagement']['contracted_deliverables_snapshot']))->toBe(2)
        ->and($snapshot['authorization_boundary']['execution_started'])->toBeFalse()
        ->and(DB::table('transformation_implementation_requests')->where('id',$f['requestId'])->value('status'))
            ->toBe('ready_for_commercial');
});

test('D1F2 HTTP rejects malformed contracted indices with no commercial draft', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->postJson(d1f2StoreUrl($f['requestId']),
        d1f2DraftPayload(['contract_deliverable_indices'=>[0,99]]))->assertUnprocessable();
    expect(DB::table('transformation_implementation_commercial_engagements')->count())->toBe(0);
});

test('D1F2 HTTP rejects non-admin draft creation', function () {
    $f=$this->fixture;
    $this->actingAs($f['owner'])->postJson(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertForbidden();
    expect(DB::table('transformation_implementation_commercial_engagements')->count())->toBe(0);
});

test('D1F2 HTTP rejects member acceptance without changing proposal', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $eng=TransformationImplementationCommercialEngagement::query()->sole();
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$eng->id))->assertRedirect();
    $this->actingAs($f['member'])->postJson(d1f2AcceptUrl())->assertForbidden();
    expect($eng->fresh()->status)->toBe('presented');
});

test('D1F2 HTTP isolates unrelated tenant acceptance from company A commercial proposal', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $eng=TransformationImplementationCommercialEngagement::query()->sole();
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$eng->id))->assertRedirect();
    $this->actingAs($f['other'])->postJson(d1f2AcceptUrl())->assertNotFound();
    expect($eng->fresh()->status)->toBe('presented');
});

test('D1F2 HTTP rejects authorizing modified contracted evidence after acceptance', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $eng=TransformationImplementationCommercialEngagement::query()->sole();
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$eng->id))->assertRedirect();
    $this->actingAs($f['owner'])->post(d1f2AcceptUrl())->assertRedirect();
    $items=$eng->fresh()->contracted_deliverables_snapshot;
    $items[0]['deliverable']='TAMPERED';
    DB::table('transformation_implementation_commercial_engagements')->where('id',$eng->id)
        ->update(['contracted_deliverables_snapshot'=>json_encode($items)]);
    $this->actingAs($f['admin'])->postJson(d1f2AuthorizeUrl($f['requestId'],$eng->id))
        ->assertUnprocessable();
    expect(DB::table('transformation_implementation_authorizations')->count())->toBe(0);
});

test('D1F2 HTTP rejects accepting an older commercial attempt', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $eng=TransformationImplementationCommercialEngagement::query()->sole();
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$eng->id))->assertRedirect();
    $old=DB::table('transformation_implementation_requests')->where('id',$f['requestId'])->first();
    $new=(array)$old; unset($new['id']);
    $new['attempt']=2; $new['status']='requested';
    $new['ready_for_commercial_at']=null;
    DB::table('transformation_implementation_requests')->insert($new);
    $this->actingAs($f['owner'])->postJson(d1f2AcceptUrl())->assertNotFound();
    expect($eng->fresh()->status)->toBe('presented');
});

test('D1F2 HTTP refuses a second tenant acceptance for the same proposal', function () {
    $f=$this->fixture;
    $this->actingAs($f['admin'])->post(d1f2StoreUrl($f['requestId']),d1f2DraftPayload())->assertRedirect();
    $eng=TransformationImplementationCommercialEngagement::query()->sole();
    $this->actingAs($f['admin'])->post(d1f2PresentUrl($f['requestId'],$eng->id))->assertRedirect();
    $this->actingAs($f['owner'])->post(d1f2AcceptUrl())->assertRedirect();
    $this->actingAs($f['owner'])->postJson(d1f2AcceptUrl())->assertNotFound();
    expect($eng->fresh()->status)->toBe('accepted');
});
