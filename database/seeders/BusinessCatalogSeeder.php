<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Services\Billing\BusinessCatalogRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessCatalogSeeder extends Seeder
{
    public function run(?int $actor = null): void
    {
        $catalog = ServiceCatalog::firstOrCreate(['code' => 'business']);
        DB::transaction(function () use ($catalog, $actor): void {
            ServiceCatalog::whereKey($catalog->id)->lockForUpdate()->firstOrFail();
            if (ServiceCatalogVersion::where('service_catalog_id', $catalog->id)->exists()) {
                return;
            }
            $payload = self::initialPayload();
            foreach ($payload['products'] as $code => &$product) {
                $matches = Service::where('service_key', $code)->pluck('id');
                $product['service_id'] = $matches->count() === 1 ? (int) $matches->first() : null;
            }
            unset($product);
            $version = ServiceCatalogVersion::create([
                'service_catalog_id' => $catalog->id, 'version' => 1,
                'status' => 'draft', 'payload' => $payload, 'created_by' => $actor,
            ]);
            AuditLog::create(['event' => 'business.catalog.initialized', 'model_type' => ServiceCatalogVersion::class,
                'model_id' => $version->id, 'user_id' => $actor, 'data' => ['before' => [], 'after' => $version->toArray()]]);
        });
    }

    /** Seed only: operational calculations never fall back to these values. */
    public static function initialPayload(): array
    {
        $products = [];
        foreach (['ecf' => 'e-CF', 'pos' => 'POS con e-commerce', 'cumplimiento' => 'Cumplimiento', 'bys' => 'BYS'] as $code => $name) {
            $products[$code] = [
                'name' => $name, 'description' => $code === 'cumplimiento' ? 'Software de Cumplimiento. La ejecución profesional de obligaciones requiere un alcance contratado por separado.' : '', 'service_id' => null, 'available' => true,
                'features' => $code === 'pos' ? ['POS', 'E-commerce estándar incluido'] : [],
            ];
        }
        $offers = [];
        foreach ([
            'ecf' => ['e-CF', ['ecf'], 150000],
            'pos' => ['POS con e-commerce', ['pos'], 150000],
            'cumplimiento' => ['Cumplimiento', ['cumplimiento'], 100000],
            'bys' => ['BYS', ['bys'], 100000],
            'fiscal' => ['Fiscal', ['ecf', 'cumplimiento'], 200000],
            'comercial' => ['Comercial', ['ecf', 'pos'], 250000],
            'business' => ['Business', ['ecf', 'pos', 'cumplimiento', 'bys'], 350000],
        ] as $code => [$name, $included, $price]) {
            $offers[$code] = [
                'name' => $name, 'available' => true, 'products' => $included,
                'pricing_method' => 'own', 'package_discount_bps' => 0,
                'levels' => [
                    'initial' => ['enabled' => true, 'monthly_cents' => $price, 'annual_cents' => null],
                    'growth' => ['enabled' => $code === 'business', 'monthly_cents' => $code === 'business' ? 500000 : null, 'annual_cents' => null],
                    'scale' => ['enabled' => $code === 'business', 'monthly_cents' => $code === 'business' ? 800000 : null, 'annual_cents' => null],
                ],
            ];
        }

        return [
            'currency' => 'DOP', 'timezone' => 'America/Santo_Domingo',
            'annual_discount_bps' => 1000, 'quote_valid_days' => null, 'tax_rate' => 0,
            'terms' => '', 'products' => $products, 'offers' => $offers,
            'capacity_policy' => BusinessCatalogRules::INITIAL_CAPACITY_POLICY,
            'capacities' => [
                'initial' => ['name' => 'Inicial', 'documents' => 500, 'branches' => 1, 'registers' => 2, 'users' => 5],
                'growth' => ['name' => 'Crecimiento', 'documents' => 2500, 'branches' => 3, 'registers' => 6, 'users' => 15],
                'scale' => ['name' => 'Escala', 'documents' => 10000, 'branches' => 10, 'registers' => 20, 'users' => 40],
            ],
            'addons' => [
                'documents' => ['name' => 'Documentos adicionales', 'unit' => 'documento', 'block_size' => 500, 'price_cents' => 50000, 'recurring' => true, 'product' => null, 'capacity' => ['documents' => 1]],
                'branch' => ['name' => 'Sucursal con hasta 2 cajas', 'unit' => 'sucursal', 'block_size' => 1, 'price_cents' => 50000, 'recurring' => true, 'product' => 'pos', 'capacity' => ['branches' => 1, 'registers' => 2]],
                'register' => ['name' => 'Caja adicional', 'unit' => 'caja', 'block_size' => 1, 'price_cents' => 25000, 'recurring' => true, 'product' => 'pos', 'capacity' => ['registers' => 1]],
                'users' => ['name' => 'Usuarios adicionales', 'unit' => 'usuario', 'block_size' => 5, 'price_cents' => 50000, 'recurring' => true, 'product' => null, 'capacity' => ['users' => 1]],
                'hours' => ['name' => 'Hora adicional de implementación', 'unit' => 'hora', 'block_size' => 1, 'price_cents' => 150000, 'recurring' => false, 'product' => null, 'capacity' => []],
            ],
            'implementations' => [
                'guided' => ['name' => 'Guiado', 'price_cents' => 0, 'hours' => 0, 'scope' => 'Documentación, plantillas y asistentes. No incluye horas humanas ilimitadas.', 'offers' => array_keys($offers)],
                'assisted' => ['name' => 'Asistido', 'price_cents' => 600000, 'hours' => 4, 'scope' => 'Configuración acompañada y capacitación.', 'offers' => ['business']],
                'managed' => ['name' => 'Gestionado', 'price_cents' => 1500000, 'hours' => 10, 'scope' => 'Configuración y carga inicial acordadas con LAUDA.', 'offers' => ['business']],
            ],
            'discounts' => [],
        ];
    }
}
