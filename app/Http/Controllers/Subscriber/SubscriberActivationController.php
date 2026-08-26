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
use Illuminate\Support\Facades\DB;
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

        if (! $user) {
            abort(403);
        }

        /* PASO 9C-A: compatibility guard; no crea Subscription ni trial. */
        $activation = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        $subscription = $subscriberId > 0
            ? Subscription::query()
                ->where('subscriber_id', $subscriberId)
                ->latest('id')
                ->first()
            : null;

        if ($subscription && in_array(strtolower((string) $subscription->status), ['active', 'trialing'], true)) {
            AuditService::log(
                'legacy_subscriber_activation_preserved_existing',
                $activation,
                [
                    'user_id' => (int) $user->id,
                    'subscriber_id' => $subscriberId,
                    'subscription_id' => (int) $subscription->id,
                    'subscription_status' => (string) $subscription->status,
                    'hardening_step' => '9C-A',
                ],
                ['user_id' => (int) $user->id]
            );

            return redirect()->route('subscriber')->with(
                'success',
                'Tu suscripción existente se conserva. No es necesario iniciar una nueva activación.'
            );
        }

        AuditService::log(
            'legacy_subscriber_activation_blocked_t360',
            $activation,
            [
                'user_id' => (int) $user->id,
                'subscriber_id' => $subscriberId > 0 ? $subscriberId : null,
                'activation_request_id' => $activation?->id,
                'reason' => 'new_subscription_requires_lauda360_golive',
                'hardening_step' => '9C-A',
            ],
            ['user_id' => (int) $user->id]
        );

        return redirect()->route('subscriber')->with(
            'error',
            'Las nuevas suscripciones ya no se activan mediante un trial directo. La activación recurrente se realiza después del Go-Live correspondiente en LAUDA 360.'
        );
    }
}
