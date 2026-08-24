<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiagnosisDetailedRoadmapCommercialController extends Controller
{
    public function requestPurchase(
        Request $request,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmapCommercialService $commercialService
    ): RedirectResponse {
        Gate::authorize('view', $assessment);

        $commercialService->requestPurchase(
            $assessment,
            $request->user()
        );

        return back()->with(
            'success',
            'Solicitud del Roadmap Detallado recibida. LAUDA preparará la facturación correspondiente.'
        );
    }
}
