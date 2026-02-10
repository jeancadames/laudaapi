<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'level' => $request->string('level')->toString(),
            'type' => $request->string('type')->toString(),
            'user_id' => $request->input('user_id'),
            'ip' => $request->string('ip')->toString(),
        ];

        $q = ErrorLog::query()->with('user:id,name,email');

        if ($filters['level']) $q->where('level', $filters['level']);
        if ($filters['type']) $q->where('type', $filters['type']);
        if ($filters['user_id']) $q->where('user_id', $filters['user_id']);
        if ($filters['ip']) $q->where('ip', 'like', '%' . $filters['ip'] . '%');

        if ($filters['search']) {
            $s = $filters['search'];
            $q->where(function ($qq) use ($s) {
                $qq->where('message', 'like', "%{$s}%")
                    ->orWhere('route', 'like', "%{$s}%")
                    ->orWhere('url', 'like', "%{$s}%")
                    ->orWhere('fingerprint', 'like', "%{$s}%");
            });
        }

        $logs = $q->orderByDesc('last_seen_at')
            ->paginate(24)
            ->withQueryString();

        $levels = ErrorLog::query()->select('level')->distinct()->orderBy('level')->pluck('level')->all();
        $types = ErrorLog::query()->select('type')->whereNotNull('type')->distinct()->orderBy('type')->pluck('type')->all();

        return Inertia::render('Admin/ErrorLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'levels' => $levels,
            'types' => $types,
        ]);
    }

    public function show(Request $request, ErrorLog $errorLog)
    {
        $errorLog->load('user:id,name,email');

        return Inertia::render('Admin/ErrorLog/Show', [
            'log' => $errorLog,
        ]);
    }
}
