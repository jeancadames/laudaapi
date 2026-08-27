<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->assertLegacyCrmIsUntouched();

            $this->insertStagedExternalService([
                'title' => 'Social',
                'short_description' =>
                    'Contenido, interacciones, inbox, leads y analítica social.',
                'description' =>
                    'Solución Social independiente del ecosistema LAUDAAPI.',
                'slug' => 'social',
                'service_key' => 'social',
                'external_url' =>
                    'https://social.laudaapi.com',
                'launch_path' => '/launch',
                'icon' => 'message-circle',
                'sort_order' => 20,
            ]);

            $this->insertStagedExternalService([
                'title' => 'CRM',
                'short_description' =>
                    'Clientes, leads, oportunidades y seguimiento comercial.',
                'description' =>
                    'Solución CRM independiente del ecosistema LAUDAAPI.',
                'slug' => 'crm',
                'service_key' => 'crm',
                'external_url' =>
                    'https://crm.laudaapi.com',
                'launch_path' => '/launch',
                'icon' => 'users',
                'sort_order' => 30,
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (['social', 'crm'] as $serviceKey) {
                $row = DB::table('services')
                    ->where('service_key', $serviceKey)
                    ->first(['id', 'config']);

                if (! $row) {
                    continue;
                }

                $config = json_decode(
                    (string) ($row->config ?? ''),
                    true
                );

                if (
                    ! is_array($config)
                    || ($config['ecosystem_hub_stage'] ?? false)
                        !== true
                ) {
                    continue;
                }

                if (
                    Schema::hasTable('subscription_items')
                    && DB::table('subscription_items')
                        ->where('service_id', $row->id)
                        ->exists()
                ) {
                    throw new RuntimeException(
                        "No se puede revertir {$serviceKey}: "
                        ."ya tiene SubscriptionItems."
                    );
                }

                DB::table('services')
                    ->where('id', $row->id)
                    ->delete();
            }
        });
    }

    private function assertLegacyCrmIsUntouched(): void
    {
        $legacy = DB::table('services')
            ->where('service_key', 'erp_crm')
            ->first([
                'id',
                'launch_mode',
                'active',
            ]);

        if (
            ! $legacy
            || $legacy->launch_mode !== 'internal'
            || ! (bool) $legacy->active
        ) {
            throw new RuntimeException(
                'CRM legacy erp_crm no coincide con el contrato esperado.'
            );
        }
    }

    private function insertStagedExternalService(
        array $data
    ): void {
        if (
            DB::table('services')
                ->where('service_key', $data['service_key'])
                ->exists()
        ) {
            throw new RuntimeException(
                "Ya existe Service {$data['service_key']}."
            );
        }

        if (
            DB::table('services')
                ->where('slug', $data['slug'])
                ->exists()
        ) {
            throw new RuntimeException(
                "Ya existe slug {$data['slug']}."
            );
        }

        $now = now();

        DB::table('services')->insert([
            'title' => $data['title'],
            'short_description' =>
                $data['short_description'],
            'description' =>
                $data['description'],

            'slug' => $data['slug'],
            'service_key' =>
                $data['service_key'],

            'href' => null,
            'launch_mode' => 'external',
            'external_url' =>
                $data['external_url'],
            'launch_path' =>
                $data['launch_path'],
            'integration_mode' => 'sso',
            'is_standalone' => true,

            'roles' => json_encode(
                ['admin', 'subscriber'],
                JSON_UNESCAPED_SLASHES
            ),
            'required_plan' => null,

            'icon' => $data['icon'],
            'badge' => 'Integración en preparación',
            'category' => 'ecosystem-hub',

            'parent_id' => null,
            'type' => 'addon',

            /*
             * STAGED:
             * pricing aún no aprobado.
             * No activar billing hasta decisión comercial.
             */
            'billable' => false,
            'billing_model' => 'flat',
            'currency' => 'DOP',
            'monthly_price' => null,
            'yearly_price' => null,

            'block_size' => null,
            'included_units' => null,
            'unit_name' => null,
            'overage_unit_price' => null,

            'config' => json_encode([
                'ecosystem_hub_stage' => true,
                'operational_ready' => false,
                'commercial_readiness' =>
                    'pricing_and_sso_validation_required',
                'activation_authority' =>
                    'laudaapi_central',
                'individual_activation_supported' =>
                    true,
                'transformation_360_supported' =>
                    true,
            ], JSON_UNESCAPED_SLASHES),

            /*
             * Inactivo hasta que /launch + SSO
             * estén implementados y probados.
             */
            'active' => false,
            'sort_order' =>
                (int) $data['sort_order'],

            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
