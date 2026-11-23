<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_transfer_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_key');
            $table->unique(['invoice_id', 'submission_key']);
            $table->string('reference', 100);
            $table->string('file_path');
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('business_transfer_proofs')->exists()) {
            throw new RuntimeException('Los comprobantes de transferencia conservan el historial de revisión.');
        }
        Schema::dropIfExists('business_transfer_proofs');
    }
};
