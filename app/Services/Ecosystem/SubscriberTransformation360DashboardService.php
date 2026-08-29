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

        $assessmentIds =
            $this->assessmentIdsForCompany(
                $company,
                $access
            );

        $assessment = $assessmentIds->isNotEmpty()
            ? DB::table('diagnosis_assessments')
                ->whereIn('id', $assessmentIds->all())
                ->orderByDesc('id')
                ->first()
            : null;

        if (! $assessment) {
            return $this->availablePayload(
                $access
            );
        }

        $expandedReport =
            DB::table('diagnosis_expanded_reports')
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        $roadmap =
            DB::table('diagnosis_detailed_roadmaps')
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        /*
         * Importante:
         * Sí leemos un draft para poder decirle al cliente
         * "En preparación", pero NUNCA exponemos el id, contenido
         * ni URL del draft.
         */
        $plan =
            DB::table('transformation_implementation_plans')
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        $planIsPublic =
            $plan
            && in_array(
                (string) $plan->status,
                self::PUBLIC_PLAN_STATUSES,
                true
            )
            && $plan->presented_at !== null;

        $execution =
            $planIsPublic
                ? $this->executionSummary(
                    (int) $plan->id
                )
                : [
                    'progress_percentage' => 0,
                    'phase_count' => 0,
                    'completed_phase_count' => 0,
                ];

        $diagnosisPublished =
            (string) $assessment->status === 'reviewed'
            && $assessment->published_at !== null;

        $diagnosisState =
            $diagnosisPublished
                ? 'completed'
                : 'current';

        $expandedState =
            $expandedReport
                ? 'completed'
                : (
                    $diagnosisPublished
                        ? 'available'
                        : 'pending'
                );

        $roadmapState =
            $roadmap
                ? 'completed'
                : (
                    $expandedReport
                        ? 'available'
                        : 'pending'
                );

        [$planState, $planLabel, $planDescription] =
            $this->planStage(
                $plan,
                $diagnosisPublished
            );

        [$executionState, $executionLabel, $executionDescription] =
            $this->executionStage(
                $plan,
                $planIsPublic,
                $execution
            );

        $planUrl =
            $planIsPublic
                ? route(
                    'diagnosis.implementation_plan.show',
                    $assessment->id,
                    false
                )
                : null;

        $stages = [
            [
                'key' => 'diagnosis',
                'label' => 'Diagnóstico 360',
                'state' => $diagnosisState,
                'status_label' =>
                    $diagnosisPublished
                        ? 'Publicado'
                        : $this->diagnosisStatusLabel(
                            (string) $assessment->status
                        ),
                'description' =>
                    $diagnosisPublished
                        ? 'Resultado oficial del Diagnóstico LAUDA 360 disponible.'
                        : 'El Diagnóstico LAUDA 360 se encuentra en proceso.',
                'optional' => false,
                'url' => route(
                    'diagnosis.show',
                    $assessment->id,
                    false
                ),
                'action_label' => 'Ver diagnóstico',
            ],

            [
                'key' => 'expanded_report',
                'label' => 'Informe Ampliado',
                'state' => $expandedState,
                'status_label' =>
                    $expandedReport
                        ? 'Publicado'
                        : (
                            $diagnosisPublished
                                ? 'Opcional'
                                : 'Pendiente'
                        ),
                'description' =>
                    $expandedReport
                        ? 'Informe Ampliado publicado y disponible.'
                        : 'Entregable opcional posterior al diagnóstico.',
                'optional' => true,
                'url' =>
                    $expandedReport
                        ? route(
                            'diagnosis.expanded_report.show',
                            $assessment->id,
                            false
                        )
                        : null,
                'action_label' =>
                    $expandedReport
                        ? 'Ver informe'
                        : null,
            ],

            [
                'key' => 'roadmap',
                'label' => 'Roadmap Detallado',
                'state' => $roadmapState,
                'status_label' =>
                    $roadmap
                        ? 'Publicado'
                        : (
                            $expandedReport
                                ? 'Opcional'
                                : 'Pendiente'
                        ),
                'description' =>
                    $roadmap
                        ? 'Roadmap Detallado publicado y disponible.'
                        : 'Entregable opcional. No bloquea la creación del Plan.',
                'optional' => true,
                'url' =>
                    $roadmap
                        ? route(
                            'diagnosis.detailed_roadmap.show',
                            $assessment->id,
                            false
                        )
                        : null,
                'action_label' =>
                    $roadmap
                        ? 'Ver roadmap'
                        : null,
            ],

            [
                'key' => 'implementation_plan',
                'label' => 'Plan de Implementación',
                'state' => $planState,
                'status_label' => $planLabel,
                'description' => $planDescription,
                'optional' => false,
                'url' => $planUrl,
                'action_label' =>
                    $planUrl
                        ? 'Ver Plan'
                        : null,
            ],

            [
                'key' => 'execution',
                'label' => 'Ejecución',
                'state' => $executionState,
                'status_label' => $executionLabel,
                'description' => $executionDescription,
                'optional' => false,
                'url' => $planUrl,
                'action_label' =>
                    $planUrl
                    && in_array(
                        (string) ($plan->status ?? ''),
                        ['accepted', 'active', 'completed'],
                        true
                    )
                        ? 'Ver seguimiento'
                        : null,
            ],
        ];

        $primaryAction =
            $this->primaryAction(
                $assessment,
                $plan,
                $planUrl
            );

        return [
            'visible' => true,
            'has_workflow' => true,

            'assessment_id' =>
                (int) $assessment->id,

            'organization_name' =>
                (string) (
                    $assessment->organization_name
                    ?: $company->name
                ),

            'current_label' =>
                $this->currentLabel(
                    $assessment,
                    $plan
                ),

            'plan_public' =>
                (bool) $planIsPublic,

            'execution' =>
                $execution,

            'stages' =>
                $stages,

            'primary_action' =>
                $primaryAction,
        ];
    }

    private function canView(
        Company $company,
        int $userId
    ): bool {
        if (
            (int) $company->owner_user_id
            === $userId
        ) {
            return true;
        }

        if (! $company->subscriber_id) {
            return false;
        }

        return DB::table('subscriber_user')
            ->where(
                'subscriber_id',
                $company->subscriber_id
            )
            ->where('user_id', $userId)
            ->where('active', 1)
            ->whereIn(
                'role',
                ['owner', 'admin']
            )
            ->exists();
    }

    private function latestAccessForCompany(
        Company $company
    ): ?object {
        return $this->accessQuery($company)
            ->orderByDesc('id')
            ->first();
    }

    private function accessQuery(
        Company $company
    ): Builder {
        return DB::table(
            'diagnosis_access_requests'
        )->where(
            function (Builder $query) use (
                $company
            ): void {
                $query->whereRaw(
                    "JSON_VALID(meta) "
                    ."AND JSON_UNQUOTE("
                    ."JSON_EXTRACT(meta, '$.company_id')"
                    .") = ?",
                    [(string) $company->id]
                );

                if ($company->subscriber_id) {
                    $query->orWhereRaw(
                        "JSON_VALID(meta) "
                        ."AND JSON_UNQUOTE("
                        ."JSON_EXTRACT(meta, '$.subscriber_id')"
                        .") = ?",
                        [
                            (string)
                            $company->subscriber_id,
                        ]
                    );
                }
            }
        );
    }

    private function assessmentIdsForCompany(
        Company $company,
        ?object $access
    ): Collection {
        $ids = collect();

        if (
            $access
            && $access->diagnosis_assessment_id
        ) {
            $ids->push(
                (int)
                $access->diagnosis_assessment_id
            );
        }

        $ids = $ids->merge(
            $this->accessQuery($company)
                ->whereNotNull(
                    'diagnosis_assessment_id'
                )
                ->pluck(
                    'diagnosis_assessment_id'
                )
        );

        foreach ([
            'diagnosis_expanded_report_orders',
            'diagnosis_detailed_roadmap_orders',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table)
                ->where(
                    'company_id',
                    $company->id
                );

            if ($company->subscriber_id) {
                $query->where(
                    'subscriber_id',
                    $company->subscriber_id
                );
            }

            $ids = $ids->merge(
                $query->pluck(
                    'diagnosis_assessment_id'
                )
            );
        }

        return $ids
            ->filter()
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->values();
    }

    private function executionSummary(
        int $planId
    ): array {
        $phaseIds =
            DB::table(
                'transformation_implementation_phases'
            )
                ->where(
                    'transformation_implementation_plan_id',
                    $planId
                )
                ->orderBy('sequence')
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                );

        if ($phaseIds->isEmpty()) {
            return [
                'progress_percentage' => 0,
                'phase_count' => 0,
                'completed_phase_count' => 0,
            ];
        }

        $latestExecutions =
            DB::table(
                'transformation_implementation_phase_executions'
            )
                ->whereIn(
                    'transformation_implementation_phase_id',
                    $phaseIds->all()
                )
                ->orderByDesc('id')
                ->get()
                ->unique(
                    'transformation_implementation_phase_id'
                )
                ->keyBy(
                    'transformation_implementation_phase_id'
                );

        $progressTotal = 0.0;
        $completed = 0;

        foreach ($phaseIds as $phaseId) {
            $execution =
                $latestExecutions->get(
                    $phaseId
                );

            $progress =
                (float) (
                    $execution
                        ?->progress_percentage
                    ?? 0
                );

            $progressTotal += $progress;

            if (
                (string) (
                    $execution?->status
                    ?? ''
                ) === 'completed'
                || $progress >= 100
            ) {
                $completed++;
            }
        }

        return [
            'progress_percentage' =>
                round(
                    $progressTotal
                    / $phaseIds->count(),
                    2
                ),

            'phase_count' =>
                $phaseIds->count(),

            'completed_phase_count' =>
                $completed,
        ];
    }

    private function planStage(
        ?object $plan,
        bool $diagnosisPublished
    ): array {
        if (! $plan) {
            return [
                $diagnosisPublished
                    ? 'current'
                    : 'pending',

                $diagnosisPublished
                    ? 'Pendiente de preparación'
                    : 'Pendiente',

                $diagnosisPublished
                    ? 'LAUDA puede preparar el Plan directamente desde el Diagnóstico oficial.'
                    : 'Disponible después del resultado oficial del Diagnóstico.',
            ];
        }

        return match (
            (string) $plan->status
        ) {
            'draft' => [
                'current',
                'En preparación',
                'LAUDA está preparando el Plan. El borrador administrativo no es visible para el cliente.',
            ],

            'presented' => [
                'current',
                'Disponible para revisión',
                'El Plan fue presentado y está disponible para revisión.',
            ],

            'accepted' => [
                'completed',
                'Aceptado',
                'El Plan de Implementación fue aceptado.',
            ],

            'active' => [
                'completed',
                'En ejecución',
                'El Plan está siendo ejecutado.',
            ],

            'completed' => [
                'completed',
                'Completado',
                'El Plan de Implementación fue completado.',
            ],

            default => [
                'pending',
                'Pendiente',
                'Plan de Implementación pendiente.',
            ],
        };
    }

    private function executionStage(
        ?object $plan,
        bool $planIsPublic,
        array $execution
    ): array {
        if (! $plan || ! $planIsPublic) {
            return [
                'pending',
                'Pendiente',
                'La ejecución comienza después de presentar y aceptar el Plan.',
            ];
        }

        return match (
            (string) $plan->status
        ) {
            'presented' => [
                'pending',
                'Pendiente de aceptación',
                'El cliente debe revisar y aceptar el Plan antes de iniciar ejecución.',
            ],

            'accepted' => [
                'current',
                'Pendiente de inicio',
                'El Plan está aceptado y listo para comenzar su ejecución.',
            ],

            'active' => [
                'current',
                'En ejecución',
                'Avance general: '
                .$execution['progress_percentage']
                .'%.',
            ],

            'completed' => [
                'completed',
                'Completada',
                'La ejecución del Plan fue completada.',
            ],

            default => [
                'pending',
                'Pendiente',
                'Ejecución pendiente.',
            ],
        };
    }

    private function diagnosisStatusLabel(
        string $status
    ): string {
        return match ($status) {
            'submitted' =>
                'En revisión',

            'in_progress' =>
                'En progreso',

            'reviewed' =>
                'Revisado',

            'draft' =>
                'En preparación',

            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $status
                    )
                ),
        };
    }

    private function currentLabel(
        object $assessment,
        ?object $plan
    ): string {
        if ($plan) {
            return match (
                (string) $plan->status
            ) {
                'draft' =>
                    'Plan de Implementación en preparación',

                'presented' =>
                    'Plan disponible para revisión',

                'accepted' =>
                    'Plan aceptado · listo para ejecución',

                'active' =>
                    'Implementación en curso',

                'completed' =>
                    'Transformación completada',

                default =>
                    'Transformación Digital 360',
            };
        }

        if (
            (string) $assessment->status
                === 'reviewed'
            && $assessment->published_at
        ) {
            return
                'Diagnóstico publicado · Plan de Implementación pendiente';
        }

        return match (
            (string) $assessment->status
        ) {
            'submitted' =>
                'Diagnóstico en revisión',

            'in_progress' =>
                'Diagnóstico en progreso',

            default =>
                'Diagnóstico 360',
        };
    }

    private function primaryAction(
        object $assessment,
        ?object $plan,
        ?string $planUrl
    ): array {
        if ($planUrl) {
            return [
                'label' =>
                    (string) ($plan->status ?? '')
                        === 'presented'
                        ? 'Revisar Plan'
                        : 'Ver Plan',

                'url' =>
                    $planUrl,
            ];
        }

        return [
            'label' =>
                'Ver Diagnóstico 360',

            'url' =>
                route(
                    'diagnosis.show',
                    $assessment->id,
                    false
                ),
        ];
    }

    private function availablePayload(
        ?object $access
    ): array {
        $requested = $access !== null;

        return [
            'visible' => true,
            'has_workflow' => $requested,
            'assessment_id' => null,
            'organization_name' => null,

            'current_label' =>
                $requested
                    ? 'Diagnóstico 360 solicitado'
                    : 'Diagnóstico 360 disponible',

            'plan_public' => false,

            'execution' => [
                'progress_percentage' => 0,
                'phase_count' => 0,
                'completed_phase_count' => 0,
            ],

            'stages' => [
                [
                    'key' => 'diagnosis',
                    'label' => 'Diagnóstico 360',
                    'state' => 'current',
                    'status_label' =>
                        $requested
                            ? 'Solicitado'
                            : 'Disponible',
                    'description' =>
                        $requested
                            ? 'La solicitud del Diagnóstico LAUDA 360 está registrada.'
                            : 'Evalúa la situación actual de tu empresa y define el punto de partida.',
                    'optional' => false,
                    'url' =>
                        route(
                            'app.diagnosis.show',
                            [],
                            false
                        ),
                    'action_label' =>
                        $requested
                            ? 'Ver estado'
                            : 'Iniciar diagnóstico',
                ],

                [
                    'key' => 'expanded_report',
                    'label' => 'Informe Ampliado',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Entregable opcional posterior al diagnóstico.',
                    'optional' => true,
                    'url' => null,
                    'action_label' => null,
                ],

                [
                    'key' => 'roadmap',
                    'label' => 'Roadmap Detallado',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Entregable opcional de profundización.',
                    'optional' => true,
                    'url' => null,
                    'action_label' => null,
                ],

                [
                    'key' => 'implementation_plan',
                    'label' => 'Plan de Implementación',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Se prepara después del resultado oficial del Diagnóstico.',
                    'optional' => false,
                    'url' => null,
                    'action_label' => null,
                ],

                [
                    'key' => 'execution',
                    'label' => 'Ejecución',
                    'state' => 'pending',
                    'status_label' => 'Pendiente',
                    'description' => 'Comienza después de presentar y aceptar el Plan.',
                    'optional' => false,
                    'url' => null,
                    'action_label' => null,
                ],
            ],

            'primary_action' => [
                'label' =>
                    $requested
                        ? 'Ver Diagnóstico 360'
                        : 'Iniciar Diagnóstico 360',

                'url' =>
                    route(
                        'app.diagnosis.show',
                        [],
                        false
                    ),
            ],
        ];
    }

    private function emptyPayload(
        bool $visible
    ): array {
        return [
            'visible' => $visible,
            'has_workflow' => false,
            'assessment_id' => null,
            'organization_name' => null,
            'current_label' => null,
            'plan_public' => false,
            'execution' => [
                'progress_percentage' => 0,
                'phase_count' => 0,
                'completed_phase_count' => 0,
            ],
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
            'transformation_implementation_phases',
            'transformation_implementation_phase_executions',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
