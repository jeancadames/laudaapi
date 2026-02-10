<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\Request;

class AdminContactRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all'); // all | unread | read

        $base = ContactRequest::query();

        // Búsqueda
        $base->when($search !== '', function ($q) use ($search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        // Filtro por estado
        $base->when($status === 'unread', fn($q) => $q->whereNull('read_at'))
            ->when($status === 'read', fn($q) => $q->whereNotNull('read_at'));

        // Profesional: no leídos primero, luego recientes
        $contacts = $base
            ->orderByRaw('read_at IS NOT NULL') // NULL first
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Contadores (sin depender del filtro actual)
        $counts = [
            'all' => ContactRequest::count(),
            'unread' => ContactRequest::whereNull('read_at')->count(),
            'read' => ContactRequest::whereNotNull('read_at')->count(),
        ];

        return inertia('Admin/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['all', 'unread', 'read'], true) ? $status : 'all',
            ],
            'counts' => $counts,
        ]);
    }

    public function show(ContactRequest $contact)
    {
        return inertia('Admin/Contacts/Show', [
            'contact' => $contact,
        ]);
    }

    public function markAsRead(ContactRequest $contact)
    {
        if (is_null($contact->read_at)) {
            $contact->forceFill(['read_at' => now()])->save();
        }

        return back()->with('success', 'Marcado como leído.');
    }

    public function markAllAsRead(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'unread'); // esperamos unread

        // Solo tiene sentido marcar "unread"
        if ($status !== 'unread') {
            return back()->with('success', 'No hay nada que marcar en ese filtro.');
        }

        $q = ContactRequest::query()
            ->whereNull('read_at')
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $affected = (clone $q)->count();

        // ✅ Query Builder update NO depende de $fillable
        $q->update(['read_at' => now()]);

        return back()->with('success', "Marcados como leído: {$affected}");
    }
}
