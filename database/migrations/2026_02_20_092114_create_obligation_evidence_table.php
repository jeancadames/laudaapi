<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('obligation_evidence', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instance_id')
                ->constrained('obligation_instances')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('kind', 30)->default('other')->index();

            $table->string('original_name')->nullable();
            $table->string('disk', 40)->default('private');
            $table->string('path', 255);

            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable()->index();

            $table->foreignId('uploaded_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'kind'], 'idx_company_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_evidence');
    }
};
