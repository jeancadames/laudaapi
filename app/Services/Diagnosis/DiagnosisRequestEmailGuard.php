<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiagnosisRequestEmailGuard
{
    public function blockingMessage(string $email): ?string
    {
        $email = $this->normalize($email);

        if ($email === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'role']);

        if (
            $user
            && !in_array((string) $user->role, ['user', 'subscriber'], true)
        ) {
            return 'Este correo no está disponible para una nueva solicitud de Diagnóstico LAUDA 360.';
        }

        $existing = DB::table('contact_requests as c')
            ->leftJoin(
                'diagnosis_access_requests as dar',
                'dar.contact_request_id',
                '=',
                'c.id'
            )
            ->whereRaw('LOWER(c.email) = ?', [$email])
            ->where(function ($query): void {
                $query
                    ->whereIn('c.topic', [
                        'Solicitud de acceso al Diagnóstico LAUDA 360',
                        'Solicitud de Diagnóstico Digital 360',
                    ])
                    ->orWhereIn('c.metadata->request_type', [
                        'digital_diagnosis_access_request',
                        'digital_transformation_diagnosis',
                    ]);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('dar.id')
                    ->orWhereNull('dar.status')
                    ->orWhere(
                        'dar.status',
                        '!=',
                        DiagnosisAccessRequest::STATUS_REJECTED
                    );
            })
            ->orderByDesc('c.id')
            ->first([
                'c.id',
                'dar.status as workflow_status',
            ]);

        if (!$existing) {
            return null;
        }

        return $this->messageForStatus(
            $existing->workflow_status
                ? (string) $existing->workflow_status
                : DiagnosisAccessRequest::STATUS_PENDING
        );
    }

    public function messageForStatus(?string $status): string
    {
        return match ($status) {
            DiagnosisAccessRequest::STATUS_PENDING,
            DiagnosisAccessRequest::STATUS_UNDER_REVIEW,
            DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED
                => 'Ya existe una solicitud de Diagnóstico LAUDA 360 asociada a este correo. No es necesario enviarla nuevamente.',

            DiagnosisAccessRequest::STATUS_APPROVED,
            DiagnosisAccessRequest::STATUS_INVITED
                => 'Este correo ya tiene una solicitud de Diagnóstico LAUDA 360 aprobada o invitada. Revise su correo para continuar.',

            DiagnosisAccessRequest::STATUS_ACTIVE
                => 'Este correo ya tiene acceso al Diagnóstico LAUDA 360. Utilice Iniciar sesión o el enlace de acceso recibido para continuar.',

            default
                => 'Ya existe una solicitud o acceso de Diagnóstico LAUDA 360 asociado a este correo.',
        };
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
