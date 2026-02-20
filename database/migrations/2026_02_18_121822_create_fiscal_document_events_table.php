<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_document_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')
                ->constrained('fiscal_documents')
                ->cascadeOnDelete();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type', 60)->index(); // status_changed, signed, submitted, dgii_response...
            $table->string('summary', 255)->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['document_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_events');
    }
};
