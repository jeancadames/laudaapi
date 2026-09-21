<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceProjection;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceOwnerProjectionContractTest
    extends TestCase
{
    public function test_source_owner_survives_tenant_safe_projection(): void
    {
        $projection =
            new DataTransformationBiTenantSourceWorkspaceProjection();

        $source =
            $projection->sourceAsset([
                'id' => 1,
                'display_name' => 'Clientes',
                'source_object_name' => 'Ctes',
                'description' => 'tabla de clientes',
                'origin_system' => 'Microsoft SQL Server',
                'owner' => 'Administrador',
                'delivery_format' => 'xlsx',
                'status' => 'draft',
                'structure_status' => 'pending',
                'data_status' => 'pending',

                // Internal/private material must still be removed.
                'disk' => 'private',
                'path' => '/private/source.xlsx',
                'hash' => 'secret',
                'connection_string' => 'secret',
            ]);

        self::assertNotNull($source);

        self::assertSame(
            'Administrador',
            $source['owner'] ?? null
        );

        self::assertArrayNotHasKey(
            'disk',
            $source
        );

        self::assertArrayNotHasKey(
            'path',
            $source
        );

        self::assertArrayNotHasKey(
            'hash',
            $source
        );

        self::assertArrayNotHasKey(
            'connection_string',
            $source
        );
    }

    public function test_owner_survives_workspace_state_projection(): void
    {
        $projection =
            new DataTransformationBiTenantSourceWorkspaceProjection();

        $state =
            $projection->workspaceState([
                'session' => [
                    'id' => 4,
                    'status' => 'draft',
                ],

                'actions' => [
                    'can_start_or_resume' => true,
                    'can_manage_sources' => true,
                ],

                'source_assets' => [
                    [
                        'id' => 1,
                        'display_name' => 'Clientes',
                        'source_object_name' => 'Ctes',
                        'origin_system' => 'Microsoft SQL Server',
                        'owner' => 'Administrador',
                        'delivery_format' => 'xlsx',
                        'status' => 'draft',
                        'structure_status' => 'pending',
                        'data_status' => 'pending',
                    ],
                ],
            ]);

        self::assertSame(
            'Administrador',
            $state['source_assets'][0]['owner']
            ?? null
        );
    }
}
