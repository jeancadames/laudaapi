<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                    'roles'             => json_encode(['admin', 'subscriber']),
                    'active'            => true,
                    'billable'          => true,
                    'billing_model'     => 'flat',
                    'currency'          => 'USD',
                    'sort_order'        => 0,

                    // ✅ NUEVO: descripción corta
                    'short_description' => null,

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
                'slug'              => 'api-facturacion-electronica',

                // ✅ TODO se ejecuta desde /erp/services/*
                'href'              => '/erp/services/api-facturacion',

                'icon'              => 'file-text',
                'badge'             => null,
                'parent_id'         => null,
                'type'              => 'addon',
                'monthly_price'     => 29.00,
                'yearly_price'      => 290.00,
                'description'       => 'API para emisión de comprobantes electrónicos.',
                'sort_order'        => 10,
            ]);

            $facturacionChildren = [
                [
                    'title'             => 'Certificación Emisor Electrónico',
                    'short_description' => 'Soporte para certificación y habilitación como emisor.',
                    'slug'              => 'certificacion-emisor-electronico', // ✅ NO tocar slug
                    'href'              => '/erp/services/certificacion-emisor', // ✅ ejecutar en ERP
                    'icon'              => 'shield-check',
                    'sort_order'        => 11,
                ],
                [
                    'title'             => 'API Facturación Electrónica',
                    'short_description' => 'Endpoints para emitir, enviar y consultar documentos.',
                    'slug'              => 'api-facturacion',
                    'href'              => '/erp/services/api-facturacion/electronica',
                    'icon'              => 'file',
                    'sort_order'        => 12,
                ],
                [
                    'title'             => 'Calendario Fiscal',
                    'short_description' => 'Alertas y recordatorios de fechas y obligaciones fiscales.',
                    'slug'              => 'calendario-fiscal',
                    'href'              => '/erp/services/calendario-fiscal',
                    'icon'              => 'calendar',
                    'sort_order'        => 13,
                ],
                [
                    'title'             => 'Cumplimiento Fiscal',
                    'short_description' => 'Validaciones y control para reducir riesgos y rechazos.',
                    'slug'              => 'cumplimiento-fiscal',
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
                    'href'              => $child['href'],
                    'icon'              => $child['icon'],
                    'parent_id'         => $apiFacturacionId,
                    'type'              => 'addon',
                    'monthly_price'     => null,
                    'yearly_price'      => null,
                    'description'       => null,
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
                'slug'              => 'marketplace',
                'href'              => '/marketplace',
                'icon'              => 'shopping-cart',
                'badge'             => 'MODULAR',
                'parent_id'         => null,
                'type'              => 'core',
                'description'       => 'Marketplace modular: módulos, integraciones y más.',
                'sort_order'        => 20,
            ]);

            $marketplaceChildren = [
                ['title' => 'CRM',                     'short_description' => 'Gestión de clientes, contactos y seguimiento.',                   'slug' => 'erp-crm',             'href' => '/erp/crm',             'icon' => 'users',        'sort_order' => 21],
                ['title' => 'Task Manager',            'short_description' => 'Tareas, asignaciones y control operativo.',                      'slug' => 'erp-tasks',           'href' => '/erp/tasks',           'icon' => 'check-square', 'sort_order' => 22],
                ['title' => 'Sales Retail',            'short_description' => 'Ventas retail, POS y facturación rápida.',                       'slug' => 'erp-sales-retail',    'href' => '/erp/sales/retail',    'icon' => 'shopping-bag', 'sort_order' => 23],
                ['title' => 'Sales Mayoristas',        'short_description' => 'Ventas por volumen, listas y condiciones.',                      'slug' => 'erp-sales-wholesale', 'href' => '/erp/sales/wholesale', 'icon' => 'truck',        'sort_order' => 24],
                ['title' => 'Foodshop',                'short_description' => 'Operación para food & beverage y delivery.',                      'slug' => 'erp-foodshop',        'href' => '/erp/foodshop',        'icon' => 'coffee',       'sort_order' => 25],
                ['title' => 'Kioskos',                 'short_description' => 'Kioscos y autoservicio para puntos de venta.',                    'slug' => 'erp-kiosks',          'href' => '/erp/kiosks',          'icon' => 'grid',         'sort_order' => 26],
                ['title' => 'Services',                'short_description' => 'Gestión de servicios y órdenes de trabajo.',                      'slug' => 'erp-services',        'href' => '/erp/services',        'icon' => 'tool',         'sort_order' => 27],
                ['title' => 'Proyectos',               'short_description' => 'Planificación, costos y seguimiento.',                            'slug' => 'erp-projects',        'href' => '/erp/projects',        'icon' => 'briefcase',    'sort_order' => 28],

                // ✅ AJUSTE 1
                ['title' => 'Transporte del Personal', 'short_description' => 'Rutas, asignaciones y control operativo para empresas que ofrecen este servicio.', 'slug' => 'erp-transport', 'href' => '/erp/transport', 'icon' => 'truck', 'sort_order' => 29],

                ['title' => 'Car Sales',               'short_description' => 'Ventas de vehículos y gestión de inventario.',                     'slug' => 'erp-car-sales',       'href' => '/erp/car-sales',       'icon' => 'car',          'sort_order' => 30],
                ['title' => 'Loans',                   'short_description' => 'Préstamos, cuotas y cobranza.',                                   'slug' => 'erp-loans',           'href' => '/erp/loans',           'icon' => 'dollar-sign',  'sort_order' => 31],
                ['title' => 'Eventos',                 'short_description' => 'Agenda, actividades y coordinación.',                              'slug' => 'erp-events',          'href' => '/erp/events',          'icon' => 'calendar',     'sort_order' => 32],

                // ✅ AJUSTE 2
                ['title' => 'Bienes y Servicios',      'short_description' => 'Registro de compras, gastos y bienes con trazabilidad.',            'slug' => 'erp-goods-services',  'href' => '/erp/goods-services',  'icon' => 'layers',       'sort_order' => 33],

                ['title' => 'Recursos Humanos',        'short_description' => 'Nómina, asistencia y expedientes.',                                'slug' => 'erp-hr',              'href' => '/erp/hr',              'icon' => 'user-check',   'sort_order' => 34],
                ['title' => 'Bancos',                  'short_description' => 'Cuentas, conciliación y transacciones.',                           'slug' => 'erp-banks',           'href' => '/erp/banks',           'icon' => 'bank',         'sort_order' => 35],
                ['title' => 'Contabilidad',            'short_description' => 'Asientos, mayor, reportes y cierres.',                             'slug' => 'erp-accounting',      'href' => '/erp/accounting',      'icon' => 'book',         'sort_order' => 36],
                ['title' => 'Laudago',                 'short_description' => 'Módulo complementario del ecosistema.',                            'slug' => 'erp-laudago',         'href' => '/erp/laudago',         'icon' => 'map-pin',      'sort_order' => 37],
            ];

            foreach ($marketplaceChildren as $child) {
                $upsert([
                    'title'             => $child['title'],
                    'short_description' => $child['short_description'],
                    'slug'              => $child['slug'],
                    'href'              => $child['href'],
                    'icon'              => $child['icon'],
                    'parent_id'         => $marketplaceId,
                    'type'              => 'addon',
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
                'short_description' => 'Plataforma: ecommerce, integraciones y operaciones.',
                'slug'              => 'laudaone',
                'href'              => '/laudaone',
                'icon'              => 'layers',
                'badge'             => 'PLATFORM',
                'parent_id'         => null,
                'type'              => 'core',
                'description'       => 'Plataforma LaudaOne: ecommerce B2C, B2B.',
                'sort_order'        => 5,
            ]);

            $laudaOneChildren = [
                [
                    'title'             => 'Ecommerce B2C (LaudaOne)',
                    'short_description' => 'Tienda online B2C con catálogo y checkout.',
                    'slug'              => 'laudaone-ecommerce-b2c',
                    'href'              => '/laudaone/ecommerce/b2c',
                    'icon'              => 'shopping-bag',
                    'sort_order'        => 6,
                ],
                [
                    'title'             => 'Ecommerce B2B (LaudaOne)',
                    'short_description' => 'Canal B2B con precios por cliente y crédito.',
                    'slug'              => 'laudaone-ecommerce-b2b',
                    'href'              => '/laudaone/ecommerce/b2b',
                    'icon'              => 'truck',
                    'sort_order'        => 7,
                ],
            ];

            foreach ($laudaOneChildren as $child) {
                $upsert([
                    'title'             => $child['title'],
                    'short_description' => $child['short_description'],
                    'slug'              => $child['slug'],
                    'href'              => $child['href'],
                    'icon'              => $child['icon'],
                    'parent_id'         => $laudaOneId,
                    'type'              => 'addon',
                    'sort_order'        => $child['sort_order'],
                ]);
            }

            DB::table('services')->where('slug', 'laudaone-services-api-facturacion')->delete();
        });
    }
}
