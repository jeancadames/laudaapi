<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_phase_id');

            $table->foreign(
                'transformation_implementation_phase_id',
                'tip_ms_phase_fk'
            )
                ->references('id')
                ->on('transformation_implementation_phases')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence');
            $table->string('name', 180);
            $table->text('description')->nullable();

            $table->string('modality', 20);
            $table->string('modality_label', 100);

            $table->decimal('billing_percentage', 7, 4)->nullable();
            $table->decimal('billing_amount', 12, 2);
            $table->char('currency', 3);

            $table->string('billing_status', 20)->default('draft');

            $table->timestamp('due_at')->nullable();
            $table->timestamp('ready_to_invoice_at')->nullable();

            $table->string('invoice_reference', 180)->nullable();
            $table->timestamp('invoice_issued_at')->nullable();

            $table->string('payment_reference', 180)->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->json('scope_snapshot')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_ms_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_ms_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_phase_id', 'sequence'],
                'tip_ms_phase_seq_uq'
            );

            $table->index(
                ['transformation_implementation_phase_id', 'billing_status'],
                'tip_ms_phase_status_idx'
            );

            $table->index(
                ['invoice_reference'],
                'tip_ms_invoice_ref_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_milestones');
    }
};
