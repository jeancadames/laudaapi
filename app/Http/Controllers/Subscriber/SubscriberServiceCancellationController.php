<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriber\CancelSubscriptionItemRequest;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\AuditService;
use App\Services\Billing\SubscriptionTotalsService;
use Illuminate\Support\Facades\DB;

class SubscriberServiceCancellationController extends Controller
{
    public function cancel(CancelSubscriptionItemRequest $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $company = $this->resolveCompany($user);

        if (!$company || !$company->subscriber_id) {
            return back()->with('error', 'No tienes compañía/suscriptor asignado.');
        }

        $subscriptionItemId = (int) $request->input('subscription_item_id');

        $item = SubscriptionItem::query()
            ->with(['subscription:id,subscriber_id'])
            ->find($subscriptionItemId);

        if (!$item) {
            return back()->with('error', 'El servicio activo (item) no existe.');
        }

        // ✅ Ownership: el item debe pertenecer al mismo subscriber de la compañía
        $sub = $item->subscription;
        if (!$sub || (int) $sub->subscriber_id !== (int) $company->subscriber_id) {
            return back()->with('error', 'Acción no permitida.');
        }

        // ✅ Solo cancelar si está activo o en trial
        if (!in_array($item->status, ['active', 'trialing'], true)) {
            return back()->with('error', 'Este servicio no se puede cancelar en su estado actual.');
        }

        /*
         * PASO 9E-C:
         * Subscription es el mutex de cancelación.
         * R2-J y cancelación usan el mismo orden Subscription → Item.
         */
        $result = DB::transaction(function () use ($item, $company) {
            $subscription = Subscription::query()
                ->whereKey($item->subscription_id)
                ->where(
                    'subscriber_id',
                    $company->subscriber_id
                )
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return [
                    'ok' => false,
                    'message' => 'Acción no permitida.',
                ];
            }

            $item = SubscriptionItem::query()
                ->whereKey($item->id)
                ->where(
                    'subscription_id',
                    $subscription->id
                )
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return [
                    'ok' => false,
                    'message' =>
                        'El servicio activo (item) no existe.',
                ];
            }

            if (! in_array(
                $item->status,
                ['active', 'trialing'],
                true
            )) {
                return [
                    'ok' => false,
                    'message' =>
                        'Este servicio no se puede cancelar en su estado actual.',
                ];
            }

            $oldStatus = $item->status;

            $item->status = 'cancelled';
            $item->save();

            app(SubscriptionTotalsService::class)
                ->recalculate(
                    $subscription
                );

            return [
                'ok' => true,
                'item' => $item->fresh(),
                'old_status' => $oldStatus,
            ];
        }, 3);

        if (! $result['ok']) {
            return back()->with(
                'error',
                $result['message']
            );
        }

        $item = $result['item'];
        $oldStatus = $result['old_status'];

        // ✅ AuditLog (tu sistema es manual, hay que llamarlo)
        AuditService::log(
            'subscriber.services.cancel',
            $item,
            [
                'company_id' => (int) $company->id,
                'subscriber_id' => (int) $company->subscriber_id,

                'subscription_item_id' => (int) $item->id,
                'subscription_id' => (int) $item->subscription_id,
                'service_id' => (int) $item->service_id,

                'from_status' => (string) $oldStatus,
                'to_status' => (string) $item->status,
            ],
            [
                // opcional: para asegurar user aunque sea job/console (en HTTP ya lo toma)
                'user_id' => (int) $user->id,
            ]
        );

        return back()->with('success', 'Servicio cancelado.');
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
}
