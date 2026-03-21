<?php

namespace App\Http\Controllers\LaudaErp\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CrmActivity;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
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

        $stats = [
            'customers_total' => CrmCustomer::query()
                ->where('company_id', $companyId)
                ->count(),

            'contacts_total' => \App\Models\CrmContact::query()
                ->where('company_id', $companyId)
                ->count(),

            'leads_total' => CrmLead::query()
                ->where('company_id', $companyId)
                ->count(),

            'opportunities_open' => CrmOpportunity::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->count(),

            'quotes_open' => 0, // pendiente cuando montes cotizaciones CRM/ventas

            'activities_pending' => CrmActivity::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count(),
        ];

        $pipelineStages = [
            'lead' => 'Leads',
            'qualified' => 'Calificados',
            'proposal' => 'Propuesta',
            'negotiation' => 'Negociación',
            'won' => 'Ganados',
        ];

        $pipelineCounts = CrmOpportunity::query()
            ->where('company_id', $companyId)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $pipeline = collect($pipelineStages)
            ->map(fn($title, $key) => [
                'key' => $key,
                'title' => $title,
                'count' => (int) ($pipelineCounts[$key] ?? 0),
            ])
            ->values();

        $recentActivities = CrmActivity::query()
            ->where('company_id', $companyId)
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

        $topCustomers = CrmCustomer::query()
            ->where('company_id', $companyId)
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
            [
                'title' => 'Nuevo cliente',
                'description' => 'Crear un cliente o cuenta comercial.',
                'href' => '/erp/crm/customers',
            ],
            [
                'title' => 'Nuevo contacto',
                'description' => 'Registrar una persona de contacto.',
                'href' => '/erp/crm/contacts',
            ],
            [
                'title' => 'Nuevo lead',
                'description' => 'Registrar una nueva oportunidad inicial.',
                'href' => '/erp/crm/leads',
            ],
            [
                'title' => 'Nueva oportunidad',
                'description' => 'Agregar una oportunidad al pipeline.',
                'href' => '/erp/crm/opportunities',
            ],
            [
                'title' => 'Nueva actividad',
                'description' => 'Programar seguimiento comercial.',
                'href' => '/erp/crm/activities',
            ],
        ];

        return Inertia::render('LaudaERP/CRM/Index', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'recentActivities' => $recentActivities,
            'topCustomers' => $topCustomers,
            'quickActions' => $quickActions,
        ]);
    }
}
