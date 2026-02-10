<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriberPaymentController extends Controller
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
        $method = $request->string('method')->toString(); // '', 'card', ...
        $status = $request->string('status')->toString(); // 'all' | 'paid' | 'unpaid'
        $q = trim((string) $request->get('q', ''));       // reference o invoice number
        $from = $request->get('from');                    // YYYY-MM-DD (paid_at)
        $to = $request->get('to');                        // YYYY-MM-DD (paid_at)

        // Pagos SOLO de facturas de la compañía
        $payments = Payment::query()
            ->whereHas('invoice', function ($inv) use ($company) {
                $inv->where('company_id', $company->id);
            })
            ->with(['invoice:id,company_id,number,status,currency,total'])
            ->when($method !== '' && $method !== 'all', function ($query) use ($method) {
                $query->where('method', $method);
            })
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                if ($status === 'paid') {
                    $query->whereNotNull('paid_at');
                } elseif ($status === 'unpaid') {
                    $query->whereNull('paid_at');
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('reference', 'like', "%{$q}%")
                        ->orWhereHas('invoice', function ($inv) use ($q) {
                            $inv->where('number', 'like', "%{$q}%");
                        });
                });
            })
            ->when($from, function ($query) use ($from) {
                $query->whereDate('paid_at', '>=', $from);
            })
            ->when($to, function ($query) use ($to) {
                $query->whereDate('paid_at', '<=', $to);
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Payment $p) {
                return [
                    'id' => $p->id,
                    'method' => $p->method,
                    'currency' => $p->currency,
                    'amount' => (string) $p->amount,
                    'paid_at' => optional($p->paid_at)->toDateTimeString(),
                    'reference' => $p->reference,

                    'invoice' => $p->invoice ? [
                        'id' => $p->invoice->id,
                        'number' => $p->invoice->number,
                        'status' => $p->invoice->status,
                        'currency' => $p->invoice->currency,
                        'total' => (string) $p->invoice->total,
                    ] : null,
                ];
            });

        return Inertia::render('Subscriber/Payments/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'filters' => [
                'method' => $method ?: 'all',
                'status' => $status ?: 'all',
                'q' => $q,
                'from' => $from,
                'to' => $to,
            ],
            'payments' => $payments,
        ]);
    }

    // opcional show (detalle simple)
    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompany($user);
        if (!$company) abort(404);

        $payment->load(['invoice:id,company_id,number,status,currency,total']);

        if (!$payment->invoice || (int) $payment->invoice->company_id !== (int) $company->id) {
            abort(404);
        }

        return Inertia::render('Subscriber/Payments/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'payment' => [
                'id' => $payment->id,
                'method' => $payment->method,
                'currency' => $payment->currency,
                'amount' => (string) $payment->amount,
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'reference' => $payment->reference,
                'meta' => $payment->meta,

                'invoice' => $payment->invoice ? [
                    'id' => $payment->invoice->id,
                    'number' => $payment->invoice->number,
                    'status' => $payment->invoice->status,
                    'currency' => $payment->invoice->currency,
                    'total' => (string) $payment->invoice->total,
                ] : null,
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
