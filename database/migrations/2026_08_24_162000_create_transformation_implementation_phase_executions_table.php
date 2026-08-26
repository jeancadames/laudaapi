<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_phase_executions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_phase_id');

            $table->foreign(
                'transformation_implementation_phase_id',
                'tip_pe_phase_fk'
            )
                ->references('id')
                ->on('transformation_implementation_phases')
                ->cascadeOnDelete();

            $table->string('status', 20)->default('pending');
            $table->decimal('progress_percentage', 5, 2)->default(0);

            $table->foreignId('assigned_user_id')->nullable();

            $table->foreign(
                'assigned_user_id',
                'tip_pe_assigned_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('blocking_reason')->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_pe_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_pe_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_phase_id'],
                'tip_pe_phase_uq'
            );

            $table->index(
                ['status', 'assigned_user_id'],
                'tip_pe_status_assigned_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_phase_executions');
    }
};
