<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaudaErpDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // Company del subscriber (si existe)
        $company = null;
        $taxProfileReady = false;

        if (!empty($user->subscriber_id)) {
            $company = Company::query()
                ->where('subscriber_id', (int) $user->subscriber_id)
                ->first();

            if ($company) {
                $taxProfileReady = CompanyTaxProfile::query()
                    ->where('company_id', $company->id)
                    ->exists();
            }
        }

        // ✅ El menú ERP viene por HandleInertiaRequests -> nav.erp (erpSidebarTree)
        return Inertia::render('LaudaERP/Dashboard', [
            'company' => $company,
            'taxProfileReady' => $taxProfileReady,
        ]);
    }
}
