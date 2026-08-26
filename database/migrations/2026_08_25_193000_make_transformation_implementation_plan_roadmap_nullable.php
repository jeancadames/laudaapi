<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE transformation_implementation_plans '
            .'MODIFY diagnosis_detailed_roadmap_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        $internalPlans = DB::table('transformation_implementation_plans')
            ->whereNull('diagnosis_detailed_roadmap_id')
            ->count();

        if ($internalPlans > 0) {
            throw new RuntimeException(
                'No se puede revertir diagnosis_detailed_roadmap_id a NOT NULL mientras existan Planes con fuente interna.'
            );
        }

        DB::statement(
            'ALTER TABLE transformation_implementation_plans '
            .'MODIFY diagnosis_detailed_roadmap_id BIGINT UNSIGNED NOT NULL'
        );
    }
};
