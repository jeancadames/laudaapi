<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmActivityRequest;
use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmActivityController extends Controller
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
        $status = trim((string) $request->string('status', 'pending'));
        $type = trim((string) $request->string('type', 'all'));

        $query = CrmActivity::query()
            ->where('company_id', $company->id)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($type !== 'all', fn($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with([
                'customer:id,name',
                'contact:id,full_name,first_name,last_name',
                'lead:id,name',
                'opportunity:id,title',
                'assignedUser:id,name',
            ])
            ->orderByRaw("case when status = 'pending' then 0 when status = 'completed' then 1 else 2 end")
            ->orderBy('scheduled_at')
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn(CrmActivity $item) => [
                'id' => $item->id,
                'crm_customer_id' => $item->crm_customer_id,
                'crm_contact_id' => $item->crm_contact_id,
                'crm_lead_id' => $item->crm_lead_id,
                'crm_opportunity_id' => $item->crm_opportunity_id,

                'customer_name' => $item->customer?->name,
                'contact_name' => $item->contact?->full_name ?: trim(($item->contact?->first_name ?? '') . ' ' . ($item->contact?->last_name ?? '')),
                'lead_name' => $item->lead?->name,
                'opportunity_title' => $item->opportunity?->title,

                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->status,
                'priority' => $item->priority,
                'scheduled_at' => optional($item->scheduled_at)->format('Y-m-d\TH:i'),
                'completed_at' => optional($item->completed_at)->toDateTimeString(),
                'assigned_user_id' => $item->assigned_user_id,
                'assigned_user_name' => $item->assignedUser?->name,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ]);

        $customers = CrmCustomer::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(CrmCustomer $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ]);

        $contacts = CrmContact::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'first_name', 'last_name'])
            ->map(fn(CrmContact $item) => [
                'id' => $item->id,
                'name' => $item->full_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
            ]);

        $leads = CrmLead::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['new', 'qualified'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(CrmLead $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ]);

        $opportunities = CrmOpportunity::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn(CrmOpportunity $item) => [
                'id' => $item->id,
                'name' => $item->title,
            ]);

        return Inertia::render('LaudaERP/CRM/Activities/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'items' => $items,
            'customers' => $customers,
            'contacts' => $contacts,
            'leads' => $leads,
            'opportunities' => $opportunities,
            'stats' => [
                'total' => CrmActivity::where('company_id', $company->id)->count(),
                'pending' => CrmActivity::where('company_id', $company->id)->where('status', 'pending')->count(),
                'completed' => CrmActivity::where('company_id', $company->id)->where('status', 'completed')->count(),
                'urgent' => CrmActivity::where('company_id', $company->id)->where('priority', 'urgent')->count(),
            ],
        ]);
    }

    public function store(CrmActivityRequest $request): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()?->id;

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        CrmActivity::create($data);

        return back()->with('success', 'Actividad creada correctamente.');
    }

    public function update(CrmActivityRequest $request, CrmActivity $crmActivity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmActivity->company_id === (int) $company->id, 403);

        $data = $request->validated();

        if (($data['status'] ?? null) === 'completed' && !$crmActivity->completed_at) {
            $data['completed_at'] = now();
        }

        if (($data['status'] ?? null) !== 'completed') {
            $data['completed_at'] = null;
        }

        $crmActivity->update($data);

        return back()->with('success', 'Actividad actualizada correctamente.');
    }

    public function destroy(Request $request, CrmActivity $crmActivity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmActivity->company_id === (int) $company->id, 403);

        $crmActivity->delete();

        return back()->with('success', 'Actividad archivada correctamente.');
    }
}
