<?php

namespace App\Services\Diagnosis;

/**
 * Read-only diagnostic projection for one client-native dynamic source.
 *
 * The projection is deliberately:
 * - source/domain agnostic;
 * - based only on aggregate profiling metadata;
 * - independent from mapping, canonical models and staging;
 * - free of raw client values and samples.
 */
final class DataTransformationBiSourceDiagnosticReadModel
{
    /**
     * @param array<string,mixed>|null $snapshot
     *
     * @return array<string,mixed>
     */
    public static function fromSnapshot(
        ?array $snapshot
    ): array {
        $sheets =
            is_array(
                $snapshot['sheets']
                ?? null
            )
                ? array_values(
                    $snapshot['sheets']
                )
                : [];

        $sourceRowCount =
            self::nonNegativeInteger(
                $snapshot['source_row_count']
                ?? 0
            );

        $profiledRowCount =
            self::nonNegativeInteger(
                $snapshot['profiled_row_count']
                ?? 0
            );

        $sheetCount =
            self::nonNegativeInteger(
                $snapshot['sheet_count']
                ?? count($sheets)
            );

        $columnCount = 0;

        $profiledCellCount = 0;
        $nonEmptyCellCount = 0;
        $emptyCellCount = 0;

        $completeColumnCount = 0;
        $withMissingColumnCount = 0;
        $fullyEmptyColumnCount = 0;
        $unprofiledColumnCount = 0;
        $mixedTypeColumnCount = 0;
        $otherTypeColumnCount = 0;

        foreach ($sheets as $sheet) {
            if (! is_array($sheet)) {
                continue;
            }

            $columns =
                is_array(
                    $sheet['columns']
                    ?? null
                )
                    ? array_values(
                        $sheet['columns']
                    )
                    : [];

            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }

                $columnCount++;

                $profiled =
                    self::nonNegativeInteger(
                        $column['profiled_value_count']
                        ?? 0
                    );

                $nonEmpty =
                    self::nonNegativeInteger(
                        $column['non_empty_count']
                        ?? 0
                    );

                $empty =
                    self::nonNegativeInteger(
                        $column['empty_count']
                        ?? 0
                    );

                $profiledCellCount += $profiled;
                $nonEmptyCellCount += $nonEmpty;
                $emptyCellCount += $empty;

                if ($profiled === 0) {
                    $unprofiledColumnCount++;
                } elseif ($nonEmpty === 0) {
                    $fullyEmptyColumnCount++;
                    $withMissingColumnCount++;
                } elseif ($empty > 0) {
                    $withMissingColumnCount++;
                } else {
                    $completeColumnCount++;
                }

                $primitiveTypes =
                    is_array(
                        $column['primitive_types']
                        ?? null
                    )
                        ? $column['primitive_types']
                        : [];

                $families =
                    self::observedTypeFamilies(
                        $primitiveTypes
                    );

                if (count($families) > 1) {
                    $mixedTypeColumnCount++;
                }

                if (
                    self::nonNegativeInteger(
                        $primitiveTypes['other']
                        ?? 0
                    ) > 0
                ) {
                    $otherTypeColumnCount++;
                }
            }
        }

        $available =
            ($snapshot['kind'] ?? null)
                === 'source_asset_value_profile'
            && $sheets !== [];

        return [
            'available' =>
                $available,

            'profile_version' =>
                isset($snapshot['version'])
                    ? self::nonNegativeInteger(
                        $snapshot['version']
                    )
                    : null,

            'format' =>
                isset($snapshot['format'])
                && is_string(
                    $snapshot['format']
                )
                    ? $snapshot['format']
                    : null,

            'volume' => [
                'source_row_count' =>
                    $sourceRowCount,

                'profiled_row_count' =>
                    $profiledRowCount,

                'sheet_count' =>
                    $sheetCount,

                'column_count' =>
                    $columnCount,
            ],

            'coverage' => [
                'profiled_cell_count' =>
                    $profiledCellCount,

                'non_empty_cell_count' =>
                    $nonEmptyCellCount,

                'empty_cell_count' =>
                    $emptyCellCount,

                'completeness_percent' =>
                    self::percentage(
                        $nonEmptyCellCount,
                        $profiledCellCount
                    ),

                'missing_percent' =>
                    self::percentage(
                        $emptyCellCount,
                        $profiledCellCount
                    ),
            ],

            'columns' => [
                'complete_count' =>
                    $completeColumnCount,

                'with_missing_count' =>
                    $withMissingColumnCount,

                'fully_empty_count' =>
                    $fullyEmptyColumnCount,

                'unprofiled_count' =>
                    $unprofiledColumnCount,

                'mixed_type_count' =>
                    $mixedTypeColumnCount,

                'with_other_type_count' =>
                    $otherTypeColumnCount,
            ],

            'scan' => [
                'full_scan' =>
                    is_bool(
                        $snapshot['full_scan']
                        ?? null
                    )
                        ? $snapshot['full_scan']
                        : null,

                'source_row_count' =>
                    $sourceRowCount,

                'profiled_row_count' =>
                    $profiledRowCount,
            ],
        ];
    }

    /**
     * Integer and decimal are one compatible numeric family.
     *
     * @param array<string,mixed> $types
     *
     * @return array<int,string>
     */
    private static function observedTypeFamilies(
        array $types
    ): array {
        $families = [];

        if (
            self::nonNegativeInteger(
                $types['integer']
                ?? 0
            ) > 0
            || self::nonNegativeInteger(
                $types['decimal']
                ?? 0
            ) > 0
        ) {
            $families[] = 'numeric';
        }

        if (
            self::nonNegativeInteger(
                $types['text']
                ?? 0
            ) > 0
        ) {
            $families[] = 'text';
        }

        if (
            self::nonNegativeInteger(
                $types['boolean']
                ?? 0
            ) > 0
        ) {
            $families[] = 'boolean';
        }

        if (
            self::nonNegativeInteger(
                $types['other']
                ?? 0
            ) > 0
        ) {
            $families[] = 'other';
        }

        return $families;
    }

    private static function nonNegativeInteger(
        mixed $value
    ): int {
        return max(
            0,
            (int) $value
        );
    }

    private static function percentage(
        int $part,
        int $total
    ): ?float {
        if ($total <= 0) {
            return null;
        }

        return round(
            ($part / $total) * 100,
            2
        );
    }
}
