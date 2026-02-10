<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Company;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberServiceRequestController extends Controller
{
    public function toggle(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            AuditService::log('service_request_denied', null, [
                'reason' => 'not_authenticated',
            ]);
            return back()->with('error', 'No autenticado.');
        }

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        $serviceId = (int) $data['service_id'];

        $service = Service::query()
            ->select(['id', 'slug', 'title', 'active'])
            ->findOrFail($serviceId);

        // ✅ activation request “activa” (si tu regla es 1 activa, siempre será la misma)
        $activationRequest = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ActivationRequest::ACTIVE_STATUSES ?? [
                ActivationRequest::STATUS_PENDING,
                ActivationRequest::STATUS_ACCEPTED,
                ActivationRequest::STATUS_TRIALING,
            ])
            ->latest('id')
            ->first();

        if (!$activationRequest) {
            AuditService::log('service_request_denied', $service, [
                'reason' => 'no_activation_request',
                'user_id' => $user->id,
                'service_id' => $service->id,
                'service_slug' => $service->slug,
                'service_title' => $service->title,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Debes tener una solicitud de activación antes de solicitar servicios.');
        }

        $company = $this->resolveCompany($user);

        if (!$company || !$company->subscriber_id) {
            AuditService::log('service_request_denied', $service, [
                'reason' => 'no_company_or_subscriber',
                'user_id' => $user->id,
                'activation_request_id' => $activationRequest->id,
                'service_id' => $service->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No tienes compañía/suscriptor asignado.');
        }

        // ✅ suscripción (permitir active/trialing vigente)
        $sub = Subscription::query()
            ->where('subscriber_id', $company->subscriber_id)
            ->latest('id')
            ->first();

        if (!$sub || !$this->subscriptionAllowsSelection($sub)) {
            AuditService::log('service_request_denied', $service, [
                'reason' => 'subscription_not_allowed',
                'user_id' => $user->id,
                'activation_request_id' => $activationRequest->id,
                'company_id' => $company->id,
                'subscriber_id' => $company->subscriber_id,
                'subscription_id' => $sub?->id,
                'subscription_status' => $sub?->status,
                'trial_ends_at' => $sub?->trial_ends_at,
                'current_period_end' => $sub?->current_period_end,
                'ends_at' => $sub?->ends_at,
                'service_id' => $service->id,
                'service_slug' => $service->slug,
                'service_title' => $service->title,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Requiere una suscripción activa o un trial vigente para seleccionar servicios.');
        }

        // ✅ servicio debe estar activo
        if (!(bool) $service->active) {
            AuditService::log('service_request_denied', $service, [
                'reason' => 'service_inactive',
                'user_id' => $user->id,
                'activation_request_id' => $activationRequest->id,
                'company_id' => $company->id,
                'subscriber_id' => $company->subscriber_id,
                'subscription_id' => $sub->id,
                'subscription_status' => $sub->status,
                'service_id' => $service->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Este servicio no está disponible actualmente.');
        }

        // ✅ si ya está activo en subscription_items, no se solicita
        $alreadyActive = SubscriptionItem::query()
            ->where('subscription_id', $sub->id)
            ->where('service_id', $serviceId)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if ($alreadyActive) {
            AuditService::log('service_request_denied_already_active', $service, [
                'reason' => 'already_active_in_subscription',
                'user_id' => $user->id,
                'activation_request_id' => $activationRequest->id,
                'company_id' => $company->id,
                'subscriber_id' => $company->subscriber_id,
                'subscription_id' => $sub->id,
                'subscription_status' => $sub->status,
                'service_id' => $service->id,
            ], ['user_id' => $user->id]);

            return back()->with('error', 'Este servicio ya está activo en tu suscripción.');
        }

        // ✅ Toggle en pivot activation_request_service
        $result = DB::transaction(function () use ($activationRequest, $service) {
            $row = DB::table('activation_request_service')
                ->where('activation_request_id', $activationRequest->id)
                ->where('service_id', $service->id)
                ->first();

            if (!$row) {
                DB::table('activation_request_service')->insert([
                    'activation_request_id' => $activationRequest->id,
                    'service_id' => $service->id,
                    'status' => 'pending',
                    'meta' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['action' => 'created', 'old_status' => null, 'new_status' => 'pending'];
            }

            $current = strtolower((string) ($row->status ?? ''));
            $new = $current === 'cancelled' ? 'pending' : 'cancelled';

            DB::table('activation_request_service')
                ->where('id', $row->id)
                ->update([
                    'status' => $new,
                    'updated_at' => now(),
                ]);

            return ['action' => 'toggled', 'old_status' => $current, 'new_status' => $new];
        });

        // ✅ Audit fuera de la transacción
        $event = match ($result['new_status'] ?? '') {
            'pending' => ($result['old_status'] === 'cancelled') ? 'service_request_reactivated' : 'service_request_created',
            'cancelled' => 'service_request_cancelled',
            default => 'service_request_updated',
        };

        AuditService::log($event, $service, [
            'user_id' => $user->id,
            'activation_request_id' => $activationRequest->id,
            'company_id' => $company->id,
            'subscriber_id' => $company->subscriber_id,
            'subscription_id' => $sub->id,
            'subscription_status' => $sub->status,
            'service_id' => $service->id,
            'service_slug' => $service->slug,
            'service_title' => $service->title,
            'old_status' => $result['old_status'] ?? null,
            'new_status' => $result['new_status'] ?? null,
        ], ['user_id' => $user->id]);

        $msg = match ($result['new_status'] ?? '') {
            'pending' => 'Servicio solicitado.',
            'cancelled' => 'Solicitud cancelada.',
            default => 'Solicitud de servicio actualizada.',
        };

        return back()->with('success', $msg);
    }

    private function resolveCompany($user): ?Company
    {
        $company = null;

        if (!empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }

        if (!$company) {
            $company = Company::query()->where('owner_user_id', $user->id)->first();
        }

        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()->where('subscriber_id', $user->subscriber_id)->first();
        }

        return $company;
    }

    /**
     * ✅ Misma regla que EnsureActiveSubscription
     */
    private function subscriptionAllowsSelection(Subscription $sub): bool
    {
        $now = now();

        if ($sub->status === 'active') {
            if (!is_null($sub->ends_at) && $sub->ends_at < $now) return false;
            if (!is_null($sub->current_period_end) && $sub->current_period_end < $now) return false;
            return true;
        }

        if ($sub->status === 'trialing') {
            if (!is_null($sub->ends_at) && $sub->ends_at < $now) return false;
            if (!is_null($sub->trial_ends_at) && $sub->trial_ends_at < $now) return false;
            return true;
        }

        return false;
    }
}
