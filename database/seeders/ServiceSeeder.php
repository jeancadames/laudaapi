<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::transaction(function () use ($now) {

            $upsert = function (array $data) use ($now): int {
                if (empty($data['slug'])) {
                    throw new \InvalidArgumentException('ServiceSeeder: slug is required');
                }

                $slug = $data['slug'];

                $defaults = [
                    'short_description' => null,
                    'description'       => null,

                    'service_key'       => null,

                    'href'              => null,
                    'launch_mode'       => 'internal',
                    'external_url'      => null,
                    'launch_path'       => null,
                    'integration_mode'  => 'none',
                    'is_standalone'     => false,

                    'roles'             => json_encode(['admin', 'subscriber']),
                    'required_plan'     => null,

                    'icon'              => null,
                    'badge'             => null,
                    'category'          => null,

                    'parent_id'         => null,
                    'type'              => 'addon',

                    'billable'          => true,
                    'billing_model'     => 'flat',
                    'currency'          => 'USD',
                    'monthly_price'     => null,
                    'yearly_price'      => null,

                    'block_size'        => null,
                    'included_units'    => null,
                    'unit_name'         => null,
                    'overage_unit_price' => null,

                    'config'            => null,

                    'active'            => true,
                    'sort_order'        => 0,

                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                $payload = array_merge($defaults, $data);

                DB::table('services')->updateOrInsert(
                    ['slug' => $slug],
                    $payload
                );

                return (int) DB::table('services')->where('slug', $slug)->value('id');
            };

            /*
            |--------------------------------------------------------------------------
            | PADRE 1: API Facturación Electrónica
            |--------------------------------------------------------------------------
            */
            $apiFacturacionId = $upsert([
                'title'             => 'API Facturación Electrónica',
                'short_description' => 'Emisión, validación y automatización de comprobantes electrónicos.',
                'description'       => 'API para emisión de comprobantes electrónicos.',
                'slug'              => 'api-facturacion-electronica',
                'service_key'       => 'api_facturacion_electronica',

                'href'              => '/erp/services/api-facturacion',
                'launch_mode'       => 'internal',
                'integration_mode'  => 'none',
                'is_standalone'     => false,

                'icon'              => 'file-text',
                'badge'             => null,
                'category'          => 'api-hub',

                'type'              => 'addon',
                'billable'          => true,
                'billing_model'     => 'flat',
                'currency'          => 'USD',
                'monthly_price'     => 29.00,
                'yearly_price'      => 290.00,

                'sort_order'        => 10,
                'config'            => json_encode([
                    'is_external_app' => false,
                    'supports_erp' => true,
                    'supports_api' => true,
                ], JSON_UNESCAPED_SLASHES),
            ]);

            $facturacionChildren = [
                [
                    'title'             => 'Certificación Emisor Electrónico',
                    'short_description' => 'Soporte para certificación y habilitación como emisor.',
                    'slug'              => 'certificacion-emisor-electronico',
                    'service_key'       => 'certificacion_emisor_electronico',
                    'href'              => '/erp/services/certificacion-emisor',
                    'icon'              => 'shield-check',
                    'sort_order'        => 11,
                ],
                [
                    'title'             => 'API Facturación Electrónica',
                    'short_description' => 'Endpoints para emitir, enviar y consultar documentos.',
                    'slug'              => 'api-facturacion',
                    'service_key'       => 'api_facturacion',
                    'href'              => '/erp/services/api-facturacion/electronica',
                    'icon'              => 'file',
                    'sort_order'        => 12,
                ],
                [
                    'title'             => 'Calendario Fiscal',
                    'short_description' => 'Alertas y recordatorios de fechas y obligaciones fiscales.',
                    'slug'              => 'calendario-fiscal',
                    'service_key'       => 'calendario_fiscal',
                    'href'              => '/erp/services/calendario-fiscal',
                    'icon'              => 'calendar',
                    'sort_order'        => 13,
                ],
                [
                    'title'             => 'Cumplimiento Fiscal',
                    'short_description' => 'Validaciones y control para reducir riesgos y rechazos.',
                    'slug'              => 'cumplimiento-fiscal',
                    'service_key'       => 'cumplimiento_fiscal',
                    'href'              => '/erp/services/cumplimiento-fiscal',
                    'icon'              => 'check-circle',
                    'sort_order'        => 14,
                ],
            ];

            foreach ($facturacionChildren as $child) {
                $upsert([
                    'title'             => $child['title'],
                    'short_description' => $child['short_description'],
                    'slug'              => $child['slug'],
                    'service_key'       => $child['service_key'],
                    'href'              => $child['href'],
                    'icon'              => $child['icon'],

                    'parent_id'         => $apiFacturacionId,
                    'category'          => 'api-hub',

                    'type'              => 'addon',
                    'launch_mode'       => 'internal',
                    'integration_mode'  => 'none',
                    'is_standalone'     => false,

                    'billable'          => true,
                    'billing_model'     => 'flat',

                    'sort_order'        => $child['sort_order'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | PADRE 2: Marketplace
            |--------------------------------------------------------------------------
            */
            $marketplaceId = $upsert([
                'title'             => 'Marketplace',
                'short_description' => 'Catálogo modular de apps y módulos del ecosistema.',
                'description'       => 'Marketplace modular: módulos, integraciones y más.',
                'slug'              => 'marketplace',
                'service_key'       => 'marketplace',

                'href'              => '/marketplace',
                'launch_mode'       => 'internal',
                'integration_mode'  => 'none',
                'is_standalone'     => false,

                'icon'              => 'shopping-cart',
                'badge'             => 'MODULAR',
                'category'          => 'marketplace',

                'type'              => 'core',
                'billable'          => false,

                'sort_order'        => 20,
            ]);

            $marketplaceChildren = [
                [
                    'title' => 'CRM',
                    'short_description' => 'Gestión de clientes, contactos y seguimiento.',
                    'slug' => 'erp-crm',
                    'service_key' => 'erp_crm',
                    'href' => '/erp/crm',
                    'icon' => 'users',
                    'sort_order' => 21,
                ],
                [
                    'title' => 'Task Manager',
                    'short_description' => 'Tareas, asignaciones y control operativo.',
                    'slug' => 'erp-tasks',
                    'service_key' => 'erp_tasks',
                    'href' => '/erp/tasks',
                    'icon' => 'check-square',
                    'sort_order' => 22,
                ],
                [
                    'title' => 'Ventas Retail',
                    'short_description' => 'Ventas retail, POS y facturación rápida.',
                    'slug' => 'erp-sales-retail',
                    'service_key' => 'erp_sales_retail',
                    'href' => '/erp/sales/retail',
                    'icon' => 'shopping-bag',
                    'sort_order' => 23,
                ],
                [
                    'title' => 'Ventas Mayoristas',
                    'short_description' => 'Ventas por volumen, listas y condiciones.',
                    'slug' => 'erp-sales-wholesale',
                    'service_key' => 'erp_sales_wholesale',
                    'href' => '/erp/sales/wholesale',
                    'icon' => 'truck',
                    'sort_order' => 24,
                ],
                [
                    'title' => 'Foodshop',
                    'short_description' => 'Operación para food & beverage y delivery.',
                    'slug' => 'erp-foodshop',
                    'service_key' => 'erp_foodshop',
                    'href' => '/erp/foodshop',
                    'icon' => 'coffee',
                    'sort_order' => 25,
                ],
                [
                    'title' => 'Kioskos',
                    'short_description' => 'Kioskos y autoservicio para puntos de venta.',
                    'slug' => 'erp-kiosks',
                    'service_key' => 'erp_kiosks',
                    'href' => '/erp/kiosks',
                    'icon' => 'grid',
                    'sort_order' => 26,
                ],
                [
                    'title' => 'Servicios',
                    'short_description' => 'Gestión de servicios y órdenes de trabajo.',
                    'slug' => 'erp-services',
                    'service_key' => 'erp_services',
                    'href' => '/erp/services',
                    'icon' => 'tool',
                    'sort_order' => 27,
                ],
                [
                    'title' => 'Proyectos',
                    'short_description' => 'Planificación, costos y seguimiento.',
                    'slug' => 'erp-projects',
                    'service_key' => 'erp_projects',
                    'href' => '/erp/projects',
                    'icon' => 'briefcase',
                    'sort_order' => 28,
                ],
                [
                    'title' => 'Transporte del Personal',
                    'short_description' => 'Rutas, asignaciones y control operativo para empresas que ofrecen este servicio.',
                    'slug' => 'erp-transport',
                    'service_key' => 'erp_transport',
                    'href' => '/erp/transport',
                    'icon' => 'truck',
                    'sort_order' => 29,
                ],
                [
                    'title' => 'Venta de Vehículos',
                    'short_description' => 'Ventas de vehículos y gestión de inventario.',
                    'slug' => 'erp-car-sales',
                    'service_key' => 'erp_car_sales',
                    'href' => '/erp/car-sales',
                    'icon' => 'car',
                    'sort_order' => 30,
                ],
                [
                    'title' => 'Préstamos',
                    'short_description' => 'Préstamos, cuotas y cobranza.',
                    'slug' => 'erp-loans',
                    'service_key' => 'erp_loans',
                    'href' => '/erp/loans',
                    'icon' => 'dollar-sign',
                    'sort_order' => 31,
                ],
                [
                    'title' => 'Eventos',
                    'short_description' => 'Agenda, actividades y coordinación.',
                    'slug' => 'erp-events',
                    'service_key' => 'erp_events',
                    'href' => '/erp/events',
                    'icon' => 'calendar',
                    'sort_order' => 32,
                ],
                [
                    'title' => 'Bienes y Servicios',
                    'short_description' => 'Registro de compras, gastos y bienes con trazabilidad.',
                    'slug' => 'erp-goods-services',
                    'service_key' => 'erp_goods_services',
                    'href' => '/erp/goods-services',
                    'icon' => 'layers',
                    'sort_order' => 33,
                ],
                [
                    'title' => 'Recursos Humanos',
                    'short_description' => 'Nómina, asistencia y expedientes.',
                    'slug' => 'erp-hr',
                    'service_key' => 'erp_hr',
                    'href' => '/erp/hr',
                    'icon' => 'user-check',
                    'sort_order' => 34,
                ],
                [
                    'title' => 'Bancos',
                    'short_description' => 'Cuentas, conciliación y transacciones.',
                    'slug' => 'erp-banks',
                    'service_key' => 'erp_banks',
                    'href' => '/erp/banks',
                    'icon' => 'bank',
                    'sort_order' => 35,
                ],
                [
                    'title' => 'Contabilidad',
                    'short_description' => 'Asientos, mayor, reportes y cierres.',
                    'slug' => 'erp-accounting',
                    'service_key' => 'erp_accounting',
                    'href' => '/erp/accounting',
                    'icon' => 'book',
                    'sort_order' => 36,
                ],

                // ✅ LaudaGo externo
                [
                    'title' => 'LaudaGo',
                    'short_description' => 'Fuerza de ventas en ruta para CRM, cotizaciones, órdenes, facturas y cobros.',
                    'slug' => 'laudago',
                    'service_key' => 'laudago',
                    'href' => '/erp/services/open/laudago',
                    'external_url' => 'https://go.laudaapi.com',
                    'launch_path' => '/launch',
                    'icon' => 'map-pin',
                    'sort_order' => 37,
                    'launch_mode' => 'external',
                    'integration_mode' => 'sso',
                    'is_standalone' => true,
                    'type' => 'addon',
                    'config' => json_encode([
                        'modes' => ['corporate', 'ranchero'],
                        'requires_launch_token' => true,
                        'supports_facturacion_api' => true,
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ];

            foreach ($marketplaceChildren as $child) {
                $upsert([
                    'title'             => $child['title'],
                    'short_description' => $child['short_description'],
                    'slug'              => $child['slug'],
                    'service_key'       => $child['service_key'] ?? null,

                    'href'              => $child['href'],
                    'external_url'      => $child['external_url'] ?? null,
                    'launch_path'       => $child['launch_path'] ?? null,
                    'launch_mode'       => $child['launch_mode'] ?? 'internal',
                    'integration_mode'  => $child['integration_mode'] ?? 'none',
                    'is_standalone'     => $child['is_standalone'] ?? false,

                    'icon'              => $child['icon'],
                    'category'          => 'marketplace',
                    'parent_id'         => $marketplaceId,

                    'type'              => $child['type'] ?? 'addon',
                    'billable'          => true,
                    'billing_model'     => 'flat',

                    'config'            => $child['config'] ?? null,
                    'sort_order'        => $child['sort_order'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | PADRE 3: LaudaOne
            |--------------------------------------------------------------------------
            */
            $laudaOneId = $upsert([
                'title'             => 'LaudaOne',
                'short_description' => 'Plataforma ecommerce B2C y B2B integrada al ecosistema Lauda.',
                'description'       => 'Plataforma LaudaOne: ecommerce B2C y B2B.',
                'slug'              => 'laudaone',
                'service_key'       => 'laudaone',

                'href'              => '/erp/services/open/laudaone',
                'external_url'      => 'https://one.laudaapi.com',
                'launch_path'       => '/launch',
                'launch_mode'       => 'external',
                'integration_mode'  => 'sso',
                'is_standalone'     => true,

                'icon'              => 'shopping-bag',
                'badge'             => 'PLATFORM',
                'category'          => 'platform',

                'type'              => 'core',
                'billable'          => true,
                'billing_model'     => 'flat',

                'sort_order'        => 5,
                'config'            => json_encode([
                    'modes' => ['b2c', 'b2b'],
                    'requires_launch_token' => true,
                    'supports_facturacion_api' => true,
                    'supports_inventory_sync' => true,
                    'supports_orders_sync' => true,
                ], JSON_UNESCAPED_SLASHES),
            ]);

            $laudaOneChildren = [
                [
                    'title'             => 'Ecommerce B2C (LaudaOne)',
                    'short_description' => 'Tienda online B2C con catálogo y checkout.',
                    'slug'              => 'laudaone-ecommerce-b2c',
                    'service_key'       => 'laudaone_b2c',
                    'href'              => '/erp/services/open/laudaone/b2c',
                    'icon'              => 'shopping-bag',
                    'sort_order'        => 6,
                ],
                [
                    'title'             => 'Ecommerce B2B (LaudaOne)',
                    'short_description' => 'Canal B2B con precios por cliente y crédito.',
                    'slug'              => 'laudaone-ecommerce-b2b',
                    'service_key'       => 'laudaone_b2b',
                    'href'              => '/erp/services/open/laudaone/b2b',
                    'icon'              => 'truck',
                    'sort_order'        => 7,
                ],
            ];

            foreach ($laudaOneChildren as $child) {
                $upsert([
                    'title'             => $child['title'],
                    'short_description' => $child['short_description'],
                    'slug'              => $child['slug'],
                    'service_key'       => $child['service_key'],
                    'href'              => $child['href'],
                    'icon'              => $child['icon'],

                    'parent_id'         => $laudaOneId,
                    'category'          => 'platform',

                    'type'              => 'addon',
                    'launch_mode'       => 'internal',
                    'integration_mode'  => 'none',
                    'is_standalone'     => false,

                    'billable'          => true,
                    'billing_model'     => 'flat',

                    'sort_order'        => $child['sort_order'],
                ]);
            }

            // Limpieza histórica
            DB::table('services')
                ->where('slug', 'laudaone-services-api-facturacion')
                ->delete();
        });
    }
}
