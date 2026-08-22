<?php

namespace Tests\Feature\Diagnosis;

use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiagnosisAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_reports_only_lauda360_diagnosis_flow(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $prospect = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        ContactRequest::create([
            'name' => 'Contacto general',
            'email' => 'general@example.com',
            'phone' => '8095550000',
            'company' => 'General SRL',
            'topic' => 'Demo - Varias soluciones',
            'message' => 'No pertenece al diagnóstico.',
            'terms' => true,
            'metadata' => [
                'source' => 'laudaapi.com',
                'request_type' => 'Demo',
            ],
        ]);

        $pendingContact = ContactRequest::create([
            'name' => 'Empresa Pendiente',
            'email' => 'pendiente@example.com',
            'phone' => '8095550001',
            'company' => 'Empresa Pendiente SRL',
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'message' => 'Solicitud de diagnóstico.',
            'terms' => true,
            'metadata' => [
                'source' => 'laudaapi.com',
                'request_type' => 'digital_diagnosis_access_request',
            ],
        ]);

        $completedContact = ContactRequest::create([
            'name' => 'Empresa Completada',
            'email' => 'completada@example.com',
            'phone' => '8095550002',
            'company' => 'Empresa Completada SRL',
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'message' => 'Solicitud de diagnóstico.',
            'terms' => true,
            'metadata' => [
                'source' => 'laudaapi.com',
                'request_type' => 'digital_diagnosis_access_request',
            ],
        ]);

        $assessment = DiagnosisAssessment::create([
            'user_id' => $prospect->id,
            'organization_id' => null,
            'organization_name' => 'Empresa Completada SRL',
            'methodology_version' => '1.0',
            'status' => 'submitted',
            'current_step' => 11,
            'answers' => [],
            'notes' => [],
            'maturity_score' => 62,
            'capacity_score' => 55,
            'urgency_score' => 70,
            'dimension_scores' => [],
            'maturity_level' => 'Empresa Conectada',
            'urgency_level' => 'Alta',
            'recommended_modality' => 'assisted',
            'recommended_modality_label' => 'Asistido',
            'review_required' => true,
            'submitted_at' => now(),
        ]);

        DiagnosisAccessRequest::create([
            'contact_request_id' => $completedContact->id,
            'user_id' => $prospect->id,
            'diagnosis_assessment_id' => $assessment->id,
            'reviewed_by_user_id' => $admin->id,
            'status' => DiagnosisAccessRequest::STATUS_ACTIVE,
            'approved_at' => now()->subHour(),
            'invitation_sent_at' => now()->subMinutes(50),
            'invitation_expires_at' => now()->addHours(71),
            'invitation_accepted_at' => now()->subMinutes(40),
        ]);

        $this
            ->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Dashboard')
                    ->where('stats.requests.total', 2)
                    ->where('stats.requests.pending', 1)
                    ->where('stats.requests.review_queue', 1)
                    ->where('stats.access.active', 1)
                    ->where('stats.assessments.submitted', 1)
                    ->where('stats.assessments.completed', 1)
                    ->where('stats.assessments.results_to_review', 1)
                    ->where('stats.modalities.assisted', 1)
                    ->has('recentRequests', 2)
                    ->where(
                        'recentRequests.0.id',
                        $completedContact->id
                    )
                    ->where(
                        'recentRequests.1.id',
                        $pendingContact->id
                    )
            );
    }

    public function test_non_admin_cannot_open_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }
}
