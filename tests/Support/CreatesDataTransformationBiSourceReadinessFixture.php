<?php

namespace Tests\Support;

use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CreatesDataTransformationBiSourceReadinessFixture
{
    /**
     * Creates a complete client-file source only for Feature fixtures.
     *
     * Everything remains inside the caller's DatabaseTransactions
     * transaction. No physical file is created and no remote client
     * database connection or credential is involved.
     */
    protected function createCompleteDataBiSourceReadinessFixture(
        TransformationImplementationRequest $request,
        User $actor
    ): void {
        $now =
            now();

        $sessionId =
            DB::table(
                'data_transformation_bi_intake_sessions'
            )
                ->insertGetId([
                    'company_id' => (int) $request->company_id,

                    'transformation_implementation_request_id' => (int) $request->id,

                    'transformation_implementation_definition_id' => null,

                    'definition_version' => null,

                    'schema_version' => 2,

                    'status' => 'draft',

                    'created_by_user_id' => (int) $actor->id,

                    'created_at' => $now,

                    'updated_at' => $now,
                ]);

        $sourceAssetId =
            DB::table(
                'data_transformation_bi_source_assets'
            )
                ->insertGetId([
                    'data_transformation_bi_intake_session_id' => $sessionId,

                    'company_id' => (int) $request->company_id,

                    'display_name' => 'Feature Test · Fuente completa',

                    'source_object_name' => 'FEATURE_TEST_SOURCE',

                    'description' => 'Fixture transaccional de readiness dinámico.',

                    'origin_system' => 'Legacy ERP',

                    'delivery_format' => 'csv',

                    'status' => 'active',

                    /*
                     * Structure is optional by contract.
                     */
                    'structure_status' => 'pending',

                    'data_status' => 'received',

                    'sort_order' => 0,

                    'created_by_user_id' => (int) $actor->id,

                    'updated_by_user_id' => (int) $actor->id,

                    'data_received_at' => $now,

                    'created_at' => $now,

                    'updated_at' => $now,
                ]);

        DB::table(
            'data_transformation_bi_source_asset_files'
        )
            ->insert([
                'data_transformation_bi_source_asset_id' => $sourceAssetId,

                'company_id' => (int) $request->company_id,

                'status' => 'uploaded',

                /*
                 * Metadata-only test artifact.
                 * This path is never materialized on disk.
                 */
                'source_disk' => 'local',

                'source_path' => 'feature-tests/not-created/data-bi-request-'
                    .$request->id
                    .'/source.csv',

                'original_filename' => 'feature-source.csv',

                'source_format' => 'csv',

                'source_mime_type' => 'text/csv',

                'source_size_bytes' => 64,

                'source_sha256' => hash(
                    'sha256',
                    'data-bi-feature-source|'
                    .$request->id
                    .'|'
                    .$actor->id
                ),

                'reader_configuration' => null,

                'source_structure_snapshot' => null,

                'source_row_count' => 1,

                'uploaded_by_user_id' => (int) $actor->id,

                'uploaded_at' => $now,

                'created_at' => $now,

                'updated_at' => $now,
            ]);
    }
}
