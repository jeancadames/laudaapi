<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AdminCompanyController extends Controller
{
    /**
     * ✅ Admin: LISTA (read-only)
     * /admin/company
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('search', ''));

        $companies = Company::query()
            ->with(['taxProfile:id,company_id,legal_name,trade_name,country_code,tax_id,tax_id_type,tax_exempt,default_itbis_rate,updated_at'])
            ->when($q !== '', function ($query) use ($q) {
                // Ajusta estos campos según tu tabla companies (name/legal_name/trade_name/etc)
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('legal_name', 'like', "%{$q}%")
                        ->orWhere('trade_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $companies->getCollection()->transform(function ($c) {
            $displayName = $c->name
                ?? $c->legal_name
                ?? $c->trade_name
                ?? ('Company #' . $c->id);

            return [
                'id' => $c->id,
                'name' => $displayName,
                'active' => (bool) ($c->active ?? true),
                'created_at' => optional($c->created_at)?->toISOString(),
                'updated_at' => optional($c->updated_at)?->toISOString(),

                'tax_profile' => $c->taxProfile ? [
                    'exists' => true,
                    'legal_name' => $c->taxProfile->legal_name,
                    'trade_name' => $c->taxProfile->trade_name,
                    'country_code' => $c->taxProfile->country_code,
                    'tax_id' => $c->taxProfile->tax_id,
                    'tax_id_type' => $c->taxProfile->tax_id_type,
                    'tax_exempt' => (bool) $c->taxProfile->tax_exempt,
                    'default_itbis_rate' => (string) $c->taxProfile->default_itbis_rate,
                    'updated_at' => optional($c->taxProfile->updated_at)?->toISOString(),
                ] : [
                    'exists' => false,
                    'legal_name' => null,
                    'trade_name' => null,
                    'country_code' => 'DO',
                    'tax_id' => null,
                    'tax_id_type' => 'RNC',
                    'tax_exempt' => false,
                    'default_itbis_rate' => '18.000',
                    'updated_at' => null,
                ],
            ];
        });

        return Inertia::render('Admin/Company/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $q,
            ],
        ]);
    }

    /**
     * ✅ Admin: VER Tax Profile (read-only)
     * /admin/company/{company}/tax-profile
     */
    public function taxProfile(Company $company)
    {
        $taxProfile = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        $displayName = $company->name
            ?? $company->legal_name
            ?? $company->trade_name
            ?? ('Company #' . $company->id);

        return Inertia::render('Admin/Company/TaxProfile', [
            'company' => [
                'id' => $company->id,
                'name' => $displayName,
            ],
            'taxProfile' => $taxProfile ? [
                'exists' => true,
                'id' => $taxProfile->id,
                'legal_name' => $taxProfile->legal_name,
                'trade_name' => $taxProfile->trade_name,
                'country_code' => $taxProfile->country_code,
                'tax_id' => $taxProfile->tax_id,
                'tax_id_type' => $taxProfile->tax_id_type,
                'address_line1' => $taxProfile->address_line1,
                'address_line2' => $taxProfile->address_line2,
                'city' => $taxProfile->city,
                'state' => $taxProfile->state,
                'postal_code' => $taxProfile->postal_code,
                'billing_email' => $taxProfile->billing_email,
                'billing_phone' => $taxProfile->billing_phone,
                'billing_contact_name' => $taxProfile->billing_contact_name,
                'tax_exempt' => (bool) $taxProfile->tax_exempt,
                'default_itbis_rate' => (string) $taxProfile->default_itbis_rate,
                'updated_at' => optional($taxProfile->updated_at)?->toISOString(),
                'created_at' => optional($taxProfile->created_at)?->toISOString(),
            ] : [
                'exists' => false,
            ],
        ]);
    }

    /**
     * ✅ Admin: VER Transacciones (read-only)
     * /admin/company/{company}/transactions
     *
     * Nota: como no me diste tu tabla/modelo de transacciones, este método:
     * - Si existe invoices o payments, trae listas básicas filtradas por company_id
     * - Si no existen, retorna arrays vacíos (no falla)
     */
    public function transactions(Request $request, Company $company)
    {
        $displayName = $company->name
            ?? $company->legal_name
            ?? $company->trade_name
            ?? ('Company #' . $company->id);

        $invoices = [];
        $payments = [];

        // ✅ Invoices (si existe tabla)
        if (Schema::hasTable('invoices')) {
            $invoiceModel = $this->resolveModel('App\\Models\\Invoice');

            if ($invoiceModel) {
                $invoices = $invoiceModel::query()
                    ->where('company_id', $company->id)
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->map(fn($i) => [
                        'id' => $i->id,
                        'number' => $i->number ?? $i->invoice_number ?? null,
                        'status' => $i->status ?? null,
                        'total' => $i->total ?? $i->amount_total ?? null,
                        'created_at' => optional($i->created_at)?->toISOString(),
                    ])
                    ->all();
            }
        }

        // ✅ Payments (si existe tabla)
        if (Schema::hasTable('payments')) {
            $paymentModel = $this->resolveModel('App\\Models\\Payment');

            if ($paymentModel) {
                $payments = $paymentModel::query()
                    ->where('company_id', $company->id)
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->map(fn($p) => [
                        'id' => $p->id,
                        'status' => $p->status ?? null,
                        'amount' => $p->amount ?? null,
                        'provider' => $p->provider ?? null,
                        'created_at' => optional($p->created_at)?->toISOString(),
                    ])
                    ->all();
            }
        }

        return Inertia::render('Admin/Company/Transactions/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $displayName,
            ],
            'invoices' => $invoices,
            'payments' => $payments,
        ]);
    }

    /**
     * Helper: evita fatal si el modelo no existe todavía.
     */
    private function resolveModel(string $fqcn): ?string
    {
        return class_exists($fqcn) ? $fqcn : null;
    }
}
