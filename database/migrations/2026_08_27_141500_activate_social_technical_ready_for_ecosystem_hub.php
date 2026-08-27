<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $service = DB::table('services')
                ->where('service_key', 'social')
                ->lockForUpdate()
                ->first();

            if (! $service) {
                throw new \RuntimeException(
                    'Service social no existe.'
                );
            }

            if (
                (bool) $service->active
                || (bool) $service->billable
                || $service->launch_mode !== 'external'
                || $service->integration_mode !== 'sso'
                || rtrim(
                    (string) $service->external_url,
                    '/'
                ) !== 'https://social.laudaapi.com'
                || (string) $service->launch_path !== '/launch'
                || $service->monthly_price !== null
                || $service->yearly_price !== null
            ) {
                throw new \RuntimeException(
                    'Social no coincide con el staging '
                    .'certificado para activación técnica.'
                );
            }

            $config = json_decode(
                (string) ($service->config ?? ''),
                true
            );

            if (! is_array($config)) {
                $config = [];
            }

            $config['operational_ready'] = true;
            $config['ecosystem_hub_stage'] = false;
            $config['technical_readiness'] =
                'social_sso_e2e_certified';
            $config['commercial_readiness'] =
                'pricing_required_before_billing';
            $config['activation_authority'] =
                'laudaapi_central';
            $config['individual_activation_supported'] =
                true;
            $config['transformation_360_supported'] =
                true;

            DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'active' => true,
                    'billable' => false,
                    'badge' => 'Disponible',
                    'config' => json_encode(
                        $config,
                        JSON_UNESCAPED_SLASHES
                    ),
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $service = DB::table('services')
                ->where('service_key', 'social')
                ->lockForUpdate()
                ->first();

            if (! $service) {
                return;
            }

            $activeItems = DB::table(
                'subscription_items'
            )
                ->where(
                    'service_id',
                    $service->id
                )
                ->whereIn(
                    'status',
                    ['active', 'trialing']
                )
                ->exists();

            if ($activeItems) {
                throw new \RuntimeException(
                    'No se puede desactivar Social: '
                    .'existen entitlements activos.'
                );
            }

            $config = json_decode(
                (string) ($service->config ?? ''),
                true
            );

            if (! is_array($config)) {
                $config = [];
            }

            $config['operational_ready'] = false;
            $config['ecosystem_hub_stage'] = true;
            unset($config['technical_readiness']);
            $config['commercial_readiness'] =
                'pricing_and_sso_validation_required';

            DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'active' => false,
                    'billable' => false,
                    'badge' =>
                        'Integración en preparación',
                    'config' => json_encode(
                        $config,
                        JSON_UNESCAPED_SLASHES
                    ),
                    'updated_at' => now(),
                ]);
        });
    }
};
