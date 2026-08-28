<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AppHubOnboardingController extends Controller
{
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($this->hasHubContext($user)) {
            return redirect()->route('app.gateway');
        }

        // No secuestrar usuarios que llegaron por Transformación 360.
        if ($assessment = $this->t360Assessment($user->id)) {
            return redirect()->route('diagnosis.show', $assessment);
        }

        return Inertia::render('Onboarding/AppHub', [
            'account' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'defaults' => [
                'country_code' => 'DO',
                'currency' => 'DOP',
                'timezone' => 'America/Santo_Domingo',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if(($user->role ?? null) === 'admin', 403);

        if ($this->hasHubContext($user)) {
            return redirect()->route('app.gateway');
        }

        if ($assessment = $this->t360Assessment($user->id)) {
            return redirect()->route('diagnosis.show', $assessment);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'country_code' => ['required', 'string', 'size:2'],
            'currency' => ['required', Rule::in(['DOP', 'USD', 'EUR'])],
            'timezone' => ['required', 'string', 'max:100'],
            'company_size' => [
                'required',
                Rule::in(['1-10', '11-50', '51-200', '201+']),
            ],
        ]);

        DB::transaction(function () use ($user, $data): void {
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
                    'country_code' => strtoupper($data['country_code']),
                    'currency' => $data['currency'],
                    'timezone' => $data['timezone'],
                    'active' => true,
                    'meta' => [
                        'source' => 'app_hub_direct_onboarding',
                        'app_hub_onboarding' => [
                            'phone' => $data['phone'] ?: null,
                            'legal_name' => $data['legal_name'] ?: null,
                            'tax_id' => $data['tax_id'] ?: null,
                            'company_size' => $data['company_size'],
                            'completed_at' => now()->toIso8601String(),
                        ],
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

            $meta = is_array($subscriber->meta)
                ? $subscriber->meta
                : [];

            $previous = is_array($meta['app_hub_onboarding'] ?? null)
                ? $meta['app_hub_onboarding']
                : [];

            $meta['source'] ??= 'app_hub_direct_onboarding';
            $meta['app_hub_onboarding'] = array_merge($previous, [
                'phone' => $data['phone'] ?: null,
                'legal_name' => $data['legal_name'] ?: null,
                'tax_id' => $data['tax_id'] ?: null,
                'company_size' => $data['company_size'],
                'completed_at' =>
                    $previous['completed_at']
                    ?? now()->toIso8601String(),
            ]);

            $subscriber->forceFill([
                'country_code' => strtoupper($data['country_code']),
                'currency' => $data['currency'],
                'timezone' => $data['timezone'],
                'meta' => $meta,
                'active' => true,
            ])->save();

            $user->forceFill([
                'name' => $data['name'],
                'role' => 'subscriber',
            ])->save();
        });

        return redirect()
            ->route('app.gateway')
            ->with('success', 'Tu cuenta LAUDAAPI está lista.');
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

    private function uniqueSlug(string $modelClass, string $value): string
    {
        $base = Str::slug($value) ?: 'empresa';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
