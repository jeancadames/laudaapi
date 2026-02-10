<?php
// app/Http/Controllers/ServiceController.php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user ? $user->role : null;
        $plan = $user && method_exists($user, 'currentPlan') ? $user->currentPlan() : null;

        $cacheKey = 'menu:' . ($role ?? 'guest') . ':' . ($plan ?? 'none');

        $menu = Cache::remember($cacheKey, 60, function () use ($role, $plan) {
            $parents = Service::active()->whereNull('parent_id')->get();

            return $parents->map(function ($p) use ($role, $plan) {
                if ($p->roles && count($p->roles) && (! $role || ! in_array($role, $p->roles))) {
                    return null;
                }
                if ($p->required_plan && $plan && $p->required_plan !== $plan) {
                    return null;
                }

                $children = $p->children()->active()->get()->filter(function ($c) use ($role, $plan) {
                    if ($c->roles && count($c->roles) && (! $role || ! in_array($role, $c->roles))) {
                        return false;
                    }
                    if ($c->required_plan && $plan && $c->required_plan !== $plan) {
                        return false;
                    }
                    return true;
                })->values();

                return [
                    'title' => $p->title,
                    'href' => $p->href,
                    'icon' => $p->icon,
                    'badge' => $p->badge,
                    'slug' => $p->slug,
                    'children' => $children->map(fn($c) => [
                        'title' => $c->title,
                        'href' => $c->href,
                        'icon' => $c->icon,
                        'badge' => $c->badge,
                        'slug' => $c->slug,
                    ]),
                ];
            })->filter()->values();
        });

        return response()->json($menu);
    }
}
