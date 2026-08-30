<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_capability_needs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'transformation_capability_activation_id'
                );

                $table->unsignedSmallInteger(
                    'sequence'
                );

                $table->string(
                    'need_key',
                    120
                );

                $table->string(
                    'title',
                    255
                );

                $table->text(
                    'description'
                )->nullable();

                $table->string(
                    'source_type',
                    60
                );

                $table->json(
                    'source_snapshot'
                )->nullable();

                $table->string(
                    'status',
                    40
                )->default('identified');

                $table->timestamp(
                    'identified_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'transformation_capability_activation_id',
                        'need_key',
                    ],
                    'tcn_activation_need_uq'
                );

                $table->index(
                    [
                        'transformation_capability_activation_id',
                        'status',
                    ],
                    'tcn_activation_status_idx'
                );

                $table->foreign(
                    'transformation_capability_activation_id',
                    'tcn_activation_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_capability_activations'
                    )
                    ->onDelete('cascade');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_capability_needs'
        );
    }
};
