<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) ($request->get('status', 'all') ?: 'all');

        $validStatuses = ['all', 'draft', 'issued', 'paid', 'void', 'overdue'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'all';
        }

        // Base query (con search)
        $base = Invoice::query()
            ->with([
                'company:id,name',
            ])
            ->withCount('items')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('number', 'like', "%{$search}%")
                        ->orWhere('fiscal_number', 'like', "%{$search}%")
                        ->orWhere('provider_invoice_id', 'like', "%{$search}%")
                        ->orWhereHas('company', fn($c) => $c->where('name', 'like', "%{$search}%"));
                });
            });

        // Filtro status
        if ($status !== 'all') {
            $base->where('status', $status);
        }

        // Totales + counts (solo respetan search, no el status actual)
        $allBase = Invoice::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('number', 'like', "%{$search}%")
                        ->orWhere('fiscal_number', 'like', "%{$search}%")
                        ->orWhere('provider_invoice_id', 'like', "%{$search}%")
                        ->orWhereHas('company', fn($c) => $c->where('name', 'like', "%{$search}%"));
                });
            });

        $byStatus = (clone $allBase)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $totalInvoices = (int) (clone $allBase)->count();

        $totalInvoiced = (float) (clone $allBase)
            ->where('status', '!=', 'void')
            ->sum('total');

        $totalAmountPaid = (float) (clone $allBase)
            ->where('status', '!=', 'void')
            ->sum('amount_paid');

        // AR recomendado: issued + overdue
        $accountsReceivable = (float) (clone $allBase)
            ->whereIn('status', ['issued', 'overdue'])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as s')
            ->value('s');

        $counts = [
            'all' => $totalInvoices,
            'by_status' => [
                'draft' => (int) ($byStatus['draft'] ?? 0),
                'issued' => (int) ($byStatus['issued'] ?? 0),
                'overdue' => (int) ($byStatus['overdue'] ?? 0),
                'paid' => (int) ($byStatus['paid'] ?? 0),
                'void' => (int) ($byStatus['void'] ?? 0),
            ],
            'totals' => [
                'total_invoiced' => $totalInvoiced,
                'total_amount_paid_on_invoices' => $totalAmountPaid,
                'accounts_receivable' => $accountsReceivable,
                'outstanding_issued_overdue' => $accountsReceivable, // mismo valor (tu UI lo usa así)
            ],
        ];

        $invoices = $base
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $invoices->getCollection()->transform(function (Invoice $inv) {
            $company = $inv->company;

            $total = (float) $inv->total;
            $paid = (float) $inv->amount_paid;
            $balance = max(0, $total - $paid);

            return [
                'id' => $inv->id,
                'number' => $inv->number,
                'status' => $inv->status,
                'currency' => $inv->currency ?? 'DOP',

                'issued_on' => $inv->issued_on?->toDateString(),
                'due_on' => $inv->due_on?->toDateString(),

                'total' => $inv->total,
                'amount_paid' => $inv->amount_paid,
                'balance' => $balance,

                'document_class' => $inv->document_class,
                'document_type' => $inv->document_type,
                'fiscal_number' => $inv->fiscal_number,

                'items_count' => (int) ($inv->items_count ?? 0),

                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : null,

                'created_at' => $inv->created_at?->toISOString(),
                'updated_at' => $inv->updated_at?->toISOString(),
            ];
        });

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $invoice->load([
            'company:id,name',
            'items:id,invoice_id,service_id,description,quantity,unit_price,line_total',
            'items.service:id,title,slug',
            'payments:id,invoice_id,method,currency,amount,paid_at,reference',
        ]);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => $invoice,
            'back' => [
                'href' => '/admin/invoices' . ($request->getQueryString() ? ('?' . $request->getQueryString()) : ''),
            ],
        ]);
    }
}
