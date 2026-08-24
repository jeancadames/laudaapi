<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisDetailedRoadmapService
{
    public function __construct(
        private readonly DiagnosisDetailedRoadmapGenerator $generator
    ) {
    }

    public function createOrGetDraft(
        DiagnosisAssessment $assessment,
        User $actor
    ): DiagnosisDetailedRoadmap {
        $this->assertEligible($assessment);

        return DB::transaction(function () use ($assessment, $actor) {
            $locked = DiagnosisAssessment::query()
                ->whereKey($assessment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEligible($locked);

            $active = DiagnosisDetailedRoadmap::query()
                ->where('diagnosis_assessment_id', $locked->id)
                ->whereIn('status', [
                    DiagnosisDetailedRoadmap::STATUS_DRAFT,
                    DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW,
                ])
                ->orderByDesc('version')
                ->first();

            if ($active) {
                return $active;
            }

            $sourceReport = $this->latestPublishedReport($locked);

            $latestVersion = (int) DiagnosisDetailedRoadmap::query()
                ->where('diagnosis_assessment_id', $locked->id)
                ->max('version');

            $generated = $this->generator->generate(
                $locked,
                $sourceReport
            );

            return DiagnosisDetailedRoadmap::create([
                'diagnosis_assessment_id' => $locked->id,
                'source_expanded_report_id' => $sourceReport->id,
                'version' => $latestVersion + 1,
                'status' => DiagnosisDetailedRoadmap::STATUS_DRAFT,
                'generated_by_user_id' => $actor->id,
                'methodology_version' => $locked->methodology_version,
                'source_snapshot' => $generated['source_snapshot'],
                'roadmap' => $generated['roadmap'],
            ]);
        });
    }

    public function regenerateDraft(
        DiagnosisDetailedRoadmap $roadmap,
        User $actor
    ): DiagnosisDetailedRoadmap {
        if ($roadmap->status !== DiagnosisDetailedRoadmap::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'roadmap' => [
                    'Solo una versión en borrador puede regenerarse.',
                ],
            ]);
        }

        $assessment = $roadmap->assessment;

        if (! $assessment) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El Roadmap no tiene un diagnóstico asociado.',
                ],
            ]);
        }

        $this->assertEligible($assessment);
        $sourceReport = $this->latestPublishedReport($assessment);

        $generated = $this->generator->generate(
            $assessment,
            $sourceReport
        );

        $roadmap->forceFill([
            'source_expanded_report_id' => $sourceReport->id,
            'generated_by_user_id' => $actor->id,
            'methodology_version' => $assessment->methodology_version,
            'source_snapshot' => $generated['source_snapshot'],
            'roadmap' => $generated['roadmap'],
        ])->save();

        return $roadmap->fresh();
    }

    public function saveReviewNotes(
        DiagnosisDetailedRoadmap $roadmap,
        User $actor,
        ?string $notes
    ): DiagnosisDetailedRoadmap {
        if (! $roadmap->isEditable()) {
            throw ValidationException::withMessages([
                'roadmap' => ['Una versión publicada no puede editarse.'],
            ]);
        }

        $roadmap->forceFill([
            'review_notes' => filled($notes) ? trim((string) $notes) : null,
            'reviewed_by_user_id' => $actor->id,
        ])->save();

        AuditService::log(
            'diagnosis_detailed_roadmap_review_notes_saved',
            $roadmap,
            [
                'assessment_id' => $roadmap->diagnosis_assessment_id,
                'version' => $roadmap->version,
                'actor_user_id' => $actor->id,
            ]
        );

        return $roadmap->fresh();
    }

    public function markUnderReview(
        DiagnosisDetailedRoadmap $roadmap,
        User $actor
    ): DiagnosisDetailedRoadmap {
        if ($roadmap->status !== DiagnosisDetailedRoadmap::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'roadmap' => ['Solo un borrador puede pasar a revisión.'],
            ]);
        }

        $roadmap->forceFill([
            'status' => DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW,
            'reviewed_by_user_id' => $actor->id,
        ])->save();

        AuditService::log(
            'diagnosis_detailed_roadmap_under_review',
            $roadmap,
            [
                'assessment_id' => $roadmap->diagnosis_assessment_id,
                'version' => $roadmap->version,
                'actor_user_id' => $actor->id,
            ]
        );

        return $roadmap->fresh();
    }

    public function publish(
        DiagnosisDetailedRoadmap $roadmap,
        User $actor
    ): DiagnosisDetailedRoadmap {
        if (! $roadmap->isEditable()) {
            throw ValidationException::withMessages([
                'roadmap' => ['Solo una versión editable puede publicarse.'],
            ]);
        }

        $payload = $roadmap->roadmap ?? [];

        foreach ([
            'executive_direction.objective',
            'planning_principles',
            'horizons',
            'phases',
            'initiatives',
            'governance',
            'success_framework',
            'scope_note.body',
        ] as $path) {
            $value = data_get($payload, $path);

            if ($value === null || $value === '' || $value === []) {
                throw ValidationException::withMessages([
                    'roadmap' => ["El Roadmap no está completo: falta {$path}."],
                ]);
            }
        }

        foreach (data_get($payload, 'initiatives', []) as $index => $initiative) {
            foreach ([
                'id',
                'title',
                'objective',
                'actions',
                'owner_role',
                'dependencies',
                'impact',
                'effort',
                'success_metrics',
                'phase',
                'horizon',
                'sequence',
            ] as $field) {
                if (
                    ! array_key_exists($field, $initiative)
                    || $initiative[$field] === null
                    || $initiative[$field] === ''
                ) {
                    throw ValidationException::withMessages([
                        'roadmap' => [
                            sprintf(
                                'La iniciativa %d está incompleta: falta %s.',
                                $index + 1,
                                $field
                            ),
                        ],
                    ]);
                }
            }
        }

        $roadmap->forceFill([
            'status' => DiagnosisDetailedRoadmap::STATUS_PUBLISHED,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'published_at' => now(),
        ])->save();

        AuditService::log(
            'diagnosis_detailed_roadmap_published',
            $roadmap,
            [
                'assessment_id' => $roadmap->diagnosis_assessment_id,
                'version' => $roadmap->version,
                'source_expanded_report_id' => $roadmap->source_expanded_report_id,
                'actor_user_id' => $actor->id,
            ]
        );

        return $roadmap->fresh();
    }

    private function assertEligible(
        DiagnosisAssessment $assessment
    ): void {
        if (
            $assessment->status !== 'reviewed'
            || $assessment->published_at === null
        ) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El Roadmap Detallado requiere un resultado oficial publicado.',
                ],
            ]);
        }

        if (! DiagnosisExpandedReport::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'expanded_report' => [
                    'Debe existir un Informe Ampliado publicado antes de preparar el Roadmap Detallado.',
                ],
            ]);
        }
    }

    private function latestPublishedReport(
        DiagnosisAssessment $assessment
    ): DiagnosisExpandedReport {
        return DiagnosisExpandedReport::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();
    }
}
