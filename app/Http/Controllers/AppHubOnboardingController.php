<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\Subscriber;
use App\Services\Companies\CentralCompanyProfileService;
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

        return Inertia::render('Onboarding/AppHub', [
            'account' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'profile' => $profiles->onboardingDefaults($user),
        ]);
    }

    public function store(
        Request $request,
        CentralCompanyProfileService $profiles
    ): RedirectResponse {
        $user = $request->user();

        abort_if(($user->role ?? null) === 'admin', 403);

        if ($this->hasHubContext($user)) {
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

        return redirect()
            ->route('app.gateway')
            ->with(
                'success',
                'Tu cuenta LAUDAAPI está lista.'
            );
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
