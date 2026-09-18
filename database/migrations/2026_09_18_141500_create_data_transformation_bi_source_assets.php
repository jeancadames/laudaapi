<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Dynamic client-native source.
         *
         * A SourceAsset represents one logical source as the client knows it:
         * CTES, FAC.DBF, Maestro de clientes, ventas_2025.xlsx, etc.
         *
         * It deliberately does NOT require a canonical LAUDA domain.
         * Canonical mapping happens later.
         *
         * It also stores no server/database credentials and no raw data rows.
         */
        Schema::create(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_session_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                /*
                 * Human-friendly name shown in the workspace.
                 * Example: "Maestro de clientes".
                 */
                $table->string(
                    'display_name',
                    191
                );

                /*
                 * Native object/file name.
                 * Examples:
                 * CTES
                 * FAC.DBF
                 * clientes.xlsx
                 */
                $table->string(
                    'source_object_name',
                    255
                );

                /*
                 * Short business description supplied by the client/admin.
                 */
                $table->text(
                    'description'
                )->nullable();

                /*
                 * Informational only.
                 *
                 * Examples:
                 * SQL Server
                 * FoxPro
                 * Clarion
                 * Monica
                 * Excel
                 * Sistema propio
                 *
                 * This is intentionally free text and must never become
                 * a connectivity or acceptance gate.
                 */
                $table->string(
                    'origin_system',
                    191
                )->nullable();

                /*
                 * Optional description of how the structure was supplied.
                 * Examples:
                 * sql_server_ddl
                 * field_type_list
                 * csv_headers
                 * xlsx_headers
                 *
                 * This field stores only the structure-format identifier.
                 * Any supplied DDL lives in structure_text as inert text;
                 * LAUDA never executes that client-provided definition.
                 */
                $table->string(
                    'structure_format',
                    64
                )->nullable();

                /*
                 * Client-provided structural definition.
                 *
                 * Examples:
                 * - field/type list;
                 * - SQL Server CREATE TABLE text;
                 * - another textual schema description.
                 *
                 * This value is inert input. LAUDA never executes it.
                 */
                $table->text(
                    'structure_text'
                )->nullable();

                /*
                 * Delivery preference for actual data.
                 *
                 * Application validation will limit new deliveries to
                 * CSV/XLSX. Kept as string at DB level for forward
                 * compatibility.
                 */
                $table->string(
                    'delivery_format',
                    16
                )->nullable();

                /*
                 * Overall logical source lifecycle.
                 *
                 * Current application contract will define the allowed
                 * transitions. Database remains non-enum intentionally.
                 */
                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->string(
                    'structure_status',
                    32
                )->default('pending');

                $table->string(
                    'data_status',
                    32
                )->default('pending');

                /*
                 * Normalized structural metadata only:
                 * fields, primitive types, sheets, keys, observations, etc.
                 *
                 * No raw client rows and no credentials.
                 */
                $table->json(
                    'structure_snapshot'
                )->nullable();

                /*
                 * Profiling metadata for the source after data arrives.
                 * Raw rows must not be copied here.
                 */
                $table->json(
                    'profiling_snapshot'
                )->nullable();

                /*
                 * Stable UI ordering for the horizontal source workspace.
                 */
                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->timestamp(
                    'structure_analyzed_at'
                )->nullable();

                $table->timestamp(
                    'data_received_at'
                )->nullable();

                $table->timestamp(
                    'profiled_at'
                )->nullable();

                $table->timestamp(
                    'archived_at'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_intake_session_id',
                    'dtbi_source_assets_session_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_intake_sessions'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_source_assets_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_source_assets_created_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_source_assets_updated_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'data_transformation_bi_intake_session_id',
                        'sort_order',
                        'id',
                    ],
                    'dtbi_source_assets_session_order_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_source_assets_company_status_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_intake_session_id',
                        'structure_status',
                        'data_status',
                    ],
                    'dtbi_source_assets_progress_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_source_assets'
        );
    }
};
