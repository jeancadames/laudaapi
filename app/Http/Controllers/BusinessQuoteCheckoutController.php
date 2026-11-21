<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessQuoteCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessQuoteCheckoutController extends Controller
{
    public function show(Request $request, string $publicId, BusinessQuoteCheckoutService $checkout): Response
    {
        return Inertia::render('Business/Quotes/Checkout', [
            'checkout' => $checkout->details($request->user(), $publicId), 'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, string $publicId, BusinessQuoteCheckoutService $checkout): JsonResponse
    {
        $checkout->ensureAvailable();
        $data = $request->validate([
            'confirmed' => 'required|accepted', 'snapshot_hash' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'idempotency_key' => 'required|uuid',
        ]);
        $checkout->checkout($request->user(), $publicId, $data['snapshot_hash'], $data['idempotency_key']);

        return response()->json(['checkout' => $checkout->details($request->user(), $publicId)]);
    }
}
