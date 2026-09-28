<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiSourceAssetService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceBusinessDomainsFoundationContractTest
    extends TestCase
{
    /**
     * @return list<array{domain:string,group:string}>
     */
    private function normalize(
        mixed $value
    ): array {
        $reflection =
            new ReflectionClass(
                DataTransformationBiSourceAssetService::class
            );

        $service =
            $reflection->newInstanceWithoutConstructor();

        $method =
            $reflection->getMethod(
                'normalizeBusinessDomains'
            );

        $method->setAccessible(true);

        /** @var list<array{domain:string,group:string}> $result */
        $result =
            $method->invoke(
                $service,
                $value
            );

        return $result;
    }

    public function test_three_business_groups_are_the_only_controlled_values(): void
    {
        self::assertSame(
            [
                'operaciones',
                'gestion',
                'finanzas',
            ],
            DataTransformationBiSourceAsset::BUSINESS_GROUPS
        );
    }

    public function test_domains_are_dynamic_and_group_is_normalized(): void
    {
        self::assertSame(
            [
                [
                    'domain' => 'Clientes',
                    'group' => 'gestion',
                ],
                [
                    'domain' => 'Reservas',
                    'group' => 'operaciones',
                ],
                [
                    'domain' => 'CxC',
                    'group' => 'finanzas',
                ],
            ],
            $this->normalize([
                [
                    'domain' => 'Clientes',
                    'group' => 'GESTION',
                ],
                [
                    'domain' => 'Reservas',
                    'group' => 'operaciones',
                ],
                [
                    'domain' => 'CxC',
                    'group' => 'finanzas',
                ],
            ])
        );
    }

    public function test_empty_classification_is_allowed(): void
    {
        self::assertSame(
            [],
            $this->normalize([])
        );

        self::assertSame(
            [],
            $this->normalize(null)
        );
    }

    public function test_invalid_business_group_is_rejected(): void
    {
        $this->expectException(
            ValidationException::class
        );

        $this->normalize([
            [
                'domain' => 'Clientes',
                'group' => 'comercial',
            ],
        ]);
    }

    public function test_duplicate_domain_is_rejected_case_insensitively(): void
    {
        $this->expectException(
            ValidationException::class
        );

        $this->normalize([
            [
                'domain' => 'Clientes',
                'group' => 'gestion',
            ],
            [
                'domain' => 'clientes',
                'group' => 'operaciones',
            ],
        ]);
    }

    public function test_business_domains_are_persisted_projected_and_frozen(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_28_210000_add_business_domains_to_'
                .'data_transformation_bi_source_assets.php'
            );

        $model =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiSourceAsset.php'
            );

        $controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiSourceWorkspaceController.php'
            );

        $state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $projection =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceProjection.php'
            );

        $submission =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceSubmissionService.php'
            );

        $readiness =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceReadinessService.php'
            );

        foreach (
            [
                $migration,
                $model,
                $controller,
                $state,
                $projection,
                $submission,
                $readiness,
            ]
            as $source
        ) {
            self::assertIsString(
                $source
            );
        }

        self::assertStringContainsString(
            "->json('business_domains')",
            $migration
        );

        self::assertStringContainsString(
            "'business_domains'",
            $model
        );

        self::assertStringContainsString(
            "'business_domains' =>\n                'array'",
            $model
        );

        self::assertStringContainsString(
            "'business_domains',",
            $controller
        );

        self::assertStringContainsString(
            "'business_domains' =>",
            $state
        );

        self::assertStringContainsString(
            "'business_domains',",
            $projection
        );

        self::assertStringContainsString(
            'private const MANIFEST_VERSION = 2;',
            $submission
        );

        self::assertStringContainsString(
            "'business_domains' =>",
            $submission
        );

        /*
         * Classification is diagnostic metadata only. It must never
         * become a delivery/readiness requirement.
         */
        self::assertStringNotContainsString(
            'business_domains',
            $readiness
        );
    }
}
