<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaudaErp\Crm\CrmLeadRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\CrmLead;
use App\Models\CrmCustomer;
use App\Models\CrmOpportunity;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmLeadController extends Controller
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
        $status = trim((string) $request->string('status', 'new'));
        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $query = CrmLead::query()
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
                        ->orWhere('source', 'like', "%{$search}%");
                });
            })
            ->with(['assignedUser:id,name'])
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn(CrmLead $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'business_name' => $item->business_name,
                'document_type' => $item->document_type,
                'document_number' => $item->document_number,
                'email' => $item->email,
                'phone' => $item->phone,
                'mobile' => $item->mobile,
                'source' => $item->source,
                'status' => $item->status,
                'estimated_value' => $item->estimated_value,
                'score' => $item->score,
                'assigned_user_id' => $item->assigned_user_id,
                'assigned_user_name' => $item->assignedUser?->name,
                'qualified_at' => optional($item->qualified_at)->toDateTimeString(),
                'converted_at' => optional($item->converted_at)->toDateTimeString(),
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

        return Inertia::render('LaudaERP/CRM/Leads/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'items' => $items,
            'users' => $users,
            'stats' => [
                'total' => $applyAssigned(
                    CrmLead::where('company_id', $company->id)
                )->count(),

                'new' => $applyAssigned(
                    CrmLead::where('company_id', $company->id)
                        ->where('status', 'new')
                )->count(),

                'qualified' => $applyAssigned(
                    CrmLead::where('company_id', $company->id)
                        ->where('status', 'qualified')
                )->count(),

                'converted' => $applyAssigned(
                    CrmLead::where('company_id', $company->id)
                        ->where('status', 'converted')
                )->count(),
            ],
        ]);
    }

    public function store(CrmLeadRequest $request): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()?->id;

        if (($data['status'] ?? null) === 'qualified') {
            $data['qualified_at'] = now();
        }

        if (($data['status'] ?? null) === 'converted') {
            $data['converted_at'] = now();
        }

        CrmLead::create($data);

        return back()->with('success', 'Lead creado correctamente.');
    }

    public function update(CrmLeadRequest $request, CrmLead $crmLead): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmLead->company_id === (int) $company->id, 403);

        $data = $request->validated();

        if (($data['status'] ?? null) === 'qualified' && !$crmLead->qualified_at) {
            $data['qualified_at'] = now();
        }

        if (($data['status'] ?? null) === 'converted' && !$crmLead->converted_at) {
            $data['converted_at'] = now();
        }

        $crmLead->update($data);

        return back()->with('success', 'Lead actualizado correctamente.');
    }

    public function destroy(Request $request, CrmLead $crmLead): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmLead->company_id === (int) $company->id, 403);

        $crmLead->delete();

        return back()->with('success', 'Lead archivado correctamente.');
    }

    public function convert(Request $request, CrmLead $crmLead): RedirectResponse
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $crmLead->company_id === (int) $company->id, 403);

        if ($crmLead->status === 'converted') {
            return back()->with('error', 'Este lead ya fue convertido.');
        }

        $data = $request->validate([
            'create_customer' => ['nullable', 'boolean'],
            'create_opportunity' => ['nullable', 'boolean'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'opportunity_title' => ['nullable', 'string', 'max:180'],
            'opportunity_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $createCustomer = (bool) ($data['create_customer'] ?? true);
        $createOpportunity = (bool) ($data['create_opportunity'] ?? true);

        if (! $createCustomer && ! $createOpportunity) {
            throw ValidationException::withMessages([
                'convert' => 'Debes crear al menos un cliente o una oportunidad.',
            ]);
        }

        DB::transaction(function () use ($request, $crmLead, $company, $data, $createCustomer, $createOpportunity) {
            $customer = null;

            if ($createCustomer) {
                $customer = CrmCustomer::create([
                    'company_id' => $company->id,
                    'type' => $crmLead->type ?: 'company',
                    'name' => $data['customer_name'] ?? $crmLead->name,
                    'business_name' => $crmLead->business_name,
                    'document_type' => $crmLead->document_type,
                    'document_number' => $crmLead->document_number,
                    'email' => $crmLead->email,
                    'phone' => $crmLead->phone,
                    'mobile' => $crmLead->mobile,
                    'source' => $crmLead->source,
                    'status' => 'active',
                    'assigned_user_id' => $crmLead->assigned_user_id,
                    'created_by' => $request->user()?->id,
                    'notes' => $crmLead->notes,
                ]);
            }

            if ($createOpportunity) {
                CrmOpportunity::create([
                    'company_id' => $company->id,
                    'crm_customer_id' => $customer?->id,
                    'crm_lead_id' => $crmLead->id,
                    'title' => $data['opportunity_title'] ?? ('Oportunidad - ' . $crmLead->name),
                    'stage' => 'qualified',
                    'status' => 'open',
                    'amount' => $data['opportunity_amount'] ?? $crmLead->estimated_value,
                    'probability' => 25,
                    'assigned_user_id' => $crmLead->assigned_user_id,
                    'created_by' => $request->user()?->id,
                    'description' => 'Oportunidad creada desde conversión de lead.',
                    'notes' => $crmLead->notes,
                ]);
            }

            $crmLead->update([
                'status' => 'converted',
                'converted_at' => now(),
                'qualified_at' => $crmLead->qualified_at ?: now(),
            ]);
        });

        return back()->with('success', 'Lead convertido correctamente.');
    }
}
