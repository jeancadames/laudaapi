<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table->string(
                    'profiling_status',
                    32
                )
                    ->default('idle')
                    ->after('profiling_snapshot');

                $table->uuid(
                    'profiling_job_uuid'
                )
                    ->nullable()
                    ->after('profiling_status');

                $table->timestamp(
                    'profiling_queued_at'
                )
                    ->nullable()
                    ->after('profiling_job_uuid');

                $table->timestamp(
                    'profiling_started_at'
                )
                    ->nullable()
                    ->after('profiling_queued_at');

                $table->timestamp(
                    'profiling_finished_at'
                )
                    ->nullable()
                    ->after('profiling_started_at');

                $table->index(
                    [
                        'company_id',
                        'profiling_status',
                    ],
                    'dtbi_source_assets_profiling_status_idx'
                );
            }
        );

        /*
         * Preserve the semantic state of any source that had already been
         * successfully profiled before async orchestration was introduced.
         */
        DB::table(
            'data_transformation_bi_source_assets'
        )
            ->whereNotNull(
                'profiled_at'
            )
            ->whereNotNull(
                'profiling_snapshot'
            )
            ->update([
                'profiling_status' =>
                    'completed',
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_source_assets',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'dtbi_source_assets_profiling_status_idx'
                );

                $table->dropColumn([
                    'profiling_status',
                    'profiling_job_uuid',
                    'profiling_queued_at',
                    'profiling_started_at',
                    'profiling_finished_at',
                ]);
            }
        );
    }
};
