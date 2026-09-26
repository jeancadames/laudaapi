<?php

use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

function diagnosisLifecycleAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function diagnosisLifecycleTenant(): User
{
    return User::factory()->create([
        'role' => 'subscriber',
        'email_verified_at' => now(),
    ]);
}

function diagnosisLifecycleContact(
    string $email = 'lifecycle-admin@example.com'
): ContactRequest {
    return ContactRequest::create([
        'name' => 'Lifecycle Admin Test',
        'email' => $email,
        'company' => 'Lifecycle Test SRL',
        'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
        'message' => 'Solicitud de prueba de lifecycle.',
        'metadata' => [
            'request_type' => 'digital_diagnosis_access_request',
        ],
    ]);
}

function diagnosisLifecycleFixture(array $assessmentOverrides = []): array
{
    $user = diagnosisLifecycleTenant();

    $assessment = DiagnosisAssessment::create(array_merge([
        'user_id' => $user->id,
        'organization_id' => 99001,
        'organization_name' => 'Lifecycle Test SRL',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'draft',
        'current_step' => 1,
        'answers' => [],
        'notes' => [],
        'is_active' => true,
    ], $assessmentOverrides));

    $contact = diagnosisLifecycleContact();

    $workflow = DiagnosisAccessRequest::create([
        'contact_request_id' => $contact->id,
        'user_id' => $user->id,
        'diagnosis_assessment_id' => $assessment->id,
        'status' => DiagnosisAccessRequest::STATUS_ACTIVE,
    ]);

    return compact(
        'user',
        'assessment',
        'contact',
        'workflow'
    );
}

test('admin can inactivate an active assessment without unpublishing it', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'status' => 'reviewed',
        'published_at' => now()->subDay(),
        'reviewed_at' => now()->subDay(),
        'submitted_at' => now()->subDays(2),
    ]);

    $publishedAt = $assessment->published_at->toDateTimeString();
    $status = $assessment->status;

    $this
        ->actingAs($admin)
        ->post(
            route(
                'admin.diagnosis_requests.inactivate',
                $contact
            )
        )
        ->assertRedirect();

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeFalse()
        ->and($assessment->inactivated_at)
        ->not->toBeNull()
        ->and($assessment->status)
        ->toBe($status)
        ->and($assessment->published_at->toDateTimeString())
        ->toBe($publishedAt)
        ->and($assessment->superseded_by_assessment_id)
        ->toBeNull();
});

test('inactivation is idempotent', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $route = route(
        'admin.diagnosis_requests.inactivate',
        $contact
    );

    $this->actingAs($admin)->post($route)->assertRedirect();

    $assessment->refresh();
    $firstInactivatedAt =
        $assessment->inactivated_at->toDateTimeString();

    $this->actingAs($admin)->post($route)->assertRedirect();

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeFalse()
        ->and($assessment->inactivated_at->toDateTimeString())
        ->toBe($firstInactivatedAt);
});

test('admin can reactivate an inactive assessment', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'is_active' => false,
        'inactivated_at' => now()->subHour(),
    ]);

    $this
        ->actingAs($admin)
        ->post(
            route(
                'admin.diagnosis_requests.reactivate',
                $contact
            )
        )
        ->assertRedirect();

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeTrue()
        ->and($assessment->inactivated_at)
        ->toBeNull()
        ->and($assessment->superseded_by_assessment_id)
        ->toBeNull();
});

test('reactivation is idempotent when assessment is already active', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $this
        ->actingAs($admin)
        ->post(
            route(
                'admin.diagnosis_requests.reactivate',
                $contact
            )
        )
        ->assertRedirect();

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeTrue()
        ->and($assessment->inactivated_at)
        ->toBeNull();
});

test('superseded assessment cannot be reactivated', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'is_active' => false,
        'inactivated_at' => now()->subHour(),
    ]);

    $replacement = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => $assessment->organization_id,
        'organization_name' => $assessment->organization_name,
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'reviewed',
        'current_step' => 11,
        'answers' => [],
        'notes' => [],
        'is_active' => true,
        'published_at' => now(),
    ]);

    $assessment->forceFill([
        'superseded_by_assessment_id' => $replacement->id,
    ])->save();

    $this
        ->actingAs($admin)
        ->post(
            route(
                'admin.diagnosis_requests.reactivate',
                $contact
            )
        )
        ->assertStatus(422);

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeFalse()
        ->and($assessment->inactivated_at)
        ->not->toBeNull()
        ->and($assessment->superseded_by_assessment_id)
        ->toBe($replacement->id);
});

test('non admin cannot inactivate or reactivate assessments', function () {
    [
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $this
        ->actingAs($user)
        ->post(
            route(
                'admin.diagnosis_requests.inactivate',
                $contact
            )
        )
        ->assertForbidden();

    $assessment->refresh();

    expect((bool) $assessment->is_active)->toBeTrue();

    $assessment->forceFill([
        'is_active' => false,
        'inactivated_at' => now(),
    ])->save();

    $this
        ->actingAs($user)
        ->post(
            route(
                'admin.diagnosis_requests.reactivate',
                $contact
            )
        )
        ->assertForbidden();

    $assessment->refresh();

    expect((bool) $assessment->is_active)
        ->toBeFalse()
        ->and($assessment->inactivated_at)
        ->not->toBeNull();
});

test('admin can delete a clean draft assessment while preserving access request', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
        'workflow' => $workflow,
    ] = diagnosisLifecycleFixture();

    $assessmentId = $assessment->id;
    $workflowId = $workflow->id;

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertRedirect();

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessmentId)
            ->exists()
    )->toBeFalse();

    $workflow = DiagnosisAccessRequest::query()
        ->findOrFail($workflowId);

    expect($workflow->diagnosis_assessment_id)
        ->toBeNull();
});

test('published assessment cannot be deleted', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'status' => 'reviewed',
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'published_at' => now()->subHour(),
    ]);

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();
});

test('submitted assessment cannot be deleted', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();
});

test('reviewed assessment cannot be deleted', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture([
        'status' => 'reviewed',
        'reviewed_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();
});

test('superseded assessment cannot be deleted', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $replacement = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => $assessment->organization_id,
        'organization_name' => $assessment->organization_name,
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'draft',
        'current_step' => 1,
        'answers' => [],
        'notes' => [],
        'is_active' => true,
    ]);

    $assessment->forceFill([
        'is_active' => false,
        'inactivated_at' => now(),
        'superseded_by_assessment_id' => $replacement->id,
    ])->save();

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();
});

test('assessment that supersedes another assessment cannot be deleted', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $older = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => $assessment->organization_id,
        'organization_name' => $assessment->organization_name,
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'reviewed',
        'current_step' => 11,
        'answers' => [],
        'notes' => [],
        'is_active' => false,
        'published_at' => now()->subDay(),
        'superseded_by_assessment_id' => $assessment->id,
    ]);

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();

    expect(
        DiagnosisAssessment::query()
            ->whereKey($older->id)
            ->value('superseded_by_assessment_id')
    )->toBe($assessment->id);
});

test('non admin cannot delete assessment', function () {
    [
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $this
        ->actingAs($user)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertForbidden();

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();
});

test('deletion service reports downstream blockers', function () {
    [
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $service = app(
        \App\Services\Diagnosis\DiagnosisAssessmentDeletionService::class
    );

    $tables = [
        'diagnosis_deliverable_validations',
        'diagnosis_detailed_roadmap_orders',
        'diagnosis_detailed_roadmaps',
        'diagnosis_expanded_report_orders',
        'diagnosis_expanded_reports',
        'transformation_capability_activations',
        'transformation_capability_decisions',
        'transformation_implementation_definitions',
        'transformation_implementation_plans',
        'transformation_implementation_requests',
    ];

    foreach ($tables as $table) {
        expect(
            Schema::hasColumn(
                $table,
                'diagnosis_assessment_id'
            )
        )->toBeTrue(
            "La tabla {$table} debe conservar diagnosis_assessment_id."
        );
    }

    expect($service->blockers($assessment))
        ->toBe([]);
});

test('real downstream dependency blocks assessment deletion and is preserved', function () {
    $admin = diagnosisLifecycleAdmin();

    [
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisLifecycleFixture();

    $validation = \App\Models\DiagnosisDeliverableValidation::create([
        'diagnosis_assessment_id' => $assessment->id,
        'deliverable_type' =>
            \App\Models\DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
        'deliverable_id' => 999999,
        'deliverable_version' => 1,
    ]);

    $service = app(
        \App\Services\Diagnosis\DiagnosisAssessmentDeletionService::class
    );

    expect($service->blockers($assessment))
        ->toContain('diagnosis_deliverable_validations');

    $this
        ->actingAs($admin)
        ->delete(
            route(
                'admin.diagnosis_requests.assessment.delete',
                $contact
            )
        )
        ->assertSessionHasErrors('assessment');

    expect(
        DiagnosisAssessment::query()
            ->whereKey($assessment->id)
            ->exists()
    )->toBeTrue();

    expect(
        \App\Models\DiagnosisDeliverableValidation::query()
            ->whereKey($validation->id)
            ->exists()
    )->toBeTrue();
});
