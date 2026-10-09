<?php

use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceProjection;

test(
    'tenant source failures never disclose raw technical messages',
    function (): void {
        $projection =
            new DataTransformationBiTenantSourceWorkspaceProjection();

        $raw =
            'SQLSTATE[HY000] password=secret '
            .'/var/www/private/config.php';

        $asset = [
            'id' => 42,
            'display_name' => 'Fuente de prueba',
            'status' => 'failed',
            'failure_message' => $raw,
        ];

        $expected =
            'No se pudo procesar la fuente. '
            .'Contacta al equipo de LAUDA para revisar el problema.';

        $single = $projection->sourceAsset($asset);

        expect($single['failure_message'])
            ->toBe($expected);

        expect($single['failure_message'])
            ->not->toContain('SQLSTATE')
            ->not->toContain('password')
            ->not->toContain('/var/www');

        expect($single['id'])->toBe(42);
        expect($single['status'])->toBe('failed');

        $state = [
            'source_assets' => [$asset],
        ];

        $fromState =
            $projection->sourceAssetsFromState($state);

        expect($fromState[0]['failure_message'])
            ->toBe($expected);

        $workspace = $projection->workspaceState($state);

        expect(
            $workspace['source_assets'][0]['failure_message']
        )->toBe($expected);

        $empty = $projection->sourceAsset([
            'failure_message' => '',
        ]);

        expect($empty['failure_message'])->toBeNull();

        $missing = $projection->sourceAsset([
            'status' => 'ready',
        ]);

        expect($missing)
            ->not->toHaveKey('failure_message');

        $nonString = $projection->sourceAsset([
            'failure_message' => [
                'password' => 'private',
            ],
        ]);

        expect($nonString['failure_message'])
            ->toBeNull();
    }
);
