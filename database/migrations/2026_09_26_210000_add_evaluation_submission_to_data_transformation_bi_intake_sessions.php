<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_intake_sessions',
            function (Blueprint $table): void {
                $table->timestamp(
                    'submitted_at'
                )
                    ->nullable()
                    ->after('ready_at');

                $table->unsignedBigInteger(
                    'submitted_by_user_id'
                )
                    ->nullable()
                    ->after('submitted_at');

                /*
                 * SHA-256 over the frozen tenant-controlled source manifest.
                 *
                 * No raw values, profiling snapshots or diagnostic results
                 * are stored in this evidence.
                 */
                $table->char(
                    'submitted_manifest_sha256',
                    64
                )
                    ->nullable()
                    ->after('submitted_by_user_id');

                $table->foreign(
                    'submitted_by_user_id',
                    'dtbi_intake_sessions_submitted_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_intake_sessions',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'dtbi_intake_sessions_submitted_user_fk'
                );

                $table->dropColumn([
                    'submitted_manifest_sha256',
                    'submitted_by_user_id',
                    'submitted_at',
                ]);
            }
        );
    }
};
