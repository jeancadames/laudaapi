<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_implementation_capability_service_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('capability_key', 120);
            $table->string('capability_label', 180)->nullable();

            $table->foreignId('service_id');

            $table->foreign(
                'service_id',
                'tip_csm_service_fk'
            )
                ->references('id')
                ->on('services')
                ->restrictOnDelete();

            $table->boolean('active')->default(true);
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable();

            $table->foreign(
                'created_by_user_id',
                'tip_csm_created_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable();

            $table->foreign(
                'updated_by_user_id',
                'tip_csm_updated_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['capability_key'],
                'tip_csm_capability_uq'
            );

            $table->index(
                ['service_id', 'active'],
                'tip_csm_service_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_implementation_capability_service_mappings');
    }
};
