<?php

namespace App\Services\Diagnosis;

use App\Mail\DiagnosisInvitationMail;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiagnosisAccessService
{
    public function isDiagnosisContact(ContactRequest $contact): bool
    {
        $metadata = $contact->metadata ?? [];

        return ($metadata['request_type'] ?? null) === 'digital_transformation_diagnosis'
            || $contact->topic === 'Solicitud de Diagnóstico Digital 360';
    }

    public function workflowFor(ContactRequest $contact): DiagnosisAccessRequest
    {
        if (!$this->isDiagnosisContact($contact)) {
            abort(404);
        }

        return DiagnosisAccessRequest::firstOrCreate(
            ['contact_request_id' => $contact->id],
            ['status' => DiagnosisAccessRequest::STATUS_PENDING]
        );
    }

    public function approve(ContactRequest $contact, User $admin): DiagnosisAccessRequest
    {
        if (!$this->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DB::transaction(function () use ($contact, $admin): DiagnosisAccessRequest {
            $workflow = DiagnosisAccessRequest::query()
                ->where('contact_request_id', $contact->id)
                ->lockForUpdate()
                ->first();

            if (!$workflow) {
                $workflow = DiagnosisAccessRequest::create([
                    'contact_request_id' => $contact->id,
                    'status' => DiagnosisAccessRequest::STATUS_PENDING,
                ]);

                $workflow = DiagnosisAccessRequest::query()
                    ->whereKey($workflow->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($workflow->status === DiagnosisAccessRequest::STATUS_REJECTED) {
                throw ValidationException::withMessages([
                    'status' => ['La solicitud está rechazada. Cambie su estado antes de aprobarla.'],
                ]);
            }

            $email = strtolower(trim((string) $contact->email));

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($user && !in_array((string) $user->role, ['user', 'subscriber'], true)) {
                throw ValidationException::withMessages([
                    'email' => ['Este correo pertenece a una cuenta con un rol incompatible con el acceso de diagnóstico.'],
                ]);
            }

            if (!$user) {
                $user = User::create([
                    'name' => $contact->name ?: 'Cliente LAUDA 360',
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'user',
                    'must_change_password' => true,
                    'password_changed_at' => null,
                ]);
            }

            $assessment = $workflow->assessment;

            if (!$assessment) {
                $assessment = DiagnosisAssessment::create([
                    'user_id' => $user->id,
                    'organization_id' => null,
                    'organization_name' => trim((string) ($contact->company ?: 'Empresa por definir')),
                    'methodology_version' => '1.0',
                    'status' => 'draft',
                    'current_step' => 1,
                    'answers' => [],
                    'notes' => [],
                    'review_required' => false,
                ]);
            } elseif ((int) $assessment->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'assessment' => ['El diagnóstico ya está vinculado a otro usuario.'],
                ]);
            }

            $workflow->forceFill([
                'user_id' => $user->id,
                'diagnosis_assessment_id' => $assessment->id,
                'reviewed_by_user_id' => $admin->id,
                'status' => DiagnosisAccessRequest::STATUS_APPROVED,
                'approved_at' => $workflow->approved_at ?? now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            AuditService::log('diagnosis_access_approved', $workflow, [
                'contact_request_id' => $contact->id,
                'user_id' => $user->id,
                'diagnosis_assessment_id' => $assessment->id,
                'reviewed_by_user_id' => $admin->id,
            ]);

            return $workflow->fresh(['contactRequest', 'user', 'assessment']);
        });

        return $this->sendInvitation($workflow, $admin);
    }

    public function sendInvitation(DiagnosisAccessRequest $workflow, User $actor): DiagnosisAccessRequest
    {
        $workflow->loadMissing(['contactRequest', 'user', 'assessment']);

        if (!$workflow->user || !$workflow->assessment || !$workflow->contactRequest) {
            throw ValidationException::withMessages([
                'invitation' => ['La solicitud no tiene usuario, contacto y diagnóstico completos para enviar la invitación.'],
            ]);
        }

        if ($workflow->status === DiagnosisAccessRequest::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'status' => ['No se puede invitar una solicitud rechazada.'],
            ]);
        }

        $expiresAt = now()->addHours(72);

        $invitationUrl = URL::temporarySignedRoute(
            'diagnosis.invitation.accept',
            $expiresAt,
            ['access' => $workflow]
        );

        Mail::to($workflow->user->email)
            ->send(new DiagnosisInvitationMail($workflow, $invitationUrl));

        $meta = $workflow->meta ?? [];
        $meta['invitation_send_count'] = (int) ($meta['invitation_send_count'] ?? 0) + 1;
        $meta['last_invitation_sent_by_user_id'] = $actor->id;

        $workflow->forceFill([
            'status' => DiagnosisAccessRequest::STATUS_INVITED,
            'invitation_sent_at' => now(),
            'invitation_expires_at' => $expiresAt,
            'meta' => $meta,
        ])->save();

        AuditService::log('diagnosis_invitation_sent', $workflow, [
            'email' => $workflow->user->email,
            'expires_at' => $expiresAt->toISOString(),
            'sent_by_user_id' => $actor->id,
            'send_count' => $meta['invitation_send_count'],
        ]);

        return $workflow->fresh(['contactRequest', 'user', 'assessment']);
    }
}
