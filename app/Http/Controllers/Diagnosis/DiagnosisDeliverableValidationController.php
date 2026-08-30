<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiagnosisDeliverableValidationController extends Controller
{
    public function review(
        Request $request,
        DiagnosisAssessment $assessment,
        string $deliverable,
        DiagnosisDeliverableValidationService $validations
    ): RedirectResponse {
        $this->authorizeTenant($request, $assessment);

        $validations->markReviewed(
            $this->resolveDeliverable($assessment, $deliverable),
            $request->user()
        );

        return back()->with(
            'success',
            'Documento marcado como revisado.'
        );
    }

    public function validateDocument(
        Request $request,
        DiagnosisAssessment $assessment,
        string $deliverable,
        DiagnosisDeliverableValidationService $validations
    ): RedirectResponse {
        $this->authorizeTenant($request, $assessment);

        $validations->validate(
            $this->resolveDeliverable($assessment, $deliverable),
            $request->user()
        );

        return back()->with(
            'success',
            'Documento validado. Esta validación no constituye contratación ni aceptación comercial.'
        );
    }

    public function requestAdjustment(
        Request $request,
        DiagnosisAssessment $assessment,
        string $deliverable,
        DiagnosisDeliverableValidationService $validations
    ): RedirectResponse {
        $this->authorizeTenant($request, $assessment);

        $data = $request->validate([
            'adjustment_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $validations->requestAdjustment(
            $this->resolveDeliverable($assessment, $deliverable),
            $request->user(),
            $data['adjustment_note']
        );

        return back()->with(
            'success',
            'Solicitud de ajuste registrada para esta versión.'
        );
    }

    private function authorizeTenant(
        Request $request,
        DiagnosisAssessment $assessment
    ): void {
        Gate::authorize('view', $assessment);

        abort_if(
            $request->user()?->isAdmin(),
            403,
            'La validación corresponde al tenant.'
        );
    }

    private function resolveDeliverable(
        DiagnosisAssessment $assessment,
        string $deliverable
    ): Model {
        return match ($deliverable) {
            'expanded-report' => DiagnosisExpandedReport::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->firstOrFail(),

            'roadmap' => DiagnosisDetailedRoadmap::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->where('status', DiagnosisDetailedRoadmap::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->firstOrFail(),

            'implementation-plan' => TransformationImplementationPlan::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->whereIn('status', [
                    TransformationImplementationPlan::STATUS_PRESENTED,
                    TransformationImplementationPlan::STATUS_ACCEPTED,
                    TransformationImplementationPlan::STATUS_ACTIVE,
                    TransformationImplementationPlan::STATUS_COMPLETED,
                ])
                ->whereNotNull('presented_at')
                ->orderByDesc('version')
                ->firstOrFail(),

            default => abort(404),
        };
    }
}
