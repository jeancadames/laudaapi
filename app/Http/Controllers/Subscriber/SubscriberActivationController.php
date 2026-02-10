<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubscriberActivationController extends Controller
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Estados permitidos para ver la pantalla de activación en el panel.
     */
    private const VIEW_ALLOWED_STATUSES = [
        ActivationRequest::STATUS_ACCEPTED,
        ActivationRequest::STATUS_TRIALING,
        ActivationRequest::STATUS_CONVERTED,
    ];

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', self::VIEW_ALLOWED_STATUSES)
            ->latest('id')
            ->first();

        if (!$activation) {
            return redirect()
                ->route('subscriber')
                ->with('error', 'No tienes una solicitud de activación aceptada para continuar.');
        }

        // Fuente de verdad del subscriber actual del user: pivot subscriber_user (active=1)
        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->value('subscriber_id');

        $subscriber = $subscriberId ? Subscriber::query()->find($subscriberId) : null;

        // Company por unique subscriber_id (si ya existe)
        $company = $subscriber
            ? Company::query()->where('subscriber_id', $subscriber->id)->first()
            : null;

        // Tax profile 1:1 por company
        $taxProfile = $company
            ? CompanyTaxProfile::query()->where('company_id', $company->id)->first()
            : null;

        // Subscription por subscriber
        $subscription = $subscriber
            ? Subscription::query()->where('subscriber_id', $subscriber->id)->latest('id')->first()
            : null;

        // Trial info (de activation)
        $trialActive = false;
        $trialDaysLeft = 0;

        if ($activation->trial_starts_at && $activation->trial_ends_at) {
            $trialActive = $activation->trial_starts_at <= now() && $activation->trial_ends_at >= now();
            $trialDaysLeft = max(0, now()->startOfDay()->diffInDays($activation->trial_ends_at, false));
        }

        return Inertia::render('Subscriber/Activation', [
            'activation' => [
                'id' => $activation->id,
                'status' => $activation->status,
                'company' => $activation->company,
                'email' => $activation->email,
                'trial_starts_at_human' => $activation->trial_starts_at?->format('Y-m-d'),
                'trial_ends_at_human' => $activation->trial_ends_at?->format('Y-m-d'),
                'trial_days_left' => (int) $trialDaysLeft,
                'trial_active' => (bool) $trialActive,
                'trial_days' => (int) ($activation->trial_days ?? 30),
            ],

            'state' => [
                'has_activation_request' => true,
                'has_subscriber' => (bool) $subscriber,
                'has_company' => (bool) $company,
                'has_tax_profile' => (bool) $taxProfile,
                'has_subscription' => (bool) $subscription,
            ],

            'subscriber' => $subscriber ? [
                'id' => $subscriber->id,
                'name' => $subscriber->name,
                'currency' => $subscriber->currency,
                'timezone' => $subscriber->timezone,
            ] : null,

            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ] : null,

            // opcional: mandar el tax profile a UI si quieres mostrarlo aquí
            'tax_profile' => $taxProfile ? [
                'id' => $taxProfile->id,
                'legal_name' => $taxProfile->legal_name,
                'trade_name' => $taxProfile->trade_name,
                'country_code' => $taxProfile->country_code,
                'tax_id' => $taxProfile->tax_id,
                'tax_id_type' => $taxProfile->tax_id_type,
                'billing_email' => $taxProfile->billing_email,
            ] : null,

            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'currency' => $subscription->currency,
                'trial_ends_at_human' => $subscription->trial_ends_at?->format('Y-m-d'),
                'period_end_human' => $subscription->current_period_end?->format('Y-m-d'),
            ] : null,
        ]);
    }

    public function activate(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // activar SOLO la última accepted
        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->where('status', ActivationRequest::STATUS_ACCEPTED)
            ->latest('id')
            ->first();

        if (!$activation) {
            AuditService::log('subscriber_activation_denied', null, [
                'reason' => 'no_accepted_activation_request',
                'user_id' => $user->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No tienes una solicitud aceptada para iniciar el trial.');
        }

        try {
            $result = DB::transaction(function () use ($user, $activation) {

                // 1) subscriber + pivot
                [$subscriber, $subscriberCreated] = $this->ensureSubscriber($user, $activation);

                // 2) company (unique subscriber_id)
                [$company, $companyCreated] = $this->ensureCompany($user, $subscriber, $activation);

                // ✅ 2.5) tax profile 1:1 por company (creado en activación)
                [$taxProfile, $taxProfileCreated] = $this->ensureCompanyTaxProfile($company, $activation);

                // 3) subscription trialing (solo crea si no existe)
                [$subscription, $subscriptionCreated] = $this->ensureSubscription($user, $subscriber, $activation);

                // 4) activation status + trial dates (accepted -> trialing)
                $this->ensureActivationTrial($activation);

                // 5) NO-OP (users no tiene subscriber_id/company_id)
                $this->safeLinkUser($user, $subscriber->id, $company->id);

                // 6) invalidar cache dashboard subscriber
                Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

                return [
                    'subscriber_id' => $subscriber->id,
                    'company_id' => $company->id,
                    'tax_profile_id' => $taxProfile->id,
                    'subscription_id' => $subscription->id,
                    'created' => [
                        'subscriber' => $subscriberCreated,
                        'company' => $companyCreated,
                        'tax_profile' => $taxProfileCreated,
                        'subscription' => $subscriptionCreated,
                    ],
                ];
            });

            // ✅ Audit FUERA de la transacción
            AuditService::log('subscriber_activation_completed', $activation, [
                'user_id' => $user->id,
                'activation_id' => $activation->id,
                'subscriber_id' => $result['subscriber_id'],
                'company_id' => $result['company_id'],
                'tax_profile_id' => $result['tax_profile_id'],
                'subscription_id' => $result['subscription_id'],
                'created' => $result['created'],
                'new_activation_status' => ActivationRequest::STATUS_TRIALING,
            ], ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            AuditService::log('subscriber_activation_failed', $activation, [
                'user_id' => $user->id,
                'activation_id' => $activation->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'Falló la activación: ' . $e->getMessage());
        }

        return back()->with('success', 'Activación completada. Ya tienes empresa, perfil fiscal y trial activo.');
    }

    // ------------------------
    // ENSURE METHODS
    // ------------------------

    protected function ensureSubscriber($user, ActivationRequest $activation): array
    {
        // 1) pivot activo
        $existingId = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->value('subscriber_id');

        if ($existingId > 0) {
            $s = Subscriber::query()->find($existingId);
            if ($s) return [$s, false];
        }

        $name = trim((string) ($activation->company ?: $activation->name ?: 'Subscriber'));
        $slug = $this->uniqueSlug('subscribers', 'slug', Str::slug($name) ?: 'subscriber');

        $subscriber = Subscriber::create([
            'name' => $name,
            'slug' => $slug,
            'country_code' => 'DO',
            'currency' => 'USD',
            'timezone' => 'America/Bogota',
            'active' => true,
            'meta' => null,
        ]);

        $this->ensureSubscriberUserPivot($subscriber->id, $user->id);

        return [$subscriber, true];
    }

    protected function ensureSubscriberUserPivot(int $subscriberId, int $userId): void
    {
        DB::table('subscriber_user')->insertOrIgnore([
            'subscriber_id' => $subscriberId,
            'user_id' => $userId,
            'role' => 'owner',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriber_user')
            ->where('subscriber_id', $subscriberId)
            ->where('user_id', $userId)
            ->update([
                'active' => 1,
                'role' => 'owner',
                'updated_at' => now(),
            ]);
    }

    protected function ensureCompany($user, Subscriber $subscriber, ActivationRequest $activation): array
    {
        $company = Company::query()->where('subscriber_id', $subscriber->id)->first();

        if ($company) {
            if (!$company->owner_user_id) {
                $company->owner_user_id = $user->id;
                $company->save();
            }
            return [$company, false];
        }

        $name = trim((string) ($activation->company ?: $subscriber->name ?: 'Company'));
        $slug = $this->uniqueSlug('companies', 'slug', Str::slug($name) ?: 'company');

        $company = Company::create([
            'name' => $name,
            'slug' => $slug,
            'currency' => $subscriber->currency ?? 'USD',
            'timezone' => $subscriber->timezone ?? 'America/Bogota',
            'owner_user_id' => $user->id,
            'subscriber_id' => $subscriber->id,
            'active' => true,
        ]);

        return [$company, true];
    }

    /**
     * ✅ Perfil fiscal 1:1 por Company (company_tax_profiles.unique(company_id)).
     * Idempotente: si ya existe, NO crea otro.
     */
    protected function ensureCompanyTaxProfile(Company $company, ActivationRequest $activation): array
    {
        $existing = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        if ($existing) {
            // opcional: backfill de billing_email si está vacío y viene en activation
            if (!$existing->billing_email && $activation->email) {
                $existing->billing_email = $activation->email;
                $existing->save();
            }

            return [$existing, false];
        }

        $legalName = trim((string) ($activation->company ?: $company->name ?: '—'));

        $profile = CompanyTaxProfile::create([
            'company_id' => $company->id,

            'legal_name' => $legalName,
            'trade_name' => null,

            'country_code' => 'DO',

            'tax_id' => null,
            'tax_id_type' => 'RNC',

            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,

            'billing_email' => $activation->email ?: null,
            'billing_phone' => null,
            'billing_contact_name' => null,

            'tax_exempt' => false,
            'default_itbis_rate' => 18.000,

            'meta' => null,
        ]);

        return [$profile, true];
    }

    protected function ensureSubscription($user, Subscriber $subscriber, ActivationRequest $activation): array
    {
        $sub = Subscription::query()
            ->where('subscriber_id', $subscriber->id)
            ->latest('id')
            ->first();

        if ($sub) return [$sub, false];

        $trialDays = (int) ($activation->trial_days ?? 30);
        $trialStarts = $activation->trial_starts_at ?? now();
        $trialEnds = $activation->trial_ends_at ?? $trialStarts->copy()->addDays($trialDays);

        $sub = Subscription::create([
            'subscriber_id' => $subscriber->id,
            'created_by_user_id' => $user->id,

            'status' => ActivationRequest::STATUS_TRIALING,
            'billing_cycle' => 'monthly',
            'currency' => $subscriber->currency ?? 'USD',

            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,

            'trial_ends_at' => $trialEnds,
            'current_period_start' => $trialStarts,
            'current_period_end' => $trialEnds,

            'starts_at' => now(),
            'ends_at' => null,
            'cancelled_at' => null,

            'provider' => null,
            'provider_subscription_id' => null,
            'meta' => null,
        ]);

        return [$sub, true];
    }

    protected function ensureActivationTrial(ActivationRequest $activation): void
    {
        $trialDays = (int) ($activation->trial_days ?? 30);

        $dirty = false;

        if (!$activation->trial_starts_at) {
            $activation->trial_starts_at = now();
            $dirty = true;
        }

        if (!$activation->trial_ends_at) {
            $activation->trial_ends_at = $activation->trial_starts_at->copy()->addDays($trialDays);
            $dirty = true;
        }

        if (($activation->status ?? '') === ActivationRequest::STATUS_ACCEPTED) {
            $activation->status = ActivationRequest::STATUS_TRIALING;
            $dirty = true;
        }

        if ($dirty) $activation->save();
    }

    protected function safeLinkUser($user, int $subscriberId, int $companyId): void
    {
        // NO-OP
    }

    protected function uniqueSlug(string $table, string $column, string $base): string
    {
        $base = trim($base) ?: 'item';
        $slug = $base;
        $i = 2;

        while (DB::table($table)->where($column, $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
