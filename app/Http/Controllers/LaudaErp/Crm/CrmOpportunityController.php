<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmOpportunityRequest;
use App\Models\Company;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmOpportunityController extends Controller
{
    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        return Company::query()
            ->where('subscriber_id', $subscriberId)
            ->firstOrFail();
    }

    public function index(Request $request): Response
    {
        $company = $this->companyFromErp($request);

        $search = trim((string) $request->string('search', ''));
        $stage = trim((string) $request->string('stage', 'all'));
        $status = trim((string) $request->string('status', 'open'));

        $query = CrmOpportunity::query()
            ->where('company_id', $company->id)
            ->when($stage !== 'all', fn($q) => $q->where('stage', $stage))
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('loss_reason', 'like', "%{$search}%");
                });
            })
            ->with([
                'customer:id,name,business_name',
                'lead:id,name,business_name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn(CrmOpportunity $item) => [
                'id' => $item->id,
                'crm_customer_id' => $item->crm_customer_id,
                'crm_lead_id' => $item->crm_lead_id,
                'customer_name' => $item->customer?->name,
                'lead_name' => $item->lead?->name,
                'title' => $item->title,
                'stage' => $item->stage,
                'status' => $item->status,
                'amount' => $item->amount,
                'probability' => $item->probability,
                'expected_close_date' => optional($item->expected_close_date)->format('Y-m-d'),
                'closed_at' => optional($item->closed_at)->toDateTimeString(),
                'assigned_user_id' => $item->assigned_user_id,
                'assigned_user_name' => $item->assignedUser?->name,
                'description' => $item->description,
                'notes' => $item->notes,
                'loss_reason' => $item->loss_reason,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ]);

        $customers = CrmCustomer::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'business_name'])
            ->map(fn(CrmCustomer $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'business_name' => $item->business_name,
            ]);

        $leads = CrmLead::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['new', 'qualified'])
            ->orderBy('name')
            ->get(['id', 'name', 'business_name'])
            ->map(fn(CrmLead $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'business_name' => $item->business_name,
            ]);

        return Inertia::render('LaudaERP/CRM/Opportunities/Index', [
            'filters' => [
                'search' => $search,
                'stage' => $stage,
                'status' => $status,
            ],
            'items' => $items,
            'customers' => $customers,
            'leads' => $leads,
            'stats' => [
                'total' => CrmOpportunity::where('company_id', $company->id)->count(),
                'open' => CrmOpportunity::where('company_id', $company->id)->where('status', 'open')->count(),
                'won' => CrmOpportunity::where('company_id', $company->id)->where('status', 'won')->count(),
                'lost' => CrmOpportunity::where('company_id', $company->id)->where('status', 'lost')->count(),
            ],
        ]);
    }

    public function store(CrmOpportunityRequest $request): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()?->id;

        if (($data['status'] ?? null) === 'won' || ($data['status'] ?? null) === 'lost') {
            $data['closed_at'] = now();
        }

        CrmOpportunity::create($data);

        return back()->with('success', 'Oportunidad creada correctamente.');
    }

    public function update(CrmOpportunityRequest $request, CrmOpportunity $crmOpportunity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $data = $request->validated();

        if (
            (($data['status'] ?? null) === 'won' || ($data['status'] ?? null) === 'lost')
            && !$crmOpportunity->closed_at
        ) {
            $data['closed_at'] = now();
        }

        if (($data['status'] ?? null) === 'open') {
            $data['closed_at'] = null;
        }

        $crmOpportunity->update($data);

        return back()->with('success', 'Oportunidad actualizada correctamente.');
    }

    public function destroy(Request $request, CrmOpportunity $crmOpportunity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $crmOpportunity->delete();

        return back()->with('success', 'Oportunidad archivada correctamente.');
    }
}
