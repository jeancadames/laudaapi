<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Models\ActivationRequest;
use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\ContactRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;

class AdminDashboardController extends Controller
{
    private const DASHBOARD_CACHE_KEY = 'admin.dashboard.stats';
    private const DASHBOARD_CACHE_TTL_SECONDS = 60;

    // ✅ single-currency (hard)
    private const CURRENCY = 'USD';

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $stats = Cache::remember(
            self::DASHBOARD_CACHE_KEY,
            now()->addSeconds(self::DASHBOARD_CACHE_TTL_SECONDS),
            fn() => $this->getStats()
        );

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
        ]);
    }

    protected function getStats(): array
    {
        // =========================================================
        // Contact Requests (1 query)
        // =========================================================
        $contactsAgg = ContactRequest::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->selectRaw('SUM(CASE WHEN terms = 1 THEN 1 ELSE 0 END) as with_terms')
            ->first();

        $contacts = [
            'total' => (int) ($contactsAgg?->total ?? 0),
            'unread' => (int) ($contactsAgg?->unread ?? 0),
            'with_terms' => (int) ($contactsAgg?->with_terms ?? 0),
        ];

        // =========================================================
        // Activations (groupBy status + activeTrials + services)
        // =========================================================
        $activationByStatus = ActivationRequest::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $activeTrials = ActivationRequest::query()
            ->whereNotNull('trial_starts_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', now())
            ->count();

        $activations = [
            'total' => (int) array_sum(array_map('intval', $activationByStatus)),
            'pending' => (int) ($activationByStatus['pending'] ?? 0),
            'contacted' => (int) ($activationByStatus['contacted'] ?? 0),
            'activated' => (int) ($activationByStatus['activated'] ?? 0),
            'trialing' => (int) ($activationByStatus['trialing'] ?? 0),
            'active_trials' => (int) $activeTrials,
            'services' => (int) ActivationRequestService::count(),
        ];

        // =========================================================
        // Subscriptions (groupBy status)
        // =========================================================
        $subByStatus = Subscription::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        // TODO: cuando tengas subscription_items, cambia services => count(items)
        $subscriptions = [
            'total' => (int) array_sum(array_map('intval', $subByStatus)),
            'active' => (int) ($subByStatus['active'] ?? 0),
            'trialing' => (int) ($subByStatus['trialing'] ?? 0),
            'expired' => (int) ($subByStatus['expired'] ?? 0),
            'services' => (int) Subscription::count(), // placeholder (igual a tu versión actual)
        ];

        // =========================================================
        // Companies (aggregate + tax profile count)
        // =========================================================
        $companyAgg = Company::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active')
            ->first();

        // ✅ robusto y eficiente (tu tabla ya tiene unique(company_id))
        $companiesWithTaxProfile = (int) DB::table('company_tax_profiles')
            ->distinct('company_id')
            ->count('company_id');

        $company = [
            'total' => (int) ($companyAgg?->total ?? 0),
            'active' => (int) ($companyAgg?->active ?? 0),
            'tax_profile_count' => $companiesWithTaxProfile,
        ];

        // =========================================================
        // Invoices (USD-only)
        // =========================================================
        $invoiceBase = Invoice::query()->where('currency', self::CURRENCY);

        $totalInvoices = (clone $invoiceBase)->count();

        $invoiceByStatus = (clone $invoiceBase)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $invoicedTotal = (float) (clone $invoiceBase)
            ->where('status', '!=', 'void')
            ->sum('total');

        $amountPaidTotal = (float) (clone $invoiceBase)
            ->where('status', '!=', 'void')
            ->sum('amount_paid');

        // ✅ AR (solo issued + overdue) — recomendado
        $accountsReceivable = (float) (clone $invoiceBase)
            ->whereIn('status', ['issued', 'overdue'])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as s')
            ->value('s');

        // ✅ tu UI lo usa
        $outstandingIssuedOverdue = $accountsReceivable;

        // =========================================================
        // Payments (USD-only, posted only)
        // =========================================================
        $paymentAgg = Payment::query()
            ->where('currency', self::CURRENCY)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN paid_at IS NOT NULL THEN 1 ELSE 0 END) as posted')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_at IS NOT NULL THEN amount ELSE 0 END), 0) as total_paid')
            ->first();

        $payments = [
            'total' => (int) ($paymentAgg?->total ?? 0),
            'posted' => (int) ($paymentAgg?->posted ?? 0),
            'total_paid' => (float) ($paymentAgg?->total_paid ?? 0),
        ];

        return [
            'contacts' => $contacts,
            'activations' => $activations,
            'subscriptions' => $subscriptions,
            'company' => $company,

            'billing' => [
                'currency' => self::CURRENCY, // opcional, útil para UI
                'invoices' => [
                    'total' => (int) $totalInvoices,
                    'by_status' => [
                        'draft' => (int) ($invoiceByStatus['draft'] ?? 0),
                        'issued' => (int) ($invoiceByStatus['issued'] ?? 0),
                        'overdue' => (int) ($invoiceByStatus['overdue'] ?? 0),
                        'paid' => (int) ($invoiceByStatus['paid'] ?? 0),
                        'void' => (int) ($invoiceByStatus['void'] ?? 0),
                    ],
                    'total_invoiced' => $invoicedTotal,
                    'total_amount_paid_on_invoices' => $amountPaidTotal,

                    // ✅ requerido por tu UI
                    'outstanding_issued_overdue' => $outstandingIssuedOverdue,
                ],

                'payments' => $payments,

                'balance' => [
                    'accounts_receivable' => $accountsReceivable,
                ],
            ],
        ];
    }
}
