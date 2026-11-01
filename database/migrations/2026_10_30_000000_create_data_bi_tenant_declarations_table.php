<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D2F-T4C: preparada para MySQL DEV; no ejecutar sin autorización posterior. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_bi_tenant_declarations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('data_bi_information_request_id');
            $table->unsignedBigInteger('company_id');
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->json('declaration_snapshot');
            $table->char('content_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Una fila por versión publicada, incluso si se intenta cambiar la empresa.
            $table->unique('data_bi_information_request_id', 'dbtd_questionnaire_uq');
            $table->index(['company_id', 'status'], 'dbtd_company_status_idx');
            $table->foreign('data_bi_information_request_id', 'dbtd_questionnaire_fk')
                ->references('id')->on('data_bi_information_requests')->restrictOnDelete();
            $table->foreign('company_id', 'dbtd_company_fk')
                ->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'dbtd_creator_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by_user_id', 'dbtd_editor_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('submitted_by_user_id', 'dbtd_submitter_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_bi_tenant_declarations');
    }
};
