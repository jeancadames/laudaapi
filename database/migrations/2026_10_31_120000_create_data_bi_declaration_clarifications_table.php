<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T4F-B2: preparada; NO ejecutar en esta tarea. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_bi_declaration_clarifications', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            // ID entero firmado por B1 antes de insertar, generado exclusivamente en servidor.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('transformation_implementation_request_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('data_bi_information_request_id');
            $table->unsignedBigInteger('data_bi_tenant_declaration_id');
            $table->string('requirement_key', 191)->collation('utf8mb4_bin');
            $table->char('question_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->enum('status', ['requested', 'answered', 'resolved', 'cancelled']);
            $table->unsignedTinyInteger('revision');
            $table->json('clarification_snapshot');
            $table->char('content_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            // UTC literal: evita conversiones dependientes del time_zone de la sesión MySQL.
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            // La huella incluye requirement_key exacta y pregunta normalizada por B1.
            $table->unique(['data_bi_tenant_declaration_id', 'question_sha256'], 'dbdc_question_uq');
            $table->index(['transformation_implementation_request_id', 'status'], 'dbdc_request_status_idx');
            foreach (['transformation_implementation_request_id' => ['transformation_implementation_requests', 'request'],
                'company_id' => ['companies', 'company'], 'data_bi_information_request_id' => ['data_bi_information_requests', 'questionnaire'],
                'data_bi_tenant_declaration_id' => ['data_bi_tenant_declarations', 'declaration'],
                'created_by_user_id' => ['users', 'creator'], 'updated_by_user_id' => ['users', 'editor']] as $column => [$parent, $name]) {
                $table->foreign($column, 'dbdc_'.$name.'_fk')->references('id')->on($parent)->restrictOnDelete()->restrictOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_bi_declaration_clarifications');
    }
};
