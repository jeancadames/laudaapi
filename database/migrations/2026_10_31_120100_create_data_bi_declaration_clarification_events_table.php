<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T4F-B2: preparada; NO ejecutar en esta tarea. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_bi_declaration_clarification_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('data_bi_declaration_clarification_id');
            $table->unsignedTinyInteger('sequence');
            $table->enum('action', ['request', 'answer', 'resolve', 'cancel']);
            $table->text('message');
            $table->unsignedBigInteger('actor_user_id');
            $table->enum('actor_kind', ['lauda_admin', 'tenant']);
            $table->dateTime('occurred_at');
            $table->char('previous_sha256', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('projection_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('content_sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->unique(['data_bi_declaration_clarification_id', 'sequence'], 'dbdce_sequence_uq');
            $table->foreign('data_bi_declaration_clarification_id', 'dbdce_clarification_fk')
                ->references('id')->on('data_bi_declaration_clarifications')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('actor_user_id', 'dbdce_actor_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_bi_declaration_clarification_events');
    }
};
