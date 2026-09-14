<?php

use Tests\TestCase;

uses(TestCase::class);

function qa2I16D6Source(): string
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
    'completed batch reuse verifies immutable artifact contract first',
    function () {
        $source =
            qa2I16D6Source();

        $reuse =
            strpos(
                $source,
                "(\$reservation['reused'] ?? false)"
            );

        $summary =
            strpos(
                $source,
                '$this->summary(',
                $reuse
            );

        $contract =
            strpos(
                $source,
                '$this->assertCompletedBatchContract(',
                $reuse
            );

        $artifact =
            strpos(
                $source,
                '$this->ensureSourceArtifact(',
                $reuse
            );

        expect($reuse)
            ->not
            ->toBeFalse()
            ->and($contract)
            ->not
            ->toBeFalse()
            ->and($artifact)
            ->not
            ->toBeFalse()
            ->and($summary)
            ->not
            ->toBeFalse()
            ->and($contract)
            ->toBeLessThan($summary)
            ->and($artifact)
            ->toBeLessThan($summary);
    }
);

test(
    'existing source artifact is checksum verified and never blindly overwritten',
    function () {
        $source =
            qa2I16D6Source();

        expect($source)
            ->toContain(
                'private function ensureSourceArtifact('
            )
            ->toContain(
                '$disk->exists('
            )
            ->toContain(
                '$this->assertSourceArtifactIntegrity('
            )
            ->toContain(
                'hash_equals('
            )
            ->toContain(
                'hash_init('
            )
            ->toContain(
                "'sha256'"
            )
            ->toContain(
                '$disk->readStream('
            )
            ->toContain(
                'El archivo privado de intake no coincide '
            )
            ->toContain(
                'SHA-256 esperado.'
            );
    }
);

test(
    'new source artifact is verified immediately after write',
    function () {
        $source =
            qa2I16D6Source();

        $methodStart =
            strpos(
                $source,
                'private function ensureSourceArtifact('
            );

        $methodEnd =
            strpos(
                $source,
                'private function assertCompletedBatchContract(',
                $methodStart
            );

        $method =
            substr(
                $source,
                $methodStart,
                $methodEnd - $methodStart
            );

        $put =
            strpos(
                $method,
                '$disk->put('
            );

        $verify =
            strrpos(
                $method,
                '$this->assertSourceArtifactIntegrity('
            );

        expect($put)
            ->not
            ->toBeFalse()
            ->and($verify)
            ->not
            ->toBeFalse()
            ->and($verify)
            ->toBeGreaterThan($put)
            ->and($method)
            ->toContain(
                '$disk->delete('
            );
    }
);

test(
    'failed staging deletes only an artifact created by the same attempt',
    function () {
        $source =
            qa2I16D6Source();

        expect($source)
            ->toContain(
                '$artifactCreatedNow'
            )
            ->toContain(
                'if ($artifactCreatedNow)'
            )
            ->toContain(
                '$this->markFailed('
            )
            ->not
            ->toContain(
                '$artifactExistedBefore'
            );
    }
);

test(
    'completed batch immutable contract covers ownership format schema hash disk and path',
    function () {
        $source =
            qa2I16D6Source();

        $start =
            strpos(
                $source,
                'private function assertCompletedBatchContract('
            );

        $end =
            strpos(
                $source,
                'private function assertSourceArtifactIntegrity(',
                $start
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        foreach (
            [
                'STATUS_COMPLETED',
                'company_id',
                'transformation_implementation_request_id',
                'schema_version',
                'source_format',
                'source_sha256',
                'source_disk',
                'source_path',
                'SOURCE_DISK',
            ]
            as $token
        ) {
            expect($method)
                ->toContain(
                    $token
                );
        }
    }
);

test(
    'integrity hardening does not introduce public storage or lifecycle mutation',
    function () {
        $source =
            qa2I16D6Source();

        expect($source)
            ->not
            ->toContain(
                "Storage::disk('public')"
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
                'bi_fact_'
            )
            ->not
            ->toContain(
                'bi_dimension_'
            );
    }
);
