<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],  // busca en event/model_type/ip/user_agent/data
            'event' => ['nullable', 'string', 'max:120'],
            'model_type' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'ip' => ['nullable', 'string', 'max:60'],
        ]);

        $q = AuditLog::query()
            ->with(['user:id,name,email'])
            ->latest('id');

        if (!empty($filters['event'])) {
            $q->where('event', $filters['event']);
        }

        if (!empty($filters['model_type'])) {
            $q->where('model_type', $filters['model_type']);
        }

        if (!empty($filters['user_id'])) {
            $q->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['ip'])) {
            $q->where('ip', $filters['ip']);
        }

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);

            $q->where(function ($w) use ($s) {
                $w->where('event', 'like', "%{$s}%")
                    ->orWhere('model_type', 'like', "%{$s}%")
                    ->orWhere('ip', 'like', "%{$s}%")
                    ->orWhere('user_agent', 'like', "%{$s}%")
                    ->orWhere('data', 'like', "%{$s}%"); // en MySQL funciona (JSON como texto), en PG quizá quieras to_tsvector
            });
        }

        $logs = $q->paginate(30)->withQueryString();

        // para dropdowns simples
        $events = AuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $modelTypes = AuditLog::query()
            ->whereNotNull('model_type')
            ->select('model_type')
            ->distinct()
            ->orderBy('model_type')
            ->pluck('model_type');

        return Inertia::render('Admin/AuditLog/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'event' => $filters['event'] ?? '',
                'model_type' => $filters['model_type'] ?? '',
                'user_id' => $filters['user_id'] ?? null,
                'ip' => $filters['ip'] ?? '',
            ],
            'events' => $events,
            'modelTypes' => $modelTypes,
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load(['user:id,name,email']);

        return Inertia::render('Admin/AuditLog/Show', [
            'log' => $auditLog,
        ]);
    }
}
