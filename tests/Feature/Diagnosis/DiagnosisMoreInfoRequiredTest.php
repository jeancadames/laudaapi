<?php

use App\Mail\DiagnosisMoreInfoRequiredMail;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;

uses(DatabaseTransactions::class);

function diagnosisMoreInfoAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function diagnosisMoreInfoTenant(): User
{
    return User::factory()->create([
        'role' => 'user',
        'email_verified_at' => now(),
    ]);
}

function diagnosisMoreInfoContact(
    string $email,
    string $company
): ContactRequest {
    return ContactRequest::create([
        'name' => 'Contacto Diagnóstico',
        'email' => $email,
        'phone' => '8095550101',
        'company' => $company,
        'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
        'message' => 'Solicitud para prueba automatizada.',
        'terms' => true,
        'metadata' => [
            'source' => 'laudaapi.com',
            'request_type' => 'digital_diagnosis_access_request',
        ],
    ]);
}

function diagnosisMoreInfoRequiredQuestionIds(): array
{
    $ids = [];

    foreach (
        config('lauda360_diagnosis.dimensions', [])
        as $dimension
    ) {
        $ids = array_merge(
            $ids,
            $dimension['questions'] ?? []
        );
    }

    $ids = array_merge(
        $ids,
        config('lauda360_diagnosis.capacity_questions', []),
        config('lauda360_diagnosis.urgency_questions', [])
    );

    return array_values(array_unique($ids));
}

function diagnosisMoreInfoIncompleteWorkflow(): array
{
    $admin = diagnosisMoreInfoAdmin();
    $user = diagnosisMoreInfoTenant();

    $assessment = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => null,
        'organization_name' => 'Empresa Incompleta SRL',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'draft',
        'is_active' => true,
        'current_step' => 1,
        'answers' => [],
        'notes' => [],
    ]);

    $contact = diagnosisMoreInfoContact(
        $user->email,
        'Empresa Incompleta SRL'
    );

    $workflow = DiagnosisAccessRequest::create([
        'contact_request_id' => $contact->id,
        'user_id' => $user->id,
        'diagnosis_assessment_id' => $assessment->id,
        'status' => DiagnosisAccessRequest::STATUS_UNDER_REVIEW,
    ]);

    return compact(
        'admin',
        'user',
        'contact',
        'assessment',
        'workflow'
    );
}

function diagnosisMoreInfoCompleteWorkflow(): array
{
    $admin = diagnosisMoreInfoAdmin();
    $user = diagnosisMoreInfoTenant();

    $answers = [];

    foreach (diagnosisMoreInfoRequiredQuestionIds() as $questionId) {
        $answers[$questionId] = 3;
    }

    $assessment = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => null,
        'organization_name' => 'Empresa Completa SRL',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'submitted',
        'is_active' => true,
        'current_step' => (int) config(
            'lauda360_diagnosis.steps',
            11
        ),
        'answers' => $answers,
        'notes' => [],
        'business_activity_type' => 'services',
        'business_sector' => 'professional_services',
        'customer_market' => 'b2b',
        'sales_channels' => [
            array_key_first(
                config('lauda360_business_profile.sales_channels', [])
            ),
        ],
        'business_activity_description' =>
            'Empresa dedicada a servicios profesionales para clientes empresariales.',
        'business_profile_completed_at' => now(),
        'submitted_at' => now(),
    ]);

    $contact = diagnosisMoreInfoContact(
        $user->email,
        'Empresa Completa SRL'
    );

    $workflow = DiagnosisAccessRequest::create([
        'contact_request_id' => $contact->id,
        'user_id' => $user->id,
        'diagnosis_assessment_id' => $assessment->id,
        'status' => DiagnosisAccessRequest::STATUS_UNDER_REVIEW,
    ]);

    return compact(
        'admin',
        'user',
        'contact',
        'assessment',
        'workflow'
    );
}

test('admin can request more information and mail is queued', function () {
    Mail::fake();

    [
        'admin' => $admin,
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
        'workflow' => $workflow,
    ] = diagnosisMoreInfoIncompleteWorkflow();

    $this
        ->actingAs($admin)
        ->post(
            route('admin.diagnosis_requests.status', $contact),
            [
                'status' =>
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                'review_notes' =>
                    'Completa la información pendiente para continuar.',
            ]
        )
        ->assertRedirect();

    $workflow->refresh();

    expect($workflow->status)
        ->toBe(DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED)
        ->and($workflow->reviewed_by_user_id)
        ->toBe($admin->id)
        ->and($workflow->review_notes)
        ->toBe('Completa la información pendiente para continuar.');

    Mail::assertQueued(
        DiagnosisMoreInfoRequiredMail::class,
        function (DiagnosisMoreInfoRequiredMail $mail) use (
            $user,
            $workflow
        ): bool {
            return $mail->hasTo($user->email)
                && $mail->workflow->is($workflow)
                && $mail->missingInformation['complete'] === false
                && count(
                    $mail->missingInformation['missing_answers']
                ) > 0;
        }
    );

    Mail::assertQueued(DiagnosisMoreInfoRequiredMail::class, 1);

    $assessment->refresh();

    expect($assessment->published_at)
        ->toBeNull()
        ->and($assessment->superseded_by_assessment_id)
        ->toBeNull();
});

test('repeating more info required does not queue duplicate mail', function () {
    Mail::fake();

    [
        'admin' => $admin,
        'contact' => $contact,
    ] = diagnosisMoreInfoIncompleteWorkflow();

    $this
        ->actingAs($admin)
        ->post(
            route('admin.diagnosis_requests.status', $contact),
            [
                'status' =>
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                'review_notes' => 'Primera solicitud.',
            ]
        )
        ->assertRedirect();

    Mail::assertQueued(DiagnosisMoreInfoRequiredMail::class, 1);

    $this
        ->actingAs($admin)
        ->post(
            route('admin.diagnosis_requests.status', $contact),
            [
                'status' =>
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                'review_notes' =>
                    'Actualización de observaciones.',
            ]
        )
        ->assertRedirect();

    Mail::assertQueued(DiagnosisMoreInfoRequiredMail::class, 1);
});

test('complete assessment can receive manual review note without fake missing items', function () {
    Mail::fake();

    [
        'admin' => $admin,
        'user' => $user,
        'contact' => $contact,
        'assessment' => $assessment,
    ] = diagnosisMoreInfoCompleteWorkflow();

    $this
        ->actingAs($admin)
        ->post(
            route('admin.diagnosis_requests.status', $contact),
            [
                'status' =>
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                'review_notes' =>
                    'Necesitamos confirmar un dato con tu equipo.',
            ]
        )
        ->assertRedirect();

    Mail::assertQueued(
        DiagnosisMoreInfoRequiredMail::class,
        function (DiagnosisMoreInfoRequiredMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->missingInformation['complete'] === true
                && $mail->missingInformation['business_profile'] === []
                && $mail->missingInformation['missing_answers'] === []
                && $mail->reviewNotes ===
                    'Necesitamos confirmar un dato con tu equipo.';
        }
    );

    $assessment->refresh();

    expect($assessment->published_at)->toBeNull();
});

test('more info required does not supersede existing official assessment', function () {
    Mail::fake();

    $admin = diagnosisMoreInfoAdmin();
    $user = diagnosisMoreInfoTenant();

    $official = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => 9001,
        'organization_name' => 'Tenant Lifecycle SRL',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'reviewed',
        'is_active' => true,
        'current_step' => (int) config(
            'lauda360_diagnosis.steps',
            11
        ),
        'answers' => [],
        'notes' => [],
        'published_at' => now()->subDay(),
        'reviewed_at' => now()->subDay(),
        'submitted_at' => now()->subDays(2),
    ]);

    $working = DiagnosisAssessment::create([
        'user_id' => $user->id,
        'organization_id' => 9001,
        'organization_name' => 'Tenant Lifecycle SRL',
        'methodology_version' => (string) config(
            'lauda360_diagnosis.version'
        ),
        'status' => 'draft',
        'is_active' => true,
        'current_step' => 1,
        'answers' => [],
        'notes' => [],
    ]);

    $contact = diagnosisMoreInfoContact(
        'lifecycle@example.com',
        'Tenant Lifecycle SRL'
    );

    $workflow = DiagnosisAccessRequest::create([
        'contact_request_id' => $contact->id,
        'user_id' => $user->id,
        'diagnosis_assessment_id' => $working->id,
        'status' => DiagnosisAccessRequest::STATUS_UNDER_REVIEW,
    ]);

    $this
        ->actingAs($admin)
        ->post(
            route('admin.diagnosis_requests.status', $contact),
            [
                'status' =>
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                'review_notes' =>
                    'Completa el nuevo diagnóstico.',
            ]
        )
        ->assertRedirect();

    $official->refresh();
    $working->refresh();
    $workflow->refresh();

    expect($workflow->status)
        ->toBe(DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED)
        ->and((bool) $official->is_active)
        ->toBeTrue()
        ->and($official->published_at)
        ->not->toBeNull()
        ->and($official->superseded_by_assessment_id)
        ->toBeNull()
        ->and((bool) $working->is_active)
        ->toBeTrue()
        ->and($working->published_at)
        ->toBeNull()
        ->and($working->superseded_by_assessment_id)
        ->toBeNull();

    Mail::assertQueued(DiagnosisMoreInfoRequiredMail::class, 1);
});
