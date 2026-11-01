<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2F-T3B.
 * Registra quién envía una versión del cuestionario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_bi_information_requests',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('sent_by_user_id')
                    ->nullable()
                    ->after('sent_at');

                $table->foreign(
                    'sent_by_user_id',
                    'dbir_sent_actor_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_bi_information_requests',
            function (Blueprint $table): void {
                $table->dropForeign('dbir_sent_actor_fk');
                $table->dropColumn('sent_by_user_id');
            }
        );
    }
};
