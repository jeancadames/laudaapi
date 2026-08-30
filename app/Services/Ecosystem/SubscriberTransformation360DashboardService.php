<?php

namespace App\Services\Ecosystem;

use App\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SubscriberTransformation360DashboardService
{
    private const PUBLIC_PLAN_STATUSES = [
        'presented',
        'accepted',
        'active',
        'completed',
    ];

    public function forCompany(
        Company $company,
        int $userId
    ): array {
        if (
            ! $this->schemaReady()
            || ! $this->canView($company, $userId)
        ) {
            return $this->emptyPayload(false);
        }

        $access = $this->latestAccessForCompany($company);
        $assessmentIds = $this->assessmentIdsForCompany($company, $access);

        $assessment = $assessmentIds->isNotEmpty()
            ? DB::table('diagnosis_assessments')
                ->whereIn('id', $assessmentIds->all())
                ->orderByDesc('id')
                ->first()
            : null;

        if (! $assessment) {
            return $this->availablePayload($access, $company);
        }

        $expandedReport = DB::table('diagnosis_expanded_reports')
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        $roadmap = DB::table('diagnosis_detailed_roadmaps')
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        $plan = DB::table('transformation_implementation_plans')
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        $diagnosisPublished = (string) $assessment->status === 'reviewed'
            && $assessment->published_at !== null;

        $planIsPublic = $plan
            && in_array((string) $plan->status, self::PUBLIC_PLAN_STATUSES, true)
            && $plan->presented_at !== null;

        $stages = [
            [
                'key' => 'diagnosis',
                'label' => 'Diagnóstico 360',
                'state' => $diagnosisPublished ? 'completed' : 'current',
                'status_label' => $diagnosisPublished
                    ? 'Publicado'
                    : $this->diagnosisStatusLabel((string) $assessment->status),
                'description' => $diagnosisPublished
                    ? 'Resultado oficial del Diagnóstico 360 disponible.'
                    : 'El Diagnóstico 360 se encuentra en proceso.',
                'optional' => false,
                'url' => route('diagnosis.show', $assessment->id, false),
                'action_label' => 'Ver diagnóstico',
            ],
            [
                'key' => 'expanded_report',
                'label' => 'Informe Ampliado',
                'state' => $expandedReport
                    ? 'completed'
                    : ($diagnosisPublished ? 'available' : 'pending'),
                'status_label' => $expandedReport
                    ? 'Publicado'
                    : ($diagnosisPublished ? 'En preparación' : 'Pendiente'),
                'description' => $expandedReport
                    ? 'Informe Ampliado gratuito publicado y disponible.'
                    : 'Entregable gratuito que profundiza hallazgos, riesgos y oportunidades.',
                'optional' => false,
                'url' => $expandedReport
                    ? route('diagnosis.expanded_report.show', $assessment->id, false)
                    : null,
                'action_label' => $expandedReport ? 'Ver informe' : null,
            ],
            [
                'key' => 'roadmap',
                'label' => 'Roadmap Detallado',
                'state' => $roadmap
                    ? 'completed'
                    : ($expandedReport ? 'available' : 'pending'),
                'status_label' => $roadmap
                    ? 'Publicado'
                    : ($expandedReport ? 'En preparación' : 'Pendiente'),
                'description' => $roadmap
                    ? 'Roadmap Detallado gratuito publicado y disponible.'
                    : 'Entregable gratuito que organiza prioridades, fases e iniciativas.',
                'optional' => false,
                'url' => $roadmap
                    ? route('diagnosis.detailed_roadmap.show', $assessment->id, false)
                    : null,
                'action_label' => $roadmap ? 'Ver roadmap' : null,
            ],
            [
                'key' => 'implementation_plan',
                'label' => 'Plan de Implementación',
                'state' => $planIsPublic
                    ? (($plan->status ?? null) === 'completed' ? 'completed' : 'available')
                    : ($plan ? 'current' : 'pending'),
                'status_label' => $planIsPublic
                    ? (($plan->status ?? null) === 'completed' ? 'Completado' : 'Presentado')
                    : ($plan ? 'En preparación' : 'Pendiente'),
                'description' => $planIsPublic
                    ? 'Plan consultivo gratuito presentado para revisión.'
                    : 'Documento gratuito con fases, actividades, responsables, dependencias y entregables.',
                'optional' => false,
                'url' => $planIsPublic
                    ? route('diagnosis.implementation_plan.show', $assessment->id, false)
                    : null,
                'action_label' => $planIsPublic ? 'Ver Plan' : null,
            ],
        ];

        return [
            'visible' => true,
            'has_workflow' => true,
            'assessment_id' => (int) $assessment->id,
            'organization_name' => (string) (
                $assessment->organization_name ?: $company->name
            ),
            'current_label' => $this->currentLabel(
                $diagnosisPublished,
                (bool) $expandedReport,
                (bool) $roadmap,
                (bool) $planIsPublic
            ),
            'plan_public' => (bool) $planIsPublic,
            'stages' => $stages,
            'primary_action' => $this->primaryAction($stages),
        ];
    }

    private function canView(Company $company, int $userId): bool
    {
        if ((int) $company->owner_user_id === $userId) {
            return true;
        }

        if (! $company->subscriber_id) {
            return false;
        }

        return DB::table('subscriber_user')
            ->where('subscriber_id', $company->subscriber_id)
            ->where('user_id', $userId)
            ->where('active', 1)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    private function latestAccessForCompany(Company $company): ?object
    {
        return $this->accessQuery($company)
            ->orderByDesc('id')
            ->first();
    }

    private function accessQuery(Company $company): Builder
    {
        return DB::table('diagnosis_access_requests')
            ->where(function (Builder $query) use ($company): void {
                $query->whereRaw(
                    "JSON_VALID(meta) AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.company_id')) = ?",
                    [(string) $company->id]
                );

                if ($company->subscriber_id) {
                    $query->orWhereRaw(
                        "JSON_VALID(meta) AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.subscriber_id')) = ?",
                        [(string) $company->subscriber_id]
                    );
                }
            });
    }

    private function assessmentIdsForCompany(
        Company $company,
        ?object $access
    ): Collection {
        $ids = collect();

        if ($access && $access->diagnosis_assessment_id) {
            $ids->push((int) $access->diagnosis_assessment_id);
        }

        $ids = $ids->merge(
            $this->accessQuery($company)
                ->whereNotNull('diagnosis_assessment_id')
                ->pluck('diagnosis_assessment_id')
        );

        // Compatibilidad histórica: órdenes antiguas pueden ayudar a localizar
        // diagnósticos anteriores, pero ya no son requisito del flujo gratuito.
        foreach ([
            'diagnosis_expanded_report_orders',
            'diagnosis_detailed_roadmap_orders',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table)
                ->where('company_id', $company->id);

            if ($company->subscriber_id) {
                $query->where('subscriber_id', $company->subscriber_id);
            }

            $ids = $ids->merge($query->pluck('diagnosis_assessment_id'));
        }

        return $ids
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function currentLabel(
        bool $diagnosis,
        bool $expanded,
        bool $roadmap,
        bool $plan
    ): string {
        if ($plan) {
            return 'Plan de Implementación disponible';
        }
        if ($roadmap) {
            return 'Preparando Plan de Implementación';
        }
        if ($expanded) {
            return 'Preparando Roadmap Detallado';
        }
        if ($diagnosis) {
            return 'Preparando Informe Ampliado';
        }

        return 'Diagnóstico 360 en proceso';
    }

    private function primaryAction(array $stages): ?array
    {
        foreach (array_reverse($stages) as $stage) {
            if (! empty($stage['url']) && ! empty($stage['action_label'])) {
                return [
                    'url' => $stage['url'],
                    'label' => $stage['action_label'],
                ];
            }
        }

        return null;
    }

    private function diagnosisStatusLabel(string $status): string
    {
        return match ($status) {
            'draft', 'started' => 'En proceso',
            'submitted' => 'En revisión',
            'reviewed' => 'Revisado',
            default => 'Pendiente',
        };
    }

    private function availablePayload(?object $access, Company $company): array
    {
        return [
            'visible' => true,
            'has_workflow' => false,
            'assessment_id' => null,
            'organization_name' => $company->name,
            'current_label' => $access
                ? 'Preparando Diagnóstico 360'
                : 'Diagnóstico 360 disponible',
            'plan_public' => false,
            'stages' => [
                [
                    'key' => 'diagnosis',
                    'label' => 'Diagnóstico 360',
                    'state' => 'current',
                    'status_label' => $access ? 'En preparación' : 'Disponible',
                    'description' => 'Punto de partida del recorrido consultivo LAUDA 360.',
                    'optional' => false,
                    'url' => '/app/diagnostico-360',
                    'action_label' => $access ? 'Ver diagnóstico' : 'Iniciar diagnóstico',
                ],
                [
                    'key' => 'expanded_report',
                    'label' => 'Informe Ampliado',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Entregable gratuito posterior al diagnóstico oficial.',
                    'optional' => false,
                    'url' => null,
                    'action_label' => null,
                ],
                [
                    'key' => 'roadmap',
                    'label' => 'Roadmap Detallado',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Entregable gratuito de prioridades y fases.',
                    'optional' => false,
                    'url' => null,
                    'action_label' => null,
                ],
                [
                    'key' => 'implementation_plan',
                    'label' => 'Plan de Implementación',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Entregable consultivo gratuito para organizar la transformación.',
                    'optional' => false,
                    'url' => null,
                    'action_label' => null,
                ],
            ],
            'primary_action' => [
                'url' => '/app/diagnostico-360',
                'label' => $access ? 'Ver diagnóstico' : 'Iniciar diagnóstico',
            ],
        ];
    }

    private function emptyPayload(bool $visible): array
    {
        return [
            'visible' => $visible,
            'has_workflow' => false,
            'assessment_id' => null,
            'organization_name' => null,
            'current_label' => null,
            'plan_public' => false,
            'stages' => [],
            'primary_action' => null,
        ];
    }

    private function schemaReady(): bool
    {
        foreach ([
            'subscriber_user',
            'diagnosis_access_requests',
            'diagnosis_assessments',
            'diagnosis_expanded_reports',
            'diagnosis_detailed_roadmaps',
            'transformation_implementation_plans',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
