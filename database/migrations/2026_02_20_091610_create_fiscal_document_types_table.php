<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_document_types', function (Blueprint $table) {
            $table->id();

            $table->string('country_code', 2)->default('DO')->index();
            $table->string('document_class', 10)->default('ECF')->index(); // ECF|NCF
            $table->string('code', 10)->index(); // E31, E32, B01...
            $table->string('name', 160);
            $table->boolean('active')->default(true)->index();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'document_class', 'code'], 'uniq_doc_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_types');
    }
};
