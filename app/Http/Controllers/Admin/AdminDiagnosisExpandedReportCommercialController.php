<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\RecordDiagnosisExpandedReportPaymentRequest;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReportOrder;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisCommercialNotificationService;
use App\Services\Diagnosis\DiagnosisExpandedReportCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminDiagnosisExpandedReportCommercialController extends Controller
{
    public function prepareInvoice(
        Request $request,
        ContactRequest $contact,
        DiagnosisExpandedReportOrder $order,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportCommercialService $commercialService,
        DiagnosisCommercialNotificationService $notificationService
    ): RedirectResponse
    {
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

        $freshOrder = $order->fresh();

        if ($freshOrder) {
            $notificationService->invoiceRequired(
                $assessment,
                'expanded_report',
                $invoice,
                $freshOrder
            );
        }

        return back()->with(
            'success',
            "Factura {$invoice->number} preparada correctamente."
        );
    }

    public function recordPayment(
        RecordDiagnosisExpandedReportPaymentRequest $request,
        ContactRequest $contact,
        DiagnosisExpandedReportOrder $order,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportCommercialService $commercialService,
        DiagnosisCommercialNotificationService $notificationService
    ): RedirectResponse
    {
        $assessment = $this->assessmentFor($contact, $accessService);
        $this->assertOrder($order, $assessment);
        $payment = $commercialService->recordFullPayment(
            $order,
            $request->user(),
            $request->string('method')->toString(),
            $request->filled('reference') ? $request->string('reference')->toString() : null
        );

        $freshOrder = $order->fresh([
            'invoice',
        ]);

        if (
            $freshOrder
            && $freshOrder->invoice
        ) {
            $notificationService->paymentConfirmed(
                $assessment,
                'expanded_report',
                $freshOrder->invoice,
                $freshOrder
            );
        }

        return back()->with('success', "Pago #{$payment->id} registrado. Acceso comercial habilitado.");
    }

    private function assessmentFor(ContactRequest $contact, DiagnosisAccessService $accessService): DiagnosisAssessment
    {
        if (! $accessService->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->with('assessment')
            ->firstOrFail();

        if (! $workflow->assessment) {
            abort(422, 'La solicitud no tiene diagnóstico vinculado.');
        }

        return $workflow->assessment;
    }

    private function assertOrder(DiagnosisExpandedReportOrder $order, DiagnosisAssessment $assessment): void
    {
        abort_unless((int) $order->diagnosis_assessment_id === (int) $assessment->id, 404);
    }
}
