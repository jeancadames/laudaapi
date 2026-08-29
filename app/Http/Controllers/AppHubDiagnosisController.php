<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Services\Companies\CentralCompanyProfileService;
use App\Services\Diagnosis\InitialDiagnosisCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AppHubDiagnosisController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        $request->session()->put(
            'apphub.intent',
            InitialDiagnosisCommercialService::INTENT
        );

        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (($request->user()->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('app.diagnosis.show');
    }

    public function show(
        Request $request,
        CentralCompanyProfileService $profiles,
        InitialDiagnosisCommercialService $commercial
    ): Response|RedirectResponse {
        $user = $request->user();

        abort_unless($user, 403);

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        [$subscriber, $company, $role] =
            $profiles->resolveEditableContext($user);

        if (! $subscriber || ! $company) {
            $hasActiveMembership = DB::table('subscriber_user')
                ->where('user_id', $user->id)
                ->where('active', 1)
                ->exists();

            if ($hasActiveMembership) {
                abort(
                    403,
                    'Solo el owner o un administrador del tenant puede administrar el Diagnóstico 360.'
                );
            }

            $historical = DiagnosisAccessRequest::query()
                ->where('user_id', $user->id)
                ->whereNotNull('diagnosis_assessment_id')
                ->with('assessment')
                ->latest('id')
                ->first();

            if ($historical?->assessment) {
                return redirect()->route(
                    'diagnosis.show',
                    $historical->assessment
                );
            }

            $request->session()->put(
                'apphub.intent',
                InitialDiagnosisCommercialService::INTENT
            );

            return redirect()->route('app.onboarding.show');
        }

        abort_unless(
            in_array($role, ['owner', 'admin'], true),
            403
        );

        return Inertia::render('App/Diagnosis360', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subscriber_id' => $subscriber->id,
            ],
            'state' => $commercial->state($user, $company),
            'auto_start' =>
                $request->session()->get('apphub.intent')
                === InitialDiagnosisCommercialService::INTENT,
            'endpoints' => [
                'request' => route(
                    'app.diagnosis.request',
                    [],
                    false
                ),
                'invoices' => url('/subscriber/invoices'),
                'company' => url('/subscriber/company'),
                'home' => route('app.gateway', [], false),
            ],
        ]);
    }

    public function store(
        Request $request,
        InitialDiagnosisCommercialService $commercial
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user, 403);
        abort_if(($user->role ?? null) === 'admin', 403);

        $workflow = $commercial->ensure($user);

        $request->session()->forget('apphub.intent');

        if (
            $workflow->assessment
            && $workflow->status === DiagnosisAccessRequest::STATUS_ACTIVE
        ) {
            return redirect()
                ->route('app.diagnosis.show')
                ->with(
                    'success',
                    'Tu Diagnóstico 360 ya estaba habilitado.'
                );
        }

        return redirect()
            ->route('app.diagnosis.show')
            ->with(
                'success',
                'Solicitud registrada. Generamos tu factura de cortesía en RD$0.00 y quedó pendiente de confirmación.'
            );
    }
}
