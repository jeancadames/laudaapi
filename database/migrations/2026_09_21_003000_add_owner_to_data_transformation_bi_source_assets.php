<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                /*
                 * Operational/business owner of this concrete source.
                 *
                 * Examples:
                 * - Contabilidad
                 * - Sistemas
                 * - Administración
                 * - Juan Pérez
                 *
                 * This is descriptive source metadata only.
                 * It is not:
                 * - a canonical LAUDA domain;
                 * - a remote-access credential;
                 * - a connectivity gate;
                 * - a LAUDA/client/shared lifecycle assignment.
                 */
                $table->string(
                    'owner',
                    191
                )
                    ->nullable()
                    ->after('origin_system');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table->dropColumn('owner');
            }
        );
    }
};
