<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FiscalYearEndCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'country_code' => 'DO',
                'close_month' => 12,
                'close_day' => 31,
                'label' => '31 de Diciembre',
                'common_business_types' => 'La mayoría de las empresas y comercios.',
                'ir2_due_days' => 120,
                'sort_order' => 10,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_code' => 'DO',
                'close_month' => 3,
                'close_day' => 31,
                'label' => '31 de Marzo',
                'common_business_types' => 'Empresas con ciclos industriales o agrícolas.',
                'ir2_due_days' => 120,
                'sort_order' => 20,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_code' => 'DO',
                'close_month' => 6,
                'close_day' => 30,
                'label' => '30 de Junio',
                'common_business_types' => 'Muy común en el sector educativo o agropecuario.',
                'ir2_due_days' => 120,
                'sort_order' => 30,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_code' => 'DO',
                'close_month' => 9,
                'close_day' => 30,
                'label' => '30 de Septiembre',
                'common_business_types' => 'Frecuente en el sector turístico o multinacionales.',
                'ir2_due_days' => 120,
                'sort_order' => 40,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('fiscal_year_end_catalog')->updateOrInsert(
                ['country_code' => $r['country_code'], 'close_month' => $r['close_month'], 'close_day' => $r['close_day']],
                $r
            );
        }
    }
}
