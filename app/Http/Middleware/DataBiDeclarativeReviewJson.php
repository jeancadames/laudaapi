<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** B3 solamente: envuelve sesión/CSRF/auth/binding y elimina detalles técnicos de errores. */
final class DataBiDeclarativeReviewJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);
        if ($response->getStatusCode() >= 400
            && ! ($response instanceof JsonResponse && isset($response->getData(true)['error']['code']))) {
            $status = $response->getStatusCode();
            [$code, $message] = match ($status) {
                401 => ['unauthenticated', 'Se requiere una sesión autenticada.'],
                403 => $request->user() !== null && ! $request->user()->hasVerifiedEmail()
                    ? ['email_unverified', 'Debes verificar tu correo.'] : ['forbidden', 'No tienes permiso para esta operación.'],
                404 => ['not_found', 'Recurso no disponible.'],
                405 => ['method_not_allowed', 'Operación no disponible.'],
                419 => ['csrf_token_mismatch', 'La sesión o el token CSRF no son válidos.'],
                422 => ['validation_failed', 'El contenido de la solicitud no es válido.'],
                503 => ['service_unavailable', 'El servicio de revisión declarativa no está disponible.'],
                default => ['http_error', 'No fue posible procesar la revisión declarativa.'],
            };
            $cookies = $response->headers->getCookies();
            $response = self::error($code, $message, $status);
            foreach ($cookies as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public static function error(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) $fields]], $status);
    }
}
