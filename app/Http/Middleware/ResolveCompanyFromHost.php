<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Company;

class ResolveCompanyFromHost
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost(); // ej: acme.laudaapi.com

        // 1) Si manejas dominios custom, prioriza match exacto
        $company = Company::query()->where('domain', $host)->first();

        // 2) Si no, cae a subdominio *.laudaapi.com
        if (!$company && Str::endsWith($host, '.laudaapi.com')) {
            $sub = Str::before($host, '.laudaapi.com'); // "acme"
            if ($sub && $sub !== 'www' && $sub !== 'laudaapi') {
                $company = Company::query()->where('slug', $sub)->first();
            }
        }

        if (!$company) {
            abort(404);
        }

        // deja la compañía disponible para controllers/services
        $request->attributes->set('company', $company);

        return $next($request);
    }
}
