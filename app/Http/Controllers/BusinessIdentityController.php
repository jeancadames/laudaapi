<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBusinessIdentity;
use App\Http\Requests\BusinessIdentityRequest;
use App\Models\Company;
use App\Models\Service;
use App\Services\BusinessIdentity\CentralIdentityService;
use App\Services\BusinessIdentity\IdentityPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessIdentityController extends Controller
{
    use HandlesBusinessIdentity;

    public function __construct(private CentralIdentityService $identity) {}

    public function index(Request $request, IdentityPage $page): Response
    {
        if (! $request->user()) {
            $request->session()->put('url.intended', route('business.identity.index'));
        }

        return Inertia::render('Business/Identity', ['identity' => $page->data($request->user())]);
    }

    public function preview(BusinessIdentityRequest $request, string $service): JsonResponse
    {
        return $this->identityResponse(fn () => response()->json($this->identity->complete($request->user(), $service, $request->validated('proof_token'), true)));
    }

    public function ready(BusinessIdentityRequest $request, string $service): JsonResponse
    {
        return $this->identityResponse(fn () => response()->json($this->identity->ready($request->user(), $service, $request->validated('ready_token'))));
    }

    public function cancel(Request $request, string $nonce): JsonResponse
    {
        $this->identity->cancel($request->user(), $nonce);

        return response()->json(['cancelled' => true]);
    }

    public function start(BusinessIdentityRequest $request, Company $company, string $service): JsonResponse
    {
        return $this->identityResponse(fn () => response()->json($this->identity->start($request->user(), $company, $service, $request->boolean('link_company'), $request->boolean('restart'))));
    }

    public function complete(BusinessIdentityRequest $request, string $service): JsonResponse
    {
        return $this->identityResponse(fn () => response()->json($this->identity->complete($request->user(), $service, $request->validated('proof_token'))));
    }

    public function launch(Request $request, Company $company, Service $service): RedirectResponse
    {
        return redirect()->away($this->identity->launch($request->user(), $company, $service));
    }

    public function revoke(BusinessIdentityRequest $request, Company $company, string $service): JsonResponse
    {
        $this->identity->revoke($request->user(), $company, $service, $request->boolean('whole_company'));

        return response()->json(['revoked' => true]);
    }
}
