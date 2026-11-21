<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessRenewalController extends Controller
{
    public function show(Request $request, string $publicId, BusinessRenewalService $service): Response
    {
        return Inertia::render('Business/Renewal/Show', ['renewal' => $service->details($request->user(), $publicId, $request->routeIs('admin.*'))]);
    }

    public function propose(Request $request, string $publicId, BusinessRenewalService $service): JsonResponse
    {
        $service->propose($request->user(), $publicId, $request->all());

        return response()->json(['renewal' => $service->details($request->user(), $publicId, true)]);
    }

    public function accept(Request $request, string $publicId, BusinessRenewalService $service): JsonResponse
    {
        $service->accept($request->user(), $publicId, $request->all());

        return response()->json(['renewal' => $service->details($request->user(), $publicId)]);
    }

    public function invoice(Request $request, string $publicId, BusinessRenewalService $service): JsonResponse
    {
        $service->issue($request->user(), $publicId, $request->all());

        return response()->json(['renewal' => $service->details($request->user(), $publicId, true)]);
    }
}
