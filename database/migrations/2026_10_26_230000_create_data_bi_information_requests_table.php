<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D2F-T2C2: esquema preparado, NO ejecutar migración sin autorización. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_bi_information_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transformation_implementation_request_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->json('review_snapshot');
            $table->char('content_sha256', 64);
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['transformation_implementation_request_id', 'version'], 'dbir_request_version_uq');
            $table->index(['company_id', 'status'], 'dbir_company_status_idx');
            $table->foreign('transformation_implementation_request_id', 'dbir_request_fk')
                ->references('id')->on('transformation_implementation_requests')->restrictOnDelete();
            $table->foreign('company_id', 'dbir_company_fk')
                ->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'dbir_actor_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_bi_information_requests');
    }
};
