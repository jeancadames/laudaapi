<?php

namespace App\Http\Controllers;

use App\Models\BusinessImplementationOrder;
use App\Services\Billing\BusinessImplementationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessImplementationController extends Controller
{
    public function index(Request $request, BusinessImplementationService $service): Response
    {
        $service->ensureAvailable();
        $service->authorizeAdmin($request->user());
        $orders = BusinessImplementationOrder::query()
            ->join('service_quote_checkouts as checkouts', 'checkouts.id', '=', 'business_implementation_orders.service_quote_checkout_id')
            ->join('service_quote_acceptances as acceptances', 'acceptances.id', '=', 'checkouts.service_quote_acceptance_id')
            ->join('service_commercial_quotes as quotes', 'quotes.id', '=', 'acceptances.service_commercial_quote_id')
            ->join('companies', 'companies.id', '=', 'checkouts.company_id')
            ->select(['business_implementation_orders.id', 'business_implementation_orders.status', 'companies.name as company_name', 'quotes.public_id'])
            ->orderByDesc('business_implementation_orders.updated_at')->orderByDesc('business_implementation_orders.id')
            ->paginate(20)->through(fn ($order) => ['id' => $order->id, 'company_name' => $order->company_name,
                'status_label' => BusinessImplementationService::LABELS[$order->status],
                'url' => route('admin.business_implementations.show', $order->public_id, false)]);

        return Inertia::render('Business/Implementation/Index', ['orders' => $orders]);
    }

    public function show(Request $request, string $publicId, BusinessImplementationService $service): Response
    {
        return Inertia::render('Business/Implementation/Show', [
            'implementation' => $service->details($request->user(), $publicId, $request->routeIs('admin.*')),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, string $publicId, BusinessImplementationService $service): JsonResponse
    {
        $service->ensureAvailable();
        $data = $request->validate([
            'confirmed' => 'required|accepted', 'action' => 'required|in:request,start,block,resume,deliver,complete,request_changes,cancel',
            'snapshot_hash' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'idempotency_key' => 'required|uuid',
            'version' => 'required|integer|min:0|max:4294967294', 'note' => 'required|string|min:3|max:4000',
        ]);
        $admin = $request->routeIs('admin.*');
        $service->act($request->user(), $publicId, $data, $admin);

        return response()->json(['implementation' => $service->details($request->user(), $publicId, $admin), 'idempotency_key' => (string) Str::uuid()]);
    }
}
