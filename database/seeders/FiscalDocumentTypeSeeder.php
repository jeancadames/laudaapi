<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FiscalDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ✅ NCF (DGII): B + (tipo 2 dígitos) + 8 secuencial
        $ncf = [
            ['code' => 'B01', 'label' => 'Factura de Crédito Fiscal',            'requires_buyer_tax_id' => true],
            ['code' => 'B02', 'label' => 'Factura de Consumo',                   'requires_buyer_tax_id' => false],
            ['code' => 'B03', 'label' => 'Nota de Débito',                       'requires_buyer_tax_id' => true],
            ['code' => 'B04', 'label' => 'Nota de Crédito',                      'requires_buyer_tax_id' => true],
            ['code' => 'B11', 'label' => 'Comprobante de Compras',               'requires_buyer_tax_id' => false],
            ['code' => 'B12', 'label' => 'Registro Único de Ingresos',           'requires_buyer_tax_id' => false],
            ['code' => 'B13', 'label' => 'Gastos Menores',                       'requires_buyer_tax_id' => false],
            ['code' => 'B14', 'label' => 'Regímenes Especiales',                 'requires_buyer_tax_id' => true],
            ['code' => 'B15', 'label' => 'Gubernamental',                        'requires_buyer_tax_id' => true],
            ['code' => 'B16', 'label' => 'Exportaciones',                        'requires_buyer_tax_id' => true],
            ['code' => 'B17', 'label' => 'Pagos al Exterior',                    'requires_buyer_tax_id' => true],
        ];

        // ✅ e-CF (DGII): E + (tipo 2 dígitos) + 10 secuencial, tipos 31–47
        $ecf = [
            ['code' => 'E31', 'label' => 'Factura de Crédito Fiscal Electrónica',  'requires_buyer_tax_id' => true],
            ['code' => 'E32', 'label' => 'Factura de Consumo Electrónica',         'requires_buyer_tax_id' => false],
            ['code' => 'E33', 'label' => 'Nota de Débito Electrónica',             'requires_buyer_tax_id' => true],
            ['code' => 'E34', 'label' => 'Nota de Crédito Electrónica',            'requires_buyer_tax_id' => true],
            ['code' => 'E41', 'label' => 'Compras Electrónico',                    'requires_buyer_tax_id' => false],
            ['code' => 'E43', 'label' => 'Gastos Menores Electrónico',             'requires_buyer_tax_id' => false],
            ['code' => 'E44', 'label' => 'Regímenes Especiales Electrónicos',      'requires_buyer_tax_id' => true],
            ['code' => 'E45', 'label' => 'Gubernamental Electrónico',              'requires_buyer_tax_id' => true],
            ['code' => 'E46', 'label' => 'Exportación Electrónico',                'requires_buyer_tax_id' => true],
            ['code' => 'E47', 'label' => 'Pagos al Exterior Electrónico',          'requires_buyer_tax_id' => true],
        ];

        $rows = [];

        foreach ($ncf as $t) {
            $rows[] = [
                'country_code'            => 'DO',
                'code'                    => $t['code'],
                'label'                   => $t['label'],
                'kind'                    => 'ncf',
                'requires_buyer_tax_id'   => (bool) $t['requires_buyer_tax_id'],
                'allows_credit_terms'     => true,
                'allows_discount'         => true,
                'active'                  => true,
                'meta'                    => json_encode(['dgii' => true, 'digits' => 8]),
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }

        foreach ($ecf as $t) {
            $rows[] = [
                'country_code'            => 'DO',
                'code'                    => $t['code'],
                'label'                   => $t['label'],
                'kind'                    => 'ecf',
                'requires_buyer_tax_id'   => (bool) $t['requires_buyer_tax_id'],
                'allows_credit_terms'     => true,
                'allows_discount'         => true,
                'active'                  => true,
                'meta'                    => json_encode(['dgii' => true, 'digits' => 10]),
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }

        // ✅ Upsert por (country_code, code)
        DB::table('fiscal_document_types')->upsert(
            $rows,
            ['country_code', 'code'],
            [
                'label',
                'kind',
                'requires_buyer_tax_id',
                'allows_credit_terms',
                'allows_discount',
                'active',
                'meta',
                'updated_at',
            ]
        );
    }
}
