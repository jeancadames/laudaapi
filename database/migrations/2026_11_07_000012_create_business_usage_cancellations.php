<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_usage_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('identity_link_id')->constrained('business_identity_links')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->timestamp('created_at');
            $table->unique(['identity_link_id', 'operation_key'], 'business_usage_cancellation');
        });
    }

    public function down(): void
    {
        if (DB::table('business_usage_cancellations')->exists()) {
            throw new RuntimeException('Las cancelaciones comerciales impiden admitir solicitudes tardías y se conservan.');
        }
        Schema::dropIfExists('business_usage_cancellations');
    }
};
