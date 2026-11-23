<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Models\BusinessImplementationOrder;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteCheckout;
use App\Models\User;
use App\Services\BusinessIdentity\IdentityPage;
use App\Services\Entitlements\BusinessContractCoverage;
use App\Services\LaudaErp\ServiceAccessResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BusinessPackageAccess
{
    public function details(User $actor, Company $company, ServiceCommercialQuote $quote, ServiceQuoteCheckout $checkout, string $payment): array
    {
        $products = $quote->snapshot['self_service']['selection']['products'];
        $message = match ($payment) {
            'payable', 'preparing' => 'Esperando el pago.',
            'processing' => 'Pendiente de confirmación del pago.',
            'paid' => 'Pago confirmado. Falta completar la activación.',
            'reversed' => 'El pago se revirtió.',
            default => 'Revisa el estado del pago.',
        };
        $access = array_map(fn ($code) => ['product' => $code, 'status' => 'pending', 'message' => $message, 'action' => null], $products);
        if ($payment !== 'paid' || ! config('business_commercial.activation_enabled')
            || ! Schema::hasTable('business_contracts') || ! Schema::hasTable('business_contract_items')) {
            return $access;
        }

        $identity = app(IdentityPage::class)->data($actor, $company->id);
        $links = array_column($identity['companies'][0]['services'] ?? [], null, 'key');
        $contract = BusinessContract::where('service_quote_checkout_id', $checkout->id)->first();
        $items = $contract ? DB::table('business_contract_items')->where('business_contract_id', $contract->id)
            ->pluck('subscription_item_id', 'service_id') : collect();
        $implementation = config('business_commercial.implementation_enabled') && Schema::hasTable('business_implementation_orders');
        $orderStatus = $implementation ? BusinessImplementationOrder::where('service_quote_checkout_id', $checkout->id)->value('status') : null;
        $guided = $implementation && ! $contract && ! $orderStatus && $quote->isGuidedPackage();
        $hold = $guided ? (app(BusinessImplementationService::class)->identityHold($quote)
            ?? app(BusinessContractActivationService::class)->guidedHold($quote, $checkout->id)) : null;
        $services = Service::whereIn('id', array_column($quote->snapshot['products'], 'service_id'))->get()->keyBy('id');

        foreach ($products as $i => $code) {
            $link = $links[$code] ?? null;
            $service = $services->get($quote->snapshot['products'][$code]['service_id']);
            if (! $service || $service->service_key !== $code || ! $link || ! $link['configured']) {
                $access[$i] = ['product' => $code, 'status' => 'unavailable', 'message' => 'La conexión con esta aplicación requiere revisión de LAUDA.', 'action' => null];
            } elseif (! $link['ready'] && $link['can_link']) {
                $access[$i] = ['product' => $code, 'status' => 'link_required',
                    'action' => ['label' => 'Vincular mi cuenta y empresa', 'url' => route('business.identity.index', absolute: false)]];
            } elseif ($contract) {
                $item = $items->get($service->id);
                $ready = $link['launch_url'] && $item && BusinessContractCoverage::itemIsCovered($item)
                    && app(ServiceAccessResolver::class)->userCanAccess($actor, $company, $service);
                $access[$i] = $ready
                    ? ['product' => $code, 'status' => 'ready', 'action' => ['label' => 'Abrir '.$link['name'], 'url' => $link['launch_url']]]
                    : ['product' => $code, 'status' => 'unavailable', 'message' => 'El acceso contratado requiere revisión.', 'action' => null];
            } elseif (($guided && $hold === null) || $orderStatus === 'delivered') {
                $access[$i] = ['product' => $code, 'status' => 'consent_required',
                    'action' => ['label' => 'Revisar la implementación', 'url' => route('business.quotes.implementation.show', $quote->public_id, false)]];
            } elseif ($hold !== null) {
                $access[$i]['message'] = $hold;
            }
        }

        return $access;
    }
}
