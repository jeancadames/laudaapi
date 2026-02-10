<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminActivationRequestController extends Controller
{
    private const STATUSES = [
        'pending',
        'contacted',
        'accepted',
        'activated',
        'trialing',
        'expired',
        'converted',
        'discarded',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all'); // all | pending | contacted | ...

        if ($status !== 'all' && !in_array($status, self::STATUSES, true)) {
            $status = 'all';
        }

        $base = ActivationRequest::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('topic', 'like', "%{$search}%")
                        ->orWhere('system', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn($q) => $q->where('status', $status));

        // Orden pro: estados más urgentes primero, luego recientes
        // (CASE funciona bien en MySQL y PostgreSQL)
        $orderCase = "CASE status
            WHEN 'pending' THEN 1
            WHEN 'contacted' THEN 2
            WHEN 'activated' THEN 3
            WHEN 'trialing' THEN 4
            WHEN 'converted' THEN 5
            WHEN 'expired' THEN 6
            WHEN 'discarded' THEN 7
            ELSE 99 END";

        $requests = $base
            ->orderByRaw($orderCase)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Contadores globales por status (para tabs)
        $countsByStatus = ActivationRequest::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $counts = [
            'all' => array_sum(array_map('intval', $countsByStatus)),
        ];

        foreach (self::STATUSES as $st) {
            $counts[$st] = (int) ($countsByStatus[$st] ?? 0);
        }

        return inertia('Admin/Requests/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'counts' => $counts,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(ActivationRequest $activationRequest)
    {
        return inertia('Admin/Requests/Show', [
            'request' => $activationRequest->fresh(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, ActivationRequest $activationRequest)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', self::STATUSES)],
        ]);

        $newStatus = $data['status'];

        // Idempotente
        if ($activationRequest->status === $newStatus) {
            return back()->with('success', 'Estado sin cambios.');
        }

        // Lógica pro: si pasa a trialing y no tiene fechas, inicializa trial
        $updates = ['status' => $newStatus];

        if ($newStatus === 'trialing') {
            $trialDays = (int) ($activationRequest->trial_days ?? 30);

            if (is_null($activationRequest->trial_starts_at)) {
                $updates['trial_starts_at'] = now();
            }
            if (is_null($activationRequest->trial_ends_at)) {
                $updates['trial_ends_at'] = now()->addDays($trialDays);
            }
        }

        // Si se marca expired, y no hay trial_ends_at, lo ponemos en now
        if ($newStatus === 'expired' && is_null($activationRequest->trial_ends_at)) {
            $updates['trial_ends_at'] = now();
        }

        $activationRequest->forceFill($updates)->save();

        return back()->with('success', 'Estado actualizado.');
    }
}
