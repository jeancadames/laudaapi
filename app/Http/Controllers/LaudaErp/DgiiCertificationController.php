<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DgiiCertificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        $company = Company::where('subscriber_id', $subscriberId)->firstOrFail();

        // ✅ Defaults temporales (sin DgiiCompanySetting aún)
        $setting = [
            'environment'   => 'precert',
            'use_directory' => true,
            'endpoints'     => [],
        ];

        return Inertia::render('LaudaERP/CertificacionEmisor/Index', [
            'company' => [
                'id'   => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc'  => $company->rnc ?? null,
            ],
            'setting' => $setting,
        ]);
    }
}
