<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessContractActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessContractActivationController extends Controller
{
    public function show(Request $request, string $publicId, BusinessContractActivationService $service): Response
    {
        return Inertia::render('Business/Activation/Show', [
            'activation' => $service->details($request->user(), $publicId, $request->routeIs('admin.*')),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, string $publicId, BusinessContractActivationService $service): JsonResponse
    {
        $service->ensureAvailable();
        $data = $request->validate([
            'confirmed' => 'required|accepted', 'snapshot_hash' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'idempotency_key' => 'required|uuid', 'implementation_version' => 'required|integer|min:1',
            'technical_evidence' => 'required|array|min:1|max:4', 'technical_evidence.*' => 'required|string|min:10|max:2000',
        ]);
        $service->activate($request->user(), $publicId, $data);

        return response()->json(['activation' => $service->details($request->user(), $publicId, true)]);
    }
}
