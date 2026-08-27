<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\ActivationRequestService;
use App\Models\Company;
use App\Models\Service;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriberServiceRequestController extends Controller
{
    public function toggle(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return back()->with('error', 'No autenticado.');
        }

        $data = $request->validate([
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
        ]);

        $serviceId = (int) $data['service_id'];

        $service = Service::query()
            ->select(['id', 'slug', 'title', 'active'])
            ->findOrFail($serviceId);

        $activationRequest = ActivationRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ActivationRequest::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        if (! $activationRequest) {
            return back()->with(
                'error',
                'Debes tener una solicitud de activación '
                .'antes de solicitar servicios.'
            );
        }

        $company = $this->resolveCompany($user);

        if (! $company || ! $company->subscriber_id) {
            return back()->with(
                'error',
                'No tienes compañía/suscriptor asignado.'
            );
        }

        if (! $service->active) {
            return back()->with(
                'error',
                'Este servicio no está disponible.'
            );
        }

        /*
         * No pre-gate Subscription.
         * Solo bloquea si ya existe entitlement real.
         */
        $alreadyActive = SubscriptionItem::query()
            ->where('service_id', $serviceId)
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->whereHas(
                'subscription',
                function ($query) use ($company) {
                    $query
                        ->where(
                            'subscriber_id',
                            $company->subscriber_id
                        )
                        ->whereIn(
                            'status',
                            ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES
                        );
                }
            )
            ->exists();

        if ($alreadyActive) {
            return back()->with(
                'error',
                'Este servicio ya está activo.'
            );
        }

        $currentRow = ActivationRequestService::query()
            ->where(
                'activation_request_id',
                $activationRequest->id
            )
            ->where('service_id', $serviceId)
            ->first();

        if (
            $currentRow
            && strtolower((string) $currentRow->status) === 'pending_payment'
        ) {
            return back()->with(
                'error',
                'La solicitud ya tiene un checkout pendiente '
                .'de pago y no puede modificarse desde el catálogo.'
            );
        }

        $result = DB::transaction(function () use (
            $activationRequest,
            $serviceId
        ): array {
            $row = ActivationRequestService::query()
                ->where(
                    'activation_request_id',
                    $activationRequest->id
                )
                ->where('service_id', $serviceId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                ActivationRequestService::query()->create([
                    'activation_request_id' => $activationRequest->id,
                    'service_id' => $serviceId,
                    'status' => 'pending',
                    'meta' => json_encode([
                        'source' => 'subscriber_service_request',
                        'entitlement_granted' => false,
                    ]),
                ]);

                return [
                    'old_status' => null,
                    'new_status' => 'pending',
                ];
            }

            $current = strtolower((string) $row->status);

            if ($current === 'pending_payment') {
                throw new \RuntimeException(
                    'La solicitud ya tiene un checkout pendiente de pago.'
                );
            }

            if (! in_array($current, ['pending', 'cancelled'], true)) {
                throw new \RuntimeException(
                    "Estado de solicitud no modificable: {$current}."
                );
            }

            $newStatus = $current === 'cancelled'
                ? 'pending'
                : 'cancelled';

            $row->status = $newStatus;
            $row->save();

            return [
                'old_status' => $current,
                'new_status' => $newStatus,
            ];
        }, 3);

        $event = match ($result['new_status']) {
            'pending' =>
                $result['old_status'] === 'cancelled'
                    ? 'service_request_reactivated'
                    : 'service_request_created',
            'cancelled' => 'service_request_cancelled',
            default => 'service_request_updated',
        };

        AuditService::log(
            $event,
            $service,
            [
                'user_id' => (int) $user->id,
                'activation_request_id' => (int) $activationRequest->id,
                'company_id' => (int) $company->id,
                'subscriber_id' => (int) $company->subscriber_id,
                'service_id' => (int) $service->id,
                'old_status' => $result['old_status'],
                'new_status' => $result['new_status'],
                'standalone_without_subscription_pre_gate' => true,
            ],
            ['user_id' => (int) $user->id]
        );

        return back()->with(
            'success',
            $result['new_status'] === 'pending'
                ? 'Servicio solicitado.'
                : 'Solicitud cancelada.'
        );
    }

    private function resolveCompany($user): ?Company
    {
        $company = null;

        if (! empty($user->company_id)) {
            $company = Company::query()->find($user->company_id);
        }

        if (! $company) {
            $company = Company::query()
                ->where('owner_user_id', $user->id)
                ->first();
        }

        if (! $company && ! empty($user->subscriber_id)) {
            $company = Company::query()
                ->where('subscriber_id', $user->subscriber_id)
                ->first();
        }

        return $company;
    }
}
