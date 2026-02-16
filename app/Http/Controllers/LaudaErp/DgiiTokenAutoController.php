<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCompanySetting;
use App\Services\Dgii\DgiiTokenManager;
use App\Services\Entitlements\SubscriberEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DgiiTokenAutoController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $request->boolean('enabled');

        $user = $request->user();
        abort_unless($user, 401);

        $subscriberId = $this->resolveSubscriberId($user);
        abort_unless($subscriberId && $subscriberId > 0, 403);

        /** @var SubscriberEntitlements $entitlements */
        $entitlements = app(SubscriberEntitlements::class);
        $details = $entitlements->dgiiCapabilitiesDetails((int) $subscriberId);

        abort_unless(($details['enabled'] ?? false) === true, 403);
        abort_if(($details['enabled_by_item_status'] ?? null) === 'pending', 403, 'Servicio pendiente de pago.');

        $company = Company::query()
            ->select(['id'])
            ->when(
                !empty($user->company_id),
                fn($q) => $q->where('id', (int) $user->company_id),
                fn($q) => $q->where('subscriber_id', (int) $subscriberId)->orderByDesc('id')
            )
            ->firstOrFail();

        /** @var DgiiTokenManager $tm */
        $tm = app(DgiiTokenManager::class);

        try {
            DB::transaction(function () use ($company, $enabled, $tm, &$setting, &$wasAuto) {
                /** @var DgiiCompanySetting $setting */
                $setting = DgiiCompanySetting::query()->firstOrCreate(
                    ['company_id' => $company->id],
                    [
                        'environment' => 'precert',
                        // ✅ importante: cf_prefix debe ser DGII-native: testecf/certecf/ecf
                        // si ya estás mapeando en HttpDgiiAuthClient, puedes dejarlo, pero esto ayuda.
                        'cf_prefix' => 'testecf',
                        'use_directory' => true,
                        'endpoints' => null,
                        'meta' => null,
                        'dgii_token_auto' => false,
                        'dgii_token_refresh_before_seconds' => 0,
                    ]
                );

                $wasAuto = (bool) $setting->dgii_token_auto;

                // ✅ actualizar flag
                $setting->update([
                    'dgii_token_auto' => $enabled,
                ]);

                $setting = $setting->fresh();

                // ✅ OFF -> ON: genera token inmediatamente (y si falla, lanzamos excepción para rollback)
                if (!$wasAuto && $enabled) {
                    $tm->invalidateToken($setting);
                    $tm->getValidToken($setting, 90);
                }

                // ✅ ON -> OFF: opcional invalidar para forzar manual real
                if ($wasAuto && !$enabled) {
                    $tm->invalidateToken($setting);
                }
            });

            if (!$wasAuto && $enabled) {
                return back()->with('success', 'Modo automático activado. Token generado.');
            }

            return back()->with('success', 'Modo automático de token actualizado.');
        } catch (\Throwable $e) {
            // ✅ si falló al activar ON, revierte a OFF (seguro aunque la tx haya rollback)
            try {
                $s = DgiiCompanySetting::query()->where('company_id', $company->id)->first();
                if ($s) {
                    $s->update([
                        'dgii_token_auto' => false,
                        'dgii_token_last_error' => mb_substr($e->getMessage(), 0, 255),
                        'dgii_token_last_requested_at' => now(),
                    ]);
                }
            } catch (\Throwable $ignored) {
            }

            $msg = mb_substr($e->getMessage(), 0, 180);

            return back()->with('error', "No se pudo generar el token al activar automático: {$msg}");
        }
    }
    private function resolveSubscriberId($user): ?int
    {
        $sid = (int) ($user->subscriber_id ?? 0);
        if ($sid > 0) return $sid;

        $sid = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        if ($sid > 0) return $sid;

        if (!empty($user->company_id)) {
            $sid = (int) Company::query()
                ->where('id', (int) $user->company_id)
                ->value('subscriber_id');

            if ($sid > 0) return $sid;
        }

        $sid = (int) Company::query()
            ->where('owner_user_id', $user->id)
            ->value('subscriber_id');

        return $sid > 0 ? $sid : null;
    }
}
