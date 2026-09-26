<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceSubmissionFreezeConcurrencyContractTest
    extends TestCase
{
    private string $sourceService;
    private string $structureService;
    private string $uploadService;
    private string $submissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->sourceService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetService.php'
            );

        $this->structureService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetStructureService.php'
            );

        $this->uploadService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );

        $this->submissionService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceSubmissionService.php'
            );

        foreach ([
            $this->sourceService,
            $this->structureService,
            $this->uploadService,
            $this->submissionService,
        ] as $source) {
            self::assertIsString(
                $source
            );
        }
    }

    public function test_every_tenant_mutation_family_has_an_authoritative_session_lock(): void
    {
        self::assertSame(
            5,
            substr_count(
                $this->sourceService,
                'lockEditableSession('
            )
        );

        self::assertSame(
            2,
            substr_count(
                $this->structureService,
                'lockEditableSession('
            )
        );

        self::assertSame(
            2,
            substr_count(
                $this->uploadService,
                'lockEditableSession('
            )
        );

        foreach ([
            $this->sourceService,
            $this->structureService,
            $this->uploadService,
        ] as $source) {
            self::assertStringContainsString(
                '->lockForUpdate()',
                $source
            );

            self::assertStringContainsString(
                'assertEditableSession(',
                $source
            );

            /*
             * submitted_for_evaluation must remain outside every
             * tenant mutation allowlist.
             */
            self::assertStringNotContainsString(
                'STATUS_SUBMITTED_FOR_EVALUATION',
                $source
            );
        }
    }

    public function test_source_mutations_lock_session_before_mutating_rows(): void
    {
        foreach ([
            'create',
            'update',
            'reorder',
            'archive',
        ] as $method) {
            $block =
                $this->publicMethodBlock(
                    $this->sourceService,
                    $method
                );

            $transaction =
                strpos(
                    $block,
                    'DB::transaction('
                );

            $sessionLock =
                strpos(
                    $block,
                    'lockEditableSession('
                );

            self::assertNotFalse(
                $transaction
            );

            self::assertNotFalse(
                $sessionLock
            );

            self::assertGreaterThan(
                $transaction,
                $sessionLock
            );
        }
    }

    public function test_structure_mutation_locks_session_inside_transaction(): void
    {
        $block =
            $this->publicMethodBlock(
                $this->structureService,
                'save'
            );

        $transaction =
            strpos(
                $block,
                'DB::transaction('
            );

        $sessionLock =
            strpos(
                $block,
                'lockEditableSession('
            );

        self::assertNotFalse(
            $transaction
        );

        self::assertNotFalse(
            $sessionLock
        );

        self::assertGreaterThan(
            $transaction,
            $sessionLock
        );
    }

    public function test_upload_keeps_file_io_outside_database_lock_and_cleans_rejected_artifact(): void
    {
        $block =
            $this->publicMethodBlock(
                $this->uploadService,
                'persist'
            );

        $put =
            strpos(
                $block,
                '$disk->put('
            );

        $transaction =
            strpos(
                $block,
                'DB::transaction('
            );

        $sessionLock =
            strpos(
                $block,
                'lockEditableSession(',
                $transaction
            );

        $assetLock =
            strpos(
                $block,
                '->lockForUpdate()',
                $sessionLock
            );

        $cleanup =
            strpos(
                $block,
                '$disk->delete(',
                $transaction
            );

        foreach ([
            $put,
            $transaction,
            $sessionLock,
            $assetLock,
            $cleanup,
        ] as $position) {
            self::assertNotFalse(
                $position
            );
        }

        self::assertLessThan(
            $transaction,
            $put
        );

        self::assertLessThan(
            $sessionLock,
            $transaction
        );

        self::assertLessThan(
            $assetLock,
            $sessionLock
        );

        self::assertLessThan(
            $cleanup,
            $assetLock
        );

        self::assertStringContainsString(
            '! $pathExistedBefore',
            $block
        );
    }

    public function test_submission_uses_the_same_session_first_lock_order(): void
    {
        $session =
            strpos(
                $this->submissionService,
                '$lockedSession ='
            );

        $sessionLock =
            strpos(
                $this->submissionService,
                '->lockForUpdate()',
                $session
            );

        $sources =
            strpos(
                $this->submissionService,
                '$sources =',
                $sessionLock
            );

        $sourceLock =
            strpos(
                $this->submissionService,
                '->lockForUpdate()',
                $sources
            );

        $file =
            strpos(
                $this->submissionService,
                '$file =',
                $sourceLock
            );

        $fileLock =
            strpos(
                $this->submissionService,
                '->lockForUpdate()',
                $file
            );

        foreach ([
            $session,
            $sessionLock,
            $sources,
            $sourceLock,
            $file,
            $fileLock,
        ] as $position) {
            self::assertNotFalse(
                $position
            );
        }

        self::assertLessThan(
            $sources,
            $sessionLock
        );

        self::assertLessThan(
            $sourceLock,
            $sources
        );

        self::assertLessThan(
            $file,
            $sourceLock
        );

        self::assertLessThan(
            $fileLock,
            $file
        );
    }

    private function publicMethodBlock(
        string $source,
        string $method
    ): string {
        $start =
            strpos(
                $source,
                "    public function {$method}("
            );

        self::assertNotFalse(
            $start
        );

        $nextPublic =
            strpos(
                $source,
                "\n    public function ",
                $start + 1
            );

        $nextPrivate =
            strpos(
                $source,
                "\n    private function ",
                $start + 1
            );

        $ends =
            array_values(
                array_filter(
                    [
                        $nextPublic,
                        $nextPrivate,
                    ],
                    static fn ($position): bool =>
                        $position !== false
                )
            );

        $end =
            $ends !== []
                ? min(
                    $ends
                )
                : strlen(
                    $source
                );

        return substr(
            $source,
            $start,
            $end - $start
        );
    }
}
