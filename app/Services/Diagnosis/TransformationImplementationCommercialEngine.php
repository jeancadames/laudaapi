<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationCommercialEngine
{
    public function __construct(
        private readonly TransformationImplementationCommercialCalculator $calculator,
        private readonly TransformationImplementationPricingService $pricing,
        private readonly TransformationImplementationModalityCatalog $modalities
    ) {
    }

    public function preview(
        TransformationImplementationPlan $plan
    ): array {
        $plan->loadMissing(
            'phases.capabilities'
        );

        return $this->calculator->quotePlan(
            $this->normalizedPhases($plan),
            $this->matrix(),
            array_keys(
                $this->modalities->all()
            )
        );
    }

    public function generate(
        TransformationImplementationPlan $plan,
        ?int $userId = null
    ): TransformationImplementationPlan {
        return DB::transaction(
            function () use (
                $plan,
                $userId
            ) {
                $locked =
                    TransformationImplementationPlan::query()
                        ->lockForUpdate()
                        ->findOrFail($plan->id);

                if (
                    $locked->status
                    !== TransformationImplementationPlan::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'commercial_engine' =>
                            'La cotización automática solo puede regenerarse mientras el Plan está en borrador.',
                    ]);
                }

                $locked->load(
                    'phases.capabilities'
                );

                $preview =
                    $this->calculator->quotePlan(
                        $this->normalizedPhases(
                            $locked
                        ),
                        $this->matrix(),
                        array_keys(
                            $this->modalities->all()
                        )
                    );

                if (! $preview['ready']) {
                    $missing =
                        array_slice(
                            $preview['missing'],
                            0,
                            20
                        );

                    throw ValidationException::withMessages([
                        'commercial_matrix' =>
                            'La matriz comercial de Transformación 360 no está completa. Faltan: '
                            .implode(', ', $missing),
                    ]);
                }

                foreach (
                    $preview['modalities']
                    as $modality => $scenario
                ) {
                    foreach (
                        $scenario['phases']
                        as $phaseQuote
                    ) {
                        $phase =
                            $locked->phases
                                ->firstWhere(
                                    'id',
                                    $phaseQuote['phase_id']
                                );

                        if (! $phase) {
                            throw ValidationException::withMessages([
                                'phase' =>
                                    'Una fase calculada ya no pertenece al Plan actual.',
                            ]);
                        }

                        $this->pricing->upsertEstimate(
                            $phase,
                            [
                                'modality' =>
                                    $modality,

                                'price_amount' =>
                                    $phaseQuote[
                                        'price_amount'
                                    ],

                                'currency' =>
                                    $preview['currency'],

                                'estimated_duration_value' =>
                                    $phaseQuote[
                                        'duration_days'
                                    ],

                                'estimated_duration_unit' =>
                                    'days',

                                'scope_snapshot' => [
                                    'generated_by' =>
                                        'transformation_implementation_commercial_engine',

                                    'matrix_version' =>
                                        $preview['version'],

                                    'generated_at' =>
                                        now()->toISOString(),

                                    'phase_id' =>
                                        $phase->id,

                                    'phase_sequence' =>
                                        $phase->sequence,

                                    'phase_name' =>
                                        $phase->name,

                                    'modality' =>
                                        $modality,

                                    'calculation_basis' =>
                                        'initiative_effort_plus_professional_capabilities',

                                    'breakdown' =>
                                        $phaseQuote[
                                            'breakdown'
                                        ],

                                    'recurring_solution_pricing_included' =>
                                        false,

                                    'subscription_created' =>
                                        false,

                                    'subscription_item_created' =>
                                        false,
                                ],

                                'internal_notes' =>
                                    'Estimación generada automáticamente desde la matriz comercial de Transformación 360.',
                            ],
                            $userId
                        );
                    }
                }

                return $locked->fresh(
                    'phases.estimates'
                );
            }
        );
    }

    private function normalizedPhases(
        TransformationImplementationPlan $plan
    ): array {
        return $plan->phases
            ->map(
                function (
                    TransformationImplementationPhase $phase
                ): array {
                    $snapshot =
                        $phase->source_snapshot
                        ?? [];

                    $initiatives =
                        collect(
                            data_get(
                                $snapshot,
                                'initiatives',
                                []
                            )
                        )
                        ->filter(
                            fn ($initiative): bool =>
                                is_array($initiative)
                        )
                        ->values()
                        ->all();

                    $professionalCapabilities =
                        $phase->capabilities
                            ->filter(
                                function ($capability): bool {
                                    $kind =
                                        data_get(
                                            $capability
                                                ->source_snapshot,
                                            'kind'
                                        );

                                    return
                                        $kind
                                        === 'professional_service';
                                }
                            )
                            ->map(
                                fn ($capability): array => [
                                    'key' =>
                                        $capability
                                            ->capability_key,

                                    'label' =>
                                        $capability
                                            ->capability_label,
                                ]
                            )
                            ->values()
                            ->all();

                    return [
                        'id' =>
                            $phase->id,

                        'sequence' =>
                            $phase->sequence,

                        'name' =>
                            $phase->name,

                        'initiatives' =>
                            $initiatives,

                        'professional_capabilities' =>
                            $professionalCapabilities,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function matrix(): array
    {
        return app(
            TransformationImplementationCommercialMatrixService::class
        )->current();
    }
}
