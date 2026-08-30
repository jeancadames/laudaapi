<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationMilestone;
use App\Models\TransformationImplementationPhaseEstimate;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPlanService
{
    public function __construct(
        private readonly DiagnosisDeliverableValidationService $validations
    ) {
    }

    public function createDraftFromPublishedRoadmap(
        DiagnosisDetailedRoadmap $roadmap,
        User $actor
    ): TransformationImplementationPlan {
        if (
            $roadmap->status
            !== DiagnosisDetailedRoadmap::STATUS_PUBLISHED
            || $roadmap->published_at === null
        ) {
            throw ValidationException::withMessages([
                'roadmap' => [
                    'El Plan de Implementación solo puede crearse desde un Roadmap publicado.',
                ],
            ]);
        }

        return DB::transaction(function () use ($roadmap, $actor) {
            $assessment = DiagnosisAssessment::query()
                ->lockForUpdate()
                ->findOrFail(
                    $roadmap->diagnosis_assessment_id
                );

            $existing = TransformationImplementationPlan::query()
                ->where(
                    'diagnosis_detailed_roadmap_id',
                    $roadmap->id
                )
                ->whereNotIn(
                    'status',
                    [
                        TransformationImplementationPlan::STATUS_COMPLETED,
                        TransformationImplementationPlan::STATUS_CANCELLED,
                    ]
                )
                ->latest('version')
                ->first();

            if ($existing) {
                return $existing;
            }

            $nextVersion = (
                (int) TransformationImplementationPlan::query()
                    ->where(
                        'diagnosis_assessment_id',
                        $assessment->id
                    )
                    ->max('version')
            ) + 1;

            $plan = TransformationImplementationPlan::create([
                'diagnosis_assessment_id' =>
                    $assessment->id,
                'diagnosis_detailed_roadmap_id' =>
                    $roadmap->id,
                'version' =>
                    $nextVersion,
                'status' =>
                    TransformationImplementationPlan::STATUS_DRAFT,
                'recommended_modality' =>
                    null,
                'recommended_modality_label' =>
                    null,
                'selected_modality' =>
                    null,
                'selected_modality_label' =>
                    null,
                'source_snapshot' => [
                    'source_type' =>
                          'published_roadmap',
                      'assessment_id' =>
                        $assessment->id,
                    'roadmap_id' =>
                        $roadmap->id,
                    'roadmap_version' =>
                        $roadmap->version,
                    'roadmap_published_at' =>
                        $roadmap->published_at?->toISOString(),
                    'roadmap_methodology_version' =>
                        $roadmap->methodology_version,
                    'published_roadmap_source' =>
                        $roadmap->source_snapshot ?? [],
                    'published_roadmap' =>
                        $roadmap->roadmap ?? [],
                ],
                'created_by_user_id' =>
                    $actor->id,
                'updated_by_user_id' =>
                    $actor->id,
            ]);

            AuditService::log(
                'transformation_implementation_plan_created',
                $plan,
                [
                    'assessment_id' =>
                        $assessment->id,
                    'roadmap_id' =>
                        $roadmap->id,
                    'roadmap_version' =>
                        $roadmap->version,
                    'plan_version' =>
                        $plan->version,
                    'commercial_context_attached' =>
                        false,
                    'actor_user_id' =>
                        $actor->id,
                    'subscription_created' =>
                        false,
                ]
            );

            return $plan->fresh();
        });
    }

          public function createDraftFromAssessment(
          DiagnosisAssessment $assessment,
          User $actor
      ): TransformationImplementationPlan {
          if (
              $assessment->status !== 'reviewed'
              || $assessment->published_at === null
          ) {
              throw ValidationException::withMessages([
                  'assessment' => [
                      'El Plan de Implementación requiere un resultado oficial del Diagnóstico publicado.',
                  ],
              ]);
          }

          return DB::transaction(function () use ($assessment, $actor) {
              $locked = DiagnosisAssessment::query()
                  ->whereKey($assessment->id)
                  ->lockForUpdate()
                  ->firstOrFail();

              if (
                  $locked->status !== 'reviewed'
                  || $locked->published_at === null
              ) {
                  throw ValidationException::withMessages([
                      'assessment' => [
                          'El Plan de Implementación requiere un resultado oficial del Diagnóstico publicado.',
                      ],
                  ]);
              }

              $existing = TransformationImplementationPlan::query()
                  ->where('diagnosis_assessment_id', $locked->id)
                  ->whereNotIn('status', [
                      TransformationImplementationPlan::STATUS_COMPLETED,
                      TransformationImplementationPlan::STATUS_CANCELLED,
                  ])
                  ->latest('version')
                  ->first();

              if ($existing) {
                  return $existing;
              }

              $nextVersion = (
                  (int) TransformationImplementationPlan::query()
                      ->where(
                          'diagnosis_assessment_id',
                          $locked->id
                      )
                      ->max('version')
              ) + 1;

              $generated = app(
                  DiagnosisDetailedRoadmapGenerator::class
              )->generateFromAssessment($locked);

              $plan = TransformationImplementationPlan::create([
                  'diagnosis_assessment_id' =>
                      $locked->id,
                  'diagnosis_detailed_roadmap_id' =>
                      null,
                  'version' =>
                      $nextVersion,
                  'status' =>
                      TransformationImplementationPlan::STATUS_DRAFT,
                  'recommended_modality' =>
                      null,
                  'recommended_modality_label' =>
                      null,
                  'selected_modality' =>
                      null,
                  'selected_modality_label' =>
                      null,
                  'source_snapshot' => [
                      'source_type' =>
                          'internal_assessment',
                      'assessment_id' =>
                          $locked->id,
                      'assessment_methodology_version' =>
                          $locked->methodology_version,
                      'assessment_published_at' =>
                          $locked->published_at?->toISOString(),
                      'internal_roadmap_source' =>
                          $generated['source_snapshot'] ?? [],
                      'internal_roadmap' =>
                          $generated['roadmap'] ?? [],
                  ],
                  'created_by_user_id' =>
                      $actor->id,
                  'updated_by_user_id' =>
                      $actor->id,
              ]);

              AuditService::log(
                  'transformation_implementation_plan_created',
                  $plan,
                  [
                      'assessment_id' =>
                          $locked->id,
                      'source_type' =>
                          'internal_assessment',
                      'roadmap_id' =>
                          null,
                      'plan_version' =>
                          $plan->version,
                      'commercial_context_attached' =>
                          false,
                      'actor_user_id' =>
                          $actor->id,
                      'subscription_created' =>
                          false,
                  ]
              );

              return $plan->fresh();
          });
      }

    public function markPresented(
        TransformationImplementationPlan $plan,
        User $actor
    ): TransformationImplementationPlan {
        $this->validations->assertNotValidated($plan);

        return DB::transaction(function () use ($plan, $actor) {
            $locked = TransformationImplementationPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            if (
                $locked->status
                    === TransformationImplementationPlan::STATUS_PRESENTED
                && $locked->presented_at !== null
            ) {
                return $locked->fresh();
            }

            if (
                $locked->status
                    !== TransformationImplementationPlan::STATUS_DRAFT
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Solo un Plan draft puede marcarse como presented.',
                ]);
            }

            $readiness = $this->consultiveReadiness($locked);

            $locked->forceFill([
                'status' =>
                    TransformationImplementationPlan::STATUS_PRESENTED,
                'presented_at' => now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            AuditService::log(
                'transformation_implementation_plan_presented',
                $locked,
                [
                    'actor_user_id' => $actor->id,
                    'consultive_readiness' => $readiness,
                    'commercial_context_attached' => false,
                    'subscription_created' => false,
                ]
            );

            return $locked->fresh();
        });
    }

    public function acceptPlan(
        TransformationImplementationPlan $plan,
        User $actor
    ): TransformationImplementationPlan {
        return DB::transaction(function () use ($plan, $actor) {
            $locked = TransformationImplementationPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            if (
                $locked->status
                    === TransformationImplementationPlan::STATUS_ACCEPTED
                && $locked->accepted_at !== null
            ) {
                return $locked->fresh();
            }

            if (
                $locked->status
                    !== TransformationImplementationPlan::STATUS_PRESENTED
                || $locked->presented_at === null
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Solo un Plan presented puede aceptarse.',
                ]);
            }

            $readiness = $this->commercialReadiness($locked);

            $locked->forceFill([
                'status' =>
                    TransformationImplementationPlan::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            AuditService::log(
                'transformation_implementation_plan_accepted',
                $locked,
                [
                    'actor_user_id' => $actor->id,
                    'selected_modality' =>
                        $locked->selected_modality,
                    'commercial_readiness' => $readiness,
                    'subscription_created' => false,
                ]
            );

            return $locked->fresh();
        });
    }

    private function consultiveReadiness(
        TransformationImplementationPlan $plan
    ): array {
        $plan->load('phases.capabilities');

        if ($plan->phases->isEmpty()) {
            throw ValidationException::withMessages([
                'phases' =>
                    'El Plan debe contener al menos una fase antes de presentarse.',
            ]);
        }

        return [
            'phase_count' => $plan->phases->count(),
            'capability_count' => $plan->phases->sum(
                fn ($phase): int => $phase->capabilities->count()
            ),
            'commercial_requirements' => false,
        ];
    }

    private function commercialReadiness(
        TransformationImplementationPlan $plan
    ): array {
        $selectedModality = trim(
            (string) ($plan->selected_modality ?? '')
        );

        if (
            $selectedModality === ''
            || !array_key_exists(
                $selectedModality,
                TransformationImplementationPlan::modalities()
            )
        ) {
            throw ValidationException::withMessages([
                'selected_modality' =>
                    'Seleccione una modalidad válida antes de presentar o aceptar el Plan.',
            ]);
        }

        $plan->load('phases.capabilities');

        if ($plan->phases->isEmpty()) {
            throw ValidationException::withMessages([
                'phases' =>
                    'El Plan debe contener al menos una fase antes de presentarse.',
            ]);
        }

        $phaseReadiness = [];

        foreach ($plan->phases as $phase) {
            if ($phase->capabilities->isEmpty()) {
                throw ValidationException::withMessages([
                    'capabilities' =>
                        "La fase [{$phase->name}] debe contener al menos una capability.",
                ]);
            }

            $estimate = TransformationImplementationPhaseEstimate::query()
                ->where(
                    'transformation_implementation_phase_id',
                    $phase->id
                )
                ->where('modality', $selectedModality)
                ->first();

            if (!$estimate) {
                throw ValidationException::withMessages([
                    'estimate' =>
                        "La fase [{$phase->name}] no tiene estimate para la modalidad seleccionada.",
                ]);
            }

            if (strtoupper((string) $estimate->currency) !== 'DOP') {
                throw ValidationException::withMessages([
                    'currency' =>
                        "La fase [{$phase->name}] debe cotizarse en DOP.",
                ]);
            }

            $milestones = TransformationImplementationMilestone::query()
                ->where(
                    'transformation_implementation_phase_id',
                    $phase->id
                )
                ->where('modality', $selectedModality)
                ->where(
                    'billing_status',
                    '!=',
                    TransformationImplementationMilestone::STATUS_CANCELLED
                )
                ->orderBy('sequence')
                ->get();

            if ($milestones->isEmpty()) {
                throw ValidationException::withMessages([
                    'milestones' =>
                        "La fase [{$phase->name}] debe tener al menos un hito comercial.",
                ]);
            }

            foreach ($milestones as $milestone) {
                if (
                    strtoupper((string) $milestone->currency)
                    !== strtoupper((string) $estimate->currency)
                ) {
                    throw ValidationException::withMessages([
                        'currency' =>
                            "Los hitos de [{$phase->name}] deben usar la misma moneda del estimate.",
                    ]);
                }
            }

            $estimateAmount = round(
                (float) $estimate->price_amount,
                2
            );

            $allocatedAmount = round(
                (float) $milestones->sum(
                    fn (
                        TransformationImplementationMilestone $milestone
                    ) => (float) $milestone->billing_amount
                ),
                2
            );

            if (
                abs($estimateAmount - $allocatedAmount)
                >= 0.005
            ) {
                throw ValidationException::withMessages([
                    'milestones' =>
                        "La suma de hitos de [{$phase->name}] debe coincidir exactamente con el precio de la fase.",
                ]);
            }

            $phaseReadiness[] = [
                'phase_id' => $phase->id,
                'phase_sequence' => $phase->sequence,
                'phase_name' => $phase->name,
                'capabilities_count' =>
                    $phase->capabilities->count(),
                'estimate_id' => $estimate->id,
                'modality' => $selectedModality,
                'price_amount' => $estimateAmount,
                'currency' => 'DOP',
                'milestones_count' => $milestones->count(),
                'allocated_amount' => $allocatedAmount,
            ];
        }

        return [
            'ready' => true,
            'selected_modality' => $selectedModality,
            'currency' => 'DOP',
            'phases_count' => $plan->phases->count(),
            'phases' => $phaseReadiness,
        ];
    }

    public function latestForAssessment(
        DiagnosisAssessment $assessment
    ): ?TransformationImplementationPlan {
        return TransformationImplementationPlan::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->latest('version')
            ->first();
    }
}
