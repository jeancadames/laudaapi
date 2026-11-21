<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_identity_intents', function (Blueprint $table): void {
            $table->unsignedBigInteger('generation')->nullable();
            $table->text('request_ciphertext')->nullable();
            $table->text('activation_ciphertext')->nullable();
            $table->unique(['link_id', 'central_user_id', 'generation'], 'business_intent_generation');
        });
        Schema::table('business_identity_members', function (Blueprint $table): void {
            $table->unsignedBigInteger('generation')->default(0);
            $table->timestamp('ready_at')->nullable();
        });
        Schema::create('business_identity_proofs', function (Blueprint $table): void {
            $table->char('nonce', 32)->primary();
            // A pending proof must not reserve a permanent company/account association.
            $table->uuid('link_id')->index();
            $table->unsignedBigInteger('central_user_id');
            $table->unsignedBigInteger('local_user_id');
            $table->unsignedBigInteger('local_company_id');
            $table->unsignedBigInteger('generation');
            $table->unsignedBigInteger('expires_at')->index();
            $table->char('request_hash', 64);
            $table->text('payload_ciphertext');
            $table->text('proof_ciphertext');
            $table->text('ready_ciphertext')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['link_id', 'central_user_id', 'generation'], 'business_proof_generation');
        });
    }

    public function down(): void
    {
        if (DB::table('business_identity_proofs')->exists() || DB::table('business_identity_intents')->where('generation', '>', 0)->exists()) {
            throw new RuntimeException('No se puede eliminar el historial de confirmación de identidad.');
        }
        // InnoDB may replace its implicit FK index with the generation index.
        if (! Schema::hasIndex('business_identity_intents', ['link_id'])) {
            Schema::table('business_identity_intents', fn (Blueprint $table) => $table->index('link_id', 'business_identity_intents_link_id_index'));
        }
        Schema::dropIfExists('business_identity_proofs');
        Schema::table('business_identity_members', fn (Blueprint $table) => $table->dropColumn(['generation', 'ready_at']));
        Schema::table('business_identity_intents', function (Blueprint $table): void {
            $table->dropUnique('business_intent_generation');
            $table->dropColumn(['generation', 'request_ciphertext', 'activation_ciphertext']);
        });
    }
};
