<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        /*
         * No usar redirect()->intended() aquí.
         *
         * Una URL protegida visitada antes del login (por ejemplo /dashboard)
         * puede quedar guardada como intended y enviar un subscriber al lane
         * administrativo, produciendo 403 después de autenticarse.
         *
         * /app es el gateway canónico y decide:
         * - subscriber sin Company -> /onboarding
         * - subscriber listo -> App Hub
         * - usuario T360 -> diagnóstico
         * - admin -> dashboard
         */
        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('app.gateway');
    }
}
