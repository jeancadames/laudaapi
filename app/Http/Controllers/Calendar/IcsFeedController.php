<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IcsFeedController extends Controller
{
    public function show(Request $request, Company $company, string $token): Response
    {
        // Placeholder (lo implementamos luego)
        abort(501, 'ICS feed no implementado todavía.');
    }
}
