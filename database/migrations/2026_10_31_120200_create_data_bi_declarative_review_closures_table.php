<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T4F-B2: preparada; NO ejecutar en esta tarea. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_bi_declarative_review_closures', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('transformation_implementation_request_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('data_bi_information_request_id');
            $table->unsignedBigInteger('data_bi_tenant_declaration_id');
            $table->text('conclusion');
            $table->text('limitations');
            $table->json('declared_gaps');
            $table->json('declared_limitations');
            $table->json('clarification_references');
            $table->json('closure_snapshot');
            $table->char('content_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('closed_by_user_id');
            $table->dateTime('closed_at');
            $table->unique('data_bi_tenant_declaration_id', 'dbdrc_declaration_uq');
            $table->index(['transformation_implementation_request_id', 'data_bi_information_request_id'], 'dbdrc_request_version_idx');
            foreach (['transformation_implementation_request_id' => ['transformation_implementation_requests', 'request'],
                'company_id' => ['companies', 'company'], 'data_bi_information_request_id' => ['data_bi_information_requests', 'questionnaire'],
                'data_bi_tenant_declaration_id' => ['data_bi_tenant_declarations', 'declaration'],
                'closed_by_user_id' => ['users', 'actor']] as $column => [$parent, $name]) {
                $table->foreign($column, 'dbdrc_'.$name.'_fk')->references('id')->on($parent)->restrictOnDelete()->restrictOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_bi_declarative_review_closures');
    }
};
