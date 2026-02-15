<?php

namespace App\Services\Entitlements;

use App\Models\Service;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class SubscriberEntitlements
{
    /**
     * Cache por request/instancia para no repetir queries.
     */
    private array $erpServicesCache = [];
    private array $dgiiCapabilitiesCache = [];
    private array $dgiiCapabilitiesDetailsCache = [];

    /**
     * Servicios ERP por entitlements (SOLO para /erp):
     * incluye active|trialing|pending (Opción B).
     */
    public function erpServicesForSubscriber(int $subscriberId): Collection
    {
        if (isset($this->erpServicesCache[$subscriberId])) {
            return $this->erpServicesCache[$subscriberId];
        }

        $subscriptionId = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByRaw("FIELD(status,'active','trialing')")
            ->orderByDesc('id')
            ->value('id');

        if (!$subscriptionId) {
            return $this->erpServicesCache[$subscriberId] = collect();
        }

        return $this->erpServicesCache[$subscriberId] = Service::query()
            ->select('services.*', 'subscription_items.status as item_status')
            ->join('subscription_items', 'subscription_items.service_id', '=', 'services.id')
            ->where('subscription_items.subscription_id', $subscriptionId)
            ->whereIn('subscription_items.status', ['active', 'trialing', 'pending'])
            ->where('services.active', true)
            ->orderBy('services.sort_order')
            ->get();
    }

    /**
     * Sidebar ERP por 3 grupos (padres): api-facturacion-electronica | marketplace | laudaone
     * - Muestra hijos permitidos.
     * - ✅ Si el padre está permitido (root-only), lo muestra como item aunque no tenga hijos.
     */
    public function erpSidebarTree(int $subscriberId): array
    {
        $services = $this->erpServicesForSubscriber($subscriberId);

        $parents = Service::query()
            ->whereNull('parent_id')
            ->whereIn('slug', ['api-facturacion-electronica', 'marketplace', 'laudaone'])
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        $groups = [];
        foreach ($parents as $p) {
            $groups[$p->id] = [
                'title' => $p->title,
                'slug'  => $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'items' => [],
            ];
        }

        $servicesById = $services->keyBy('id');

        foreach ($parents as $p) {
            if (!$servicesById->has($p->id)) continue;

            $st = strtolower((string) ($servicesById[$p->id]->item_status ?? ''));

            $groups[$p->id]['items'][] = [
                'title' => $p->title,
                'href'  => $p->href ?: '/erp/modules/' . $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'badge' => $st === 'trialing' ? 'TRIAL' : ($st === 'pending' ? 'PAGO' : null),
            ];
        }

        foreach ($services as $s) {
            if (!$s->parent_id) continue;
            if (!isset($groups[$s->parent_id])) continue;

            $st = strtolower((string) ($s->item_status ?? ''));

            $groups[$s->parent_id]['items'][] = [
                'title' => $s->title,
                'href'  => $s->href ?: '/erp/modules/' . $s->slug,
                'icon'  => $this->normalizeIcon($s->icon),
                'badge' => $st === 'trialing' ? 'TRIAL' : ($st === 'pending' ? 'PAGO' : null),
            ];
        }

        foreach ($groups as $pid => $g) {
            $seen = [];
            $unique = [];
            foreach ($g['items'] as $it) {
                $key = $it['href'] . '|' . $it['title'];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $unique[] = $it;
            }
            $groups[$pid]['items'] = $unique;
        }

        $groups = array_values(array_filter($groups, fn($g) => count($g['items']) > 0));

        return ['groups' => $groups];
    }

    /**
     * ✅ DGII habilitado (boolean) — rápido con exists().
     */
    public function hasDgiiCapabilities(int $subscriberId): bool
    {
        if (isset($this->dgiiCapabilitiesCache[$subscriberId])) {
            return $this->dgiiCapabilitiesCache[$subscriberId];
        }

        $subscriptionId = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByRaw("FIELD(status,'active','trialing')")
            ->orderByDesc('id')
            ->value('id');

        if (!$subscriptionId) {
            return $this->dgiiCapabilitiesCache[$subscriberId] = false;
        }

        $enabled = Service::query()
            ->join('subscription_items', 'subscription_items.service_id', '=', 'services.id')
            ->where('subscription_items.subscription_id', $subscriptionId)
            ->whereIn('subscription_items.status', ['active', 'trialing', 'pending'])
            ->where('services.active', true)
            ->whereIn('services.slug', [
                'api-facturacion-electronica',
                'certificacion-emisor-electronico',
            ])
            ->exists();

        return $this->dgiiCapabilitiesCache[$subscriberId] = $enabled;
    }

    /**
     * ✅ Detalles DGII para UI (tooltip/badge):
     * - enabled
     * - enabled_by (prioridad: api-facturacion-electronica > certificacion-emisor-electronico)
     * - enabled_services (slugs encontrados)
     * - enabled_by_item_status (active|trialing|pending)
     */
    public function dgiiCapabilitiesDetails(int $subscriberId): array
    {
        if (isset($this->dgiiCapabilitiesDetailsCache[$subscriberId])) {
            return $this->dgiiCapabilitiesDetailsCache[$subscriberId];
        }

        $subscriptionId = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByRaw("FIELD(status,'active','trialing')")
            ->orderByDesc('id')
            ->value('id');

        if (!$subscriptionId) {
            return $this->dgiiCapabilitiesDetailsCache[$subscriberId] = [
                'enabled' => false,
                'enabled_by' => null,
                'enabled_services' => [],
                'enabled_by_item_status' => null,
            ];
        }

        // ✅ Máximo 2 filas (barato)
        $rows = Service::query()
            ->select([
                'services.slug',
                'subscription_items.status as item_status',
            ])
            ->join('subscription_items', 'subscription_items.service_id', '=', 'services.id')
            ->where('subscription_items.subscription_id', $subscriptionId)
            ->whereIn('subscription_items.status', ['active', 'trialing', 'pending'])
            ->where('services.active', true)
            ->whereIn('services.slug', [
                'api-facturacion-electronica',
                'certificacion-emisor-electronico',
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->dgiiCapabilitiesCache[$subscriberId] = false;

            return $this->dgiiCapabilitiesDetailsCache[$subscriberId] = [
                'enabled' => false,
                'enabled_by' => null,
                'enabled_services' => [],
                'enabled_by_item_status' => null,
            ];
        }

        // alimenta cache booleano
        $this->dgiiCapabilitiesCache[$subscriberId] = true;

        $priority = ['api-facturacion-electronica', 'certificacion-emisor-electronico'];

        $enabledBy = null;
        $enabledByItemStatus = null;

        foreach ($priority as $p) {
            $hit = $rows->firstWhere('slug', $p);
            if ($hit) {
                $enabledBy = $p;
                $enabledByItemStatus = strtolower((string) ($hit->item_status ?? '')) ?: null;
                break;
            }
        }

        return $this->dgiiCapabilitiesDetailsCache[$subscriberId] = [
            'enabled' => true,
            'enabled_by' => $enabledBy,
            'enabled_services' => $rows->pluck('slug')->unique()->values()->all(),
            'enabled_by_item_status' => $enabledByItemStatus,
        ];
    }

    /**
     * ✅ No rompas iconos ya bien formateados (ej: LayoutGrid)
     */
    private function normalizeIcon(?string $icon): string
    {
        $icon = trim((string) $icon);
        if ($icon === '') return 'LayoutGrid';

        if (!str_contains($icon, '-') && !str_contains($icon, '_') && preg_match('/[A-Z]/', $icon)) {
            return $icon;
        }

        $icon = str_replace(['-', '_'], ' ', strtolower($icon));
        return str_replace(' ', '', ucwords($icon));
    }
}
