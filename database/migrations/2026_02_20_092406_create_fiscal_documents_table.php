<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->ulid('public_id')->unique();

            $table->foreignId('document_type_id')
                ->constrained('fiscal_document_types')
                ->restrictOnDelete();

            $table->foreignId('dgii_sequence_id')
                ->nullable()
                ->constrained('dgii_document_sequences')
                ->nullOnDelete();

            $table->foreignId('buyer_party_id')
                ->nullable()
                ->constrained('fiscal_parties')
                ->nullOnDelete();

            // Versioning simple
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('fiscal_documents')
                ->nullOnDelete();

            $table->unsignedInteger('version')->default(1);

            // Inbound origin (Sales/RRHH/etc)
            $table->string('source_module', 40)->nullable()->index();
            $table->string('source_ref', 120)->nullable()->index();
            $table->string('idempotency_key', 120)->nullable();

            $table->string('external_ref', 80)->nullable()->index();

            // NCF/eCF final
            $table->string('number', 60)->nullable()->index();

            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();

            $table->enum('status', [
                'draft',
                'received',   // ✅ nuevo (XML llegó desde módulos)
                'issued',
                'signed',
                'submitted',
                'accepted',
                'rejected',
                'voided',
            ])->default('draft')->index();

            $table->enum('target_environment', ['precert', 'cert', 'prod'])->default('precert')->index();

            $table->enum('currency', ['USD', 'DOP', 'EUR'])->default('DOP');
            $table->decimal('exchange_rate', 18, 6)->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);

            // XML storage
            $table->string('unsigned_xml_path', 255)->nullable();
            $table->string('signed_xml_path', 255)->nullable();
            $table->string('unsigned_xml_sha256', 64)->nullable()->index();
            $table->string('signed_xml_sha256', 64)->nullable()->index();

            $table->timestamp('received_at')->nullable()->index(); // cuando entró
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->json('payload')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_company_status_doc');

            $table->unique(['company_id', 'number'], 'uniq_company_doc_number');
            $table->unique(['company_id', 'external_ref'], 'uniq_company_external_ref');

            $table->unique(['company_id', 'source_module', 'source_ref'], 'uniq_doc_source_ref');
            $table->unique(['company_id', 'idempotency_key'], 'uniq_doc_idempo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
