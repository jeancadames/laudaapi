<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_phase_estimates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_phase_id');

            $table->foreign(
                'transformation_implementation_phase_id',
                'tip_est_phase_fk'
            )
                ->references('id')
                ->on('transformation_implementation_phases')
                ->cascadeOnDelete();

            $table->string('modality', 20);
            $table->string('modality_label', 100);

            $table->decimal('price_amount', 12, 2);
            $table->char('currency', 3)->default('DOP');

            $table->unsignedSmallInteger('estimated_duration_value');
            $table->string('estimated_duration_unit', 20)->default('weeks');

            $table->json('scope_snapshot')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_est_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_est_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_phase_id', 'modality'],
                'tip_est_phase_modality_uq'
            );

            $table->index(
                ['modality', 'currency'],
                'tip_est_mod_currency_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_phase_estimates');
    }
};
