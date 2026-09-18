<?php

use App\Models\DataTransformationBiSourceDomainFile;
use App\Models\DataTransformationBiSourceFieldMapping;
use App\Services\Diagnosis\DataTransformationBiSourceDomainRegistry;
use App\Services\Diagnosis\DataTransformationBiStandardIntakeSchema;

it(
    'defines exactly the six agreed client native source domains',
    function (): void {
        expect(
            DataTransformationBiSourceDomainRegistry::domainKeys()
        )->toBe([
            'customers',
            'sales',
            'accounts_receivable',
            'products',
            'suppliers',
            'accounts_payable',
        ]);

        expect(
            DataTransformationBiSourceDomainRegistry::domainKeys()
        )->not->toContain(
            'inventory'
        );
    }
);

it(
    'keeps every source domain inside the canonical schema',
    function (): void {
        $canonical =
            DataTransformationBiStandardIntakeSchema::domainKeys();

        expect($canonical)->toHaveCount(7);

        expect($canonical)->toBe([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ]);

        foreach (
            DataTransformationBiSourceDomainRegistry::domainKeys()
            as $domain
        ) {
            expect($canonical)->toContain($domain);

            DataTransformationBiSourceDomainRegistry
                ::assertSupported($domain);
        }
    }
);

it(
    'keeps inventory canonical without enabling native source intake yet',
    function (): void {
        expect(
            DataTransformationBiStandardIntakeSchema::domainKeys()
        )->toContain(
            'inventory'
        );

        expect(
            DataTransformationBiSourceDomainRegistry
                ::supports('inventory')
        )->toBeFalse();
    }
);

it(
    'uses auxiliary source models instead of another intake session',
    function (): void {
        expect(
            (new DataTransformationBiSourceDomainFile())
                ->getTable()
        )->toBe(
            'data_transformation_bi_source_domain_files'
        );

        expect(
            (new DataTransformationBiSourceFieldMapping())
                ->getTable()
        )->toBe(
            'data_transformation_bi_source_field_mappings'
        );
    }
);

it(
    'protects the private source path from serialization',
    function (): void {
        expect(
            (new DataTransformationBiSourceDomainFile())
                ->getHidden()
        )->toContain(
            'source_path'
        );
    }
);

it(
    'defines the source file lifecycle required before canonical intake',
    function (): void {
        expect(
            DataTransformationBiSourceDomainFile::STATUS_UPLOADED
        )->toBe('uploaded');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_PROFILING
        )->toBe('profiling');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_PROFILED
        )->toBe('profiled');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_MAPPING
        )->toBe('mapping');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_READY
        )->toBe('ready');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_TRANSFORMING
        )->toBe('transforming');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_TRANSFORMED
        )->toBe('transformed');

        expect(
            DataTransformationBiSourceDomainFile::STATUS_FAILED
        )->toBe('failed');
    }
);

it(
    'accepts csv and xlsx as native source formats',
    function (): void {
        expect(
            DataTransformationBiSourceDomainFile::FORMAT_CSV
        )->toBe('csv');

        expect(
            DataTransformationBiSourceDomainFile::FORMAT_XLSX
        )->toBe('xlsx');
    }
);

it(
    'defines only declarative mapping types',
    function (): void {
        expect(
            DataTransformationBiSourceFieldMapping::TYPE_DIRECT
        )->toBe('direct');

        expect(
            DataTransformationBiSourceFieldMapping::TYPE_DEFAULT
        )->toBe('default');

        expect(
            DataTransformationBiSourceFieldMapping::TYPE_TRANSFORM
        )->toBe('transform');

        expect(
            DataTransformationBiSourceFieldMapping::TYPE_UNMAPPED
        )->toBe('unmapped');
    }
);

it(
    'keeps the migration attached to existing intake domain deliveries',
    function (): void {
        $migration =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/database/migrations/'
                .'2026_09_18_031500_'
                .'create_data_transformation_bi_'
                .'source_domain_extension.php'
            );

        expect($migration)
            ->toContain(
                'data_transformation_bi_source_domain_files'
            )
            ->toContain(
                'data_transformation_bi_source_field_mappings'
            )
            ->toContain(
                'data_transformation_bi_intake_domain_delivery_id'
            )
            ->toContain(
                'data_transformation_bi_intake_domain_deliveries'
            )
            ->toContain(
                'source_structure_snapshot'
            )
            ->toContain(
                'profiling_snapshot'
            )
            ->toContain(
                'target_field'
            )
            ->toContain(
                'source_field'
            );
    }
);

it(
    'does not introduce a parallel source session or raw source row table',
    function (): void {
        $migration =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/database/migrations/'
                .'2026_09_18_031500_'
                .'create_data_transformation_bi_'
                .'source_domain_extension.php'
            );

        expect($migration)
            ->not->toContain(
                'data_transformation_bi_source_intake_sessions'
            )
            ->not->toContain(
                'data_transformation_bi_source_domain_deliveries'
            )
            ->not->toContain(
                'data_transformation_bi_source_rows'
            );
    }
);
