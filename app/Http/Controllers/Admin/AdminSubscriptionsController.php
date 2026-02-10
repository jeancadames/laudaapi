<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSubscriptionsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all');
        $cycle  = (string) $request->get('cycle', 'all');

        $query = Subscription::query()
            ->with(['subscriber:id,name,slug,active'])
            ->select([
                'id',
                'subscriber_id',
                'status',
                'billing_cycle',
                'currency',
                'subtotal_amount',
                'discount_amount',
                'tax_amount',
                'total_amount',
                'trial_ends_at',
                'current_period_start',
                'current_period_end',
                'provider',
                'provider_subscription_id',
                'created_at',
            ])
            ->latest('id');

        // status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // cycle
        if ($cycle !== 'all') {
            $query->where('billing_cycle', $cycle);
        }

        // search: subscriber.name / subscriber.slug / provider_subscription_id / id
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('subscriber', function ($s) use ($search) {
                    $s->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
                    ->orWhere('provider_subscription_id', 'like', "%{$search}%");

                // si es numérico, permitir buscar por id exacto
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $subscriptions = $query
            ->paginate(12)
            ->withQueryString()
            ->through(function ($sub) {
                return [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'billing_cycle' => $sub->billing_cycle,
                    'currency' => $sub->currency,

                    'subtotal_amount' => (float) $sub->subtotal_amount,
                    'discount_amount' => (float) $sub->discount_amount,
                    'tax_amount' => (float) $sub->tax_amount,
                    'total_amount' => (float) $sub->total_amount,

                    'trial_ends_at' => optional($sub->trial_ends_at)->toIso8601String(),
                    'current_period_start' => optional($sub->current_period_start)->toIso8601String(),
                    'current_period_end' => optional($sub->current_period_end)->toIso8601String(),

                    'provider' => $sub->provider,
                    'provider_subscription_id' => $sub->provider_subscription_id,

                    'created_at' => optional($sub->created_at)->toIso8601String(),

                    'subscriber' => $sub->subscriber ? [
                        'id' => $sub->subscriber->id,
                        'name' => $sub->subscriber->name,
                        'slug' => $sub->subscriber->slug,
                        'active' => (bool) $sub->subscriber->active,
                    ] : null,
                ];
            });

        // counts para dropdown
        // ⚠️ si quieres que counts RESPETE los demás filtros, se puede,
        // pero normalmente counts es global (sin search) para que sea estable.
        $countsBase = Subscription::query();

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,

            'filters' => [
                'search' => $search,
                'status' => $status,
                'cycle'  => $cycle,
            ],

            'counts' => [
                'all'       => (clone $countsBase)->count(),
                'trialing'  => (clone $countsBase)->where('status', 'trialing')->count(),
                'active'    => (clone $countsBase)->where('status', 'active')->count(),
                'past_due'  => (clone $countsBase)->where('status', 'past_due')->count(),
                'cancelled' => (clone $countsBase)->where('status', 'cancelled')->count(),
                'expired'   => (clone $countsBase)->where('status', 'expired')->count(),
            ],
        ]);
    }
}
