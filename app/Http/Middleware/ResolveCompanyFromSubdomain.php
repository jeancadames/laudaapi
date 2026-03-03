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
        $host = $request->getHost();                 // demo.laudaapi.com
        $base = config('app.base_domain');           // laudaapi.com

        if (!Str::endsWith($host, $base)) {
            abort(404);
        }

        $sub = Str::before($host, ".{$base}");       // demo
        if ($sub === '' || $sub === 'www') {
            abort(404);
        }

        // Hard validation: solo [a-z0-9-] para evitar cosas raras
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sub)) {
            abort(404);
        }

        $company = Company::query()
            ->where('ws_subdomain', $sub)
            ->orWhere('slug', $sub) // fallback si quieres reusar slug
            ->firstOrFail();

        $request->attributes->set('company', $company);

        // si tienes un TenantManager, aquí lo enciendes:
        // app(\App\Support\TenantManager::class)->setCompany($company);

        return $next($request);
    }
}
