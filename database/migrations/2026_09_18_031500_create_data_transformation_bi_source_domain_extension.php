<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Archivo original entregado por el cliente para un dominio ya
         * existente dentro de Intake v2.
         *
         * Este archivo todavía NO tiene que respetar el esquema canónico.
         * LAUDA lo analiza, perfila, mapea y transforma antes de utilizar
         * el gate canónico existente.
         */
        Schema::create(
            'data_transformation_bi_source_domain_files',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_domain_delivery_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    100
                );

                $table->string(
                    'status',
                    32
                )->default('uploaded');

                /*
                 * Artefacto original privado.
                 */
                $table->string(
                    'source_disk',
                    64
                )->default('private');

                $table->string(
                    'source_path',
                    2048
                );

                $table->string(
                    'original_filename',
                    512
                );

                $table->string(
                    'source_format',
                    32
                );

                $table->string(
                    'source_mime_type',
                    255
                )->nullable();

                $table->unsignedBigInteger(
                    'source_size_bytes'
                );

                $table->char(
                    'source_sha256',
                    64
                );

                /*
                 * Configuración necesaria para releer el archivo:
                 * hoja seleccionada, fila de encabezado, delimitador,
                 * encoding u otras decisiones determinísticas.
                 */
                $table->json(
                    'reader_configuration'
                )->nullable();

                /*
                 * Metadata estructural:
                 * hojas, encabezados originales, cantidad de columnas
                 * y tipos primitivos inferidos.
                 *
                 * NO almacena copia completa de las filas del cliente.
                 */
                $table->json(
                    'source_structure_snapshot'
                )->nullable();

                /*
                 * Perfilado del archivo fuente:
                 * conteos, vacíos, duplicados, patrones y observaciones
                 * necesarias para planificación de transformación.
                 */
                $table->json(
                    'profiling_snapshot'
                )->nullable();

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'uploaded_by_user_id'
                )->nullable();

                $table->timestamp(
                    'uploaded_at'
                )->nullable();

                $table->timestamp(
                    'profiled_at'
                )->nullable();

                $table->timestamp(
                    'mapping_ready_at'
                )->nullable();

                $table->timestamp(
                    'transformed_at'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamps();

                /*
                 * Primera versión:
                 * un archivo fuente actual por delivery/dominio.
                 */
                $table->unique(
                    'data_transformation_bi_intake_domain_delivery_id',
                    'dtbi_source_file_delivery_uq'
                );

                $table->foreign(
                    'data_transformation_bi_intake_domain_delivery_id',
                    'dtbi_source_file_delivery_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_intake_domain_deliveries'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_source_file_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'uploaded_by_user_id',
                    'dtbi_source_file_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'company_id',
                        'domain_key',
                        'status',
                    ],
                    'dtbi_source_file_company_domain_idx'
                );
            }
        );

        /*
         * Plan declarativo columna fuente -> campo canónico.
         *
         * No se guardan expresiones PHP/SQL ejecutables.
         */
        Schema::create(
            'data_transformation_bi_source_field_mappings',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_source_domain_file_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    100
                );

                $table->string(
                    'target_field',
                    191
                );

                $table->string(
                    'source_field',
                    191
                )->nullable();

                /*
                 * direct
                 * default
                 * transform
                 * unmapped
                 */
                $table->string(
                    'mapping_type',
                    32
                )->default('direct');

                $table->text(
                    'default_value'
                )->nullable();

                /*
                 * Transformación controlada por catálogo de LAUDA.
                 */
                $table->string(
                    'transformation_key',
                    100
                )->nullable();

                $table->json(
                    'configuration_snapshot'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->text(
                    'notes'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->timestamp(
                    'validated_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_source_domain_file_id',
                    'dtbi_source_map_file_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_source_domain_files'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_source_map_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_source_map_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Una decisión por campo canónico objetivo.
                 */
                $table->unique(
                    [
                        'data_transformation_bi_source_domain_file_id',
                        'target_field',
                    ],
                    'dtbi_source_map_file_target_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'domain_key',
                        'status',
                    ],
                    'dtbi_source_map_company_domain_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_source_field_mappings'
        );

        Schema::dropIfExists(
            'data_transformation_bi_source_domain_files'
        );
    }
};
