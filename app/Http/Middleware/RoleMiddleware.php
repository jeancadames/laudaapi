<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Permite "admin,subscriber" y también "admin|subscriber"
        $roles = str_replace('|', ',', $roles);

        $allowed = array_filter(array_map('trim', explode(',', $roles)));

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
