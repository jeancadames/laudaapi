<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPaymentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $method = (string) ($request->get('method', 'all') ?: 'all');
        $status = (string) ($request->get('status', 'all') ?: 'all'); // paid|unpaid|all

        // Query principal (con eager loads)
        $base = Payment::query()
            ->with([
                'invoice:id,company_id,number,status,total,currency',
                'invoice.company:id,name',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn($i) => $i->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('invoice.company', fn($c) => $c->where('name', 'like', "%{$search}%"));
                });
            });

        if ($method !== 'all') {
            $base->where('method', $method);
        }

        if ($status === 'paid') {
            $base->whereNotNull('paid_at');
        } elseif ($status === 'unpaid') {
            $base->whereNull('paid_at');
        }

        // Counts (solo search, sin method/status)
        $countsBase = Payment::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn($i) => $i->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('invoice.company', fn($c) => $c->where('name', 'like', "%{$search}%"));
                });
            });

        $methods = ['card', 'bank_transfer', 'cash', 'check', 'other'];

        $counts = [
            'all' => (clone $countsBase)->count(),
            'paid' => (clone $countsBase)->whereNotNull('paid_at')->count(),
            'unpaid' => (clone $countsBase)->whereNull('paid_at')->count(),
        ];

        foreach ($methods as $m) {
            // ✅ CAMBIO CLAVE: SIN ':'
            $counts['method_' . $m] = (clone $countsBase)->where('method', $m)->count();
        }

        $payments = $base
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $payments->getCollection()->transform(function ($p) {
            $inv = $p->invoice;
            $company = $inv?->company;

            return [
                'id' => $p->id,
                'method' => $p->method,
                'currency' => $p->currency,
                'amount' => $p->amount,
                'paid_at' => optional($p->paid_at)?->toISOString(),
                'reference' => $p->reference,

                'invoice' => $inv ? [
                    'id' => $inv->id,
                    'number' => $inv->number,
                    'status' => $inv->status,
                    'total' => $inv->total,
                    'currency' => $inv->currency,
                ] : null,

                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : null,

                'created_at' => optional($p->created_at)?->toISOString(),
            ];
        });

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => [
                'search' => $search,
                'method' => $method,
                'status' => $status,
            ],
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load([
            'invoice:id,company_id,number,status,total,currency',
            'invoice.company:id,name',
        ]);

        $inv = $payment->invoice;
        $company = $inv?->company;

        return Inertia::render('Admin/Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'method' => $payment->method,
                'currency' => $payment->currency,
                'amount' => $payment->amount,
                'paid_at' => optional($payment->paid_at)?->toISOString(),
                'reference' => $payment->reference,
                'meta' => $payment->meta,

                'invoice' => $inv ? [
                    'id' => $inv->id,
                    'number' => $inv->number,
                    'status' => $inv->status,
                    'total' => $inv->total,
                    'currency' => $inv->currency,
                ] : null,

                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : null,

                'created_at' => optional($payment->created_at)?->toISOString(),
                'updated_at' => optional($payment->updated_at)?->toISOString(),
            ],
            'back' => [
                'href' => '/admin/payments' . ($request->getQueryString() ? ('?' . $request->getQueryString()) : ''),
            ],
        ]);
    }
}
