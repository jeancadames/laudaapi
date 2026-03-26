<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\User;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
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
        $companyId = (int) $company->id;

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $assignedUserId = (int) $request->integer('assigned_user_id', 0);

        $applyAssigned = function ($query) use ($assignedUserId) {
            if ($assignedUserId > 0) {
                $query->where('assigned_user_id', $assignedUserId);
            }

            return $query;
        };

        // Ajusta esto luego si quieres restringir por miembros reales de la empresa
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(User $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])
            ->values();

        $customersTotal = $applyAssigned(
            CrmCustomer::query()->where('company_id', $companyId)
        )->count();

        $contactsTotal = $applyAssigned(
            CrmContact::query()->where('company_id', $companyId)
        )->count();

        $leadsTotal = $applyAssigned(
            CrmLead::query()->where('company_id', $companyId)
        )->count();

        $convertedLeadsTotal = $applyAssigned(
            CrmLead::query()
                ->where('company_id', $companyId)
                ->where('status', 'converted')
        )->count();

        $opportunitiesOpen = $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
        )->count();

        $activitiesPending = $applyAssigned(
            CrmActivity::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending')
        )->count();

        $activitiesOverdue = $applyAssigned(
            CrmActivity::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<', $now)
        )->count();

        $openPipelineValue = (float) $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
        )->sum('amount');

        $wonThisMonthValue = (float) $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'won')
                ->whereBetween('closed_at', [$monthStart, $monthEnd])
        )->sum('amount');

        $lostThisMonthValue = (float) $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'lost')
                ->whereBetween('closed_at', [$monthStart, $monthEnd])
        )->sum('amount');

        $wonThisMonthCount = $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'won')
                ->whereBetween('closed_at', [$monthStart, $monthEnd])
        )->count();

        $lostThisMonthCount = $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'lost')
                ->whereBetween('closed_at', [$monthStart, $monthEnd])
        )->count();

        $leadToOpportunityRate = $leadsTotal > 0
            ? round(($convertedLeadsTotal / $leadsTotal) * 100, 1)
            : 0.0;

        $pipelineStages = [
            'lead' => 'Leads',
            'qualified' => 'Calificados',
            'proposal' => 'Propuesta',
            'negotiation' => 'Negociación',
            'won' => 'Ganados',
            'lost' => 'Perdidos',
        ];

        $pipelineCounts = $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->selectRaw('stage, COUNT(*) as total')
                ->groupBy('stage')
        )->pluck('total', 'stage');

        $pipelineAmounts = $applyAssigned(
            CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->selectRaw('stage, COALESCE(SUM(amount), 0) as total_amount')
                ->groupBy('stage')
        )->pluck('total_amount', 'stage');

        $pipeline = collect($pipelineStages)
            ->map(fn($title, $key) => [
                'key' => $key,
                'title' => $title,
                'count' => (int) ($pipelineCounts[$key] ?? 0),
                'amount' => (float) ($pipelineAmounts[$key] ?? 0),
            ])
            ->values();

        $recentActivities = $applyAssigned(
            CrmActivity::query()->where('company_id', $companyId)
        )
            ->with([
                'customer:id,name',
                'contact:id,full_name,first_name,last_name',
                'lead:id,name',
                'opportunity:id,title',
                'assignedUser:id,name',
            ])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (CrmActivity $item) {
                $contactName = $item->contact?->full_name
                    ?: trim(($item->contact?->first_name ?? '') . ' ' . ($item->contact?->last_name ?? ''));

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->type,
                    'status' => $item->status,
                    'priority' => $item->priority,
                    'scheduled_at' => optional($item->scheduled_at)->format('Y-m-d H:i'),
                    'completed_at' => optional($item->completed_at)->format('Y-m-d H:i'),
                    'customer_name' => $item->customer?->name,
                    'contact_name' => $contactName !== '' ? $contactName : null,
                    'lead_name' => $item->lead?->name,
                    'opportunity_title' => $item->opportunity?->title,
                    'assigned_user_name' => $item->assignedUser?->name,
                ];
            })
            ->values();

        $topCustomers = $applyAssigned(
            CrmCustomer::query()->where('company_id', $companyId)
        )
            ->withCount([
                'contacts',
                'opportunities',
                'activities',
            ])
            ->orderByDesc('opportunities_count')
            ->orderByDesc('activities_count')
            ->orderByDesc('contacts_count')
            ->limit(8)
            ->get()
            ->map(fn(CrmCustomer $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'business_name' => $item->business_name,
                'status' => $item->status,
                'contacts_count' => (int) $item->contacts_count,
                'opportunities_count' => (int) $item->opportunities_count,
                'activities_count' => (int) $item->activities_count,
                'email' => $item->email,
                'phone' => $item->phone,
            ])
            ->values();

        $quickActions = [
            ['title' => 'Nuevo cliente', 'description' => 'Crear un cliente o cuenta comercial.', 'href' => '/erp/crm/customers'],
            ['title' => 'Nuevo contacto', 'description' => 'Registrar una persona de contacto.', 'href' => '/erp/crm/contacts'],
            ['title' => 'Nuevo lead', 'description' => 'Registrar una nueva oportunidad inicial.', 'href' => '/erp/crm/leads'],
            ['title' => 'Nueva oportunidad', 'description' => 'Agregar una oportunidad al pipeline.', 'href' => '/erp/crm/opportunities'],
            ['title' => 'Nueva actividad', 'description' => 'Programar seguimiento comercial.', 'href' => '/erp/crm/activities'],
            ['title' => 'Ver pipeline', 'description' => 'Abrir la vista kanban del pipeline.', 'href' => '/erp/crm/pipeline'],
        ];

        return Inertia::render('LaudaERP/CRM/Index', [
            'stats' => [
                'customers_total' => $customersTotal,
                'contacts_total' => $contactsTotal,
                'leads_total' => $leadsTotal,
                'opportunities_open' => $opportunitiesOpen,
                'quotes_open' => 0,
                'activities_pending' => $activitiesPending,
            ],
            'executive' => [
                'open_pipeline_value' => $openPipelineValue,
                'won_this_month_value' => $wonThisMonthValue,
                'lost_this_month_value' => $lostThisMonthValue,
                'won_this_month_count' => $wonThisMonthCount,
                'lost_this_month_count' => $lostThisMonthCount,
                'lead_to_opportunity_rate' => $leadToOpportunityRate,
                'activities_overdue' => $activitiesOverdue,
            ],
            'pipeline' => $pipeline,
            'recentActivities' => $recentActivities,
            'topCustomers' => $topCustomers,
            'quickActions' => $quickActions,
            'filters' => [
                'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            ],
            'users' => $users,
        ]);
    }
}
