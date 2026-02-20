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

    // ✅ micro-cache subscription_id por subscriber (evita duplicar query en 3 métodos)
    private array $subscriptionIdCache = [];

    /**
     * Servicios ERP por entitlements (SOLO para /erp):
     * incluye active|trialing|pending (Opción B).
     */
    public function erpServicesForSubscriber(int $subscriberId): Collection
    {
        if (isset($this->erpServicesCache[$subscriberId])) {
            return $this->erpServicesCache[$subscriberId];
        }

        $subscriptionId = $this->resolveActiveSubscriptionId($subscriberId);

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
     * Sidebar ERP:
     * - Grupos (padres): api-facturacion-electronica | marketplace | laudaone
     * - Footer fijo (si están activos): calendario-fiscal | cumplimiento-fiscal
     *
     * ✅ No tocamos jerarquía DB, no rompemos Admin/Subscriber dashboards.
     */
    public function erpSidebarTree(int $subscriberId): array
    {
        $services = $this->erpServicesForSubscriber($subscriberId);

        // ✅ Grupos padres reales (no romper estructura actual)
        $parentSlugs = [
            'api-facturacion-electronica',
            'marketplace',
            'laudaone',
        ];

        // ✅ Items “importantísimos” que NO deben ir dentro de API; van en footer
        $footerSlugs = [
            'calendario-fiscal',
            'cumplimiento-fiscal',
        ];
        $footerSet = array_flip($footerSlugs);

        // =====
        // Parents (grupos)
        // =====
        $parents = Service::query()
            ->whereNull('parent_id')
            ->whereIn('slug', $parentSlugs)
            ->where('active', true)
            ->get()
            ->keyBy('slug');

        // Pre-build groups con orden estable
        $groups = [];
        foreach ($parentSlugs as $slug) {
            $p = $parents->get($slug);
            if (!$p) continue;

            $groups[$p->id] = [
                'title' => $p->title,
                'slug'  => $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'items' => [],
            ];
        }

        $servicesById = $services->keyBy('id');

        // 1) Si el padre está permitido, agréguelo como item (root link)
        foreach ($parentSlugs as $slug) {
            $p = $parents->get($slug);
            if (!$p) continue;

            if (!$servicesById->has($p->id)) continue;

            $st = strtolower((string) ($servicesById[$p->id]->item_status ?? ''));

            $groups[$p->id]['items'][] = [
                'title' => $p->title,
                'href'  => $p->href ?: '/erp/modules/' . $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'badge' => $this->badgeFromItemStatus($st),
            ];
        }

        // 2) Hijos permitidos (EXCEPTO los del footer)
        foreach ($services as $s) {
            if (!$s->parent_id) continue;
            if (!isset($groups[$s->parent_id])) continue;

            // 🚫 No mostrar Calendario/Cumplimiento dentro del grupo API
            if (isset($footerSet[$s->slug])) continue;

            $st = strtolower((string) ($s->item_status ?? ''));

            $groups[$s->parent_id]['items'][] = [
                'title' => $s->title,
                'href'  => $s->href ?: '/erp/modules/' . $s->slug,
                'icon'  => $this->normalizeIcon($s->icon),
                'badge' => $this->badgeFromItemStatus($st),
            ];
        }

        // 3) Dedupe de items por group
        foreach ($groups as $pid => $g) {
            $groups[$pid]['items'] = $this->dedupeItems($g['items'] ?? []);
        }

        // 4) Remueve groups vacíos y devuelve en orden
        $groups = array_values(array_filter($groups, fn($g) => count($g['items']) > 0));
        $groups = $this->sortGroupsBySlugOrder($groups, $parentSlugs);

        // =====
        // Footer fijo (solo si los servicios están activos en entitlements)
        // =====
        $footer = [];
        foreach ($footerSlugs as $slug) {
            $hit = $services->firstWhere('slug', $slug);
            if (!$hit) continue;

            $st = strtolower((string) ($hit->item_status ?? ''));

            $footer[] = [
                'title' => $hit->title,
                'href'  => $hit->href ?: '/erp/modules/' . $hit->slug,
                'icon'  => $this->normalizeIcon($hit->icon),
                'badge' => $this->badgeFromItemStatus($st),
            ];
        }
        $footer = $this->dedupeItems($footer);

        return [
            'groups' => $groups,
            'footer' => $footer, // ✅ NUEVO: para renderizar en el footer del sidebar ERP
        ];
    }

    /**
     * ✅ DGII habilitado (boolean) — rápido con exists().
     */
    public function hasDgiiCapabilities(int $subscriberId): bool
    {
        if (isset($this->dgiiCapabilitiesCache[$subscriberId])) {
            return $this->dgiiCapabilitiesCache[$subscriberId];
        }

        $subscriptionId = $this->resolveActiveSubscriptionId($subscriberId);

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

        $subscriptionId = $this->resolveActiveSubscriptionId($subscriberId);

        if (!$subscriptionId) {
            return $this->dgiiCapabilitiesDetailsCache[$subscriberId] = [
                'enabled' => false,
                'enabled_by' => null,
                'enabled_services' => [],
                'enabled_by_item_status' => null,
            ];
        }

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

    // =========================
    // Helpers
    // =========================

    private function resolveActiveSubscriptionId(int $subscriberId): ?int
    {
        if (array_key_exists($subscriberId, $this->subscriptionIdCache)) {
            return $this->subscriptionIdCache[$subscriberId] ?: null;
        }

        $id = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByRaw("FIELD(status,'active','trialing')")
            ->orderByDesc('id')
            ->value('id');

        $this->subscriptionIdCache[$subscriberId] = $id ? (int) $id : 0;

        return $id ? (int) $id : null;
    }

    private function badgeFromItemStatus(string $st): ?string
    {
        return $st === 'trialing' ? 'TRIAL' : ($st === 'pending' ? 'PAGO' : null);
    }

    private function dedupeItems(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $it) {
            $href  = (string) ($it['href'] ?? '');
            $title = (string) ($it['title'] ?? '');
            $key   = $href . '|' . $title;

            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $unique[] = $it;
        }

        return $unique;
    }

    private function sortGroupsBySlugOrder(array $groups, array $slugOrder): array
    {
        $rank = [];
        foreach ($slugOrder as $i => $slug) $rank[$slug] = $i;

        usort($groups, function ($a, $b) use ($rank) {
            $ra = $rank[$a['slug']] ?? 999;
            $rb = $rank[$b['slug']] ?? 999;
            return $ra <=> $rb;
        });

        return $groups;
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
