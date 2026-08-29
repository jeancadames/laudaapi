<?php

namespace App\Services\Ecosystem;

use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TransformationControlPanelService
{
    /**
     * Snapshot read-only para el Control Panel del tenant.
     *
     * La facturación T360 está modelada por fase/hito. Por eso los
     * importes se agregan por fase y nunca se replican por capability.
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
            ->orderByDesc('id')
            ->get();

        if ($plans->isEmpty()) {
            return $this->emptyPayload();
        }

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
                'estimated_total' => round(
                    (float) $phases->sum(
                        fn (array $phase): float =>
                            (float) ($phase['commercial']['estimate_amount'] ?? 0)
                    ),
                    2
                ),
                'milestone_total' => round(
                    (float) $phases->sum(
                        fn (array $phase): float =>
                            (float) ($phase['commercial']['milestone_total'] ?? 0)
                    ),
                    2
                ),
                'paid_total' => round(
                    (float) $phases->sum(
                        fn (array $phase): float =>
                            (float) ($phase['commercial']['paid_total'] ?? 0)
                    ),
                    2
                ),
                'currency' => $this->singleCurrency($phases),
            ],
        ];
    }

    private function schemaReady(): bool
    {
        foreach ([
            'diagnosis_detailed_roadmap_orders',
            'diagnosis_expanded_report_orders',
            'transformation_implementation_plans',
            'transformation_implementation_phases',
            'transformation_implementation_phase_capabilities',
            'transformation_implementation_phase_estimates',
            'transformation_implementation_milestones',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
            'transformation_implementation_capability_service_mappings',
            'transformation_implementation_subscription_item_activations',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function assessmentIdsForCompany(Company $company): Collection
    {
        $ids = collect();

        foreach ([
            'diagnosis_detailed_roadmap_orders',
            'diagnosis_expanded_report_orders',
        ] as $table) {
            $query = DB::table($table)
                ->where('company_id', $company->id);

            if ($company->subscriber_id) {
                $query->where(
                    'subscriber_id',
                    $company->subscriber_id
                );
            }

            $ids = $ids->merge(
                $query->pluck('diagnosis_assessment_id')
            );
        }

        return $ids
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function planPayload(object $plan): array
    {
        $phases = DB::table('transformation_implementation_phases')
            ->where(
                'transformation_implementation_plan_id',
                $plan->id
            )
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(
                fn (object $phase): array =>
                    $this->phasePayload($phase, $plan)
            )
            ->values();

        return [
            'id' => (int) $plan->id,
            'status' => (string) $plan->status,
            'version' => (int) $plan->version,
            'selected_modality' =>
                $plan->selected_modality ?: null,
            'selected_modality_label' =>
                $plan->selected_modality_label ?: null,
            'presented_at' => $plan->presented_at,
            'accepted_at' => $plan->accepted_at,
            'phases' => $phases->all(),
        ];
    }

    private function phasePayload(object $phase, object $plan): array
    {
        $estimate = $this->phaseEstimate(
            (int) $phase->id,
            $plan->selected_modality ?: null
        );

        $milestones = DB::table(
            'transformation_implementation_milestones'
        )
            ->where(
                'transformation_implementation_phase_id',
                $phase->id
            )
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $execution = DB::table(
            'transformation_implementation_phase_executions'
        )
            ->where(
                'transformation_implementation_phase_id',
                $phase->id
            )
            ->orderByDesc('id')
            ->first();

        $capabilities = DB::table(
            'transformation_implementation_phase_capabilities'
        )
            ->where(
                'transformation_implementation_phase_id',
                $phase->id
            )
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(
                fn (object $capability): array =>
                    $this->capabilityPayload($capability)
            )
            ->values();

        $milestoneTotal = round(
            (float) $milestones->sum(
                fn (object $item): float =>
                    (float) ($item->billing_amount ?? 0)
            ),
            2
        );

        $paidTotal = round(
            (float) $milestones
                ->filter(fn (object $item): bool => ! empty($item->paid_at))
                ->sum(
                    fn (object $item): float =>
                        (float) ($item->billing_amount ?? 0)
                ),
            2
        );

        $invoicedTotal = round(
            (float) $milestones
                ->filter(
                    fn (object $item): bool =>
                        ! empty($item->invoice_reference)
                )
                ->sum(
                    fn (object $item): float =>
                        (float) ($item->billing_amount ?? 0)
                ),
            2
        );

        return [
            'id' => (int) $phase->id,
            'sequence' => (int) $phase->sequence,
            'name' => (string) $phase->name,
            'objective' => $phase->objective ?: null,
            'execution' => [
                'status' => $execution?->status ?? 'pending',
                'progress_percentage' =>
                    (int) ($execution?->progress_percentage ?? 0),
            ],
            'commercial' => [
                'estimate_amount' =>
                    $estimate
                        ? round((float) $estimate->price_amount, 2)
                        : null,
                'currency' =>
                    $estimate?->currency
                    ?? $milestones->first()?->currency
                    ?? null,
                'estimated_duration_value' =>
                    $estimate?->estimated_duration_value
                        ? (int) $estimate->estimated_duration_value
                        : null,
                'estimated_duration_unit' =>
                    $estimate?->estimated_duration_unit ?: null,
                'milestone_count' => $milestones->count(),
                'milestone_total' => $milestoneTotal,
                'invoiced_total' => $invoicedTotal,
                'paid_total' => $paidTotal,
                'billing_status' =>
                    $this->phaseBillingStatus($milestones),
                'next_due_at' =>
                    $milestones
                        ->first(fn (object $item): bool => empty($item->paid_at))
                        ?->due_at,
                'milestones' => $milestones
                    ->map(fn (object $item): array => [
                        'id' => (int) $item->id,
                        'sequence' => (int) $item->sequence,
                        'name' => (string) $item->name,
                        'billing_amount' => round(
                            (float) ($item->billing_amount ?? 0),
                            2
                        ),
                        'currency' => $item->currency ?: null,
                        'billing_status' =>
                            $item->billing_status ?: null,
                        'due_at' => $item->due_at,
                        'invoice_reference' =>
                            $item->invoice_reference ?: null,
                        'invoice_issued_at' =>
                            $item->invoice_issued_at,
                        'payment_reference' =>
                            $item->payment_reference ?: null,
                        'paid_at' => $item->paid_at,
                    ])
                    ->all(),
            ],
            'capabilities' => $capabilities->all(),
        ];
    }

    private function phaseEstimate(
        int $phaseId,
        ?string $selectedModality
    ): ?object {
        $query = DB::table(
            'transformation_implementation_phase_estimates'
        )
            ->where(
                'transformation_implementation_phase_id',
                $phaseId
            );

        if ($selectedModality) {
            $selected = (clone $query)
                ->where('modality', $selectedModality)
                ->orderByDesc('id')
                ->first();

            if ($selected) {
                return $selected;
            }
        }

        return $query
            ->orderByDesc('id')
            ->first();
    }

    private function capabilityPayload(object $capability): array
    {
        $execution = DB::table(
            'transformation_implementation_capability_executions'
        )
            ->where(
                'transformation_implementation_phase_capability_id',
                $capability->id
            )
            ->orderByDesc('id')
            ->first();

        $goLive = DB::table(
            'transformation_implementation_capability_go_lives'
        )
            ->where(
                'transformation_implementation_phase_capability_id',
                $capability->id
            )
            ->orderByDesc('attempt')
            ->orderByDesc('id')
            ->first();

        $mapping = DB::table(
            'transformation_implementation_capability_service_mappings'
        )
            ->where('capability_key', $capability->capability_key)
            ->where('active', 1)
            ->orderByDesc('id')
            ->first();

        $activation = null;

        if ($goLive && $mapping) {
            $activation = DB::table(
                'transformation_implementation_subscription_item_activations'
            )
                ->where(
                    'transformation_implementation_capability_go_live_id',
                    $goLive->id
                )
                ->where(
                    'transformation_implementation_capability_service_mapping_id',
                    $mapping->id
                )
                ->orderByDesc('id')
                ->first();
        }

        return [
            'id' => (int) $capability->id,
            'key' => (string) $capability->capability_key,
            'label' => (string) $capability->capability_label,
            'summary' => $capability->capability_summary ?: null,
            'execution' => [
                'status' => $execution?->status ?? 'pending',
                'progress_percentage' =>
                    (int) ($execution?->progress_percentage ?? 0),
            ],
            'go_live' => $goLive ? [
                'status' => (string) $goLive->status,
                'ready_at' => $goLive->ready_at,
                'scheduled_at' => $goLive->scheduled_at,
                'went_live_at' => $goLive->went_live_at,
            ] : null,
            'service_activation' => $activation ? [
                'status' => (string) $activation->status,
                'service_id' => (int) $activation->service_id,
                'subscription_item_id' =>
                    (int) $activation->subscription_item_id,
                'activated_at' => $activation->activated_at,
                'price_snapshot' =>
                    $this->decodeSnapshot(
                        $activation->price_snapshot ?? null
                    ),
            ] : null,
        ];
    }

    private function phaseBillingStatus(Collection $milestones): string
    {
        if ($milestones->isEmpty()) {
            return 'not_scheduled';
        }

        if ($milestones->every(
            fn (object $item): bool => ! empty($item->paid_at)
        )) {
            return 'paid';
        }

        if ($milestones->contains(
            fn (object $item): bool => ! empty($item->invoice_reference)
        )) {
            return 'invoiced';
        }

        if ($milestones->contains(
            fn (object $item): bool =>
                ! empty($item->ready_to_invoice_at)
        )) {
            return 'ready_to_invoice';
        }

        return 'scheduled';
    }

    private function decodeSnapshot(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : $value;
    }

    private function singleCurrency(Collection $phases): ?string
    {
        $currencies = $phases
            ->map(
                fn (array $phase) =>
                    $phase['commercial']['currency'] ?? null
            )
            ->filter()
            ->unique()
            ->values();

        return $currencies->count() === 1
            ? (string) $currencies->first()
            : null;
    }

    private function emptyPayload(): array
    {
        return [
            'plans' => [],
            'summary' => [
                'plan_count' => 0,
                'phase_count' => 0,
                'capability_count' => 0,
                'estimated_total' => 0.0,
                'milestone_total' => 0.0,
                'paid_total' => 0.0,
                'currency' => null,
            ],
        ];
    }
}
