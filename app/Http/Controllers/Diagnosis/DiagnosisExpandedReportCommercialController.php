<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisExpandedReportCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiagnosisExpandedReportCommercialController extends Controller
{
    public function requestPurchase(Request $request, DiagnosisAssessment $assessment, DiagnosisExpandedReportCommercialService $commercialService): RedirectResponse
    {
        Gate::authorize('view', $assessment);
        $commercialService->requestPurchase($assessment, $request->user());

        return back()->with('success', 'Solicitud del Informe Ampliado recibida. LAUDA continuará con la preparación comercial.');
    }
}
