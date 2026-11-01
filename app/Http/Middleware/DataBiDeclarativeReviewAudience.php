<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Control del canal antes del binding. El controlador y B2 reautorizan desde BD bajo lock. */
final class DataBiDeclarativeReviewAudience
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()->hasVerifiedEmail(), 403);
        if ($audience === 'admin') {
            abort_unless($request->user()->role === 'admin', 403);
        } else {
            abort_if($request->user()->role === 'admin', 404);
        }

        return $next($request);
    }
}
