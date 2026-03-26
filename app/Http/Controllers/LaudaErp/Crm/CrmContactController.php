<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmContactRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\CrmContact;
use App\Models\CrmCustomer;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmContactController extends Controller
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
        $status = trim((string) $request->string('status', 'active'));
        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $query = CrmContact::query()
            ->where('company_id', $company->id)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($assignedUserId > 0, fn($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->with([
                'customer:id,name,business_name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('is_primary')
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn(CrmContact $item) => [
                'id' => $item->id,
                'crm_customer_id' => $item->crm_customer_id,
                'customer_name' => $item->customer?->name,
                'customer_business_name' => $item->customer?->business_name,
                'first_name' => $item->first_name,
                'last_name' => $item->last_name,
                'full_name' => $item->full_name ?: trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
                'position' => $item->position,
                'department' => $item->department,
                'email' => $item->email,
                'phone' => $item->phone,
                'mobile' => $item->mobile,
                'is_primary' => (bool) $item->is_primary,
                'status' => $item->status,
                'assigned_user_id' => $item->assigned_user_id,
                'assigned_user_name' => $item->assignedUser?->name,
                'notes' => $item->notes,
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

        return Inertia::render('LaudaERP/CRM/Contacts/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'items' => $items,
            'customers' => $customers,
            'users' => $users,
            'stats' => [
                'total' => $applyAssigned(
                    CrmContact::where('company_id', $company->id)
                )->count(),

                'active' => $applyAssigned(
                    CrmContact::where('company_id', $company->id)
                        ->where('status', 'active')
                )->count(),

                'primary' => $applyAssigned(
                    CrmContact::where('company_id', $company->id)
                        ->where('is_primary', true)
                )->count(),
            ],
        ]);
    }

    public function store(CrmContactRequest $request): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        $customer = CrmCustomer::query()
            ->where('company_id', $company->id)
            ->findOrFail($request->integer('crm_customer_id'));

        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()?->id;

        if (!empty($data['is_primary'])) {
            CrmContact::query()
                ->where('company_id', $company->id)
                ->where('crm_customer_id', $customer->id)
                ->update(['is_primary' => false]);
        }

        CrmContact::create($data);

        return back()->with('success', 'Contacto creado correctamente.');
    }

    public function update(CrmContactRequest $request, CrmContact $crmContact): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmContact->company_id === (int) $company->id, 403);

        $customer = CrmCustomer::query()
            ->where('company_id', $company->id)
            ->findOrFail($request->integer('crm_customer_id'));

        $data = $request->validated();

        if (!empty($data['is_primary'])) {
            CrmContact::query()
                ->where('company_id', $company->id)
                ->where('crm_customer_id', $customer->id)
                ->where('id', '!=', $crmContact->id)
                ->update(['is_primary' => false]);
        }

        $crmContact->update($data);

        return back()->with('success', 'Contacto actualizado correctamente.');
    }

    public function destroy(Request $request, CrmContact $crmContact): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmContact->company_id === (int) $company->id, 403);

        $crmContact->delete();

        return back()->with('success', 'Contacto archivado correctamente.');
    }
}
