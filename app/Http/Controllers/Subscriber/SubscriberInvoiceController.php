<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriberInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompany($user);
        if (!$company) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes compañía asignada.');
        }

        // Filtros
        $status = $request->string('status')->toString(); // '', 'draft','issued','paid','void','overdue'
        $q = trim((string) $request->get('q', ''));
        $from = $request->get('from'); // YYYY-MM-DD
        $to = $request->get('to');     // YYYY-MM-DD

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('number', 'like', "%{$q}%")
                        ->orWhere('fiscal_number', 'like', "%{$q}%")
                        ->orWhere('provider_invoice_id', 'like', "%{$q}%");
                });
            })
            ->when($from, function ($query) use ($from) {
                $query->whereDate('issued_on', '>=', $from);
            })
            ->when($to, function ($query) use ($to) {
                $query->whereDate('issued_on', '<=', $to);
            })
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Invoice $inv) {
                $total = (float) $inv->total;
                $paid = (float) $inv->amount_paid;
                $balance = max(0, $total - $paid);

                return [
                    'id' => $inv->id,
                    'number' => $inv->number,
                    'status' => $inv->status,

                    'issued_on' => optional($inv->issued_on)->format('Y-m-d'),
                    'due_on' => optional($inv->due_on)->format('Y-m-d'),

                    'currency' => $inv->currency,
                    'total' => (string) $inv->total,
                    'amount_paid' => (string) $inv->amount_paid,
                    'balance' => number_format($balance, 2, '.', ''),

                    // DGII (opcional mostrar)
                    'document_class' => $inv->document_class,
                    'document_type' => $inv->document_type,
                    'fiscal_number' => $inv->fiscal_number,

                    // pagos online (si existe)
                    'hosted_invoice_url' => $inv->hosted_invoice_url,
                    'payment_url' => $inv->payment_url,
                ];
            });

        return Inertia::render('Subscriber/Invoices/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'filters' => [
                'status' => $status ?: 'all',
                'q' => $q,
                'from' => $from,
                'to' => $to,
            ],
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, \App\Models\Invoice $invoice)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompany($user);
        if (!$company || (int) $invoice->company_id !== (int) $company->id) {
            abort(404);
        }

        $total = (float) $invoice->total;
        $paid = (float) $invoice->amount_paid;
        $balance = max(0, $total - $paid);

        return \Inertia\Inertia::render('Subscriber/Invoices/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],

            'invoice' => [
                'id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'subscription_id' => $invoice->subscription_id,

                'number' => $invoice->number,
                'status' => $invoice->status,

                'issued_on' => optional($invoice->issued_on)->format('Y-m-d'),
                'due_on' => optional($invoice->due_on)->format('Y-m-d'),

                'period_start' => optional($invoice->period_start)->toDateTimeString(),
                'period_end' => optional($invoice->period_end)->toDateTimeString(),

                'currency' => $invoice->currency,

                'subtotal' => (string) $invoice->subtotal,
                'discount_total' => (string) $invoice->discount_total,
                'tax_total' => (string) $invoice->tax_total,
                'total' => (string) $invoice->total,
                'amount_paid' => (string) $invoice->amount_paid,
                'balance' => number_format($balance, 2, '.', ''),

                'billing_snapshot' => $invoice->billing_snapshot, // json
                'document_class' => $invoice->document_class,
                'document_type' => $invoice->document_type,
                'fiscal_number' => $invoice->fiscal_number,
                'security_code' => $invoice->security_code,
                'fiscal_meta' => $invoice->fiscal_meta, // json

                'provider' => $invoice->provider,
                'provider_invoice_id' => $invoice->provider_invoice_id,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'payment_url' => $invoice->payment_url,

                'created_at' => optional($invoice->created_at)->toDateTimeString(),
                'updated_at' => optional($invoice->updated_at)->toDateTimeString(),
            ],
        ]);
    }


    private function resolveCompany($user): ?Company
    {
        $company = null;

        if (!empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }
        if (!$company) {
            $company = Company::query()->where('owner_user_id', $user->id)->first();
        }
        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()->where('subscriber_id', $user->subscriber_id)->first();
        }

        return $company;
    }
}
