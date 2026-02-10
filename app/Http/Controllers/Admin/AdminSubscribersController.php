<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSubscribersController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all'); // all | active | inactive
        $provider = (string) $request->get('provider', 'all'); // all | stripe | (otros)

        $query = Subscriber::query()
            ->select([
                'id',
                'name',
                'slug',
                'country_code',
                'currency',
                'timezone',
                'provider',
                'provider_mode',
                'provider_customer_id',
                'active',
                'created_at',
                'updated_at',
            ])
            ->latest('id');

        if ($status !== 'all') {
            $query->where('active', $status === 'active');
        }

        if ($provider !== 'all') {
            $query->where('provider', $provider);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('provider_customer_id', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $subscribers = $query
            ->paginate(12)
            ->withQueryString()
            ->through(function (Subscriber $s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'country_code' => $s->country_code,
                    'currency' => $s->currency,
                    'timezone' => $s->timezone,
                    'provider' => $s->provider,
                    'provider_mode' => $s->provider_mode,
                    'provider_customer_id' => $s->provider_customer_id,
                    'active' => (bool) $s->active,
                    'created_at' => optional($s->created_at)->toIso8601String(),
                ];
            });

        // counts estables (globales)
        $countsBase = Subscriber::query();

        return Inertia::render('Admin/Subscribers/Index', [
            'subscribers' => $subscribers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'provider' => $provider,
            ],
            'counts' => [
                'all' => (clone $countsBase)->count(),
                'active' => (clone $countsBase)->where('active', true)->count(),
                'inactive' => (clone $countsBase)->where('active', false)->count(),
            ],
        ]);
    }

    public function toggleActive(Subscriber $subscriber)
    {
        $subscriber->update([
            'active' => ! $subscriber->active,
        ]);

        return back();
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:subscribers,slug,' . $subscriber->id],
            'country_code' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],

            'provider' => ['nullable', 'string', 'max:50'],
            'provider_mode' => ['required', 'in:live,test'],
            'provider_customer_id' => ['nullable', 'string', 'max:255'],

            'active' => ['boolean'],
        ]);

        $subscriber->update($data);

        return back();
    }
}
