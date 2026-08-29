<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_implementation_commercial_rates',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'matrix_version',
                    60
                );

                $table->string(
                    'modality',
                    20
                );

                $table->string(
                    'component_type',
                    40
                );

                $table->string(
                    'component_key',
                    100
                );

                $table->decimal(
                    'price_amount',
                    12,
                    2
                )->nullable();

                $table->unsignedSmallInteger(
                    'duration_days'
                )->nullable();

                $table->char(
                    'currency',
                    3
                )->default('DOP');

                $table->foreignId(
                    'created_by_user_id'
                )->nullable();

                $table->foreign(
                    'created_by_user_id',
                    't360_com_rate_created_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'updated_by_user_id'
                )->nullable();

                $table->foreign(
                    'updated_by_user_id',
                    't360_com_rate_updated_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'matrix_version',
                        'modality',
                        'component_type',
                        'component_key',
                    ],
                    't360_com_rate_slot_uq'
                );

                $table->index(
                    [
                        'matrix_version',
                        'modality',
                    ],
                    't360_com_rate_matrix_mod_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_implementation_commercial_rates'
        );
    }
};
