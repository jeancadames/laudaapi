<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'data_transformation_bi_evaluations',
            function (Blueprint $table): void {
                /*
                 * Diagnostic analysis is deliberately separated from
                 * evidence_snapshot.
                 *
                 * Evidence = what was delivered / observed.
                 * Analysis = deterministic conclusions derived from
                 * that evidence under a versioned diagnostic contract.
                 *
                 * These columns remain nullable for historical
                 * evaluations created before diagnostic-analysis
                 * snapshots existed.
                 */
                $table
                    ->unsignedSmallInteger(
                        'diagnostic_analysis_schema_version'
                    )
                    ->nullable()
                    ->after(
                        'evidence_captured_at'
                    );

                /*
                 * Explicitly pins the analysis to the exact evaluation
                 * evidence revision from which it was generated.
                 */
                $table
                    ->unsignedInteger(
                        'diagnostic_analysis_evidence_version'
                    )
                    ->nullable()
                    ->after(
                        'diagnostic_analysis_schema_version'
                    );

                $table
                    ->char(
                        'diagnostic_analysis_sha256',
                        64
                    )
                    ->nullable()
                    ->after(
                        'diagnostic_analysis_evidence_version'
                    );

                /*
                 * Frozen diagnostic result only.
                 *
                 * No source rows, raw values, samples, staging,
                 * mappings or canonical entities belong here.
                 */
                $table
                    ->json(
                        'diagnostic_analysis_snapshot'
                    )
                    ->nullable()
                    ->after(
                        'diagnostic_analysis_sha256'
                    );

                $table
                    ->timestamp(
                        'diagnostic_analysis_generated_at'
                    )
                    ->nullable()
                    ->after(
                        'diagnostic_analysis_snapshot'
                    );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'data_transformation_bi_evaluations',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'diagnostic_analysis_schema_version',
                    'diagnostic_analysis_evidence_version',
                    'diagnostic_analysis_sha256',
                    'diagnostic_analysis_snapshot',
                    'diagnostic_analysis_generated_at',
                ]);
            }
        );
    }
};
