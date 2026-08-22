<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\SubmitDiagnosisAssessmentRequest;
use App\Http\Requests\Diagnosis\UpdateDiagnosisAssessmentRequest;
use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DigitalDiagnosisController extends Controller
{
    public function show(DiagnosisAssessment $assessment): Response
    {
        Gate::authorize('view', $assessment);

        return Inertia::render('Diagnosis/Show', [
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'current_step' => $assessment->current_step,
                'answers' => $assessment->answers ?? [],
                'notes' => $assessment->notes ?? [],
                'started_at' => $assessment->started_at?->toIso8601String(),
                'submitted_at' => $assessment->submitted_at?->toIso8601String(),
                'updated_at' => $assessment->updated_at?->toIso8601String(),
            ],
            'organization' => [
                'id' => $assessment->organization_id ?? $assessment->id,
                'name' => $assessment->organization_name,
            ],
            'endpoints' => [
                'update' => route('diagnosis.update', $assessment),
                'submit' => route('diagnosis.submit', $assessment),
                'back' => url('/'),
            ],
        ]);
    }

    public function update(
        UpdateDiagnosisAssessmentRequest $request,
        DiagnosisAssessment $assessment
    ): RedirectResponse {
        Gate::authorize('update', $assessment);

        DB::transaction(function () use ($request, $assessment): void {
            $assessment->answers = $request->validated('answers');
            $assessment->notes = $request->validated('notes') ?? [];
            $assessment->current_step = $request->integer('current_step');

            if ($assessment->status === 'draft') {
                $assessment->status = 'in_progress';
                $assessment->started_at ??= now();
            }

            $assessment->save();
        });

        return back()->with('success', 'Diagnóstico guardado.');
    }

    public function submit(
        SubmitDiagnosisAssessmentRequest $request,
        DiagnosisAssessment $assessment,
        Lauda360ScoringService $scoring
    ): RedirectResponse {
        Gate::authorize('submit', $assessment);

        $answers = $request->validated('answers');
        $notes = $request->validated('notes') ?? [];
        $result = $scoring->calculate($answers);

        DB::transaction(function () use ($assessment, $answers, $notes, $result): void {
            $assessment->fill([
                'answers' => $answers,
                'notes' => $notes,
                'current_step' => (int) config('lauda360_diagnosis.steps', 11),
                'status' => 'submitted',
                'started_at' => $assessment->started_at ?? now(),
                'submitted_at' => now(),
                ...$result,
            ])->save();
        });

        return redirect()
            ->route('diagnosis.show', $assessment)
            ->with('success', 'Diagnóstico enviado correctamente.');
    }
}
