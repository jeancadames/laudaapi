<?php

namespace App\Http\Middleware;

use App\Models\ActivationRequest;
use Closure;
use Illuminate\Http\Request;

class EnsureActivationAccepted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // ✅ Regla correcta:
        // No usamos "latest status" porque si el user crea otra solicitud pending,
        // latest = pending y lo bloquearía aunque ya tenga una accepted/trialing/converted.

        $hasAccess = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->accessAllowed() // accepted | trialing | converted
            ->exists();

        if (!$hasAccess) {
            return redirect('/')
                ->with('error', 'Debes confirmar tu activación desde el enlace enviado a tu correo.');
        }

        return $next($request);
    }
}
