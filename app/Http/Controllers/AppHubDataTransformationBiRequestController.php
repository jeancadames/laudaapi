<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use App\Services\Ecosystem\SubscriberTransformation360DashboardService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppHubDataTransformationBiRequestController
    extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        SubscriberTransformation360DashboardService $dashboard,
        TransformationImplementationRequestService $implementationRequests
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user, 403);

        abort_unless(
            ($user->role ?? null) === 'subscriber',
            403
        );

        $subscriberId = (int) (
            $subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless(
            $subscriberId > 0,
            403
        );

        $tenantAccess = $tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            ),
            403,
            'Solo un owner o administrador del tenant puede solicitar la implementación.'
        );

        /*
         * La Company siempre se resuelve en servidor.
         *
         * El endpoint no acepta company_id, assessment_id,
         * plan_id ni phase_capability_id desde el navegador.
         */
        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            $company,
            404
        );

        abort_unless(
            (int) ($company->subscriber_id ?? 0)
                === $subscriberId,
            403
        );

        $transformation360 = $dashboard->forCompany(
            $company,
            (int) $user->id
        );

        abort_unless(
            (bool) (
                $transformation360['visible']
                ?? false
            ),
            403
        );

        $assessmentId = (int) (
            $transformation360['assessment_id']
            ?? 0
        );

        abort_unless(
            $assessmentId > 0,
            422,
            'La empresa todavía no tiene un Diagnóstico 360 disponible para esta solicitud.'
        );

        $assessment = DiagnosisAssessment::query()
            ->findOrFail($assessmentId);

        /*
         * Siempre seleccionamos en servidor la última versión
         * presentada del Plan del Diagnóstico vigente.
         */
        $plan = TransformationImplementationPlan::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->where(
                'status',
                TransformationImplementationPlan::STATUS_PRESENTED
            )
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        abort_unless(
            $plan,
            422,
            'La solicitud requiere un Plan de Implementación presentado.'
        );

        /*
         * Resolver exclusivamente la capability BI dentro de ese Plan.
         * Tampoco se recibe su ID desde el navegador.
         */
        $phaseCapability =
            TransformationImplementationPhaseCapability::query()
                ->join(
                    'transformation_implementation_phases as phase',
                    'phase.id',
                    '=',
                    'transformation_implementation_phase_capabilities.transformation_implementation_phase_id'
                )
                ->where(
                    'phase.transformation_implementation_plan_id',
                    $plan->id
                )
                ->where(
                    'transformation_implementation_phase_capabilities.capability_key',
                    'data_transformation_bi'
                )
                ->select(
                    'transformation_implementation_phase_capabilities.*'
                )
                ->orderBy('phase.sequence')
                ->orderBy(
                    'transformation_implementation_phase_capabilities.sequence'
                )
                ->first();

        abort_unless(
            $phaseCapability,
            422,
            'Datos e Inteligencia BI no forma parte del Plan de Implementación presentado.'
        );

        $implementationRequests->requestFromTenantAdmin(
            $company,
            $assessment,
            $plan,
            $phaseCapability,
            $user
        );

        return back()->with(
            'success',
            'Solicitud de implementación enviada. LAUDA revisará el alcance antes de avanzar.'
        );
    }
}
