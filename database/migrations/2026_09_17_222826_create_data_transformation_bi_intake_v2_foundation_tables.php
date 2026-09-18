<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'data_transformation_bi_intake_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_definition_id'
                )->nullable();

                $table->unsignedInteger(
                    'definition_version'
                )->nullable();

                $table->unsignedInteger(
                    'schema_version'
                );

                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                /*
                 * Once the seven domains are resolved and the relational
                 * gate passes, the session materializes exactly one
                 * immutable canonical staging batch.
                 */
                $table->unsignedBigInteger(
                    'resulting_intake_batch_id'
                )->nullable();

                /*
                 * SHA-256 over the resolved seven-domain manifest.
                 * This is metadata identity, not a consolidated customer
                 * data file.
                 */
                $table->char(
                    'resolved_manifest_sha256',
                    64
                )->nullable();

                /*
                 * Final cross-domain validation evidence.
                 * Row payloads must not be stored here.
                 */
                $table->json(
                    'relational_validation_snapshot'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_message'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'ready_at'
                )->nullable();

                $table->timestamp(
                    'finalized_at'
                )->nullable();

                $table->timestamp(
                    'cancelled_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'company_id',
                    'dtbi_intake_sessions_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_request_id',
                    'dtbi_intake_sessions_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    'transformation_implementation_definition_id',
                    'dtbi_intake_sessions_definition_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_definitions')
                    ->nullOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_intake_sessions_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'resulting_intake_batch_id',
                    'dtbi_intake_sessions_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->nullOnDelete();

                $table->unique(
                    'resulting_intake_batch_id',
                    'dtbi_intake_sessions_batch_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_intake_sessions_company_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'dtbi_intake_sessions_request_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'schema_version',
                    ],
                    'dtbi_intake_sessions_request_schema_idx'
                );
            }
        );

        Schema::create(
            'data_transformation_bi_intake_domain_deliveries',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'data_transformation_bi_intake_session_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'domain_key',
                    100
                );

                /*
                 * null          = todavía sin decisión
                 * uploaded      = archivo XLSX/CSV propio del dominio
                 * no_data       = declaración explícita "Sin datos"
                 * carry_forward = heredar dominio del último dataset usable
                 */
                $table->string(
                    'delivery_mode',
                    32
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('pending');

                /*
                 * Source artifact fields apply only to uploaded mode.
                 * Files remain private.
                 */
                $table->string(
                    'source_disk',
                    64
                )->nullable();

                $table->string(
                    'source_path',
                    2048
                )->nullable();

                $table->string(
                    'original_filename',
                    512
                )->nullable();

                $table->string(
                    'source_format',
                    32
                )->nullable();

                $table->string(
                    'source_mime_type',
                    255
                )->nullable();

                $table->unsignedBigInteger(
                    'source_size_bytes'
                )->nullable();

                $table->char(
                    'source_sha256',
                    64
                )->nullable();

                /*
                 * Structural/type validation evidence only.
                 * Cross-domain validation belongs to the session.
                 */
                $table->json(
                    'validation_snapshot'
                )->nullable();

                $table->unsignedBigInteger(
                    'source_row_count'
                )->default(0);

                $table->unsignedBigInteger(
                    'accepted_row_count'
                )->default(0);

                /*
                 * Carry-forward points to the canonical usable dataset,
                 * not to another v2 delivery. This keeps compatibility
                 * with datasets produced by legacy intake v1.
                 */
                $table->unsignedBigInteger(
                    'carry_forward_processing_run_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'carry_forward_intake_batch_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->timestamp(
                    'validated_at'
                )->nullable();

                $table->timestamps();

                $table->foreign(
                    'data_transformation_bi_intake_session_id',
                    'dtbi_domain_deliveries_session_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_sessions')
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_domain_deliveries_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'carry_forward_processing_run_id',
                    'dtbi_domain_deliveries_run_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_processing_runs')
                    ->nullOnDelete();

                $table->foreign(
                    'carry_forward_intake_batch_id',
                    'dtbi_domain_deliveries_batch_fk'
                )
                    ->references('id')
                    ->on('data_transformation_bi_intake_batches')
                    ->nullOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_domain_deliveries_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Exactly one current decision per canonical domain
                 * inside one intake session.
                 */
                $table->unique(
                    [
                        'data_transformation_bi_intake_session_id',
                        'domain_key',
                    ],
                    'dtbi_domain_deliveries_session_domain_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'domain_key',
                        'status',
                    ],
                    'dtbi_domain_deliveries_company_domain_status_idx'
                );

                $table->index(
                    [
                        'data_transformation_bi_intake_session_id',
                        'status',
                    ],
                    'dtbi_domain_deliveries_session_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_intake_domain_deliveries'
        );

        Schema::dropIfExists(
            'data_transformation_bi_intake_sessions'
        );
    }
};
