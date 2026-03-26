<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmCustomerRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\CrmCustomer;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;

use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmCustomerController extends Controller
{

    public function show(Request $request, CrmCustomer $crmCustomer): Response
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmCustomer->company_id === (int) $company->id, 403);

        $crmCustomer->load([
            'assignedUser:id,name',
            'contacts' => function ($query) {
                $query->orderByDesc('is_primary')->orderBy('full_name');
            },
        ]);

        $contacts = $crmCustomer->contacts
            ->map(fn($item) => [
                'id' => $item->id,
                'full_name' => $item->full_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
                'position' => $item->position,
                'department' => $item->department,
                'email' => $item->email,
                'phone' => $item->phone,
                'mobile' => $item->mobile,
                'is_primary' => (bool) $item->is_primary,
                'status' => $item->status,
            ])
            ->values();

        $opportunities = CrmOpportunity::query()
            ->where('company_id', $company->id)
            ->where('crm_customer_id', $crmCustomer->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'stage' => $item->stage,
                'status' => $item->status,
                'amount' => $item->amount,
                'probability' => $item->probability,
                'expected_close_date' => optional($item->expected_close_date)->format('Y-m-d'),
                'closed_at' => optional($item->closed_at)->toDateTimeString(),
            ])
            ->values();

        $activities = CrmActivity::query()
            ->where('company_id', $company->id)
            ->where('crm_customer_id', $crmCustomer->id)
            ->with([
                'contact:id,full_name,first_name,last_name',
                'lead:id,name',
                'opportunity:id,title',
                'assignedUser:id,name',
            ])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($item) {
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
                    'contact_name' => $contactName !== '' ? $contactName : null,
                    'lead_name' => $item->lead?->name,
                    'opportunity_title' => $item->opportunity?->title,
                    'assigned_user_name' => $item->assignedUser?->name,
                ];
            })
            ->values();

        $contactOptions = $crmCustomer->contacts
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->full_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
            ])
            ->values();

        $opportunityOptions = CrmOpportunity::query()
            ->where('company_id', $company->id)
            ->where('crm_customer_id', $crmCustomer->id)
            ->where('status', 'open')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->title,
            ])
            ->values();

        return Inertia::render('LaudaERP/CRM/Customers/Show', [
            'customer' => [
                'id' => $crmCustomer->id,
                'type' => $crmCustomer->type,
                'name' => $crmCustomer->name,
                'business_name' => $crmCustomer->business_name,
                'document_type' => $crmCustomer->document_type,
                'document_number' => $crmCustomer->document_number,
                'email' => $crmCustomer->email,
                'phone' => $crmCustomer->phone,
                'mobile' => $crmCustomer->mobile,
                'industry' => $crmCustomer->industry,
                'source' => $crmCustomer->source,
                'status' => $crmCustomer->status,
                'address' => $crmCustomer->address,
                'city' => $crmCustomer->city,
                'region' => $crmCustomer->region,
                'country' => $crmCustomer->country,
                'assigned_user_name' => $crmCustomer->assignedUser?->name,
                'notes' => $crmCustomer->notes,
                'created_at' => optional($crmCustomer->created_at)->toDateTimeString(),
            ],
            'stats' => [
                'contacts_total' => $contacts->count(),
                'opportunities_total' => $opportunities->count(),
                'activities_total' => $activities->count(),
                'open_opportunities' => collect($opportunities)->where('status', 'open')->count(),
            ],
            'contacts' => $contacts,
            'opportunities' => $opportunities,
            'activities' => $activities,
            'contactOptions' => $contactOptions,
            'opportunityOptions' => $opportunityOptions,
        ]);
    }

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
        $status = trim((string) $request->string('status', 'active'));
        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $query = CrmCustomer::query()
            ->where('company_id', $company->id)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($assignedUserId > 0, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('industry', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%");
                });
            })
            ->with(['assignedUser:id,name'])
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn(CrmCustomer $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'business_name' => $item->business_name,
                'document_type' => $item->document_type,
                'document_number' => $item->document_number,
                'email' => $item->email,
                'phone' => $item->phone,
                'mobile' => $item->mobile,
                'industry' => $item->industry,
                'source' => $item->source,
                'status' => $item->status,
                'assigned_user_id' => $item->assigned_user_id,
                'assigned_user_name' => $item->assignedUser?->name,
                'address' => $item->address,
                'city' => $item->city,
                'region' => $item->region,
                'country' => $item->country,
                'notes' => $item->notes,
                'created_at' => optional($item->created_at)->toDateTimeString(),
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

        return Inertia::render('LaudaERP/CRM/Customers/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'items' => $items,
            'users' => $users,
            'stats' => [
                'total' => $applyAssigned(
                    CrmCustomer::where('company_id', $company->id)
                )->count(),

                'active' => $applyAssigned(
                    CrmCustomer::where('company_id', $company->id)
                        ->where('status', 'active')
                )->count(),

                'inactive' => $applyAssigned(
                    CrmCustomer::where('company_id', $company->id)
                        ->where('status', 'inactive')
                )->count(),
            ],
        ]);
    }
    public function store(CrmCustomerRequest $request): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()?->id;

        CrmCustomer::create($data);

        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function update(CrmCustomerRequest $request, CrmCustomer $crmCustomer): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmCustomer->company_id === (int) $company->id, 403);

        $crmCustomer->update($request->validated());

        return back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, CrmCustomer $crmCustomer): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmCustomer->company_id === (int) $company->id, 403);

        $crmCustomer->delete();

        return back()->with('success', 'Cliente archivado correctamente.');
    }
}
