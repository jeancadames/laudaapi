<?php

namespace App\Services\Entitlements;

use App\Models\Service;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class SubscriberEntitlements
{
    /**
     * Servicios ERP por entitlements (SOLO para /erp):
     * incluye active|trialing|pending (Opción B).
     */
    public function erpServicesForSubscriber(int $subscriberId): Collection
    {
        $subscriptionId = Subscription::query()
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByRaw("FIELD(status,'active','trialing')")
            ->orderByDesc('id')
            ->value('id');

        if (!$subscriptionId) return collect();

        return Service::query()
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

        // Padres fijos (3 grupos)
        $parents = Service::query()
            ->whereNull('parent_id')
            ->whereIn('slug', ['api-facturacion-electronica', 'marketplace', 'laudaone'])
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        // Index rápido
        $parentById = $parents->keyBy('id');

        // Inicializar grupos
        $groups = [];
        foreach ($parents as $p) {
            $groups[$p->id] = [
                'title' => $p->title,
                'slug'  => $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'items' => [],
            ];
        }

        // Index de servicios permitidos por id (para detectar root-only)
        $servicesById = $services->keyBy('id');

        // 1) ✅ Root-only: si el padre está entitled, lo agregamos como item del grupo
        foreach ($parents as $p) {
            if (!$servicesById->has($p->id)) {
                continue;
            }

            $st = strtolower((string) ($servicesById[$p->id]->item_status ?? ''));

            $groups[$p->id]['items'][] = [
                'title' => $p->title,
                'href'  => $p->href ?: '/erp/modules/' . $p->slug,
                'icon'  => $this->normalizeIcon($p->icon),
                'badge' => $st === 'trialing' ? 'TRIAL' : ($st === 'pending' ? 'PAGO' : null),
            ];
        }

        // 2) Hijos: servicios con parent_id en los 3 padres
        foreach ($services as $s) {
            if (!$s->parent_id) {
                continue;
            }
            if (!isset($groups[$s->parent_id])) {
                continue;
            }

            $st = strtolower((string) ($s->item_status ?? ''));

            $groups[$s->parent_id]['items'][] = [
                'title' => $s->title,
                'href'  => $s->href ?: '/erp/modules/' . $s->slug,
                'icon'  => $this->normalizeIcon($s->icon),
                'badge' => $st === 'trialing' ? 'TRIAL' : ($st === 'pending' ? 'PAGO' : null),
            ];
        }

        // Ordenar items por title (o sort_order si quieres, pero aquí ya viene ordenado)
        foreach ($groups as $pid => $g) {
            // evitar duplicados (por si root-only y también aparece como hijo por data rara)
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

        // Quitar grupos vacíos
        $groups = array_values(array_filter($groups, fn($g) => count($g['items']) > 0));

        return ['groups' => $groups];
    }

    /**
     * ✅ No rompas iconos ya bien formateados (ej: LayoutGrid)
     * - Si viene "LayoutGrid" lo devolvemos igual.
     * - Si viene "layout-grid" => "LayoutGrid"
     * - Si viene vacío => LayoutGrid
     */
    private function normalizeIcon(?string $icon): string
    {
        $icon = trim((string) $icon);
        if ($icon === '') return 'LayoutGrid';

        // ya está en PascalCase sin separadores → lo respetamos
        if (!str_contains($icon, '-') && !str_contains($icon, '_') && preg_match('/[A-Z]/', $icon)) {
            return $icon;
        }

        $icon = str_replace(['-', '_'], ' ', strtolower($icon));
        return str_replace(' ', '', ucwords($icon));
    }
}
