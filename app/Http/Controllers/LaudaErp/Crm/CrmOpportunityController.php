<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmOpportunityRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmActivity;
use App\Models\CrmContact;
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
        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $query = CrmOpportunity::query()
            ->where('company_id', $company->id)
            ->when($stage !== 'all', fn($q) => $q->where('stage', $stage))
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($assignedUserId > 0, fn($q) => $q->where('assigned_user_id', $assignedUserId))
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

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(User $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])
            ->values();

        $applyAssigned = function ($query) use ($assignedUserId) {
            if ($assignedUserId > 0) {
                $query->where('assigned_user_id', $assignedUserId);
            }

            return $query;
        };

        return Inertia::render('LaudaERP/CRM/Opportunities/Index', [
            'filters' => [
                'search' => $search,
                'stage' => $stage,
                'status' => $status,
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'items' => $items,
            'customers' => $customers,
            'leads' => $leads,
            'users' => $users,
            'stats' => [
                'total' => $applyAssigned(
                    CrmOpportunity::where('company_id', $company->id)
                )->count(),

                'open' => $applyAssigned(
                    CrmOpportunity::where('company_id', $company->id)
                        ->where('status', 'open')
                )->count(),

                'won' => $applyAssigned(
                    CrmOpportunity::where('company_id', $company->id)
                        ->where('status', 'won')
                )->count(),

                'lost' => $applyAssigned(
                    CrmOpportunity::where('company_id', $company->id)
                        ->where('status', 'lost')
                )->count(),
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

    public function show(Request $request, CrmOpportunity $crmOpportunity): Response
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $crmOpportunity->load([
            'customer:id,name,business_name,email,phone,mobile,status',
            'lead:id,name,business_name,email,phone,mobile,status',
            'assignedUser:id,name',
        ]);

        $activities = CrmActivity::query()
            ->where('company_id', $company->id)
            ->where('crm_opportunity_id', $crmOpportunity->id)
            ->with([
                'customer:id,name',
                'contact:id,full_name,first_name,last_name',
                'lead:id,name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (CrmActivity $item) {
                $contactName = $item->contact?->full_name
                    ?: trim(($item->contact?->first_name ?? '') . ' ' . ($item->contact?->last_name ?? ''));

                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'description' => $item->description,
                    'status' => $item->status,
                    'priority' => $item->priority,
                    'scheduled_at' => optional($item->scheduled_at)->format('Y-m-d H:i'),
                    'completed_at' => optional($item->completed_at)->format('Y-m-d H:i'),
                    'customer_name' => $item->customer?->name,
                    'contact_name' => $contactName !== '' ? $contactName : null,
                    'lead_name' => $item->lead?->name,
                    'assigned_user_name' => $item->assignedUser?->name,
                ];
            })
            ->values();

        $contactOptions = collect();
        if ($crmOpportunity->crm_customer_id) {
            $contactOptions = CrmContact::query()
                ->where('company_id', $company->id)
                ->where('crm_customer_id', $crmOpportunity->crm_customer_id)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'first_name', 'last_name'])
                ->map(fn(CrmContact $item) => [
                    'id' => $item->id,
                    'name' => $item->full_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
                ])
                ->values();
        }

        return Inertia::render('LaudaERP/CRM/Opportunities/Show', [
            'opportunity' => [
                'id' => $crmOpportunity->id,
                'crm_customer_id' => $crmOpportunity->crm_customer_id,
                'crm_lead_id' => $crmOpportunity->crm_lead_id,
                'customer_name' => $crmOpportunity->customer?->name,
                'customer_business_name' => $crmOpportunity->customer?->business_name,
                'lead_name' => $crmOpportunity->lead?->name,
                'lead_business_name' => $crmOpportunity->lead?->business_name,
                'title' => $crmOpportunity->title,
                'stage' => $crmOpportunity->stage,
                'status' => $crmOpportunity->status,
                'amount' => $crmOpportunity->amount,
                'probability' => $crmOpportunity->probability,
                'expected_close_date' => optional($crmOpportunity->expected_close_date)->format('Y-m-d'),
                'closed_at' => optional($crmOpportunity->closed_at)->toDateTimeString(),
                'assigned_user_name' => $crmOpportunity->assignedUser?->name,
                'description' => $crmOpportunity->description,
                'notes' => $crmOpportunity->notes,
                'loss_reason' => $crmOpportunity->loss_reason,
                'created_at' => optional($crmOpportunity->created_at)->toDateTimeString(),
            ],
            'stats' => [
                'activities_total' => $activities->count(),
                'pending_activities' => $activities->where('status', 'pending')->count(),
                'completed_activities' => $activities->where('status', 'completed')->count(),
            ],
            'activities' => $activities,
            'contactOptions' => $contactOptions,
        ]);
    }

    public function markWon(Request $request, CrmOpportunity $crmOpportunity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $crmOpportunity->update([
            'stage' => 'won',
            'status' => 'won',
            'probability' => 100,
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Oportunidad marcada como ganada.');
    }

    public function markLost(Request $request, CrmOpportunity $crmOpportunity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'loss_reason' => ['nullable', 'string'],
        ]);

        $crmOpportunity->update([
            'stage' => 'lost',
            'status' => 'lost',
            'closed_at' => now(),
            'loss_reason' => $data['loss_reason'] ?? $crmOpportunity->loss_reason,
        ]);

        return back()->with('success', 'Oportunidad marcada como perdida.');
    }

    public function pipeline(Request $request): Response
    {
        $company = $this->companyFromErp($request);
        $companyId = (int) $company->id;

        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $query = CrmOpportunity::query()
            ->where('company_id', $companyId)
            ->when($assignedUserId > 0, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->with([
                'customer:id,name',
                'lead:id,name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('id');

        $items = $query
            ->get()
            ->map(fn(CrmOpportunity $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'stage' => $item->stage,
                'status' => $item->status,
                'amount' => $item->amount,
                'probability' => $item->probability,
                'expected_close_date' => optional($item->expected_close_date)->format('Y-m-d'),
                'customer_name' => $item->customer?->name,
                'lead_name' => $item->lead?->name,
                'assigned_user_name' => $item->assignedUser?->name,
            ])
            ->values();

        $stages = [
            ['key' => 'lead', 'title' => 'Lead'],
            ['key' => 'qualified', 'title' => 'Qualified'],
            ['key' => 'proposal', 'title' => 'Proposal'],
            ['key' => 'negotiation', 'title' => 'Negotiation'],
            ['key' => 'won', 'title' => 'Won'],
            ['key' => 'lost', 'title' => 'Lost'],
        ];

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(User $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])
            ->values();

        return Inertia::render('LaudaERP/CRM/Pipeline/Index', [
            'stages' => $stages,
            'items' => $items,
            'filters' => [
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'users' => $users,
        ]);
    }

    public function move(Request $request, CrmOpportunity $crmOpportunity): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmOpportunity->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'stage' => ['required', 'in:lead,qualified,proposal,negotiation,won,lost'],
        ]);

        $stage = $data['stage'];

        $payload = [
            'stage' => $stage,
        ];

        if ($stage === 'won') {
            $payload['status'] = 'won';
            $payload['probability'] = 100;
            $payload['closed_at'] = now();
        } elseif ($stage === 'lost') {
            $payload['status'] = 'lost';
            $payload['closed_at'] = now();
        } else {
            $payload['status'] = 'open';
            $payload['closed_at'] = null;
        }

        $crmOpportunity->update($payload);

        return back()->with('success', 'Oportunidad movida correctamente.');
    }
}
