<?php

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDeliverableValidation;
use App\Models\User;
use App\Services\Diagnosis\DiagnosisAssessmentReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function reconciliationAssessment(array $overrides = []): DiagnosisAssessment
{
    $user = User::factory()->create();

    return DiagnosisAssessment::create(array_merge([
        'user_id' => $user->id,
        'organization_id' => 999001,
        'organization_name' => 'UAT Reconciliation Tenant',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'draft',
        'current_step' => 1,
        'answers' => [],
        'notes' => [],
        'is_active' => true,
    ], $overrides));
}

test('detects published inactive assessment superseded by unpublished working assessment', function () {
    $working = reconciliationAssessment([
        'status' => 'draft',
        'is_active' => true,
    ]);

    $official = reconciliationAssessment([
        'status' => 'reviewed',
        'current_step' => 11,
        'is_active' => false,
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDays(2),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    $candidates = $service->candidates(
        $official->organization_id
    );

    expect($candidates->pluck('id')->all())
        ->toContain($official->id);

    $description = $service->describe($official);

    expect($description['official_assessment_id'])
        ->toBe($official->id)
        ->and($description['working_assessment_id'])
        ->toBe($working->id)
        ->and($description['official_is_active'])
        ->toBeFalse()
        ->and($description['working_is_active'])
        ->toBeTrue()
        ->and($description['changes'])
        ->toBe([
            'official.is_active' => true,
            'official.inactivated_at' => null,
            'official.superseded_by_assessment_id' => null,
        ]);
});

test('reconcile restores official assessment and preserves working assessment', function () {
    $working = reconciliationAssessment([
        'status' => 'draft',
        'current_step' => 1,
        'answers' => [],
        'is_active' => true,
    ]);

    $official = reconciliationAssessment([
        'status' => 'reviewed',
        'current_step' => 11,
        'is_active' => false,
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDays(2),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    $result = $service->reconcile($official);

    expect($result['changed'])
        ->toBeTrue()
        ->and($result['official_assessment_id'])
        ->toBe($official->id)
        ->and($result['working_assessment_id'])
        ->toBe($working->id);

    $official->refresh();
    $working->refresh();

    expect($official->is_active)
        ->toBeTrue()
        ->and($official->inactivated_at)
        ->toBeNull()
        ->and($official->superseded_by_assessment_id)
        ->toBeNull()
        ->and($official->published_at)
        ->not->toBeNull();

    expect($working->is_active)
        ->toBeTrue()
        ->and($working->status)
        ->toBe('draft')
        ->and($working->published_at)
        ->toBeNull();
});

test('reconcile preserves downstream deliverable records', function () {
    $working = reconciliationAssessment();

    $official = reconciliationAssessment([
        'status' => 'reviewed',
        'current_step' => 11,
        'is_active' => false,
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDays(2),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $validation = DiagnosisDeliverableValidation::create([
        'diagnosis_assessment_id' => $official->id,
        'deliverable_type' =>
            DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
        'deliverable_id' => 999999,
        'deliverable_version' => 1,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    $service->reconcile($official);

    expect(
        DiagnosisDeliverableValidation::query()
            ->whereKey($validation->id)
            ->where(
                'diagnosis_assessment_id',
                $official->id
            )
            ->exists()
    )->toBeTrue();
});

test('published replacement is not considered reconciliation candidate', function () {
    $replacement = reconciliationAssessment([
        'status' => 'reviewed',
        'published_at' => now(),
        'is_active' => true,
    ]);

    $historical = reconciliationAssessment([
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now()->subMonth(),
        'inactivated_at' => now(),
        'superseded_by_assessment_id' => $replacement->id,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    expect(
        $service
            ->candidates($historical->organization_id)
            ->pluck('id')
            ->all()
    )->not->toContain($historical->id);

    $result = $service->reconcile($historical);

    expect($result)
        ->toBe([
            'changed' => false,
            'reason' => 'replacement_already_published',
        ]);

    $historical->refresh();

    expect($historical->is_active)
        ->toBeFalse()
        ->and($historical->superseded_by_assessment_id)
        ->toBe($replacement->id);
});

test('reconciliation is idempotent', function () {
    $working = reconciliationAssessment();

    $official = reconciliationAssessment([
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDay(),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    $first = $service->reconcile($official);

    expect($first['changed'])->toBeTrue();

    $official->refresh();

    $second = $service->reconcile($official);

    expect($second)
        ->toBe([
            'changed' => false,
            'reason' => 'no_superseding_assessment',
        ]);

    $official->refresh();

    expect($official->is_active)
        ->toBeTrue()
        ->and($official->superseded_by_assessment_id)
        ->toBeNull()
        ->and($official->inactivated_at)
        ->toBeNull();
});

test('organization mismatch is never reconciled', function () {
    $foreignWorking = reconciliationAssessment([
        'organization_id' => 999002,
        'organization_name' => 'Foreign Tenant',
    ]);

    $official = reconciliationAssessment([
        'organization_id' => 999001,
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now(),
        'inactivated_at' => now(),
        'superseded_by_assessment_id' => $foreignWorking->id,
    ]);

    $service = app(
        DiagnosisAssessmentReconciliationService::class
    );

    expect(
        $service
            ->candidates($official->organization_id)
            ->pluck('id')
            ->all()
    )->not->toContain($official->id);

    $result = $service->reconcile($official);

    expect($result)
        ->toBe([
            'changed' => false,
            'reason' => 'organization_mismatch',
        ]);

    $official->refresh();

    expect($official->is_active)
        ->toBeFalse()
        ->and($official->superseded_by_assessment_id)
        ->toBe($foreignWorking->id);
});

test('reconciliation command is dry run by default and does not write', function () {
    $working = reconciliationAssessment([
        'organization_id' => 999101,
        'organization_name' => 'UAT Command Dry Run',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $official = reconciliationAssessment([
        'organization_id' => 999101,
        'organization_name' => 'UAT Command Dry Run',
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDay(),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $this->artisan(
        'lauda360:reconcile-assessments',
        ['--organization' => 999101]
    )
        ->expectsOutputToContain('Modo: DRY-RUN')
        ->expectsOutputToContain(
            'Official assessment: #'.$official->id
        )
        ->expectsOutputToContain(
            'Working assessment: #'.$working->id
        )
        ->expectsOutputToContain(
            'DATABASE CHANGES: 0 · DRY-RUN'
        )
        ->assertSuccessful();

    $official->refresh();
    $working->refresh();

    expect($official->is_active)
        ->toBeFalse()
        ->and($official->inactivated_at)
        ->not->toBeNull()
        ->and($official->superseded_by_assessment_id)
        ->toBe($working->id);

    expect($working->is_active)
        ->toBeTrue()
        ->and($working->published_at)
        ->toBeNull();
});

test('reconciliation command only writes with explicit apply option', function () {
    $working = reconciliationAssessment([
        'organization_id' => 999102,
        'organization_name' => 'UAT Command Apply',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $official = reconciliationAssessment([
        'organization_id' => 999102,
        'organization_name' => 'UAT Command Apply',
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDay(),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $this->artisan(
        'lauda360:reconcile-assessments',
        [
            '--organization' => 999102,
            '--apply' => true,
        ]
    )
        ->expectsOutputToContain('Modo: APPLY')
        ->expectsOutputToContain(
            'APPLIED · Assessment #'.$official->id.' reconciliado.'
        )
        ->expectsOutputToContain('Cambios aplicados: 1')
        ->assertSuccessful();

    $official->refresh();
    $working->refresh();

    expect($official->is_active)
        ->toBeTrue()
        ->and($official->inactivated_at)
        ->toBeNull()
        ->and($official->superseded_by_assessment_id)
        ->toBeNull()
        ->and($official->published_at)
        ->not->toBeNull();

    expect($working->is_active)
        ->toBeTrue()
        ->and($working->status)
        ->toBe('draft')
        ->and($working->published_at)
        ->toBeNull();
});

test('reconciliation command is idempotent after apply', function () {
    $working = reconciliationAssessment([
        'organization_id' => 999103,
        'organization_name' => 'UAT Command Idempotent',
    ]);

    $official = reconciliationAssessment([
        'organization_id' => 999103,
        'organization_name' => 'UAT Command Idempotent',
        'status' => 'reviewed',
        'is_active' => false,
        'published_at' => now()->subDay(),
        'inactivated_at' => now()->subDay(),
        'superseded_by_assessment_id' => $working->id,
    ]);

    $this->artisan(
        'lauda360:reconcile-assessments',
        [
            '--organization' => 999103,
            '--apply' => true,
        ]
    )
        ->expectsOutputToContain('Cambios aplicados: 1')
        ->assertSuccessful();

    $this->artisan(
        'lauda360:reconcile-assessments',
        [
            '--organization' => 999103,
            '--apply' => true,
        ]
    )
        ->expectsOutputToContain(
            'No se encontraron assessments que requieran reconciliación.'
        )
        ->expectsOutputToContain('Candidatos: 0')
        ->expectsOutputToContain('Cambios aplicados: 0')
        ->assertSuccessful();

    $official->refresh();

    expect($official->is_active)
        ->toBeTrue()
        ->and($official->superseded_by_assessment_id)
        ->toBeNull();
});

test('reconciliation command rejects invalid organization option', function () {
    $this->artisan(
        'lauda360:reconcile-assessments',
        ['--organization' => 'abc']
    )
        ->expectsOutputToContain(
            '--organization debe ser un ID entero positivo.'
        )
        ->assertFailed();
});
