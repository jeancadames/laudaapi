<?php

use App\Services\Diagnosis\DataTransformationBiStandardIntakeIngestionService;
use Tests\TestCase;

uses(TestCase::class);

function qa2I16D2Source(): string
{
    $source =
        file_get_contents(
            app_path(
                'Services/Diagnosis/DataTransformationBiStandardIntakeIngestionService.php'
            )
        );

    if ($source === false) {
        throw new RuntimeException(
            'Could not read ingestion service.'
        );
    }

    return $source;
}

test(
    'controlled intake ingestion keeps the two megabyte private source contract',
    function () {
        expect(
            DataTransformationBiStandardIntakeIngestionService
                ::SOURCE_DISK
        )
            ->toBe('private')
            ->and(
                DataTransformationBiStandardIntakeIngestionService
                    ::MAX_SOURCE_BYTES
            )
            ->toBe(2097152)
            ->and(
                DataTransformationBiStandardIntakeIngestionService
                    ::INSERT_CHUNK_SIZE
            )
            ->toBe(500);
    }
);

test(
    'controlled ingestion reuses the complete i15 validation pipeline before staging',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                '$this->validationService'
            )
            ->toContain(
                '->validate('
            )
            ->toContain(
                "(\$validation['valid'] ?? false)"
            )
            ->toContain(
                '$this->fileReader'
            )
            ->toContain(
                '->read('
            )
            ->toContain(
                'DataTransformationBiStandardIntakeSchema::VERSION'
            );
    }
);

test(
    'source artifact is content addressed and stored only on private disk',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                "hash_file("
            )
            ->toContain(
                "'sha256'"
            )
            ->toContain(
                'data-transformation-bi/intake/company_%d/request_%d/schema_v%d/%s.%s'
            )
            ->toContain(
                'Storage::disk('
            )
            ->toContain(
                'self::SOURCE_DISK'
            )
            ->not
            ->toContain(
                "Storage::disk('public')"
            );
    }
);

test(
    'same request schema and source hash resolves to one logical batch',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "'schema_version'"
            )
            ->toContain(
                "'source_sha256'"
            )
            ->toContain(
                'STATUS_COMPLETED'
            )
            ->toContain(
                "'reused' =>"
            )
            ->toContain(
                'Este mismo archivo ya está siendo procesado.'
            );
    }
);

test(
    'controlled ingestion locks request before batch and stages inside transactions',
    function () {
        $source =
            qa2I16D2Source();

        expect(
            substr_count(
                $source,
                'DB::transaction('
            )
        )
            ->toBeGreaterThanOrEqual(3)
            ->and(
                substr_count(
                    $source,
                    '->lockForUpdate()'
                )
            )
            ->toBeGreaterThanOrEqual(4)
            ->and($source)
            ->toContain(
                'stageValidatedRows'
            )
            ->toContain(
                'insertDomainRows'
            );
    }
);

test(
    'staging persists canonical rows for every schema domain',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                'DataTransformationBiStandardIntakeSchema'
            )
            ->toContain(
                '::domainKeys()'
            )
            ->toContain(
                "'data_transformation_bi_intake_rows'"
            )
            ->toContain(
                "'row_payload'"
            )
            ->toContain(
                "'row_sha256'"
            )
            ->toContain(
                "'identity_hash'"
            )
            ->toContain(
                "'source_row_number'"
            );
    }
);

test(
    'staging identity rules cover the seven schema v1 domains',
    function () {
        $source =
            qa2I16D2Source();

        foreach (
            [
                "'customers'",
                "'products'",
                "'inventory'",
                "'sales'",
                "'accounts_receivable'",
                "'suppliers'",
                "'accounts_payable'",
            ]
            as $domain
        ) {
            expect($source)
                ->toContain(
                    $domain
                );
        }

        expect($source)
            ->toContain(
                "'customer_id'"
            )
            ->toContain(
                "'product_id'"
            )
            ->toContain(
                "'document_id'"
            )
            ->toContain(
                "'supplier_id'"
            );
    }
);

test(
    'ingestion keeps definition traceability without requiring or mutating definition lifecycle',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                'latestDefinitionFor'
            )
            ->toContain(
                "'transformation_implementation_definition_id'"
            )
            ->toContain(
                "'definition_version'"
            )
            ->not
            ->toContain(
                'ready_for_commercial_at'
            )
            ->not
            ->toContain(
                'TransformationCapabilityActivation'
            )
            ->not
            ->toContain(
                'TransformationImplementationExecution'
            );
    }
);

test(
    'controlled ingestion does not invent a retention duration',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->not
            ->toContain(
                'addDays('
            )
            ->not
            ->toContain(
                'addHours('
            )
            ->not
            ->toContain(
                'addMonths('
            )
            ->not
            ->toContain(
                "'source_retention_until' => now()"
            );
    }
);

test(
    'failed staging is never exposed as a completed partial batch',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->toContain(
                'markFailed'
            )
            ->toContain(
                'STATUS_FAILED'
            )
            ->toContain(
                "'failed_at'"
            )
            ->toContain(
                "'failure_code'"
            )
            ->toContain(
                "'failure_message'"
            )
            ->toContain(
                '$disk->delete('
            );
    }
);

test(
    'controlled ingestion does not create a final bi model',
    function () {
        $source =
            qa2I16D2Source();

        expect($source)
            ->not
            ->toContain(
                'bi_fact_'
            )
            ->not
            ->toContain(
                'bi_dimension_'
            )
            ->not
            ->toContain(
                'customer_risk_score'
            )
            ->not
            ->toContain(
                'supplier_risk_score'
            );
    }
);
