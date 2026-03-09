<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubscriberDashboardController extends Controller
{
    private const DASHBOARD_CACHE_TTL_SECONDS = 60;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // ✅ Resolver compañía de forma robusta con tu schema:
        // 1) user->company_id (si existe)
        // 2) companies.owner_user_id = user
        // 3) companies.subscriber_id = user->subscriber_id (si existe)
        $company = null;

        if (!empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }

        if (!$company) {
            $company = Company::query()
                ->where('owner_user_id', $user->id)
                ->first();
        }

        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()
                ->where('subscriber_id', $user->subscriber_id)
                ->first();
        }

        // ✅ Si NO hay company todavía, igual mostramos el dashboard con activation por user_id
        if (!$company) {
            return Inertia::render('Subscriber/Dashboard', [
                'stats' => array_replace_recursive($this->emptyStats(), [
                    'activation' => $this->activationStatsByUser((int) $user->id),
                ]),
            ]);
        }

        $cacheKey = "subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}";

        $stats = Cache::remember(
            $cacheKey,
            now()->addSeconds(self::DASHBOARD_CACHE_TTL_SECONDS),
            fn() => $this->getStats($company->id, (int) $company->subscriber_id, (int) $user->id, (string) $company->currency, (string) $company->timezone)
        );

        return Inertia::render('Subscriber/Dashboard', [
            'stats' => $stats,
        ]);
    }

    protected function getStats(int $companyId, int $subscriberId, int $userId, string $currency, string $timezone): array
    {
        // =========================================================
        // Activation (por company si existe; si no, por user)
        // =========================================================
        $activation = $this->activationStatsByCompany($companyId) ?? $this->activationStatsByUser($userId);

        // =========================================================
        // Subscription (última por subscriber_id)
        // =========================================================
        $sub = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->latest('id')
            ->first();

        $subId = (int) ($sub?->id ?? 0);

        $itemsAgg = [
            'items_active_or_trialing' => 0,
            'active_services' => 0,
        ];

        if ($subId > 0) {
            // items activos / trialing
            $itemsAgg = SubscriptionItem::query()
                ->where('subscription_id', $subId)
                ->selectRaw("COUNT(*) as items_active_or_trialing")
                ->selectRaw("COUNT(DISTINCT service_id) as active_services")
                ->whereIn('status', ['active', 'trialing'])
                ->first()
                ?->toArray() ?? $itemsAgg;
        }

        $subscription = [
            'id' => $subId,
            'status' => $sub->status ?? '—',
            'billing_cycle' => $sub->billing_cycle ?? '—',
            'total_amount' => (float) ($sub->total_amount ?? 0),

            'trial_ends_at_human' => $sub?->trial_ends_at ? $sub->trial_ends_at->format('Y-m-d') : null,
            'period_end_human' => $sub?->current_period_end ? $sub->current_period_end->format('Y-m-d') : null,

            'items_active_or_trialing' => (int) ($itemsAgg['items_active_or_trialing'] ?? 0),
            'active_services' => (int) ($itemsAgg['active_services'] ?? 0),
        ];

        // =========================================================
        // Tax profile exists?
        // =========================================================
        $taxProfileExists = (bool) DB::table('company_tax_profiles')
            ->where('company_id', $companyId)
            ->exists();

        // =========================================================
        // Invoices (por company + currency)
        // =========================================================
        $invoiceBase = Invoice::query()
            ->where('company_id', $companyId)
            ->where('currency', $currency);

        $totalInvoices = (int) (clone $invoiceBase)->count();

        $invoiceByStatus = (clone $invoiceBase)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $pendingCount = (int) (clone $invoiceBase)
            ->whereIn('status', ['issued', 'overdue'])
            ->count();

        $totalInvoiced = (float) (clone $invoiceBase)
            ->where('status', '!=', 'void')
            ->sum('total');

        $totalAmountPaidOnInvoices = (float) (clone $invoiceBase)
            ->where('status', '!=', 'void')
            ->sum('amount_paid');

        // ✅ AP: lo que el subscriber debe pagar (issued + overdue)
        $accountsPayable = (float) (clone $invoiceBase)
            ->whereIn('status', ['issued', 'overdue'])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as s')
            ->value('s');

        // =========================================================
        // Payments (join invoices porque payments no tiene company_id)
        // =========================================================
        $paymentsBase = Payment::query()
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('payments.currency', $currency);

        $paymentsAgg = (clone $paymentsBase)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN payments.paid_at IS NOT NULL THEN 1 ELSE 0 END) as posted')
            ->selectRaw('COALESCE(SUM(CASE WHEN payments.paid_at IS NOT NULL THEN payments.amount ELSE 0 END), 0) as total_paid')
            ->first();

        $paidThisMonth = (float) (clone $paymentsBase)
            ->whereNotNull('payments.paid_at')
            ->whereBetween('payments.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('payments.amount');

        $payments = [
            'total' => (int) ($paymentsAgg?->total ?? 0),
            'posted' => (int) ($paymentsAgg?->posted ?? 0),
            'total_paid' => (float) ($paymentsAgg?->total_paid ?? 0),
            'paid_this_month' => $paidThisMonth,
        ];

        // =========================================================
        // Usage (si aplica) por subscription_id (mes actual)
        // =========================================================
        $usage = [
            'month_units' => 0,
            'days_with_usage' => 0,
            'services_with_usage' => 0,
        ];

        if ($subId > 0) {
            $usageRow = DB::table('usage_records')
                ->where('subscription_id', $subId)
                ->whereBetween('occurred_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->selectRaw('COALESCE(SUM(quantity), 0) as month_units')
                ->selectRaw('COUNT(DISTINCT occurred_on) as days_with_usage')
                ->selectRaw('COUNT(DISTINCT service_id) as services_with_usage')
                ->first();

            $usage = [
                'month_units' => (float) ($usageRow?->month_units ?? 0),
                'days_with_usage' => (int) ($usageRow?->days_with_usage ?? 0),
                'services_with_usage' => (int) ($usageRow?->services_with_usage ?? 0),
            ];
        }

        return [
            'currency' => $currency,
            'timezone' => $timezone,

            'activation' => $activation,
            'subscription' => $subscription,

            'tax_profile' => [
                'exists' => $taxProfileExists,
            ],

            'billing' => [
                'invoices' => [
                    'total' => $totalInvoices,
                    'pending_count' => $pendingCount,
                    'by_status' => [
                        'draft' => (int) ($invoiceByStatus['draft'] ?? 0),
                        'issued' => (int) ($invoiceByStatus['issued'] ?? 0),
                        'overdue' => (int) ($invoiceByStatus['overdue'] ?? 0),
                        'paid' => (int) ($invoiceByStatus['paid'] ?? 0),
                        'void' => (int) ($invoiceByStatus['void'] ?? 0),
                    ],
                    'total_invoiced' => $totalInvoiced,
                    'total_amount_paid_on_invoices' => $totalAmountPaidOnInvoices,
                ],

                'payments' => $payments,

                'balance' => [
                    'accounts_payable' => $accountsPayable,
                    // ✅ alias temporal por si algo viejo lee AR
                    'accounts_receivable' => $accountsPayable,
                ],
            ],

            'usage' => $usage,
        ];
    }

    protected function activationStatsByCompany(int $companyId): ?array
    {
        // OJO: en tu activation_requests no hay company_id.
        // Si en tu app relacionas activation con company de otra forma, aquí lo ajustas.
        // Por ahora devolvemos null para usar el fallback por user_id.
        return null;
    }

    protected function activationStatsByUser(int $userId): array
    {
        $req = ActivationRequest::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        $trialActive = false;
        $trialDaysLeft = 0;

        if ($req?->trial_starts_at && $req?->trial_ends_at) {
            $trialActive = $req->trial_starts_at <= now() && $req->trial_ends_at >= now();
            $trialDaysLeft = max(0, now()->startOfDay()->diffInDays($req->trial_ends_at, false));
        }

        $servicesRequested = $req
            ? (int) ActivationRequestService::where('activation_request_id', $req->id)->count()
            : 0;

        return [
            'has_request' => (bool) $req,
            'status' => $req->status ?? 'none',
            'trial_active' => (bool) $trialActive,
            'trial_starts_at_human' => $req?->trial_starts_at?->format('Y-m-d'),
            'trial_ends_at_human' => $req?->trial_ends_at?->format('Y-m-d'),
            'trial_days_left' => (int) $trialDaysLeft,
            'services_requested' => $servicesRequested,
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'currency' => 'USD',
            'timezone' => 'America/Santo_Domingo',

            'activation' => [
                'has_request' => false,
                'status' => 'none',
                'trial_active' => false,
                'trial_starts_at_human' => null,
                'trial_ends_at_human' => null,
                'trial_days_left' => 0,
                'services_requested' => 0,
            ],

            'subscription' => [
                'id' => 0,
                'status' => '—',
                'billing_cycle' => '—',
                'total_amount' => 0,
                'trial_ends_at_human' => null,
                'period_end_human' => null,
                'items_active_or_trialing' => 0,
                'active_services' => 0,
            ],

            'tax_profile' => [
                'exists' => false,
            ],

            'billing' => [
                'invoices' => [
                    'total' => 0,
                    'pending_count' => 0,
                    'by_status' => [
                        'draft' => 0,
                        'issued' => 0,
                        'overdue' => 0,
                        'paid' => 0,
                        'void' => 0,
                    ],
                    'total_invoiced' => 0,
                    'total_amount_paid_on_invoices' => 0,
                ],
                'payments' => [
                    'total' => 0,
                    'posted' => 0,
                    'total_paid' => 0,
                    'paid_this_month' => 0,
                ],
                'balance' => [
                    'accounts_payable' => 0,
                    'accounts_receivable' => 0,
                ],
            ],

            'usage' => [
                'month_units' => 0,
                'days_with_usage' => 0,
                'services_with_usage' => 0,
            ],
        ];
    }
}
