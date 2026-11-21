<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Services\Billing\BusinessCatalogRules;
use App\Services\Billing\BusinessCatalogService;
use Database\Seeders\BusinessCatalogSeeder;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AdminBusinessCatalogController extends Controller
{
    public function index(Request $request, BusinessCatalogService $catalog): Response
    {
        if (! $this->installed()) {
            return Inertia::render('Admin/BusinessCatalog/Index', [
                'installed' => false, 'versions' => [], 'selected' => null, 'currentId' => null,
                'services' => [], 'audit' => [], 'quotes' => [],
                'initialCapacityPolicy' => BusinessCatalogRules::INITIAL_CAPACITY_POLICY,
            ]);
        }
        $id = ServiceCatalog::where('code', 'business')->value('id');
        $versions = ServiceCatalogVersion::where('service_catalog_id', $id)->orderByDesc('version')->get([
            'id', 'version', 'status', 'lock_version', 'effective_at', 'created_at', 'published_at', 'retired_at',
        ]);
        $selectedId = $request->integer('version') ?: $versions->first()?->id;
        $selected = $selectedId ? ServiceCatalogVersion::where('service_catalog_id', $id)->findOrFail($selectedId) : null;

        return Inertia::render('Admin/BusinessCatalog/Index', [
            'installed' => true,
            'initialCapacityPolicy' => BusinessCatalogRules::INITIAL_CAPACITY_POLICY,
            'versions' => $versions, 'selected' => $selected, 'currentId' => $catalog->current()?->id,
            'services' => Service::whereIn('service_key', ['ecf', 'pos', 'bys', 'cumplimiento'])->get(['id', 'title', 'service_key', 'active']),
            'audit' => $selected ? AuditLog::where('model_type', ServiceCatalogVersion::class)->where('model_id', $selected->id)
                ->with('user:id,name')->latest('id')->limit(20)->get(['id', 'event', 'user_id', 'created_at', 'data']) : [],
            'quotes' => ServiceCommercialQuote::latest('id')->limit(20)->get(['id', 'public_id', 'company_id', 'service_catalog_version_id', 'issued_at', 'valid_until']),
        ]);
    }

    public function initialize(Request $request): JsonResponse
    {
        if (! $this->installed()) {
            return response()->json(['message' => 'La instalación del catálogo todavía está pendiente.'], 503);
        }
        (new BusinessCatalogSeeder)->run((int) $request->user()->id);

        return response()->json(['message' => 'Catálogo inicial disponible en borrador. Los valores existentes se conservaron.']);
    }

    public function save(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $request->validate(['lock_version' => 'required|integer|min:1', 'payload' => 'required|array']);

        return $this->domain(fn () => $catalog->save($version, (int) $data['lock_version'], $data['payload'], (int) $request->user()->id));
    }

    public function revise(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $request->validate(['lock_version' => 'required|integer|min:1']);

        return $this->domain(fn () => $catalog->revise($version, (int) $data['lock_version'], (int) $request->user()->id));
    }

    public function publish(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $request->validate(['lock_version' => 'required|integer|min:1', 'effective_at' => 'nullable|string|max:40']);

        return $this->domain(fn () => $catalog->publish($version, (int) $data['lock_version'], $data['effective_at'] ?? null, (int) $request->user()->id));
    }

    public function retire(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $request->validate(['lock_version' => 'required|integer|min:1']);

        return $this->domain(fn () => $catalog->retire($version, (int) $data['lock_version'], (int) $request->user()->id));
    }

    public function preview(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $this->selection($request);

        return $this->domain(fn () => $catalog->preview($version, $data['selection'], isset($data['company_id']) ? Company::findOrFail($data['company_id']) : null));
    }

    public function issue(Request $request, ServiceCatalogVersion $version, BusinessCatalogService $catalog): JsonResponse
    {
        $data = $this->selection($request);
        $issue = $request->validate(['company_id' => 'required|integer|exists:companies,id', 'idempotency_key' => 'required|uuid', 'lock_version' => 'required|integer|min:1',
            'transition' => 'nullable|array:mode,target_subscription_id,source_hash,confirmed',
            'transition.mode' => 'required_with:transition|in:after_paid_periods',
            'transition.target_subscription_id' => 'required_with:transition|integer|min:1',
            'transition.source_hash' => 'required_with:transition|string|regex:/^[a-f0-9]{64}$/D',
            'transition.confirmed' => 'required_with:transition|accepted']);

        return $this->domain(fn () => $catalog->issue($version, (int) $issue['lock_version'], Company::findOrFail($issue['company_id']),
            $data['selection'], $issue['idempotency_key'], (int) $request->user()->id, isset($issue['transition']) ? [...$issue['transition'], 'confirmed' => $request->boolean('transition.confirmed')] : null));
    }

    public function companies(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'required|string|min:2|max:100']);

        return response()->json(Company::where('name', 'like', '%'.$data['search'].'%')->orderBy('name')->limit(20)->get(['id', 'name', 'subscriber_id', 'active']));
    }

    public function quote(ServiceCommercialQuote $quote): JsonResponse
    {
        return response()->json($quote);
    }

    private function selection(Request $request): array
    {
        return $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'selection' => 'required|array:offer,level,cycle,implementation,addons,discount',
            'selection.offer' => 'required|string|max:50', 'selection.level' => 'required|string|max:50',
            'selection.cycle' => 'required|in:monthly,yearly', 'selection.implementation' => 'required|string|max:50',
            'selection.addons' => 'present|array|max:100', 'selection.addons.*' => 'integer|min:0|max:100000',
            'selection.discount' => 'nullable|string|max:50',
        ]);
    }

    private function installed(): bool
    {
        return Schema::hasTable('service_catalogs') && Schema::hasTable('service_catalog_versions') && Schema::hasTable('service_commercial_quotes');
    }

    private function domain(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['catalog' => $exception->getMessage()]);
        }
    }
}
