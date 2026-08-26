<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_phase_capabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_phase_id');

            $table->foreign(
                'transformation_implementation_phase_id',
                'tip_pc_phase_fk'
            )
                ->references('id')
                ->on('transformation_implementation_phases')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('capability_key', 120);
            $table->string('capability_label', 180);
            $table->text('capability_summary')->nullable();
            $table->json('source_snapshot')->nullable();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_phase_id', 'capability_key'],
                'tip_pc_phase_cap_uq'
            );

            $table->index(
                ['transformation_implementation_phase_id', 'sequence'],
                'tip_pc_phase_seq_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_phase_capabilities');
    }
};
