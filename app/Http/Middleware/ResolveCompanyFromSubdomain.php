<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResolveCompanyFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        // Host bruto
        $host = (string) ($request->getHost() ?: '');

        // Normaliza: minúsculas y quita puerto si viniera pegado
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);

        $base = strtolower((string) config('app.base_domain', 'laudaapi.com'));

        if ($host === '' || $base === '') {
            abort(404);
        }

        if (! Str::endsWith($host, $base)) {
            abort(404);
        }

        // ejemplo:
        // host = suplidores-electricos-jerez-jimenez-2.laudaapi.com
        // base = laudaapi.com
        // sub  = suplidores-electricos-jerez-jimenez-2
        $suffix = '.' . $base;

        if (! Str::endsWith($host, $suffix)) {
            abort(404);
        }

        $sub = Str::beforeLast($host, $suffix);

        if ($sub === '' || $sub === 'www') {
            abort(404);
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sub)) {
            abort(404);
        }

        $company = Company::query()
            ->where('ws_subdomain', $sub)
            ->orWhere('slug', $sub)
            ->firstOrFail();

        $request->attributes->set('company', $company);

        return $next($request);
    }
}