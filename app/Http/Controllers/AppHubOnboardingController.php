<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\Subscriber;
use App\Services\Companies\CentralCompanyProfileService;
use App\Services\Diagnosis\InitialDiagnosisCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AppHubOnboardingController extends Controller
{
    public function show(
        Request $request,
        CentralCompanyProfileService $profiles
    ): InertiaResponse|RedirectResponse {
        $user = $request->user();

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($this->hasHubContext($user)) {
            if (
                $request->session()->get('apphub.intent')
                === \App\Services\Diagnosis\InitialDiagnosisCommercialService::INTENT
            ) {
                return redirect()->route('app.diagnosis.show');
            }

            return redirect()->route('app.gateway');
        }

        // Mantener compatibilidad con entradas T360 existentes.
        // La unificación visual del Diagnóstico 360 corresponde a F4.12-C.
        if ($assessment = $this->t360Assessment($user->id)) {
            return redirect()->route(
                'diagnosis.show',
                $assessment
            );
        }

        $profile = $profiles->onboardingDefaults($user);

        if ($workflow = $this->nativeDiagnosisWorkflow($user->id)) {
            $request->session()->put(
                'apphub.intent',
                InitialDiagnosisCommercialService::INTENT
            );

            $profile = array_replace(
                $profile,
                $this->diagnosisProfilePrefill($workflow, $user)
            );
        }

        return Inertia::render('Onboarding/AppHub', [
            'account' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'profile' => $profile,
        ]);
    }

    public function store(
        Request $request,
        CentralCompanyProfileService $profiles,
        InitialDiagnosisCommercialService $commercial
    ): RedirectResponse {
        $user = $request->user();

        abort_if(($user->role ?? null) === 'admin', 403);

        if ($this->hasHubContext($user)) {
            if (
                $request->session()->get('apphub.intent')
                === \App\Services\Diagnosis\InitialDiagnosisCommercialService::INTENT
            ) {
                return redirect()->route('app.diagnosis.show');
            }

            return redirect()->route('app.gateway');
        }

        if ($assessment = $this->t360Assessment($user->id)) {
            return redirect()->route(
                'diagnosis.show',
                $assessment
            );
        }

        $data = $request->validate(array_merge(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ],
            $profiles->rules()
        ));

        DB::transaction(function () use (
            $user,
            $data,
            $profiles
        ): void {
            $subscriber = $user->activeSubscribers()
                ->orderBy('subscribers.id')
                ->first();

            if (! $subscriber) {
                $subscriber = Subscriber::query()->create([
                    'name' => $data['company_name'],
                    'slug' => $this->uniqueSlug(
                        Subscriber::class,
                        $data['company_name']
                    ),
                    'country_code' => strtoupper(
                        $data['country_code']
                    ),
                    'currency' => $data['currency'],
                    'timezone' => $data['timezone'],
                    'active' => true,
                    'meta' => [
                        'source' =>
                            'app_hub_direct_onboarding',
                    ],
                ]);
            }

            $subscriber->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => 'owner',
                    'active' => true,
                ],
            ]);

            $company = Company::query()
                ->where('subscriber_id', $subscriber->id)
                ->first();

            if (! $company) {
                $company = Company::query()->create([
                    'name' => $data['company_name'],
                    'slug' => $this->uniqueSlug(
                        Company::class,
                        $data['company_name']
                    ),
                    'currency' => $data['currency'],
                    'timezone' => $data['timezone'],
                    'owner_user_id' => $user->id,
                    'subscriber_id' => $subscriber->id,
                    'active' => true,
                ]);
            } elseif (! $company->owner_user_id) {
                $company->forceFill([
                    'owner_user_id' => $user->id,
                ])->save();
            }

            $profiles->save(
                $company,
                $subscriber,
                $data,
                $user
            );

            $user->forceFill([
                'name' => $data['name'],
                'role' => 'subscriber',
            ])->save();
        });

        if ($this->nativeDiagnosisWorkflow($user->id)) {
            /*
             * Ya existe la solicitud Welcome. Ahora sí hay Company/Subscriber
             * owner y se puede materializar de forma idempotente la factura
             * comercial RD$0.00.
             */
            $commercial->ensure($user->fresh());

            $request->session()->forget('apphub.intent');

            return redirect()
                ->route('app.diagnosis.show')
                ->with(
                    'success',
                    'Tu empresa está lista. La solicitud del Diagnóstico 360 quedó registrada con su factura de cortesía RD$0.00 y pendiente de confirmación.'
                );
        }

        if (
            $request->session()->get('apphub.intent')
            === InitialDiagnosisCommercialService::INTENT
        ) {
            return redirect()
                ->route('app.diagnosis.show')
                ->with(
                    'success',
                    'Tu empresa está lista. Continuaremos con la solicitud del Diagnóstico 360.'
                );
        }

        return redirect()
            ->route('app.gateway')
            ->with(
                'success',
                'Tu cuenta LAUDAAPI está lista.'
            );
    }

    private function nativeDiagnosisWorkflow(
        int $userId
    ): ?DiagnosisAccessRequest {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $userId)
            ->where(
                'meta->source',
                InitialDiagnosisCommercialService::SOURCE
            )
            ->where(
                'status',
                '!=',
                DiagnosisAccessRequest::STATUS_REJECTED
            )
            ->with('contactRequest')
            ->latest('id')
            ->first();
    }

    private function diagnosisProfilePrefill(
        DiagnosisAccessRequest $workflow,
        $user
    ): array {
        $contact = $workflow->contactRequest;

        if (! $contact) {
            return [];
        }

        $size = (string) data_get(
            $contact->metadata,
            'company_size',
            ''
        );

        $size = match ($size) {
            '1 a 10 personas' => '1-10',
            '11 a 50 personas' => '11-50',
            '51 a 200 personas' => '51-200',
            'Más de 200 personas' => '201+',
            '1-10', '11-50', '51-200', '201+' => $size,
            default => '',
        };

        return array_filter([
            'company_name' => (string) $contact->company,
            'company_size' => $size,
            'billing_email' => (string) $user->email,
            'billing_phone' => (string) $contact->phone,
            'billing_contact_name' => (string) $contact->name,
        ], static fn (mixed $value): bool => $value !== '');
    }

    private function hasHubContext($user): bool
    {
        return $user->activeSubscribers()
            ->whereHas('company')
            ->exists();
    }

    private function t360Assessment(int $userId): mixed
    {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $userId)
            ->whereNotNull('diagnosis_assessment_id')
            ->latest('id')
            ->value('diagnosis_assessment_id');
    }

    private function uniqueSlug(
        string $modelClass,
        string $value
    ): string {
        $base = Str::slug($value) ?: 'empresa';
        $slug = $base;
        $suffix = 2;

        while (
            $modelClass::query()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
