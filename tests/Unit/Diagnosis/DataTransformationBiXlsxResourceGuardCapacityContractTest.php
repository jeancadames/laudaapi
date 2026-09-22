<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiStandardIntakeResourceGuard;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class DataTransformationBiXlsxResourceGuardCapacityContractTest
    extends TestCase
{
    public function test_xlsx_has_a_larger_bounded_expansion_envelope(): void
    {
        self::assertSame(
            128 * 1024 * 1024,
            DataTransformationBiStandardIntakeResourceGuard
                ::MAX_XLSX_ENTRY_UNCOMPRESSED_BYTES
        );

        self::assertSame(
            256 * 1024 * 1024,
            DataTransformationBiStandardIntakeResourceGuard
                ::MAX_XLSX_TOTAL_UNCOMPRESSED_BYTES
        );

        self::assertSame(
            16 * 1024 * 1024,
            DataTransformationBiStandardIntakeResourceGuard
                ::MAX_ENTRY_UNCOMPRESSED_BYTES
        );

        self::assertSame(
            32 * 1024 * 1024,
            DataTransformationBiStandardIntakeResourceGuard
                ::MAX_TOTAL_UNCOMPRESSED_BYTES
        );

        self::assertSame(
            200.0,
            DataTransformationBiStandardIntakeResourceGuard
                ::MAX_COMPRESSION_RATIO
        );
    }

    public function test_same_seventeen_megabyte_entry_is_allowed_for_xlsx_but_not_csv_zip(): void
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'lauda-xlsx-guard-'
        );

        self::assertNotFalse($path);

        $zip = new ZipArchive();

        self::assertTrue(
            $zip->open(
                $path,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            )
        );

        $entryName =
            'xl/worksheets/sheet1.xml';

        self::assertTrue(
            $zip->addFromString(
                $entryName,
                str_repeat(
                    'X',
                    17 * 1024 * 1024
                )
            )
        );

        /*
         * Store instead of deflate so this test exercises only the
         * uncompressed-entry envelope, not compression-ratio defense.
         */
        self::assertTrue(
            $zip->setCompressionName(
                $entryName,
                ZipArchive::CM_STORE
            )
        );

        $zip->close();

        try {
            $guard =
                new DataTransformationBiStandardIntakeResourceGuard();

            $xlsx =
                $guard->validate(
                    $path,
                    'clientes.xlsx'
                );

            self::assertTrue(
                $xlsx['valid'],
                implode(
                    ' ',
                    $xlsx['errors']
                )
            );

            self::assertSame(
                128 * 1024 * 1024,
                $xlsx['limits'][
                    'entry_uncompressed_bytes'
                ]
            );

            self::assertSame(
                256 * 1024 * 1024,
                $xlsx['limits'][
                    'total_uncompressed_bytes'
                ]
            );

            $csvZip =
                $guard->validate(
                    $path,
                    'clientes.zip'
                );

            self::assertFalse(
                $csvZip['valid']
            );

            self::assertStringContainsString(
                '16 MB sin comprimir',
                implode(
                    ' ',
                    $csvZip['errors']
                )
            );

            self::assertSame(
                16 * 1024 * 1024,
                $csvZip['limits'][
                    'entry_uncompressed_bytes'
                ]
            );

            self::assertSame(
                32 * 1024 * 1024,
                $csvZip['limits'][
                    'total_uncompressed_bytes'
                ]
            );
        } finally {
            @unlink($path);
        }
    }
}
