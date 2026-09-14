<?php

namespace App\Services\Diagnosis;

use ZipArchive;

class DataTransformationBiStandardIntakeResourceGuard
{
    public const MAX_XLSX_ENTRIES = 512;

    public const MAX_CSV_ZIP_ENTRIES = 32;

    public const MAX_ENTRY_UNCOMPRESSED_BYTES =
        16 * 1024 * 1024;

    public const MAX_TOTAL_UNCOMPRESSED_BYTES =
        32 * 1024 * 1024;

    public const MAX_COMPRESSION_RATIO = 200.0;

    private const COMPRESSION_RATIO_MIN_BYTES =
        1024 * 1024;

    public function validate(
        string $path,
        string $originalName
    ): array {
        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        $format =
            match ($extension) {
                'xlsx' => 'xlsx',
                'zip' => 'csv_zip',
                default => 'unsupported',
            };

        if (
            ! in_array(
                $extension,
                [
                    'xlsx',
                    'zip',
                ],
                true
            )
        ) {
            return $this->result(
                true,
                $format,
                []
            );
        }

        $zip =
            new ZipArchive();

        if (
            $zip->open($path)
            !== true
        ) {
            return $this->result(
                false,
                $format,
                [
                    'El archivo no contiene un paquete ZIP/XLSX válido.',
                ]
            );
        }

        try {
            $errors = [];

            $maxEntries =
                $extension === 'xlsx'
                    ? self::MAX_XLSX_ENTRIES
                    : self::MAX_CSV_ZIP_ENTRIES;

            if (
                $zip->numFiles
                > $maxEntries
            ) {
                $errors[] =
                    "El archivo contiene demasiadas entradas "
                    ."({$zip->numFiles}); el máximo permitido "
                    ."para este formato es {$maxEntries}.";
            }

            $totalUncompressed =
                0;

            for (
                $index = 0;
                $index < $zip->numFiles;
                $index++
            ) {
                $stat =
                    $zip->statIndex(
                        $index
                    );

                if (! is_array($stat)) {
                    $errors[] =
                        "No se pudo inspeccionar la entrada "
                        ."{$index} del archivo.";

                    continue;
                }

                $name =
                    (string) (
                        $stat['name']
                        ?? "entrada-{$index}"
                    );

                $size =
                    max(
                        0,
                        (int) (
                            $stat['size']
                            ?? 0
                        )
                    );

                $compressedSize =
                    max(
                        0,
                        (int) (
                            $stat['comp_size']
                            ?? 0
                        )
                    );

                $totalUncompressed +=
                    $size;

                if (
                    $size
                    > self::MAX_ENTRY_UNCOMPRESSED_BYTES
                ) {
                    $errors[] =
                        "{$name}: supera el máximo permitido "
                        ."de 16 MB sin comprimir.";
                }

                if (
                    $totalUncompressed
                    > self::MAX_TOTAL_UNCOMPRESSED_BYTES
                ) {
                    $errors[] =
                        'El contenido total del archivo supera '
                        .'el máximo permitido de 32 MB sin comprimir.';

                    break;
                }

                if (
                    $size
                    >= self::COMPRESSION_RATIO_MIN_BYTES
                ) {
                    if (
                        $compressedSize <= 0
                    ) {
                        $errors[] =
                            "{$name}: relación de compresión no válida.";

                        continue;
                    }

                    $ratio =
                        $size
                        / $compressedSize;

                    if (
                        $ratio
                        > self::MAX_COMPRESSION_RATIO
                    ) {
                        $errors[] =
                            "{$name}: la relación de compresión "
                            .'es demasiado alta.';
                    }
                }
            }

            return $this->result(
                $errors === [],
                $format,
                array_values(
                    array_unique(
                        $errors
                    )
                )
            );
        } finally {
            $zip->close();
        }
    }

    private function result(
        bool $valid,
        string $format,
        array $errors
    ): array {
        return [
            'valid' => $valid,
            'format' => $format,
            'errors' => $errors,
            'warnings' => [],
            'limits' => [
                'xlsx_entries' =>
                    self::MAX_XLSX_ENTRIES,

                'csv_zip_entries' =>
                    self::MAX_CSV_ZIP_ENTRIES,

                'entry_uncompressed_bytes' =>
                    self::MAX_ENTRY_UNCOMPRESSED_BYTES,

                'total_uncompressed_bytes' =>
                    self::MAX_TOTAL_UNCOMPRESSED_BYTES,

                'compression_ratio' =>
                    self::MAX_COMPRESSION_RATIO,
            ],
        ];
    }
}
