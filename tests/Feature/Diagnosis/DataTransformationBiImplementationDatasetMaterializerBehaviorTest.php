<?php

namespace Tests\Feature\Diagnosis;

use App\Models\Company;
use App\Models\DataTransformationBiCanonicalEntity;
use App\Models\DataTransformationBiCanonicalField;
use App\Models\DataTransformationBiCanonicalRegistryVersion;
use App\Models\DataTransformationBiImplementationDataset;
use App\Models\DataTransformationBiImplementationRow;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFieldMapping;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\DataTransformationBiSourceAssetMapping;
use App\Models\TransformationImplementationAuthorization;
use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiImplementationDatasetMaterializer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DataTransformationBiImplementationDatasetMaterializerBehaviorTest
    extends TestCase
{
    use DatabaseTransactions;

    private array $createdPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (
            app()->environment() !== 'testing'
            || \DB::connection()->getDriverName() !== 'mysql'
            || \DB::connection()->getDatabaseName() !== 'laudaapi_dev'
        ) {
            throw new \RuntimeException(
                'R84 behavior test requires testing + mysql + laudaapi_dev.'
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            Storage::disk('private')
                ->delete($path);
        }

        parent::tearDown();
    }

    public function test_materializes_real_rows_and_reuses_ready_dataset(): void
    {
        $fixture =
            $this->makeFixture(
                [
                    [
                        'customer_id',
                        'name',
                        'balance',
                    ],
                    [
                        'C-001',
                        'Cliente Uno',
                        '100.50',
                    ],
                    [
                        'C-002',
                        'Cliente Dos',
                        '250.75',
                    ],
                ]
            );

        $materializer =
            app(
                DataTransformationBiImplementationDatasetMaterializer::class
            );

        $first =
            $materializer->materialize(
                $fixture['request'],
                $fixture['session'],
                $fixture['asset'],
                $fixture['mapping'],
                $fixture['admin']
            );

        $this->assertSame(
            DataTransformationBiImplementationDataset::STATUS_READY,
            $first->status
        );

        $this->assertSame(
            2,
            (int) $first->row_count
        );

        $this->assertNotNull(
            $first->dataset_sha256
        );

        $this->assertSame(
            64,
            strlen(
                (string) $first->dataset_sha256
            )
        );

        $rows =
            DataTransformationBiImplementationRow::query()
                ->where(
                    'data_transformation_bi_implementation_dataset_id',
                    $first->getKey()
                )
                ->orderBy('source_row_number')
                ->get();

        $this->assertCount(
            2,
            $rows
        );

        $firstPayload =
            $rows[0]->canonical_payload;

        ksort(
            $firstPayload,
            SORT_STRING
        );

        $this->assertSame(
            [
                'balance' =>
                    '100.5',

                'customer_id' =>
                    'C-001',

                'name' =>
                    'Cliente Uno',
            ],
            $firstPayload
        );

        $secondPayload =
            $rows[1]->canonical_payload;

        ksort(
            $secondPayload,
            SORT_STRING
        );

        $this->assertSame(
            [
                'balance' =>
                    '250.75',

                'customer_id' =>
                    'C-002',

                'name' =>
                    'Cliente Dos',
            ],
            $secondPayload
        );

        foreach ($rows as $row) {
            $this->assertSame(
                64,
                strlen(
                    (string) $row->source_row_sha256
                )
            );

            $this->assertSame(
                64,
                strlen(
                    (string) $row->canonical_payload_sha256
                )
            );
        }

        $second =
            $materializer->materialize(
                $fixture['request'],
                $fixture['session'],
                $fixture['asset'],
                $fixture['mapping'],
                $fixture['admin']
            );

        $this->assertSame(
            (int) $first->getKey(),
            (int) $second->getKey()
        );

        $this->assertSame(
            1,
            DataTransformationBiImplementationDataset::query()
                ->where(
                    'data_transformation_bi_source_asset_mapping_id',
                    $fixture['mapping']->getKey()
                )
                ->count()
        );

        $this->assertSame(
            2,
            DataTransformationBiImplementationRow::query()
                ->where(
                    'data_transformation_bi_implementation_dataset_id',
                    $first->getKey()
                )
                ->count()
        );
    }

    public function test_mid_stream_projection_failure_rolls_back_dataset_and_rows(): void
    {
        $fixture =
            $this->makeFixture(
                [
                    [
                        'customer_id',
                        'name',
                        'balance',
                    ],
                    [
                        'C-001',
                        'Cliente Uno',
                        '100.50',
                    ],
                    [
                        'C-002',
                        'Cliente Dos',
                        'NOT_A_DECIMAL',
                    ],
                ]
            );

        $materializer =
            app(
                DataTransformationBiImplementationDatasetMaterializer::class
            );

        try {
            $materializer->materialize(
                $fixture['request'],
                $fixture['session'],
                $fixture['asset'],
                $fixture['mapping'],
                $fixture['admin']
            );

            $this->fail(
                'Expected projection failure was not raised.'
            );
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(
            0,
            DataTransformationBiImplementationDataset::query()
                ->where(
                    'data_transformation_bi_source_asset_mapping_id',
                    $fixture['mapping']->getKey()
                )
                ->count()
        );

        $this->assertSame(
            0,
            DataTransformationBiImplementationRow::query()
                ->where(
                    'company_id',
                    $fixture['company']->getKey()
                )
                ->count()
        );
    }


    public function test_session_orchestrator_materializes_all_current_validated_mappings(): void
    {
        $fixture =
            $this->makeFixture([
                [
                    'customer_id',
                    'name',
                    'balance',
                ],
                [
                    'C-001',
                    'Cliente Uno',
                    '100.5',
                ],
                [
                    'C-002',
                    'Cliente Dos',
                    '250.75',
                ],
            ]);

        $second =
            $this->cloneCurrentMappingTarget(
                $fixture
            );

        $orchestrator =
            app(
                \App\Services\Diagnosis\DataTransformationBiImplementationDatasetOrchestrator::class
            );

        $first =
            $orchestrator->materializeSession(
                $fixture['request'],
                $fixture['session'],
                $fixture['admin']
            );

        $this->assertSame(
            2,
            $first['selected_mapping_count']
        );

        $this->assertSame(
            2,
            $first['materialized_dataset_count']
        );

        $this->assertSame(
            0,
            $first['reused_dataset_count']
        );

        $this->assertCount(
            2,
            $first['datasets']
        );

        $datasetMappingIds =
            collect(
                $first['datasets']
            )
                ->pluck(
                    'mapping_id'
                )
                ->sort()
                ->values()
                ->all();

        $expectedMappingIds =
            collect([
                (int) $fixture['mapping']->getKey(),
                (int) $second['mapping']->getKey(),
            ])
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            $expectedMappingIds,
            $datasetMappingIds
        );

        $this->assertSame(
            2,
            DataTransformationBiImplementationDataset::query()
                ->count()
        );

        $this->assertSame(
            4,
            \App\Models\DataTransformationBiImplementationRow::query()
                ->count()
        );

        $secondRun =
            $orchestrator->materializeSession(
                $fixture['request'],
                $fixture['session'],
                $fixture['admin']
            );

        $this->assertSame(
            2,
            $secondRun['selected_mapping_count']
        );

        $this->assertSame(
            2,
            $secondRun['materialized_dataset_count']
        );

        $this->assertSame(
            2,
            $secondRun['reused_dataset_count']
        );

        $this->assertSame(
            2,
            DataTransformationBiImplementationDataset::query()
                ->count()
        );

        $this->assertSame(
            4,
            \App\Models\DataTransformationBiImplementationRow::query()
                ->count()
        );
    }

    public function test_session_orchestrator_never_falls_back_to_older_validated_mapping(): void
    {
        $fixture =
            $this->makeFixture([
                [
                    'customer_id',
                    'name',
                    'balance',
                ],
                [
                    'C-001',
                    'Cliente Uno',
                    '100.5',
                ],
                [
                    'C-002',
                    'Cliente Dos',
                    '250.75',
                ],
            ]);

        /*
         * mapping v1 is VALIDATED.
         *
         * Create mapping v2 for the exact same logical target but leave it
         * DRAFT. The orchestrator must select v2 as current and reject the
         * session. It must NEVER silently fall back to validated v1.
         */
        $newer =
            $fixture['mapping']
                ->replicate();

        $newer->forceFill([
            'mapping_version' =>
                (int) $fixture['mapping']->mapping_version
                + 1,

            'status' =>
                DataTransformationBiSourceAssetMapping
                    ::STATUS_DRAFT,

            'validated_by_user_id' =>
                null,

            'validated_at' =>
                null,

            'created_by_user_id' =>
                $fixture['admin']->getKey(),

            'updated_by_user_id' =>
                $fixture['admin']->getKey(),
        ]);

        $newer->save();

        $orchestrator =
            app(
                \App\Services\Diagnosis\DataTransformationBiImplementationDatasetOrchestrator::class
            );

        $caught =
            null;

        try {
            $orchestrator->materializeSession(
                $fixture['request'],
                $fixture['session'],
                $fixture['admin']
            );
        } catch (
            \Illuminate\Validation\ValidationException $exception
        ) {
            $caught =
                $exception;
        }

        $this->assertInstanceOf(
            \Illuminate\Validation\ValidationException::class,
            $caught
        );

        $this->assertArrayHasKey(
            'mappings',
            $caught->errors()
        );

        $this->assertSame(
            0,
            DataTransformationBiImplementationDataset::query()
                ->count()
        );

        $this->assertSame(
            0,
            \App\Models\DataTransformationBiImplementationRow::query()
                ->count()
        );

        $this->assertSame(
            DataTransformationBiSourceAssetMapping
                ::STATUS_VALIDATED,
            (string) $fixture['mapping']
                ->fresh()
                ->status
        );

        $this->assertSame(
            DataTransformationBiSourceAssetMapping
                ::STATUS_DRAFT,
            (string) $newer
                ->fresh()
                ->status
        );
    }

    /**
     * Create a second independent current mapping target in the same
     * Request + Session without inventing a second lifecycle.
     *
     * We clone the already-valid source contract from the proven R84 fixture,
     * including its exact artifact/profile pin and field decisions.
     */
    private function cloneCurrentMappingTarget(
        array $fixture
    ): array {
        $sourceAsset =
            $fixture['asset'];

        $sourceFile =
            $fixture['artifact'];

        $mapping =
            $fixture['mapping'];

        $clonedAsset =
            $sourceAsset->replicate();

        $clonedAsset->forceFill([
            'display_name' =>
                'R89 · Customers Second Source',

            'source_object_name' =>
                'R89_CUSTOMERS_SECOND',

            'created_by_user_id' =>
                $fixture['admin']->getKey(),

            'updated_by_user_id' =>
                $fixture['admin']->getKey(),
        ]);

        $clonedAsset->save();

        $clonedFile =
            $sourceFile->replicate();

        $clonedFile->forceFill([
            'data_transformation_bi_source_asset_id' =>
                $clonedAsset->getKey(),

            'uploaded_by_user_id' =>
                $fixture['admin']->getKey(),

            'uploaded_at' =>
                now(),
        ]);

        $clonedFile->save();

        $profileSnapshot =
            (array) $clonedAsset
                ->profiling_snapshot;

        $profileSnapshot['source_file_id'] =
            (int) $clonedFile->getKey();

        $clonedAsset->forceFill([
            'profiling_snapshot' =>
                $profileSnapshot,
        ])->save();

        $clonedMapping =
            $mapping->replicate();

        $clonedMapping->forceFill([
            'data_transformation_bi_source_asset_id' =>
                $clonedAsset->getKey(),

            'data_transformation_bi_source_asset_file_id' =>
                $clonedFile->getKey(),

            'mapping_version' =>
                1,

            'status' =>
                DataTransformationBiSourceAssetMapping
                    ::STATUS_VALIDATED,

            'created_by_user_id' =>
                $fixture['admin']->getKey(),

            'updated_by_user_id' =>
                $fixture['admin']->getKey(),

            'validated_by_user_id' =>
                $fixture['admin']->getKey(),

            'validated_at' =>
                now(),
        ]);

        $clonedMapping->save();

        $fieldMappings =
            DataTransformationBiSourceAssetFieldMapping::query()
                ->where(
                    'data_transformation_bi_source_asset_mapping_id',
                    (int) $mapping->getKey()
                )
                ->orderBy('id')
                ->get();

        foreach ($fieldMappings as $fieldMapping) {
            $clone =
                $fieldMapping->replicate();

            $clone->forceFill([
                'data_transformation_bi_source_asset_mapping_id' =>
                    $clonedMapping->getKey(),

                'created_by_user_id' =>
                    $fixture['admin']->getKey(),

                'updated_by_user_id' =>
                    $fixture['admin']->getKey(),

                'validated_by_user_id' =>
                    $fixture['admin']->getKey(),

                'validated_at' =>
                    now(),
            ]);

            $clone->save();
        }

        return [
            'asset' =>
                $clonedAsset,

            'artifact' =>
                $clonedFile,

            'mapping' =>
                $clonedMapping,
        ];
    }

    private function makeFixture(
        array $csvRows
    ): array {
        /*
         * Base rows are READ-ONLY references.
         *
         * We only reuse an existing DEV Request to obtain valid parent FK
         * identities for Plan / Phase Capability / Assessment / Company.
         *
         * The test creates its own Request + complete modern lifecycle
         * inside DatabaseTransactions.
         */
        $template =
            TransformationImplementationRequest::query()
                ->where(
                    'capability_key',
                    'data_transformation_bi'
                )
                ->whereNotNull(
                    'transformation_implementation_plan_id'
                )
                ->whereNotNull(
                    'transformation_implementation_phase_capability_id'
                )
                ->firstOrFail();

        $admin =
            User::query()
                ->where(
                    'role',
                    'admin'
                )
                ->firstOrFail();

        $company =
            Company::query()
                ->findOrFail(
                    (int) $template->company_id
                );

        $nextAttempt =
            (
                TransformationImplementationRequest::query()
                    ->where(
                        'company_id',
                        $company->getKey()
                    )
                    ->where(
                        'capability_key',
                        'data_transformation_bi'
                    )
                    ->max('attempt')
                ?? 0
            ) + 100;

        $request =
            TransformationImplementationRequest::query()
                ->create([
                    'company_id' =>
                        $company->getKey(),

                    'diagnosis_assessment_id' =>
                        $template->diagnosis_assessment_id,

                    'transformation_implementation_plan_id' =>
                        $template
                            ->transformation_implementation_plan_id,

                    'transformation_implementation_phase_capability_id' =>
                        $template
                            ->transformation_implementation_phase_capability_id,

                    'capability_key' =>
                        'data_transformation_bi',

                    'attempt' =>
                        $nextAttempt,

                    'source_type' =>
                        $template->source_type
                        ?: 'diagnosis',

                    'status' =>
                        'ready_for_commercial',

                    'source_snapshot' => [
                        'fixture' =>
                            'R84',
                    ],

                    'requested_by_user_id' =>
                        $admin->getKey(),

                    'status_changed_by_user_id' =>
                        $admin->getKey(),

                    'requested_at' =>
                        now(),

                    'ready_for_commercial_at' =>
                        now(),
                ]);

        $definition =
            TransformationImplementationDefinition::query()
                ->create([
                    'transformation_implementation_plan_id' =>
                        $request
                            ->transformation_implementation_plan_id,

                    'diagnosis_assessment_id' =>
                        $request->diagnosis_assessment_id,

                    'company_id' =>
                        $company->getKey(),

                    'transformation_implementation_request_id' =>
                        $request->getKey(),

                    'transformation_implementation_phase_capability_id' =>
                        $request
                            ->transformation_implementation_phase_capability_id,

                    'capability_key' =>
                        'data_transformation_bi',

                    'version' =>
                        1,

                    'status' =>
                        TransformationImplementationDefinition
                            ::STATUS_READY,

                    'source_snapshot' => [
                        'fixture' =>
                            'R84',
                    ],

                    'implementation_scope' => [
                        'fixture' =>
                            true,
                    ],

                    'deliverables' =>
                        [],

                    'dependencies' =>
                        [],

                    'responsibility_model' =>
                        [],

                    'readiness' => [
                        'state' =>
                            'ready',

                        'definition_ready' =>
                            true,

                        'technical_readiness' =>
                            true,

                        'ready_for_execution' =>
                            false,

                        'execution_started' =>
                            false,
                    ],

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'reviewed_by_user_id' =>
                        $admin->getKey(),

                    'reviewed_at' =>
                        now(),

                    'ready_at' =>
                        now(),
                ]);

        $engagement =
            TransformationImplementationCommercialEngagement::query()
                ->create([
                    'transformation_implementation_request_id' =>
                        $request->getKey(),

                    'transformation_implementation_definition_id' =>
                        $definition->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'transformation_implementation_phase_capability_id' =>
                        $request
                            ->transformation_implementation_phase_capability_id,

                    'capability_key' =>
                        'data_transformation_bi',

                    'version' =>
                        1,

                    'status' =>
                        TransformationImplementationCommercialEngagement
                            ::STATUS_ACCEPTED,

                    'currency' =>
                        'DOP',

                    'price_amount' =>
                        '1000.00',

                    'duration_days' =>
                        1,

                    'scope_snapshot' => [
                        'fixture' =>
                            'R84',
                    ],

                    'deliverables_snapshot' =>
                        [],

                    'commercial_terms_snapshot' => [
                        'fixture' =>
                            'R84',
                    ],

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'presented_by_user_id' =>
                        $admin->getKey(),

                    'accepted_by_user_id' =>
                        $admin->getKey(),

                    'presented_at' =>
                        now(),

                    'accepted_at' =>
                        now(),
                ]);

        TransformationImplementationAuthorization::query()
            ->create([
                'transformation_implementation_commercial_engagement_id' =>
                    $engagement->getKey(),

                'transformation_implementation_request_id' =>
                    $request->getKey(),

                'transformation_implementation_definition_id' =>
                    $definition->getKey(),

                'company_id' =>
                    $company->getKey(),

                'transformation_implementation_phase_capability_id' =>
                    $request
                        ->transformation_implementation_phase_capability_id,

                'capability_key' =>
                    'data_transformation_bi',

                'status' =>
                    TransformationImplementationAuthorization
                        ::STATUS_AUTHORIZED,

                'authorization_snapshot' => [
                    'request' => [
                        'id' =>
                            (int) $request->getKey(),

                        'status' =>
                            'ready_for_commercial',
                    ],

                    'definition' => [
                        'id' =>
                            (int) $definition->getKey(),

                        'version' =>
                            (int) $definition->version,

                        'definition_ready' =>
                            true,

                        'technical_readiness' =>
                            true,

                        'ready_for_execution' =>
                            false,

                        'execution_started' =>
                            false,
                    ],

                    'commercial_engagement' => [
                        'id' =>
                            (int) $engagement->getKey(),

                        'version' =>
                            (int) $engagement->version,

                        'status' =>
                            TransformationImplementationCommercialEngagement
                                ::STATUS_ACCEPTED,

                        'accepted_by_user_id' =>
                            (int) $admin->getKey(),
                    ],

                    'scope' => [
                        'company_id' =>
                            (int) $company->getKey(),

                        'phase_capability_id' =>
                            (int) $request
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            'data_transformation_bi',
                    ],

                    'authorization_boundary' => [
                        'commercial_acceptance' =>
                            true,

                        'implementation_authorized' =>
                            true,

                        'ready_for_execution_mutated' =>
                            false,

                        'execution_started' =>
                            false,

                        'canonical_write' =>
                            false,
                    ],
                ],

                'authorized_by_user_id' =>
                    $admin->getKey(),

                'authorized_at' =>
                    now(),

                'revoked_at' =>
                    null,
            ]);

        $session =
            DataTransformationBiIntakeSession::query()
                ->create([
                    'company_id' =>
                        $company->getKey(),

                    'transformation_implementation_request_id' =>
                        $request->getKey(),

                    'transformation_implementation_definition_id' =>
                        $definition->getKey(),

                    'definition_version' =>
                        1,

                    'schema_version' =>
                        1,

                    'status' =>
                        DataTransformationBiIntakeSession
                            ::STATUS_READY,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'started_at' =>
                        now(),

                    'ready_at' =>
                        now(),
                ]);

        $asset =
            DataTransformationBiSourceAsset::query()
                ->create([
                    'data_transformation_bi_intake_session_id' =>
                        $session->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'display_name' =>
                        'R84 · Customers',

                    'source_object_name' =>
                        'R84_CUSTOMERS',

                    'description' =>
                        'Fixture transaccional del materializer moderno.',

                    'origin_system' =>
                        'R84',

                    'owner' =>
                        'LAUDA QA',

                    'business_domains' => [
                        'customers',
                    ],

                    'structure_format' =>
                        DataTransformationBiSourceAsset
                            ::STRUCTURE_FORMAT_FIELD_TYPE_LIST,

                    'delivery_format' =>
                        DataTransformationBiSourceAsset
                            ::DELIVERY_CSV,

                    'status' =>
                        DataTransformationBiSourceAsset
                            ::STATUS_READY,

                    'structure_status' =>
                        DataTransformationBiSourceAsset
                            ::STRUCTURE_ANALYZED,

                    'data_status' =>
                        DataTransformationBiSourceAsset
                            ::DATA_ANALYZED,

                    'profiling_status' =>
                        DataTransformationBiSourceAsset
                            ::PROFILING_COMPLETED,

                    'sort_order' =>
                        0,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'structure_analyzed_at' =>
                        now(),

                    'data_received_at' =>
                        now(),

                    'profiled_at' =>
                        now(),
                ]);

        $csv =
            collect(
                $csvRows
            )
                ->map(
                    static fn (array $row): string =>
                        implode(
                            ',',
                            array_map(
                                static fn ($value): string =>
                                    '"'
                                    .str_replace(
                                        '"',
                                        '""',
                                        (string) $value
                                    )
                                    .'"',
                                $row
                            )
                        )
                )
                ->implode("\n")
            ."\n";

        $sha =
            hash(
                'sha256',
                $csv
            );

        $path =
            'data-transformation-bi/'
            .'r84/'
            .uniqid(
                'materializer-',
                true
            )
            .'.csv';

        Storage::disk('private')
            ->put(
                $path,
                $csv
            );

        $this->createdPaths[] =
            $path;

        $artifact =
            DataTransformationBiSourceAssetFile::query()
                ->create([
                    'data_transformation_bi_source_asset_id' =>
                        $asset->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'status' =>
                        DataTransformationBiSourceAssetFile
                            ::STATUS_UPLOADED,

                    'source_disk' =>
                        'private',

                    'source_path' =>
                        $path,

                    'original_filename' =>
                        'r84.csv',

                    'source_format' =>
                        DataTransformationBiSourceAssetFile
                            ::FORMAT_CSV,

                    'source_mime_type' =>
                        'text/csv',

                    'source_size_bytes' =>
                        strlen($csv),

                    'source_sha256' =>
                        $sha,

                    'reader_configuration' => [
                        'header_row' =>
                            1,

                        'delimiter_character' =>
                            ',',

                        'encoding' =>
                            'UTF-8',
                    ],

                    'source_structure_snapshot' => [
                        'sheets' => [
                            [
                                'index' =>
                                    0,

                                'name' =>
                                    null,

                                'total_row_count' =>
                                    count($csvRows),

                                'row_count' =>
                                    count($csvRows) - 1,

                                'column_count' =>
                                    3,

                                'columns' => [
                                    [
                                        'index' => 0,
                                        'key' => 'column_1',
                                        'header' => 'customer_id',
                                    ],
                                    [
                                        'index' => 1,
                                        'key' => 'column_2',
                                        'header' => 'name',
                                    ],
                                    [
                                        'index' => 2,
                                        'key' => 'column_3',
                                        'header' => 'balance',
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'source_row_count' =>
                        count($csvRows) - 1,

                    'uploaded_by_user_id' =>
                        $admin->getKey(),

                    'uploaded_at' =>
                        now(),
                ]);

        $profileVersion = 1;

        $asset->forceFill([
            'profiling_snapshot' => [
                'version' =>
                    $profileVersion,

                'source_file_id' =>
                    (int) $artifact->getKey(),

                'source_sha256' =>
                    $sha,

                'sheets' => [
                    [
                        'index' =>
                            0,

                        'name' =>
                            null,

                        'columns' => [
                            [
                                'index' => 0,
                                'key' => 'column_1',
                                'header' => 'customer_id',
                            ],
                            [
                                'index' => 1,
                                'key' => 'column_2',
                                'header' => 'name',
                            ],
                            [
                                'index' => 2,
                                'key' => 'column_3',
                                'header' => 'balance',
                            ],
                        ],
                    ],
                ],
            ],
        ])->save();

        $registryVersion =
            (
                DataTransformationBiCanonicalRegistryVersion::query()
                    ->where(
                        'company_id',
                        $company->getKey()
                    )
                    ->max('version')
                ?? 0
            ) + 1;

        $registry =
            DataTransformationBiCanonicalRegistryVersion::query()
                ->create([
                    'company_id' =>
                        $company->getKey(),

                    'source_transformation_implementation_request_id' =>
                        $request->getKey(),

                    'version' =>
                        $registryVersion,

                    'status' =>
                        DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_PUBLISHED,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'published_by_user_id' =>
                        $admin->getKey(),

                    'published_at' =>
                        now(),
                ]);

        $entity =
            DataTransformationBiCanonicalEntity::query()
                ->create([
                    'canonical_registry_version_id' =>
                        $registry->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'entity_key' =>
                        'customer',

                    'label' =>
                        'Cliente',

                    'status' =>
                        DataTransformationBiCanonicalEntity
                            ::STATUS_ACTIVE,

                    'sort_order' =>
                        1,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),
                ]);

        $canonicalFields = [
            [
                'field_key' => 'customer_id',
                'label' => 'ID Cliente',
                'data_type' =>
                    DataTransformationBiCanonicalField::TYPE_TEXT,
                'required' => true,
                'is_identity' => true,
                'sort_order' => 1,
            ],
            [
                'field_key' => 'name',
                'label' => 'Nombre',
                'data_type' =>
                    DataTransformationBiCanonicalField::TYPE_TEXT,
                'required' => true,
                'is_identity' => false,
                'sort_order' => 2,
            ],
            [
                'field_key' => 'balance',
                'label' => 'Balance',
                'data_type' =>
                    DataTransformationBiCanonicalField::TYPE_DECIMAL,
                'required' => false,
                'is_identity' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($canonicalFields as $field) {
            DataTransformationBiCanonicalField::query()
                ->create([
                    ...$field,

                    'canonical_entity_id' =>
                        $entity->getKey(),

                    'status' =>
                        DataTransformationBiCanonicalField
                            ::STATUS_ACTIVE,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),
                ]);
        }

        $mapping =
            DataTransformationBiSourceAssetMapping::query()
                ->create([
                    'data_transformation_bi_source_asset_id' =>
                        $asset->getKey(),

                    'data_transformation_bi_source_asset_file_id' =>
                        $artifact->getKey(),

                    'data_transformation_bi_intake_session_id' =>
                        $session->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'canonical_entity_key' =>
                        'customer',

                    'canonical_registry_version' =>
                        $registryVersion,

                    'source_sha256' =>
                        $sha,

                    'source_profile_version' =>
                        $profileVersion,

                    'source_sheet_index' =>
                        0,

                    'source_sheet_name' =>
                        null,

                    'mapping_version' =>
                        1,

                    'status' =>
                        DataTransformationBiSourceAssetMapping
                            ::STATUS_VALIDATED,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'validated_by_user_id' =>
                        $admin->getKey(),

                    'validated_at' =>
                        now(),
                ]);

        $decisions = [
            [
                'canonical_field_key' => 'customer_id',
                'source_column_key' => 'column_1',
                'source_column_index' => 0,
                'source_header' => 'customer_id',
            ],
            [
                'canonical_field_key' => 'name',
                'source_column_key' => 'column_2',
                'source_column_index' => 1,
                'source_header' => 'name',
            ],
            [
                'canonical_field_key' => 'balance',
                'source_column_key' => 'column_3',
                'source_column_index' => 2,
                'source_header' => 'balance',
            ],
        ];

        foreach ($decisions as $decision) {
            DataTransformationBiSourceAssetFieldMapping::query()
                ->create([
                    ...$decision,

                    'data_transformation_bi_source_asset_mapping_id' =>
                        $mapping->getKey(),

                    'company_id' =>
                        $company->getKey(),

                    'mapping_type' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                    'status' =>
                        DataTransformationBiSourceAssetFieldMapping
                            ::STATUS_VALIDATED,

                    'created_by_user_id' =>
                        $admin->getKey(),

                    'updated_by_user_id' =>
                        $admin->getKey(),

                    'validated_by_user_id' =>
                        $admin->getKey(),

                    'validated_at' =>
                        now(),
                ]);
        }

        return compact(
            'admin',
            'company',
            'request',
            'definition',
            'engagement',
            'session',
            'asset',
            'artifact',
            'registry',
            'entity',
            'mapping'
        );
    }

}
