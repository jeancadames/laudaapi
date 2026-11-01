<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo la consulta administrativa T4F-A, incluidos errores anteriores al controlador. */
final class AdminDataBiDeclarationJson
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
                403 => ['forbidden', 'Esta consulta requiere una cuenta verificada de Admin LAUDA.'],
                404 => ['not_found', 'Solicitud no disponible.'],
                422 => ['validation_failed', 'La consulta no es válida.'],
                default => ['http_error', 'No fue posible consultar la declaración.'],
            };
            $cookies = $response->headers->getCookies();
            $response = new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
            foreach ($cookies as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
