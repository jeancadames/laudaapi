<?php

namespace App\Http\Controllers\BusinessCommercial;

use App\Http\Controllers\Controller;
use App\Services\BusinessCommercial\CapacityService;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\BusinessIdentity\CentralIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class CapacityController extends Controller
{
    public function __invoke(Request $request, string $service, CapacityService $commercial): JsonResponse
    {
        if (! config('business_commercial_bridge.enabled')) {
            return $this->response(['error' => 'commercial_unavailable'], 503);
        }
        try {
            $peer = app(CentralIdentityService::class)->peer($service);
        } catch (HttpExceptionInterface) {
            return $this->response(['error' => 'commercial_unavailable'], 503);
        }
        $issuer = (string) config('business_identity.issuer');
        $codec = new CommercialToken($peer['secret']);
        $token = (string) $request->bearerToken();
        try {
            $data = $codec->decode($token, $service, $issuer, 'request', now()->timestamp);
        } catch (InvalidArgumentException) {
            return $this->response(['error' => 'commercial_unauthorized'], 401);
        }
        $status = 200;
        try {
            $result = ['result' => $commercial->handle($service, $data)];
        } catch (ValidationException) {
            $status = 422;
            $result = ['error' => 'commercial_invalid', 'message' => 'La solicitud comercial no cumple el contrato v1.'];
        } catch (HttpExceptionInterface $error) {
            $status = $error->getStatusCode();
            $result = ['error' => 'commercial_rejected', 'message' => $error->getMessage()];
        }
        $reply = $codec->encode($issuer, $service, 'response', [
            'request_hash' => hash('sha256', $token), 'status' => $status, ...$result,
        ], now()->timestamp);

        return $this->response(['token' => $reply], $status);
    }

    private function response(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
