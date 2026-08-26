<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_capability_go_lives', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_phase_capability_id');

            $table->foreign(
                'transformation_implementation_phase_capability_id',
                'tip_gl_capability_fk'
            )
                ->references('id')
                ->on('transformation_implementation_phase_capabilities')
                ->cascadeOnDelete();

            $table->foreignId('transformation_implementation_capability_execution_id');

            $table->foreign(
                'transformation_implementation_capability_execution_id',
                'tip_gl_execution_fk'
            )
                ->references('id')
                ->on('transformation_implementation_capability_executions')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('status', 20)->default('draft');

            $table->json('readiness_snapshot')->nullable();
            $table->json('evidence_snapshot')->nullable();

            $table->timestamp('ready_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('went_live_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('rollback_reason')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_gl_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_gl_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('went_live_by_user_id')->nullable();

            $table->foreign(
                'went_live_by_user_id',
                'tip_gl_live_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_phase_capability_id', 'attempt'],
                'tip_gl_cap_attempt_uq'
            );

            $table->index(
                ['status', 'scheduled_at'],
                'tip_gl_status_scheduled_idx'
            );

            $table->index(
                ['transformation_implementation_phase_capability_id', 'status'],
                'tip_gl_cap_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_capability_go_lives');
    }
};
