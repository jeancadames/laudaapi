<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_implementation_requests',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id');

                $table->unsignedBigInteger(
                    'diagnosis_assessment_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_plan_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_phase_capability_id'
                );

                $table->string('capability_key', 120);

                /*
                 * Cada cancelación permite un nuevo intento explícito.
                 * Un mismo scope company + plan + capability conserva
                 * histórico mediante attempt.
                 */
                $table->unsignedSmallInteger('attempt')
                    ->default(1);

                $table->string('source_type', 40)
                    ->default('tenant_admin');

                $table->string('status', 40)
                    ->default('requested');

                /*
                 * Snapshot inmutable del contexto con el que el tenant
                 * realizó la solicitud.
                 */
                $table->json('source_snapshot');

                $table->text('tenant_note')
                    ->nullable();

                $table->text('internal_notes')
                    ->nullable();

                $table->unsignedBigInteger(
                    'requested_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'assigned_to_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'status_changed_by_user_id'
                )->nullable();

                $table->timestamp('requested_at');

                $table->timestamp('review_started_at')
                    ->nullable();

                $table->timestamp('definition_started_at')
                    ->nullable();

                $table->timestamp(
                    'tenant_review_requested_at'
                )->nullable();

                $table->timestamp('changes_requested_at')
                    ->nullable();

                $table->timestamp('definition_agreed_at')
                    ->nullable();

                $table->timestamp('ready_for_commercial_at')
                    ->nullable();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->text('cancellation_reason')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'transformation_implementation_plan_id',
                        'capability_key',
                        'attempt',
                    ],
                    'tir_scope_attempt_unique'
                );

                $table->index(
                    ['company_id', 'status'],
                    'tir_company_status_idx'
                );

                $table->index(
                    ['capability_key', 'status'],
                    'tir_capability_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_plan_id',
                        'capability_key',
                    ],
                    'tir_plan_capability_idx'
                );

                $table->index(
                    [
                        'diagnosis_assessment_id',
                        'capability_key',
                    ],
                    'tir_assessment_capability_idx'
                );

                $table->foreign(
                    'company_id',
                    'tir_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->onDelete('restrict');

                $table->foreign(
                    'diagnosis_assessment_id',
                    'tir_assessment_fk'
                )
                    ->references('id')
                    ->on('diagnosis_assessments')
                    ->onDelete('restrict');

                $table->foreign(
                    'transformation_implementation_plan_id',
                    'tir_plan_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_plans')
                    ->onDelete('restrict');

                $table->foreign(
                    'transformation_implementation_phase_capability_id',
                    'tir_phase_capability_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_phase_capabilities'
                    )
                    ->onDelete('restrict');

                $table->foreign(
                    'requested_by_user_id',
                    'tir_requested_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'assigned_to_user_id',
                    'tir_assigned_to_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'status_changed_by_user_id',
                    'tir_status_changed_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'transformation_implementation_request_events',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->string('event_type', 60);

                $table->string('from_status', 40)
                    ->nullable();

                $table->string('to_status', 40);

                /*
                 * tenant_admin | lauda_admin | system
                 */
                $table->string('actor_type', 30);

                $table->unsignedBigInteger('actor_user_id')
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamp('occurred_at');

                $table->timestamps();

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'occurred_at',
                    ],
                    'tire_request_date_idx'
                );

                $table->index(
                    ['to_status', 'occurred_at'],
                    'tire_status_date_idx'
                );

                $table->foreign(
                    'transformation_implementation_request_id',
                    'tire_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->cascadeOnDelete();

                $table->foreign(
                    'actor_user_id',
                    'tire_actor_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_implementation_request_events'
        );

        Schema::dropIfExists(
            'transformation_implementation_requests'
        );
    }
};
