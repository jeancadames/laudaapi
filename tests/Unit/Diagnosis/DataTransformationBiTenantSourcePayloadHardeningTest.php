<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceProjection;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourcePayloadHardeningTest
    extends TestCase
{
    public function test_projection_keeps_only_tenant_business_fields(): void
    {
        $projection =
            new DataTransformationBiTenantSourceWorkspaceProjection();

        $state = [
            'schema_version' => 'internal-v2',
            'session' => [
                'id' => 41,
                'status' => 'draft',
                'company_id' => 999,
                'internal_note' => 'hidden',
            ],
            'actions' => [
                'can_start_or_resume' => true,
                'can_manage_sources' => true,
                'can_finalize' => true,
            ],
            'domains' => [
                'internal' => true,
            ],
            'source_assets' => [
                [
                    'id' => 8,
                    'display_name' => 'Ventas',
                    'source_object_name' => 'dbo.Facturas',
                    'description' => 'Histórico de ventas',
                    'origin_system' => 'ERP',
                    'structure_format' => 'sql_server_ddl',
                    'structure_text' => 'CREATE TABLE dbo.Facturas (...)',
                    'delivery_format' => 'csv',
                    'status' => 'active',
                    'structure_status' => 'analyzed',
                    'data_status' => 'received',
                    'sort_order' => 1,
                    'structure_analyzed_at' => '2026-09-19T10:00:00Z',
                    'data_received_at' => '2026-09-19T10:01:00Z',
                    'profiled_at' => null,
                    'failure_message' => null,

                    'structure_snapshot' => [
                        'columns' => ['secret'],
                    ],
                    'profiling_snapshot' => [
                        'technical' => true,
                    ],
                    'failure_code' => 'INTERNAL_CODE',
                    'company_id' => 19,
                    'data_transformation_bi_intake_session_id' => 41,
                    'created_by_user_id' => 29,

                    'data_file' => [
                        'id' => 99,
                        'source_asset_id' => 8,
                        'status' => 'uploaded',
                        'original_filename' => 'ventas.csv',
                        'source_format' => 'csv',
                        'source_mime_type' => 'text/csv',
                        'source_size_bytes' => 12345,
                        'source_sha256' => 'do-not-expose',
                        'source_disk' => 'private',
                        'source_path' => '/private/internal/path.csv',
                        'reader_configuration' => [
                            'delimiter' => ',',
                        ],
                        'source_structure_snapshot' => [
                            'headers' => ['internal'],
                        ],
                        'source_row_count' => 150,
                        'uploaded_by_user_id' => 29,
                        'uploaded_at' => '2026-09-19T10:01:00Z',
                    ],
                ],
            ],
        ];

        $result =
            $projection->workspaceState(
                $state
            );

        self::assertSame(
            [
                'session',
                'actions',
                'source_assets',
            ],
            array_keys($result)
        );

        self::assertSame(
            [
                'id' => 41,
                'status' => 'draft',
            ],
            $result['session']
        );

        self::assertSame(
            [
                'can_start_or_resume' => true,
                'can_manage_sources' => true,
            ],
            $result['actions']
        );

        $asset =
            $result['source_assets'][0];

        self::assertArrayHasKey(
            'display_name',
            $asset
        );

        self::assertArrayHasKey(
            'structure_text',
            $asset
        );

        self::assertArrayHasKey(
            'data_file',
            $asset
        );

        foreach (
            [
                'structure_snapshot',
                'profiling_snapshot',
                'failure_code',
                'company_id',
                'data_transformation_bi_intake_session_id',
                'created_by_user_id',
            ]
            as $forbidden
        ) {
            self::assertArrayNotHasKey(
                $forbidden,
                $asset
            );
        }

        $file =
            $asset['data_file'];

        self::assertSame(
            [
                'status',
                'original_filename',
                'source_format',
                'source_size_bytes',
                'source_row_count',
                'uploaded_at',
            ],
            array_keys($file)
        );

        foreach (
            [
                'id',
                'source_asset_id',
                'source_mime_type',
                'source_sha256',
                'source_disk',
                'source_path',
                'reader_configuration',
                'source_structure_snapshot',
                'uploaded_by_user_id',
            ]
            as $forbidden
        ) {
            self::assertArrayNotHasKey(
                $forbidden,
                $file
            );
        }
    }

    public function test_source_asset_projection_accepts_service_payload(): void
    {
        $projection =
            new DataTransformationBiTenantSourceWorkspaceProjection();

        $result =
            $projection->sourceAsset([
                'id' => 1,
                'display_name' => 'Clientes',
                'source_sha256' => 'hidden',
                'data_file' => [
                    'status' => 'uploaded',
                    'original_filename' => 'clientes.xlsx',
                    'source_format' => 'xlsx',
                    'source_size_bytes' => 5000,
                    'source_row_count' => 20,
                    'uploaded_at' => '2026-09-19T10:00:00Z',
                    'source_sha256' => 'hidden',
                ],
            ]);

        self::assertNotNull($result);

        self::assertArrayNotHasKey(
            'source_sha256',
            $result
        );

        self::assertArrayNotHasKey(
            'source_sha256',
            $result['data_file']
        );
    }
}
