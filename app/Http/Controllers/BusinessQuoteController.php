<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessQuoteAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessQuoteController extends Controller
{
    public function index(Request $request, BusinessQuoteAcceptanceService $acceptance): Response
    {
        $quotes = $acceptance->visibleQuotes($request->user())->with('acceptance')->latest('id')->paginate(12)
            ->through(fn ($quote) => $acceptance->summary($quote));

        return Inertia::render('Business/Quotes/Index', ['quotes' => $quotes]);
    }

    public function show(Request $request, string $publicId, BusinessQuoteAcceptanceService $acceptance): Response
    {
        return Inertia::render('Business/Quotes/Show', [
            'quote' => $acceptance->details($acceptance->quote($request->user(), $publicId)),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function accept(Request $request, string $publicId, BusinessQuoteAcceptanceService $acceptance): JsonResponse
    {
        $acceptance->ensureAvailable();
        $data = $request->validate([
            'accepted' => 'required|accepted', 'snapshot_hash' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'idempotency_key' => 'required|uuid',
        ]);
        $receipt = $acceptance->accept($request->user(), $publicId, $data['snapshot_hash'], $data['idempotency_key']);

        return response()->json(['acceptance' => [
            'accepted_at' => $receipt->accepted_at->toIso8601String(), 'accepted_name' => $receipt->accepted_name,
            'snapshot_hash' => $receipt->snapshot_hash,
        ]]);
    }
}
