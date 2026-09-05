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
        $rows = $this
            ->buildRows()
            ->filter(
                fn (array $row): bool =>
                    $row['bi_present'] === true
            )
            ->values();

        $catalog =
            TransformationProfessionalCapabilityCatalog::get(
                'data_transformation_bi'
            ) ?? [];

        return Inertia::render(
            'Admin/Transformation360/DataBi',
            [
                'rows' => $rows,

                'stats' => [
                    'total' => $rows->count(),

                    'with_definition' => $rows
                        ->filter(
                            fn (array $row): bool =>
                                $row['definition'] !== null
                        )
                        ->count(),

                    'ready' => $rows
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
            ],
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
