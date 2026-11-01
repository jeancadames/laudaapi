<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo las rutas T4D: JSON incluso en errores previos al controlador (CSRF/auth/binding). */
final class DataBiTenantDeclarationJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);

        if ($response->getStatusCode() >= 400
            && ! ($response instanceof JsonResponse && isset($response->getData(true)['error']['code']))) {
            $status = $response->getStatusCode();
            $unverified = $status === 403 && $request->user() instanceof MustVerifyEmail
                && ! $request->user()->hasVerifiedEmail();
            [$code, $message] = match ($status) {
                401 => ['unauthenticated', 'Se requiere una sesión autenticada.'],
                403 => $unverified
                    ? ['email_unverified', 'Debes verificar tu correo.']
                    : ['not_found', 'Recurso no disponible.'],
                404 => ['not_found', 'Recurso no disponible.'],
                419 => ['csrf_token_mismatch', 'La sesión o el token CSRF no son válidos.'],
                422 => ['validation_failed', 'El contenido de la solicitud no es válido.'],
                default => ['http_error', 'No se pudo procesar la solicitud.'],
            };
            // El handler de Laravel conserva el reporte de errores inesperados. Nunca devolver trazas.
            $response = self::error($code, $message, $status === 403 && ! $unverified ? 404 : $status);
        }

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public static function error(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return new JsonResponse(['error' => [
            'code' => $code,
            'message' => $message,
            'fields' => (object) $fields,
        ]], $status);
    }
}
