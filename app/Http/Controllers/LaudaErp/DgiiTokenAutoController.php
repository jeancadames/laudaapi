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
        // ✅ acepta 0/1, "0"/"1", true/false
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

        /** @var DgiiCompanySetting $setting */
        $setting = DgiiCompanySetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'environment' => 'precert',
                'cf_prefix' => 'testecf',
                'use_directory' => true,
                'endpoints' => null,
                'meta' => null,
                'dgii_token_auto' => false, // default manual
                'dgii_token_refresh_before_seconds' => 0,
            ]
        );

        $wasAuto = (bool) $setting->dgii_token_auto;

        // ✅ update flag
        $setting->update([
            'dgii_token_auto' => $enabled,
        ]);

        /** @var DgiiTokenManager $tm */
        $tm = app(DgiiTokenManager::class);

        // ✅ OFF -> ON: genera token inmediatamente
        if (!$wasAuto && $enabled) {
            try {
                // si aún no agregaste ensureValidToken(), usa getValidToken() + invalidateToken()
                // Recomendado: force => invalida primero
                $tm->invalidateToken($setting->fresh());
                $tm->getValidToken($setting->fresh(), 90);

                return back()->with('success', 'Modo automático activado. Token generado.');
            } catch (\Throwable $e) {
                // El manager ya guarda last_error, pero dejamos esto por si falla antes de entrar al lock
                $setting->fresh()->update([
                    'dgii_token_last_error' => mb_substr($e->getMessage(), 0, 255),
                    'dgii_token_last_requested_at' => now(),
                ]);

                return back()->with('error', 'Se activó el modo automático, pero falló la generación del token: ' . $e->getMessage());
            }
        }

        // ✅ ON -> OFF (opcional pero recomendado): invalidar token para forzar manual real
        if ($wasAuto && !$enabled) {
            $tm->invalidateToken($setting->fresh());
        }

        return back()->with('success', 'Modo automático de token actualizado.');
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
