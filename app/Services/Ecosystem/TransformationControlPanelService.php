<?php

namespace App\Services\Ecosystem;

use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TransformationControlPanelService
{
    /**
     * Snapshot consultivo read-only para el Control Panel del tenant.
     *
     * S11-B separa por completo este panel del motor comercial histórico:
     * aquí no se leen modalidades, precios, hitos, facturas, pagos ni ejecución.
     */
    public function forCompany(Company $company): array
    {
        if (! $this->schemaReady()) {
            return $this->emptyPayload();
        }

        $assessmentIds = $this->assessmentIdsForCompany($company);

        if ($assessmentIds->isEmpty()) {
            return $this->emptyPayload();
        }

        $plans = DB::table('transformation_implementation_plans')
            ->whereIn('diagnosis_assessment_id', $assessmentIds->all())
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotNull('presented_at')
            ->orderByDesc('id')
            ->get();

        $payloadPlans = $plans
            ->map(fn (object $plan): array => $this->planPayload($plan))
            ->values();

        $phases = $payloadPlans->flatMap(
            fn (array $plan): array => $plan['phases']
        );

        return [
            'plans' => $payloadPlans->all(),
            'summary' => [
                'plan_count' => $payloadPlans->count(),
                'phase_count' => $phases->count(),
                'capability_count' => $phases->sum(
                    fn (array $phase): int =>
                        count($phase['capabilities'] ?? [])
                ),
                'initiative_count' => $phases->sum(
                    fn (array $phase): int =>
                        count($phase['initiatives'] ?? [])
                ),
                'deliverable_count' => $phases->sum(
                    fn (array $phase): int =>
                        count($phase['deliverables'] ?? [])
                ),
            ],
        ];
    }

    private function planPayload(object $plan): array
    {
        $source = $this->decodeJson($plan->source_snapshot ?? null);

        $phases = DB::table('transformation_implementation_phases')
            ->where('transformation_implementation_plan_id', $plan->id)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(fn (object $phase): array => $this->phasePayload($phase))
            ->values();

        return [
            'id' => (int) $plan->id,
            'status' => (string) $plan->status,
            'version' => (int) $plan->version,
            'presented_at' => $plan->presented_at,
            'source_type' => data_get(
                $source,
                'source_type',
                $plan->diagnosis_detailed_roadmap_id
                    ? 'published_roadmap'
                    : 'internal_assessment'
            ),
            'phases' => $phases->all(),
        ];
    }

    private function phasePayload(object $phase): array
    {
        $source = $this->decodeJson($phase->source_snapshot ?? null);

        $capabilities = DB::table(
            'transformation_implementation_phase_capabilities'
        )
            ->where('transformation_implementation_phase_id', $phase->id)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(function (object $capability): ?array {
                $snapshot = $this->decodeJson(
                    $capability->source_snapshot ?? null
                );

                $kind = data_get($snapshot, 'kind');
                $subscriptionCandidate = (bool) data_get(
                    $snapshot,
                    'subscription_candidate',
                    false
                );

                if ($kind === 'subscription_service' || $subscriptionCandidate) {
                    return null;
                }

                return [
                    'id' => (int) $capability->id,
                    'sequence' => (int) $capability->sequence,
                    'key' => (string) $capability->capability_key,
                    'label' => (string) $capability->capability_label,
                    'summary' => $capability->capability_summary ?: null,
                    'kind' => 'professional_service',
                    'includes' => data_get($snapshot, 'includes', []),
                ];
            })
            ->filter()
            ->values();

        $initiatives = collect(data_get($source, 'initiatives', []))
            ->map(fn ($initiative): array => [
                'id' => data_get($initiative, 'id'),
                'priority' => data_get($initiative, 'priority'),
                'title' => data_get($initiative, 'title'),
                'objective' => data_get($initiative, 'objective'),
                'owner_role' => data_get($initiative, 'owner_role'),
                'actions' => data_get($initiative, 'actions', []),
                'dependencies' => data_get($initiative, 'dependencies', []),
                'success_metrics' => data_get($initiative, 'success_metrics', []),
            ])
            ->values();

        return [
            'id' => (int) $phase->id,
            'sequence' => (int) $phase->sequence,
            'name' => (string) $phase->name,
            'objective' => $phase->objective ?: null,
            'horizon' => data_get($source, 'horizon'),
            'initiative_ids' => data_get($source, 'initiative_ids', []),
            'initiatives' => $initiatives->all(),
            'dependencies' => data_get($source, 'dependencies', []),
            'deliverables' => data_get($source, 'deliverables', []),
            'capabilities' => $capabilities->all(),
        ];
    }

    private function assessmentIdsForCompany(Company $company): Collection
    {
        $ids = collect();

        $accessQuery = DB::table('diagnosis_access_requests')
            ->where(function ($query) use ($company): void {
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
            })
            ->whereNotNull('diagnosis_assessment_id');

        $ids = $ids->merge(
            $accessQuery->pluck('diagnosis_assessment_id')
        );

        // Compatibilidad histórica: no son requisito para los nuevos flujos.
        foreach ([
            'diagnosis_detailed_roadmap_orders',
            'diagnosis_expanded_report_orders',
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

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function schemaReady(): bool
    {
        foreach ([
            'diagnosis_access_requests',
            'transformation_implementation_plans',
            'transformation_implementation_phases',
            'transformation_implementation_phase_capabilities',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function emptyPayload(): array
    {
        return [
            'plans' => [],
            'summary' => [
                'plan_count' => 0,
                'phase_count' => 0,
                'capability_count' => 0,
                'initiative_count' => 0,
                'deliverable_count' => 0,
            ],
        ];
    }
}
