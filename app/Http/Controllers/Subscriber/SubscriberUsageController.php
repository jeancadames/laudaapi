<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubscriberUsageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->value('subscriber_id');

        if ($subscriberId <= 0) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes suscriptor activo asignado.');
        }

        $company = Company::query()
            ->where('subscriber_id', $subscriberId)
            ->first();

        if (!$company) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes empresa asignada todavía. Completa tu activación primero.');
        }

        $subscription = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->latest('id')
            ->first();

        if (!$subscription) {
            return Inertia::render('Subscriber/Usage/Index', [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency,
                    'timezone' => $company->timezone,
                ],
                'subscription' => null,
                'filters' => [
                    'from' => null,
                    'to' => null,
                    'service_id' => null,
                ],
                'services' => [],
                'summary' => [
                    'total_records' => 0,
                    'total_quantity' => '0.0000',
                ],
                'by_service' => [],
                'rows' => [],
            ])->with('error', 'No tienes suscripción aún.');
        }

        // -----------------------------
        // Filtros
        // -----------------------------
        $from = $request->string('from')->toString(); // YYYY-MM-DD
        $to = $request->string('to')->toString();     // YYYY-MM-DD
        $serviceId = $request->integer('service_id');

        if (!$from || !$to) {
            $to = now()->toDateString();
            $from = now()->subDays(29)->toDateString();
        }

        // -----------------------------
        // Services disponibles (de subscription_items)
        // -----------------------------
        $serviceIds = DB::table('subscription_items')
            ->where('subscription_id', $subscription->id)
            ->pluck('service_id')
            ->unique()
            ->values()
            ->all();

        $services = empty($serviceIds)
            ? collect()
            : Service::query()
            ->whereIn('id', $serviceIds)
            ->get(['id', 'title', 'slug', 'billing_model', 'unit_name'])
            ->keyBy('id');

        // -----------------------------
        // Query base: usage_records
        // -----------------------------
        $q = DB::table('usage_records')
            ->where('subscription_id', $subscription->id)
            ->whereBetween('occurred_on', [$from, $to]);

        if (!empty($serviceId)) {
            $q->where('service_id', $serviceId);
        }

        // -----------------------------
        // Rows detalladas
        // -----------------------------
        $rows = $q->clone()
            ->orderByDesc('occurred_on')
            ->limit(500)
            ->get(['id', 'service_id', 'occurred_on', 'quantity', 'unit_name', 'meta', 'created_at']);

        // -----------------------------
        // Summary agregada (global)
        // -----------------------------
        $summary = $q->clone()
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            ->first();

        // -----------------------------
        // ✅ Totales por servicio
        // -----------------------------
        $byServiceRows = $q->clone()
            ->select('service_id')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            ->groupBy('service_id')
            ->orderByDesc('total_quantity')
            ->limit(100)
            ->get();

        $byService = collect($byServiceRows)->map(function ($r) use ($services) {
            $sid = (int) $r->service_id;
            $s = $services->get($sid);

            return [
                'service' => [
                    'id' => $sid,
                    'title' => $s?->title ?? '—',
                    'slug' => $s?->slug ?? null,
                ],
                'total_records' => (int) ($r->total_records ?? 0),
                'total_quantity' => number_format((float) ($r->total_quantity ?? 0), 4, '.', ''),
                'unit_name' => $s?->unit_name ?? null,
            ];
        })->values();

        $rowsMapped = collect($rows)->map(function ($r) use ($services) {
            $s = $services->get((int) $r->service_id);

            return [
                'id' => (int) $r->id,
                'occurred_on' => (string) $r->occurred_on,
                'service' => [
                    'id' => (int) $r->service_id,
                    'title' => $s?->title ?? '—',
                    'slug' => $s?->slug ?? null,
                ],
                'quantity' => (string) $r->quantity,
                'unit_name' => $r->unit_name ?: ($s?->unit_name ?? null),
                'meta' => $r->meta ? json_decode($r->meta, true) : null,
                'created_at' => (string) $r->created_at,
            ];
        })->values();

        return Inertia::render('Subscriber/Usage/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'currency' => $subscription->currency,
                'trial_ends_at_human' => $subscription->trial_ends_at?->format('Y-m-d'),
                'period_end_human' => $subscription->current_period_end?->format('Y-m-d'),
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'service_id' => $serviceId,
            ],
            'services' => $services->values()->map(fn($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
            ])->values(),
            'summary' => [
                'total_records' => (int) ($summary->total_records ?? 0),
                'total_quantity' => number_format((float) ($summary->total_quantity ?? 0), 4, '.', ''),
            ],
            'by_service' => $byService,
            'rows' => $rowsMapped,
        ]);
    }
}
