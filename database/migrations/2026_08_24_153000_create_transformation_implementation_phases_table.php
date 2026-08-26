<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_phases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transformation_implementation_plan_id');

            $table->foreign(
                'transformation_implementation_plan_id',
                'tip_phase_plan_fk'
            )
                ->references('id')
                ->on('transformation_implementation_plans')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence');
            $table->string('name', 160);
            $table->text('objective')->nullable();
            $table->json('source_snapshot')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_phase_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_phase_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['transformation_implementation_plan_id', 'sequence'],
                'tip_phase_plan_seq_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_phases');
    }
};
