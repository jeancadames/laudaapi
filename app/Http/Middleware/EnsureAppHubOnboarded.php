<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppHubOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ($user->role ?? null) === 'admin') {
            return $next($request);
        }

        $ready = $user->activeSubscribers()
            ->whereHas('company')
            ->exists();

        if (! $ready) {
            return redirect()->route('app.gateway');
        }

        return $next($request);
    }
}
