<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * MySQL necesita un índice cuyo primer campo sea
         * transformation_implementation_plan_id para sostener
         * la FK existente.
         *
         * tid_plan_version_uq estaba cumpliendo también ese rol.
         *
         * Por eso debemos crear primero el índice normal y
         * solamente después retirar la unicidad legacy.
         */

        $indexes = $this->indexNames();

        if (
            ! in_array(
                'tid_plan_version_idx',
                $indexes,
                true
            )
        ) {
            Schema::table(
                'transformation_implementation_definitions',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'transformation_implementation_plan_id',
                            'version',
                        ],
                        'tid_plan_version_idx'
                    );
                }
            );
        }

        /*
         * Refrescamos los índices después del DDL anterior.
         */
        $indexes = $this->indexNames();

        if (
            in_array(
                'tid_plan_version_uq',
                $indexes,
                true
            )
        ) {
            Schema::table(
                'transformation_implementation_definitions',
                function (Blueprint $table): void {
                    $table->dropUnique(
                        'tid_plan_version_uq'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Orden inverso seguro para la FK:
         *
         * primero recreamos UNIQUE(plan_id, version),
         * que también puede sostener la FK;
         * después retiramos el índice normal.
         *
         * Este rollback fallará correctamente si existen
         * duplicados plan_id + version creados por el nuevo
         * modelo request-scoped.
         */

        $indexes = $this->indexNames();

        if (
            ! in_array(
                'tid_plan_version_uq',
                $indexes,
                true
            )
        ) {
            Schema::table(
                'transformation_implementation_definitions',
                function (Blueprint $table): void {
                    $table->unique(
                        [
                            'transformation_implementation_plan_id',
                            'version',
                        ],
                        'tid_plan_version_uq'
                    );
                }
            );
        }

        $indexes = $this->indexNames();

        if (
            in_array(
                'tid_plan_version_idx',
                $indexes,
                true
            )
        ) {
            Schema::table(
                'transformation_implementation_definitions',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'tid_plan_version_idx'
                    );
                }
            );
        }
    }

    private function indexNames(): array
    {
        return collect(
            DB::select(
                'SHOW INDEX FROM transformation_implementation_definitions'
            )
        )
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }
};
