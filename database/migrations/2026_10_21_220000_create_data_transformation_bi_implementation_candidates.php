<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_transformation_bi_implementation_candidates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            // Origen histórico: sin FK para sobrevivir a borrados legítimos del origen.
            $table->unsignedBigInteger('origin_request_id');
            $table->unsignedBigInteger('origin_session_id');
            $table->unsignedBigInteger('origin_evaluation_id');
            $table->unsignedBigInteger('origin_challenge_id');
            // Referencias vivas: pueden quedar NULL al eliminar el objeto original.
            $table->unsignedBigInteger('request_ref_id')->nullable();
            $table->unsignedBigInteger('session_ref_id')->nullable();
            $table->unsignedBigInteger('evaluation_ref_id')->nullable();
            $table->unsignedBigInteger('challenge_ref_id')->nullable();
            $table->unsignedInteger('evidence_version');
            $table->unsignedBigInteger('selected_by_user_id')->nullable();
            $table->unsignedBigInteger('selected_actor_id');
            $table->dateTime('selected_at', 6);
            $table->json('origin_snapshot');
            $table->char('origin_sha256', 64);
            $table->timestamps();

            // Unicidad en la BD, no solo firstOrCreate o locks.
            $table->unique('origin_challenge_id', 'dtbi_h1_candidate_challenge_uq');
            $table->index(['company_id','origin_request_id','selected_at'], 'dtbi_h1_candidate_company_req_idx');
            $table->index(['origin_evaluation_id','selected_at'], 'dtbi_h1_candidate_eval_idx');

            $table->foreign('company_id','dtbi_h1_candidate_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('request_ref_id','dtbi_h1_candidate_req_fk')
                ->references('id')->on('transformation_implementation_requests')->nullOnDelete();
            $table->foreign('session_ref_id','dtbi_h1_candidate_session_fk')
                ->references('id')->on('data_transformation_bi_intake_sessions')->nullOnDelete();
            $table->foreign('evaluation_ref_id','dtbi_h1_candidate_eval_fk')
                ->references('id')->on('data_transformation_bi_evaluations')->nullOnDelete();
            $table->foreign('challenge_ref_id','dtbi_h1_candidate_chal_fk')
                ->references('id')->on('data_transformation_bi_evaluation_implementation_challenges')->nullOnDelete();
            $table->foreign('selected_by_user_id','dtbi_h1_candidate_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_transformation_bi_implementation_candidates');
    }
};
