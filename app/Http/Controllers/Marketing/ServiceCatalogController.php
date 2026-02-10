<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class ServiceCatalogController extends Controller
{
    public function __invoke()
    {
        $parents = Service::query()
            ->whereNull('parent_id')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get([
                'id',
                'title',
                'slug',
                'badge',
                'icon',
                'short_description',
                'description',
                'currency',
                'monthly_price',
                'yearly_price',
            ]);

        // ✅ Si no hay padres, evita query innecesaria y devuelve catálogo vacío
        if ($parents->isEmpty()) {
            return Inertia::render('Marketplace/Index', [
                'canRegister' => Features::enabled(Features::registration()),
                'catalog' => [],
            ]);
        }

        $children = Service::query()
            ->whereIn('parent_id', $parents->pluck('id')->all())
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get([
                'id',
                'parent_id',
                'title',
                'short_description',
            ]);

        // ✅ Agrupa + normaliza highlights (no enviar modelos al front)
        $childrenByParent = $children
            ->groupBy('parent_id')
            ->map(function ($group) {
                return $group
                    ->take(5)
                    ->map(fn($c) => [
                        'id' => $c->id,
                        'title' => $c->title,
                        'short_description' => $c->short_description,
                    ])
                    ->values();
            });

        $catalog = $parents->map(function ($p) use ($childrenByParent) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'badge' => $p->badge,
                'icon' => $p->icon,

                // ✅ manda ambos (tu UI usa short_description || description)
                'short_description' => $p->short_description,
                'description' => $p->description,

                'currency' => $p->currency,
                'monthly_price' => $p->monthly_price,
                'yearly_price' => $p->yearly_price,

                // ✅ clave: usar ->get() (Collection), no [$id]
                'highlights' => $childrenByParent->get($p->id, collect())->values(),
            ];
        })->values();

        return Inertia::render('Marketplace/Index', [
            'canRegister' => Features::enabled(Features::registration()),
            'catalog' => $catalog,
        ]);
    }
}
