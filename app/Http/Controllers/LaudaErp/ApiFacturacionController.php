<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Dgii\DgiiCertificateRequirements;
use App\Services\Entitlements\SubscriberEntitlements;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiFacturacionController extends Controller
{
    public function index(Request $request)
    {
        return $this->render($request);
    }
    public function electronica(Request $request)
    {
        return $this->render($request);
    }

    private function render(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // ✅ MISMO subscriberId que ERP + fallback resolver
        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }
        abort_unless($subscriberId > 0, 403);

        // ✅ MISMA company selection que tu DgiiTokenAutoController
        $company = Company::query()
            ->when(
                !empty($user->company_id),
                fn($q) => $q->where('id', (int) $user->company_id),
                fn($q) => $q->where('subscriber_id', (int) $subscriberId)->orderByDesc('id')
            )
            ->firstOrFail();

        /** @var SubscriberEntitlements $entitlements */
        $entitlements = app(SubscriberEntitlements::class);

        // ✅ MISMO criterio DGII capabilities que usas en token auto
        $dgii = $entitlements->dgiiCapabilitiesDetails((int) $subscriberId);

        // ✅ CLAVE: requisitos reales de certificados para ESA company
        $certReq = app(DgiiCertificateRequirements::class)->checkForCompany($company->id);

        return Inertia::render('LaudaERP/ApiFacturacion/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'timezone' => $company->timezone,
            ],

            // ✅ para pintar badges / gating interno si quieres
            'dgii' => $dgii,

            // ✅ lo que tu UI debe usar para “REVISAR / OK”
            'cert_requirements' => $certReq,
        ]);
    }
}
