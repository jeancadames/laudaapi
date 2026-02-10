<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | 1) Support Tickets
        |----------------------------------------------------------------------
        | - Soporte tipo “ticket” (thread).
        | - Scope por company (multi-tenant).
        | - user_id nullable (histórico no se rompe si borras users).
        */
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // staff/admin asignado (opcional)
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('number', 30)->unique(); // ej: SUP-2026-000123 (lo generas en el controller/service)
            $table->string('subject', 255);

            // open | pending | answered | closed
            $table->enum('status', ['open', 'pending', 'answered', 'closed'])->default('open')->index();

            // low | normal | high | urgent
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index();

            // web | email | whatsapp | other
            $table->enum('channel', ['web', 'email', 'whatsapp', 'other'])->default('web')->index();

            $table->timestamp('last_reply_at')->nullable()->index();
            $table->timestamp('first_response_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();

            // Metadata extensible: etiquetas, producto/servicio relacionado, etc.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        /*
        |----------------------------------------------------------------------
        | 2) Ticket Messages
        |----------------------------------------------------------------------
        | - Conversación del ticket (thread).
        | - is_staff: para diferenciar respuesta staff vs usuario.
        */
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_staff')->default(false)->index();

            $table->longText('body');

            // opcional: [{name,url,size,mime}, ...]
            $table->json('attachments')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        /*
        |----------------------------------------------------------------------
        | 3) FAQ Categories (Q&A)
        |----------------------------------------------------------------------
        | - Base de conocimiento / preguntas frecuentes.
        */
        Schema::create('support_faq_categories', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique(); // ej: "facturacion", "pagos", "api"
            $table->string('title', 120);
            $table->string('description', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();

            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });

        /*
        |----------------------------------------------------------------------
        | 4) FAQ Items (Q&A)
        |----------------------------------------------------------------------
        | - question + answer (publicable).
        | - view_count + helpful stats para ranking.
        */
        Schema::create('support_faq_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('support_faq_categories')
                ->nullOnDelete();

            $table->string('slug')->unique(); // ej: "como-cambiar-razon-social"
            $table->string('question', 255);
            $table->longText('answer')->nullable();

            // tags: ["dgii","ncf","itbis"]
            $table->json('tags')->nullable();

            $table->boolean('is_public')->default(true)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_up')->default(0);
            $table->unsignedInteger('helpful_down')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['category_id', 'is_published']);
        });

        /*
        |----------------------------------------------------------------------
        | 5) FAQ Votes (helpful)
        |----------------------------------------------------------------------
        | - Permite votar si fue útil o no.
        | - Soporta usuarios logueados y anónimos (ip + user_agent).
        */
        Schema::create('support_faq_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('faq_item_id')
                ->constrained('support_faq_items')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_helpful')->index();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            // evita spam: 1 voto por usuario por item
            $table->unique(['faq_item_id', 'user_id']);

            // opcional para anónimos: si quieres, puedes reforzar por IP (no perfecto pero útil)
            $table->index(['faq_item_id', 'ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_faq_votes');
        Schema::dropIfExists('support_faq_items');
        Schema::dropIfExists('support_faq_categories');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
