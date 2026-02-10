<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dgii_document_sequences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->enum('document_class', ['NCF', 'ECF'])->default('NCF');
            $table->string('document_type', 3); // B01, B02, E31, E32...

            $table->string('series')->default(''); // '' = sin serie

            $table->unsignedBigInteger('start_number')->default(1);
            $table->unsignedBigInteger('end_number')->nullable();

            $table->unsignedBigInteger('last_number')->default(0);

            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamp('expires_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            // Índice único con nombre corto para evitar límite de MySQL
            $table->unique(
                ['company_id', 'document_class', 'document_type', 'series'],
                'dgii_docseq_uq'
            );

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dgii_document_sequences');
    }
};
