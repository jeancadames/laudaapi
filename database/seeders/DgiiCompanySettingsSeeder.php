<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DgiiCompanySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // defaults:
        // precert -> testcf
        // cert   -> certecf
        // prod   -> eCF
        $defaultEnv = 'precert';
        $defaultCf  = 'testcf';

        $companyIds = DB::table('companies')->pluck('id')->all();

        DB::transaction(function () use ($companyIds, $defaultEnv, $defaultCf, $now) {
            foreach ($companyIds as $companyId) {
                DB::table('dgii_company_settings')->updateOrInsert(
                    ['company_id' => $companyId],
                    [
                        'company_id'     => $companyId,
                        'environment'    => $defaultEnv,
                        'cf_prefix'      => $defaultCf,
                        'use_directory'  => false,
                        'meta'           => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]
                );
            }
        });
    }
}
