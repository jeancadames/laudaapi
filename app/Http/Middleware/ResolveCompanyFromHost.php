<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $base = strtolower((string) config('app.base_domain', 'laudaapi.com'));

        // 1) Custom domain exacto
        $company = Company::query()
            ->whereRaw('LOWER(domain) = ?', [$host])
            ->first();

        // 2) Wildcard subdomain
        if (! $company) {
            if (! Str::endsWith($host, '.' . $base)) {
                abort(404);
            }

            $sub = Str::before($host, '.' . $base);

            if ($sub === '' || in_array($sub, ['www', 'laudaapi'], true)) {
                abort(404);
            }

            // evita nested subdomains raros tipo a.b.laudaapi.com
            if (str_contains($sub, '.')) {
                abort(404);
            }

            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sub)) {
                abort(404);
            }

            $company = Company::query()
                ->where(function ($q) use ($sub) {
                    $q->where('ws_subdomain', $sub)
                        ->orWhere('slug', $sub);
                })
                ->first();
        }

        if (! $company) {
            abort(404);
        }

        $request->attributes->set('company', $company);

        // opcional, útil para ERP y otros flows
        $request->attributes->set('resolved_company_id', $company->id);
        $request->attributes->set('resolved_subscriber_id', $company->subscriber_id);

        // opcional: compartir globalmente
        app()->instance('currentCompany', $company);

        return $next($request);
    }
}
