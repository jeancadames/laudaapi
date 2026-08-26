<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionBundleDiscountApplication;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Support\Facades\DB;

class SubscriptionTotalsService
{
    public function recalculate(
        Subscription $subscription
    ): Subscription {
        return DB::transaction(
            function () use (
                $subscription
            ): Subscription {
                $locked = Subscription::query()
                    ->whereKey(
                        $subscription->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $subtotal = round(
                    (float) DB::table(
                        'subscription_items'
                    )
                        ->where(
                            'subscription_id',
                            $locked->id
                        )
                        ->whereIn(
                            'status',
                            ServiceEntitlementPolicy::ITEM_STATUSES
                        )
                        ->sum('amount'),
                    2
                );

                $bundle = app(
                    BundleDiscountEngine::class
                )->quote(
                    $locked
                );

                $discount = round(
                    min(
                        $subtotal,
                        max(
                            0,
                            (float) $bundle[
                                'discount_amount'
                            ]
                        )
                    ),
                    2
                );

                /*
                 * Mientras existan items activos/trialing, Paso 7 conserva
                 * el tax_amount persistido. Si ya no existe base recurrente,
                 * el impuesto residual también debe quedar en cero.
                 */
                $tax = $subtotal <= 0
                    ? 0.0
                    : round(
                        max(
                            0,
                            (float) (
                                $locked->tax_amount
                                ?? 0
                            )
                        ),
                        2
                    );

                $total = round(
                    max(
                        0,
                        $subtotal
                        - $discount
                        + $tax
                    ),
                    2
                );

                $meta = is_array(
                    $locked->meta
                )
                    ? $locked->meta
                    : [];

                if ($bundle['matched']) {
                    $meta['bundle_discount'] =
                        $bundle['snapshot'];
                } else {
                    unset(
                        $meta['bundle_discount']
                    );
                }

                $locked->forceFill([
                    'subtotal_amount' =>
                        $subtotal,
                    'discount_amount' =>
                        $discount,
                    'tax_amount' =>
                        $tax,
                    'total_amount' =>
                        $total,
                    'meta' => $meta,
                ])->save();

                $this->syncApplication(
                    $locked,
                    $bundle
                );

                return $locked->fresh();
            }, 3);
    }

    private function syncApplication(
        Subscription $subscription,
        array $bundle
    ): void {
        $current = SubscriptionBundleDiscountApplication::query()
            ->where(
                'subscription_id',
                $subscription->id
            )
            ->where('active', true)
            ->lockForUpdate()
            ->first();

        if (! $bundle['matched']) {
            if ($current) {
                $current->forceFill([
                    'active' => false,
                    'superseded_at' => now(),
                ])->save();
            }

            return;
        }

        $snapshot =
            $bundle['snapshot'];

        $fingerprint = hash(
            'sha256',
            json_encode(
                [
                    'rule_id' =>
                        $bundle['rule_id'],
                    'bundle_service_id' =>
                        $bundle[
                            'bundle_service_id'
                        ],
                    'bundle_base_amount' =>
                        $bundle[
                            'bundle_base_amount'
                        ],
                    'discount_amount' =>
                        $bundle[
                            'discount_amount'
                        ],
                    'currency' =>
                        $bundle['currency'],
                    'matched_service_ids' =>
                        $snapshot[
                            'matched_service_ids'
                        ]
                        ?? [],
                    'pricing_version' =>
                        $bundle[
                            'pricing_version'
                        ],
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        if (
            $current
            && hash_equals(
                (string) $current->fingerprint,
                $fingerprint
            )
        ) {
            return;
        }

        if ($current) {
            $current->forceFill([
                'active' => false,
                'superseded_at' => now(),
            ])->save();
        }

        SubscriptionBundleDiscountApplication::query()
            ->create([
                'subscription_id' =>
                    $subscription->id,
                'rule_id' =>
                    $bundle['rule_id'],
                'bundle_service_id' =>
                    $bundle[
                        'bundle_service_id'
                    ],
                'bundle_base_amount' =>
                    $bundle[
                        'bundle_base_amount'
                    ],
                'discount_amount' =>
                    $bundle[
                        'discount_amount'
                    ],
                'currency' =>
                    $bundle['currency'],
                'matched_service_ids' =>
                    $snapshot[
                        'matched_service_ids'
                    ]
                    ?? [],
                'fingerprint' =>
                    $fingerprint,
                'snapshot' =>
                    $snapshot,
                'active' => true,
                'applied_at' => now(),
                'superseded_at' => null,
            ]);
    }
}
