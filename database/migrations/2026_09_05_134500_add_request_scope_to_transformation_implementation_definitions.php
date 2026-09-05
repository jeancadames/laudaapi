<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'transformation_implementation_definitions',
            function (Blueprint $table): void {
                /*
                 * Compatibilidad histórica:
                 *
                 * Las Definitions existentes fueron creadas
                 * con alcance Plan-wide.
                 *
                 * Por eso estos tres campos son nullable en DB.
                 * El nuevo flujo Request-scoped los exigirá
                 * desde el servicio de dominio F5C.
                 */

                $table
                    ->foreignId(
                        'transformation_implementation_request_id'
                    )
                    ->nullable()
                    ->after('company_id');

                $table
                    ->foreign(
                        'transformation_implementation_request_id',
                        'tid_request_fk'
                    )
                    ->references('id')
                    ->on(
                        'transformation_implementation_requests'
                    )
                    ->nullOnDelete();

                $table
                    ->foreignId(
                        'transformation_implementation_phase_capability_id'
                    )
                    ->nullable()
                    ->after(
                        'transformation_implementation_request_id'
                    );

                $table
                    ->foreign(
                        'transformation_implementation_phase_capability_id',
                        'tid_phase_capability_fk'
                    )
                    ->references('id')
                    ->on(
                        'transformation_implementation_phase_capabilities'
                    )
                    ->nullOnDelete();

                $table
                    ->string(
                        'capability_key',
                        120
                    )
                    ->nullable()
                    ->after(
                        'transformation_implementation_phase_capability_id'
                    );

                /*
                 * Una misma solicitud puede conservar versiones
                 * sucesivas de su Definition, pero nunca dos
                 * Definitions con la misma versión.
                 */
                $table->unique(
                    [
                        'transformation_implementation_request_id',
                        'version',
                    ],
                    'tid_request_version_uq'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'tid_request_status_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'capability_key',
                        'status',
                    ],
                    'tid_company_cap_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'transformation_implementation_definitions',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'tid_company_cap_status_idx'
                );

                $table->dropIndex(
                    'tid_request_status_idx'
                );

                $table->dropUnique(
                    'tid_request_version_uq'
                );

                $table->dropForeign(
                    'tid_phase_capability_fk'
                );

                $table->dropForeign(
                    'tid_request_fk'
                );

                $table->dropColumn([
                    'capability_key',
                    'transformation_implementation_phase_capability_id',
                    'transformation_implementation_request_id',
                ]);
            }
        );
    }
};
