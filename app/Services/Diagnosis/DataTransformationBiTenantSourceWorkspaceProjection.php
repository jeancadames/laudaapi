<?php

namespace App\Services\Diagnosis;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Tenant-safe projection for the Data BI source workspace.
 *
 * The shared Intake V2 state remains the technical source of truth for
 * LAUDA Admin. This projection deliberately reduces that state before
 * exposing it to an Admin Tenant.
 *
 * It must never expose:
 * - private storage coordinates;
 * - hashes;
 * - reader/parser configuration;
 * - technical structure/profile snapshots;
 * - canonical transformation internals;
 * - database credentials or remote connection material.
 */
final class DataTransformationBiTenantSourceWorkspaceProjection
{
    /**
     * @var list<string>
     */
    private const SOURCE_ASSET_KEYS = [
        'id',
        'display_name',
        'source_object_name',
        'description',
        'origin_system',
        'owner',
        'structure_format',
        'structure_text',
        'delivery_format',
        'status',
        'structure_status',
        'data_status',
        'sort_order',
        'structure_analyzed_at',
        'data_received_at',
        'profiled_at',
        'failure_message',
    ];

    /**
     * @var list<string>
     */
    private const DATA_FILE_KEYS = [
        'status',
        'original_filename',
        'source_format',
        'source_size_bytes',
        'source_row_count',
        'uploaded_at',
    ];

    /**
     * @return array<string,mixed>
     */
    public function workspaceState(
        mixed $state
    ): array {
        $state = $this->toArray($state);

        $session =
            $this->toArray(
                $state['session']
                ?? []
            );

        $actions =
            $this->toArray(
                $state['actions']
                ?? []
            );

        return [
            'session' =>
                $session === []
                    ? null
                    : [
                        'id' =>
                            isset($session['id'])
                                ? (int) $session['id']
                                : null,

                        'status' =>
                            isset($session['status'])
                                ? (string) $session['status']
                                : null,
                    ],

            'actions' => [
                'can_start_or_resume' =>
                    (bool) (
                        $actions['can_start_or_resume']
                        ?? false
                    ),

                'can_manage_sources' =>
                    (bool) (
                        $actions['can_manage_sources']
                        ?? false
                    ),
            ],

            'source_assets' =>
                $this->sourceAssets(
                    $state['source_assets']
                    ?? []
                ),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sourceAssetsFromState(
        mixed $state
    ): array {
        $state = $this->toArray($state);

        return $this->sourceAssets(
            $state['source_assets']
            ?? []
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sourceAssets(
        mixed $assets
    ): array {
        if (! is_iterable($assets)) {
            return [];
        }

        $result = [];

        foreach ($assets as $asset) {
            $projected =
                $this->sourceAsset(
                    $asset
                );

            if ($projected !== null) {
                $result[] = $projected;
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function sourceAsset(
        mixed $asset
    ): ?array {
        $asset = $this->toArray($asset);

        if ($asset === []) {
            return null;
        }

        $result = [];

        foreach (
            self::SOURCE_ASSET_KEYS
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $asset
                )
            ) {
                $result[$key] =
                    $asset[$key];
            }
        }

        if (
            array_key_exists(
                'data_file',
                $asset
            )
        ) {
            $result['data_file'] =
                $this->dataFile(
                    $asset['data_file']
                );
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function dataFile(
        mixed $file
    ): ?array {
        $file = $this->toArray($file);

        if ($file === []) {
            return null;
        }

        $result = [];

        foreach (
            self::DATA_FILE_KEYS
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $file
                )
            ) {
                $result[$key] =
                    $file[$key];
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function toArray(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        if (
            $value
            instanceof Arrayable
        ) {
            return $value->toArray();
        }

        if (is_object($value)) {
            return get_object_vars(
                $value
            );
        }

        return [];
    }
}
