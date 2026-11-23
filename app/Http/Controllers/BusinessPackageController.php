<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessCatalogService;
use App\Services\Billing\BusinessPackagePricing;
use App\Services\Billing\BusinessPackageChanges;
use App\Services\Billing\BusinessPackagePurchases;
use App\Services\BusinessIdentity\CentralMembership;
use App\Services\Subscribers\CompanyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessPackageController extends Controller
{
    public function __invoke(
        Request $request,
        CompanyContextResolver $companies,
        CentralMembership $membership,
        BusinessCatalogService $catalogs,
        BusinessPackagePricing $pricing,
        BusinessPackagePurchases $purchases,
    ): JsonResponse|Response {
        abort_unless(config('business_commercial.enabled'), 404);
        $user = $request->user();
        $company = $companies->resolve($user);
        if (! $company || $user->role !== 'subscriber' || ! in_array($membership->role($user, $company), ['owner', 'admin'], true)) {
            return response()->json(['code' => 'forbidden', 'message' => 'Solo el propietario o un administrador de la empresa puede armar el paquete.'], 403);
        }
        if ($request->routeIs('business.package.manage', 'business.package.change.*')) {
            $changes = app(BusinessPackageChanges::class);
            $purchase = $request->route('purchase');
            if ($request->routeIs('business.package.manage')) {
                return Inertia::render('Business/Package/Manage', ['plan' => $changes->page($company, $purchase)]);
            }
            $confirm = $request->routeIs('business.package.change.confirm');
            $data = $request->validate(['products' => ['present', 'array', 'list', 'max:4'],
                'products.*' => ['string', 'distinct:strict', Rule::in(['ecf', 'pos', 'cumplimiento', 'bys'])],
                'terms_hash' => $confirm ? ['required', 'string', 'size:64'] : ['prohibited'],
                'accepted' => $confirm ? ['required', 'accepted'] : ['prohibited']]);

            return response()->json($changes->review($user, $company, $purchase, $data['products'], $data['terms_hash'] ?? null));
        }
        if ($request->routeIs('business.package.purchase')) {
            return response()->json($purchases->details($user, $company, $request->route('purchase')));
        }
        if ($request->routeIs('business.package.confirm')) {
            $data = $request->validate(['accepted' => ['required', 'accepted'], 'proposal_ref' => ['required', 'string', 'max:12000'],
                'idempotency_key' => ['required', 'uuid']]);
            [$quote, $created] = $purchases->confirm($user, $company, $data['proposal_ref'], $data['idempotency_key']);

            return response()->json($purchases->details($user, $company, $quote->public_id), $created ? 201 : 200);
        }
        $version = Schema::hasTable('service_catalog_versions') && Schema::hasTable('service_catalogs') ? $catalogs->current() : null;
        $catalog = $version ? $pricing->catalog($version, $company) : null;
        $pending = $request->routeIs('business.package.preview') ? null : $purchases->pendingId($company);
        if ($request->routeIs('business.package.build') && $request->hasAny(['compra', 'purchase'])) {
            $key = $request->has('compra') ? 'compra' : 'purchase';
            $selected = $request->validate([$key => ['required', 'uuid']]);
            $pending = $purchases->quote($company, $selected[$key])->public_id;
        }
        if ($catalog !== null) {
            $catalog['resume_purchase_id'] = $pending;
        }
        if ($request->routeIs('business.package.build')) {
            return Inertia::render('Business/Package/Build', ['configurator' => [
                'company' => ['id' => $company->id, 'name' => $company->name],
                'availability' => $catalog && $catalog['products'] ? 'available' : 'catalog_unpublished',
                'catalog' => $catalog, 'catalog_url' => route('business.package.catalog', absolute: false),
                'calculate_url' => route('business.package.preview', absolute: false),
                'confirm_url' => config('business_commercial.checkout_enabled') ? route('business.package.confirm', absolute: false) : null,
                'resume_purchase_id' => $pending, 'home_url' => route('app.gateway', absolute: false),
            ]]);
        }
        if (! $catalog || ! $catalog['products']) {
            return response()->json(['code' => 'catalog_unavailable', 'message' => 'Todavía no hay un catálogo disponible.'], 503);
        }
        if ($request->routeIs('business.package.catalog')) {
            return response()->json($catalog);
        }
        $validator = Validator::make($request->all(), [
            'products' => ['required', 'array', 'list', 'min:1', 'max:4'],
            'products.*' => ['bail', 'required', 'string', 'distinct:strict', Rule::in(array_column($catalog['products'], 'code'))],
            'level' => ['required', 'string', Rule::in(array_column($catalog['levels'], 'code'))],
            'cycle' => ['required', 'string', Rule::in($catalog['cycles'])],
        ], [
            'products.required' => 'Elige al menos una aplicación.',
            'products.*.in' => 'Esta aplicación ya no está disponible.',
            'products.*.distinct' => 'Selecciona cada aplicación una sola vez.',
            'level.in' => 'Elige un nivel que esté disponible.',
            'cycle.in' => 'Elige pago mensual o anual.',
        ]);
        if ($validator->fails()) {
            return response()->json(['code' => 'invalid_selection', 'message' => 'Revisa las opciones seleccionadas.',
                'fields' => $validator->errors()], 422);
        }
        $preview = $pricing->preview($version, $company, $validator->validated());
        if ($preview === null) {
            return response()->json(['code' => 'invalid_selection', 'message' => 'Esta combinación no está disponible en el nivel elegido.',
                'fields' => ['level' => ['Elige otro nivel o cambia las aplicaciones.']]], 422);
        }

        return response()->json($preview);
    }
}
