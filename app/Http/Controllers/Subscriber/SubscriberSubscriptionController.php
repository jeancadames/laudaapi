<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriberSubscriptionController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompany($user);
        if (!$company || !$company->subscriber_id) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes compañía/suscriptor asignado.');
        }

        $subscription = Subscription::query()
            ->where('subscriber_id', $company->subscriber_id)
            ->latest('id')
            ->first();

        $items = collect();
        if ($subscription) {
            $items = SubscriptionItem::query()
                ->where('subscription_id', $subscription->id)
                ->orderByDesc('id')
                ->get(['id', 'service_id', 'status', 'quantity', 'meta', 'created_at']);

            $serviceIds = $items->pluck('service_id')->unique()->values()->all();

            $services = empty($serviceIds)
                ? collect()
                : Service::query()
                ->whereIn('id', $serviceIds)
                ->get(['id', 'title', 'slug', 'icon', 'short_description'])
                ->keyBy('id');

            $items = $items->map(function ($it) use ($services) {
                $s = $services->get($it->service_id);

                return [
                    'id' => $it->id,
                    'service_id' => $it->service_id,
                    'service' => $s ? [
                        'title' => $s->title,
                        'slug' => $s->slug,
                        'icon' => $s->icon,
                        'short_description' => $s->short_description,
                    ] : null,
                    'status' => $it->status,
                    'quantity' => $it->quantity,
                    'created_at' => optional($it->created_at)->toDateTimeString(),
                ];
            })->values();
        }

        return Inertia::render('Subscriber/Subscription/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'currency' => $subscription->currency,
                'trial_ends_at_human' => $subscription->trial_ends_at?->format('Y-m-d'),
                'period_start_human' => $subscription->current_period_start?->format('Y-m-d'),
                'period_end_human' => $subscription->current_period_end?->format('Y-m-d'),
                'ends_at_human' => $subscription->ends_at?->format('Y-m-d'),
                'starts_at_human' => $subscription->starts_at?->format('Y-m-d'),
            ] : null,
            'items' => $items,
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
