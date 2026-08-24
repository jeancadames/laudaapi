<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\RecordDiagnosisDetailedRoadmapPaymentRequest;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmapOrder;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminDiagnosisDetailedRoadmapCommercialController extends Controller
{
    public function prepareInvoice(
        Request $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmapOrder $order,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapCommercialService $commercialService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertOrder(
            $order,
            $assessment
        );

        $invoice = $commercialService->prepareInvoice(
            $order,
            $request->user()
        );

        return back()->with(
            'success',
            "Factura {$invoice->number} preparada."
        );
    }

    public function recordPayment(
        RecordDiagnosisDetailedRoadmapPaymentRequest $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmapOrder $order,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapCommercialService $commercialService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertOrder(
            $order,
            $assessment
        );

        $payment = $commercialService->recordFullPayment(
            $order,
            $request->user(),
            $request->validated('method'),
            $request->validated('reference')
        );

        return back()->with(
            'success',
            sprintf(
                'Pago completo de %s %s confirmado.',
                $payment->currency,
                number_format(
                    (float) $payment->amount,
                    2
                )
            )
        );
    }

    private function assessmentFor(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): DiagnosisAssessment {
        if (! $accessService->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->with('assessment')
            ->firstOrFail();

        if (! $workflow->assessment) {
            abort(
                422,
                'La solicitud no tiene diagnóstico vinculado.'
            );
        }

        return $workflow->assessment;
    }

    private function assertOrder(
        DiagnosisDetailedRoadmapOrder $order,
        DiagnosisAssessment $assessment
    ): void {
        abort_unless(
            (int) $order->diagnosis_assessment_id
                === (int) $assessment->id,
            404
        );
    }
}
