<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class AdminTransformation360OverviewController extends Controller
{
    public function index(): Response
    {
        $rows = $this->buildRows();

        return Inertia::render(
            'Admin/Transformation360/Index',
            [
                'rows' => $rows->values(),
                'stats' => [
                    'total' => $rows->count(),

                    'with_plan' => $rows
                        ->filter(
                            fn (array $row): bool =>
                                $row['plan'] !== null
                        )
                        ->count(),

                    'with_definition' => $rows
                        ->filter(
                            fn (array $row): bool =>
                                $row['definition'] !== null
                        )
                        ->count(),

                    'definitions_ready' => $rows
                        ->filter(
                            fn (array $row): bool =>
                                data_get(
                                    $row,
                                    'definition.status'
                                ) === 'ready'
                        )
                        ->count(),

                    'bi' => $rows
                        ->where(
                            'bi_present',
                            true
                        )
                        ->count(),
                ],
            ],
        );
    }

    public function dataBi(): Response
    {
        /*
         * Supervisor BI request-aware.
         *
         * El Plan identifica las empresas cuya planificación incluye
         * data_transformation_bi.
         *
         * El ciclo funcional comienza exclusivamente mediante una
         * TransformationImplementationRequest explícita del tenant.
         *
         * Esta vista no expone la vía legacy Plan -> Definition.
         */
        $baseRows =
            $this
                ->buildRows()
                ->filter(
                    fn (array $row): bool =>
                        $row['bi_present'] === true
                )
                ->values();

        $assessmentIds =
            $baseRows
                ->pluck('assessment_id')
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->filter()
                ->unique()
                ->values();

        $planIds =
            $baseRows
                ->pluck('plan.id')
                ->filter(
                    fn ($value): bool =>
                        $value !== null
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->filter()
                ->unique()
                ->values();

        $requestsByScope =
            collect();

        if (
            $assessmentIds->isNotEmpty()
            && $planIds->isNotEmpty()
        ) {
            $requestsByScope =
                \App\Models\TransformationImplementationRequest::query()
                    ->where(
                        'capability_key',
                        'data_transformation_bi'
                    )
                    ->whereIn(
                        'diagnosis_assessment_id',
                        $assessmentIds
                    )
                    ->whereIn(
                        'transformation_implementation_plan_id',
                        $planIds
                    )
                    ->orderByDesc('attempt')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy(
                        fn (
                            \App\Models\TransformationImplementationRequest $implementationRequest
                        ): string =>
                            (int) $implementationRequest
                                ->diagnosis_assessment_id
                            .':'
                            .(int) $implementationRequest
                                ->transformation_implementation_plan_id
                    )
                    ->map(
                        fn ($group) =>
                            $group->first()
                    );
        }

        $requestIds =
            $requestsByScope
                ->map(
                    fn (
                        \App\Models\TransformationImplementationRequest $implementationRequest
                    ): int =>
                        (int) $implementationRequest->id
                )
                ->filter()
                ->values();

        $definitionsByRequest =
            collect();

        $eventsByRequest =
            collect();

        if ($requestIds->isNotEmpty()) {
            /*
             * Solo Definitions request-scoped y bloqueadas a la
             * capacidad concreta de la solicitud.
             */
            $definitionsByRequest =
                \App\Models\TransformationImplementationDefinition::query()
                    ->whereIn(
                        'transformation_implementation_request_id',
                        $requestIds
                    )
                    ->orderByDesc('version')
                    ->orderByDesc('id')
                    ->get()
                    ->filter(
                        fn (
                            \App\Models\TransformationImplementationDefinition $definition
                        ): bool =>
                            data_get(
                                $definition->source_snapshot,
                                'source_type'
                            ) === 'implementation_request'
                            && data_get(
                                $definition->implementation_scope,
                                'scope_mode'
                            ) === 'single_capability'
                            && data_get(
                                $definition->implementation_scope,
                                'definition_scope_locked_to_request'
                            ) === true
                    )
                    ->groupBy(
                        'transformation_implementation_request_id'
                    );

            /*
             * Tras el acuerdo, la versión fijada por el evento
             * específico es la fuente de verdad; no "latest wins".
             */
            $eventsByRequest =
                \App\Models\TransformationImplementationRequestEvent::query()
                    ->whereIn(
                        'transformation_implementation_request_id',
                        $requestIds
                    )
                    ->whereIn(
                        'event_type',
                        [
                            'definition_agreed_by_tenant',
                            'request_ready_for_commercial_by_lauda',
                        ]
                    )
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy(
                        'transformation_implementation_request_id'
                    );
        }

        $requestStatusLabel =
            static function (?string $status): string {
                return match ($status) {
                    'requested' =>
                        'Solicitud recibida',

                    'under_lauda_review' =>
                        'En revisión por LAUDA',

                    'definition_preparation' =>
                        'Definición en preparación',

                    'awaiting_tenant_review' =>
                        'Esperando revisión de la empresa',

                    'changes_requested' =>
                        'Ajustes solicitados',

                    'definition_agreed' =>
                        'Definición acordada',

                    'ready_for_commercial' =>
                        'Lista para etapa comercial',

                    'cancelled' =>
                        'Solicitud cancelada',

                    default =>
                        'Sin solicitud',
                };
            };

        $rows =
            $baseRows
                ->map(
                    function (array $row) use (
                        $requestsByScope,
                        $definitionsByRequest,
                        $eventsByRequest,
                        $requestStatusLabel
                    ): array {
                        $assessmentId =
                            (int) (
                                $row['assessment_id']
                                ?? 0
                            );

                        $planId =
                            (int) data_get(
                                $row,
                                'plan.id',
                                0
                            );

                        $scopeKey =
                            $assessmentId
                            .':'
                            .$planId;

                        /** @var \App\Models\TransformationImplementationRequest|null $implementationRequest */
                        $implementationRequest =
                            $requestsByScope->get(
                                $scopeKey
                            );

                        $definition =
                            null;

                        if ($implementationRequest) {
                            $definitionCandidates =
                                collect(
                                    $definitionsByRequest->get(
                                        $implementationRequest->id,
                                        collect()
                                    )
                                );

                            $status =
                                (string) $implementationRequest->status;

                            if (
                                in_array(
                                    $status,
                                    [
                                        'definition_agreed',
                                        'ready_for_commercial',
                                    ],
                                    true
                                )
                            ) {
                                $eventType =
                                    $status === 'ready_for_commercial'
                                        ? 'request_ready_for_commercial_by_lauda'
                                        : 'definition_agreed_by_tenant';

                                $event =
                                    collect(
                                        $eventsByRequest->get(
                                            $implementationRequest->id,
                                            collect()
                                        )
                                    )->first(
                                        fn (
                                            \App\Models\TransformationImplementationRequestEvent $event
                                        ): bool =>
                                            $event->event_type
                                            === $eventType
                                    );

                                if ($event) {
                                    $metadata =
                                        is_array(
                                            $event->metadata
                                        )
                                            ? $event->metadata
                                            : (
                                                json_decode(
                                                    (string) $event->metadata,
                                                    true
                                                )
                                                ?: []
                                            );

                                    $definitionId =
                                        (int) (
                                            $metadata['definition_id']
                                            ?? 0
                                        );

                                    $definitionVersion =
                                        (int) (
                                            $metadata['definition_version']
                                            ?? 0
                                        );

                                    $definition =
                                        $definitionCandidates
                                            ->first(
                                                fn (
                                                    \App\Models\TransformationImplementationDefinition $candidate
                                                ): bool =>
                                                    (int) $candidate->id
                                                        === $definitionId
                                                    && (int) $candidate->version
                                                        === $definitionVersion
                                            );
                                }
                            } else {
                                /*
                                 * Antes del acuerdo corresponde la
                                 * Definition request-scoped más reciente.
                                 */
                                $definition =
                                    $definitionCandidates
                                        ->first();
                            }
                        }

                        $row['implementation_request'] =
                            $implementationRequest
                                ? [
                                    'id' =>
                                        (int) $implementationRequest->id,

                                    'status' =>
                                        (string) $implementationRequest->status,

                                    'status_label' =>
                                        $requestStatusLabel(
                                            (string) $implementationRequest->status
                                        ),

                                    'detail_url' =>
                                        route(
                                            'admin.transformation360.implementation_requests.show',
                                            [
                                                'implementationRequest' =>
                                                    $implementationRequest->id,
                                            ],
                                            false
                                        ),
                                ]
                                : null;

                        $row['definition'] =
                            $definition
                                ? [
                                    'id' =>
                                        (int) $definition->id,

                                    'version' =>
                                        (int) $definition->version,

                                    'status' =>
                                        (string) $definition->status,
                                ]
                                : null;

                        /*
                         * Cuarentena explícita de la navegación legacy.
                         */
                        $row['urls']['definition'] =
                            null;

                        return $row;
                    }
                )
                ->values();

        $catalog =
            TransformationProfessionalCapabilityCatalog::get(
                'data_transformation_bi'
            ) ?? [];

        return Inertia::render(
            'Admin/Transformation360/DataBi',
            [
                'rows' =>
                    $rows,

                'stats' => [
                    'total' =>
                        $rows->count(),

                    'active_requests' =>
                        $rows
                            ->filter(
                                fn (array $row): bool =>
                                    data_get(
                                        $row,
                                        'implementation_request.id'
                                    ) !== null
                                    && ! in_array(
                                        data_get(
                                            $row,
                                            'implementation_request.status'
                                        ),
                                        [
                                            'ready_for_commercial',
                                            'cancelled',
                                        ],
                                        true
                                    )
                            )
                            ->count(),

                    'ready' =>
                        $rows
                            ->filter(
                                fn (array $row): bool =>
                                    data_get(
                                        $row,
                                        'definition.status'
                                    ) === 'ready'
                            )
                            ->count(),
                ],

                'capability' => [
                    'key' =>
                        'data_transformation_bi',

                    'title' =>
                        $catalog['title']
                        ?? 'Transformación e Inteligencia de Datos para BI',

                    'purpose' =>
                        $catalog['purpose']
                        ?? null,

                    'scope_items' =>
                        $catalog['scope_items']
                        ?? $catalog['includes']
                        ?? [],
                ],
            ]
        );
    }

    private function buildRows(): Collection
    {
        foreach ([
            'diagnosis_access_requests',
            'contact_requests',
            'diagnosis_assessments',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return collect();
            }
        }

        $accessRows =
            DB::table('diagnosis_access_requests as access')
                ->join(
                    'contact_requests as contact',
                    'contact.id',
                    '=',
                    'access.contact_request_id',
                )
                ->leftJoin(
                    'diagnosis_assessments as assessment',
                    'assessment.id',
                    '=',
                    'access.diagnosis_assessment_id',
                )
                ->whereNotNull(
                    'access.diagnosis_assessment_id'
                )
                ->select([
                    'access.id as access_id',
                    'access.contact_request_id',
                    'access.diagnosis_assessment_id',
                    'contact.name as contact_name',
                    'contact.email as contact_email',
                    'contact.company as company_name',
                    'assessment.status as assessment_status',
                ])
                ->orderByDesc('access.id')
                ->get()
                ->unique(
                    fn (object $row): int =>
                        (int) $row->diagnosis_assessment_id
                )
                ->values();

        return $accessRows->map(
            function (object $row): array {
                $assessmentId =
                    (int) $row->diagnosis_assessment_id;

                $contactId =
                    (int) $row->contact_request_id;

                $plan =
                    $this->latestPlan(
                        $assessmentId
                    );

                $definition =
                    $plan
                        ? $this->latestDefinition(
                            (int) $plan->id
                        )
                        : null;

                $capabilities =
                    $plan
                        ? $this->planCapabilities(
                            (int) $plan->id
                        )
                        : collect();

                $biPresent =
                    $capabilities->contains(
                        fn (array $capability): bool =>
                            $capability['key']
                                === 'data_transformation_bi'
                    );

                return [
                    'contact_id' =>
                        $contactId,

                    'assessment_id' =>
                        $assessmentId,

                    'company' =>
                        $row->company_name
                        ?: $row->contact_name
                        ?: 'Empresa por definir',

                    'contact' => [
                        'name' =>
                            $row->contact_name,

                        'email' =>
                            $row->contact_email,
                    ],

                    'assessment_status' =>
                        $row->assessment_status,

                    'plan' =>
                        $plan
                            ? [
                                'id' =>
                                    (int) $plan->id,

                                'version' =>
                                    (int) $plan->version,

                                'status' =>
                                    $plan->status,
                            ]
                            : null,

                    'definition' =>
                        $definition
                            ? [
                                'id' =>
                                    (int) $definition->id,

                                'version' =>
                                    (int) $definition->version,

                                'status' =>
                                    $definition->status,
                            ]
                            : null,

                    'capabilities' =>
                        $capabilities->values(),

                    'bi_present' =>
                        $biPresent,

                    'current_stage' =>
                        $this->currentStage(
                            $row->assessment_status,
                            $plan?->status,
                            $definition?->status,
                        ),

                    'urls' => [
                        'diagnosis' =>
                            "/admin/diagnosis-requests/{$contactId}",

                        'expanded_report' =>
                            "/admin/diagnosis-requests/{$contactId}/expanded-report",

                        'detailed_roadmap' =>
                            "/admin/diagnosis-requests/{$contactId}/detailed-roadmap",

                        'implementation_plan' =>
                            "/admin/diagnosis-requests/{$contactId}/implementation-plan",

                        'definition' =>
                            $plan
                            && $plan->status === 'presented'
                                ? "/admin/diagnosis-requests/{$contactId}"
                                    ."/implementation-plan/"
                                    .$plan->id
                                    ."/definition"
                                : null,
                    ],
                ];
            }
        );
    }

    private function latestPlan(
        int $assessmentId
    ): ?object {
        if (
            ! Schema::hasTable(
                'transformation_implementation_plans'
            )
        ) {
            return null;
        }

        return DB::table(
            'transformation_implementation_plans'
        )
            ->where(
                'diagnosis_assessment_id',
                $assessmentId,
            )
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first([
                'id',
                'version',
                'status',
            ]);
    }

    private function latestDefinition(
        int $planId
    ): ?object {
        $table =
            'transformation_implementation_definitions';

        if (! Schema::hasTable($table)) {
            return null;
        }

        if (
            Schema::hasColumn(
                $table,
                'plan_id'
            )
        ) {
            $planColumn = 'plan_id';
        } elseif (
            Schema::hasColumn(
                $table,
                'transformation_implementation_plan_id'
            )
        ) {
            $planColumn =
                'transformation_implementation_plan_id';
        } else {
            return null;
        }

        return DB::table($table)
            ->where(
                $planColumn,
                $planId,
            )
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first([
                'id',
                'version',
                'status',
            ]);
    }

    private function planCapabilities(
        int $planId
    ): Collection {
        if (
            ! Schema::hasTable(
                'transformation_implementation_phases'
            )
            || ! Schema::hasTable(
                'transformation_implementation_phase_capabilities'
            )
        ) {
            return collect();
        }

        return DB::table(
            'transformation_implementation_phase_capabilities as capability'
        )
            ->join(
                'transformation_implementation_phases as phase',
                'phase.id',
                '=',
                'capability.transformation_implementation_phase_id',
            )
            ->where(
                'phase.transformation_implementation_plan_id',
                $planId,
            )
            ->orderBy('phase.sequence')
            ->orderBy('capability.sequence')
            ->get([
                'capability.capability_key',
                'capability.capability_label',
            ])
            ->map(
                fn (object $capability): array => [
                    'key' =>
                        $capability->capability_key,

                    'label' =>
                        $capability->capability_label,
                ]
            )
            ->unique('key')
            ->values();
    }

    private function currentStage(
        ?string $assessmentStatus,
        ?string $planStatus,
        ?string $definitionStatus,
    ): string {
        if ($definitionStatus === 'ready') {
            return 'Definición lista';
        }

        if ($definitionStatus === 'under_review') {
            return 'Definición en revisión';
        }

        if ($definitionStatus === 'draft') {
            return 'Definición en borrador';
        }

        if ($planStatus === 'presented') {
            return 'Plan presentado';
        }

        if ($planStatus === 'draft') {
            return 'Plan en preparación';
        }

        if ($assessmentStatus === 'reviewed') {
            return 'Diagnóstico publicado';
        }

        return match ($assessmentStatus) {
            'submitted' =>
                'Diagnóstico pendiente de revisión',

            'in_progress' =>
                'Diagnóstico en progreso',

            'draft' =>
                'Diagnóstico no iniciado',

            default =>
                $assessmentStatus
                    ?: 'Workflow disponible',
        };
    }
}
