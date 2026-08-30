<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisInvitationController extends Controller
{
    public function accept(
        Request $request,
        DiagnosisAccessRequest $access
    ): Response|RedirectResponse {
        if (!$request->hasValidSignature()) {
            $url = app('url');

            $hasCorrectSignature =
                $url->hasCorrectSignature($request);

            $isExpired =
                $hasCorrectSignature
                && !$url->signatureHasNotExpired($request);

            return Inertia::render(
                'Diagnosis/InvitationExpired',
                [
                    'reason' => $isExpired
                        ? 'expired'
                        : 'invalid',
                    'activated' => (
                        $isExpired
                        && (
                            $access->status
                                === DiagnosisAccessRequest::STATUS_ACTIVE
                            || $access->invitation_accepted_at !== null
                        )
                    ),
                    'login_url' => route('login'),
                    'home_url' => url('/'),
                ]
            );
        }

        $access->loadMissing(['user', 'assessment']);

        if (!$access->user || !$access->assessment) {
            abort(404);
        }

        abort_unless(
            (bool) $access->assessment->is_active,
            410,
            'Esta evaluación ya no está activa.'
        );

        if ($access->status === DiagnosisAccessRequest::STATUS_REJECTED) {
            abort(403, 'Este acceso fue rechazado.');
        }

        if ($request->user() && (int) $request->user()->id !== (int) $access->user_id) {
            abort(403, 'La invitación pertenece a otro usuario.');
        }

        if (!$access->user->email_verified_at) {
            $access->user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        if (!$request->user()) {
            Auth::login($access->user, true);
            $request->session()->regenerate();
        }

        if (!$access->invitation_accepted_at) {
            $access->forceFill([
                'status' => DiagnosisAccessRequest::STATUS_ACTIVE,
                'invitation_accepted_at' => now(),
            ])->save();

            AuditService::log('diagnosis_invitation_accepted', $access, [
                'user_id' => $access->user_id,
                'diagnosis_assessment_id' => $access->diagnosis_assessment_id,
            ]);
        }

        if ((bool) $access->user->must_change_password) {
            return redirect()->route('diagnosis.access.password.show', $access);
        }

        return redirect()->route('diagnosis.show', $access->assessment);
    }

    public function password(
        Request $request,
        DiagnosisAccessRequest $access
    ): Response|RedirectResponse {
        $this->authorizeAccessOwner($request, $access);

        if (!$request->user()->must_change_password) {
            return redirect()->route('diagnosis.show', $access->assessment);
        }

        return Inertia::render('Diagnosis/SetPassword', [
            'access' => [
                'public_id' => $access->public_id,
                'company' => $access->contactRequest?->company ?? $access->assessment?->organization_name,
                'email' => $request->user()->email,
            ],
            'endpoint' => route('diagnosis.access.password.store', $access),
        ]);
    }

    public function storePassword(
        Request $request,
        DiagnosisAccessRequest $access
    ): RedirectResponse {
        $this->authorizeAccessOwner($request, $access);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        AuditService::log('diagnosis_access_password_created', $access, [
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('diagnosis.show', $access->assessment)
            ->with('success', 'Contraseña creada. Ya puedes comenzar tu diagnóstico gratuito.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();

        $assessment = DiagnosisAssessment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $assessment) {
            $subscriberIds = $user->activeSubscribers()
                ->wherePivotIn('role', ['owner', 'admin'])
                ->pluck('subscribers.id');

            $companyIds = \App\Models\Company::query()
                ->whereIn('subscriber_id', $subscriberIds)
                ->where('active', true)
                ->pluck('id');

            $assessment = DiagnosisAssessment::query()
                ->whereIn('organization_id', $companyIds)
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        if (!$assessment) {
            return redirect('/')->with('error', 'No encontramos un diagnóstico asignado a tu usuario.');
        }

        return redirect()->route('diagnosis.show', $assessment);
    }

    private function authorizeAccessOwner(Request $request, DiagnosisAccessRequest $access): void
    {
        $access->loadMissing(['contactRequest', 'assessment']);

        abort_unless(
            (int) $access->user_id === (int) $request->user()->id,
            403
        );

        abort_unless($access->assessment, 404);

        abort_unless(
            (bool) $access->assessment->is_active,
            410,
            'Esta evaluación ya no está activa.'
        );
    }
}
