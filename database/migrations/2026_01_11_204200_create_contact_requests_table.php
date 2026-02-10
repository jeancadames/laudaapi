<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();

            $table->string('topic')->nullable();
            $table->text('message')->nullable();

            $table->boolean('terms')->default(false);

            // Auditoría y trazabilidad
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable()->index();

            $table->timestamps();

            // ✅ Índices para búsquedas / orden
            $table->index('email');
            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
