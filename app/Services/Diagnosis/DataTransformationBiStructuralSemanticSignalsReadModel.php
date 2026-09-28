<?php

namespace App\Services\Diagnosis;

/**
 * Safe structural semantic signals derived only from profiling metadata.
 *
 * This read model observes column names and aggregate technical metadata.
 *
 * It deliberately does NOT:
 * - read or expose raw client values;
 * - inspect samples;
 * - infer a business-domain catalog;
 * - declare that a BI capability is supported;
 * - generate findings or recommendations;
 * - calculate readiness, risk or opportunity scores;
 * - depend on canonical, staging or implementation models.
 *
 * A signal means only:
 * "the delivered structure contains metadata compatible with this concept".
 */
final class DataTransformationBiStructuralSemanticSignalsReadModel
{
    private const SCHEMA_VERSION = 1;

    /**
     * Controlled structural concepts.
     *
     * These are signals, not business domains.
     *
     * @var array<string,array{
     *     label:string,
     *     patterns:list<string>
     * }>
     */
    private const SIGNALS = [
        'identifier' => [
            'label' =>
                'Identificación',

            'patterns' => [
                'id',
                'uuid',
                'codigo',
                'code',
                'clave',
                'key',
                'identificador',
                'identifier',
            ],
        ],

        'temporal' => [
            'label' =>
                'Temporalidad',

            'patterns' => [
                'fecha',
                'date',
                'datetime',
                'timestamp',
                'nacimiento',
                'birth date',
                'vencimiento',
                'due date',
                'emision',
                'issue date',
                'creacion',
                'created at',
                'actualizacion',
                'updated at',
                'anio',
                'year',
                'mes',
                'month',
                'periodo',
                'period',
            ],
        ],

        'geographic' => [
            'label' =>
                'Geografía',

            'patterns' => [
                'pais',
                'country',
                'provincia',
                'province',
                'ciudad',
                'city',
                'municipio',
                'municipality',
                'sector',
                'zona',
                'zone',
                'region',
                'direccion',
                'address',
                'postal',
                'zip',
                'latitud',
                'latitude',
                'longitud',
                'longitude',
            ],
        ],

        'contact' => [
            'label' =>
                'Contacto',

            'patterns' => [
                'email',
                'correo',
                'correo electronico',
                'telefono',
                'phone',
                'movil',
                'mobile',
                'celular',
            ],
        ],

        'monetary' => [
            'label' =>
                'Valores monetarios',

            'patterns' => [
                'monto',
                'amount',
                'importe',
                'total',
                'subtotal',
                'precio',
                'price',
                'costo',
                'cost',
                'saldo',
                'balance',
            ],
        ],

        'quantity' => [
            'label' =>
                'Cantidades',

            'patterns' => [
                'cantidad',
                'quantity',
                'qty',
                'unidades',
                'units',
                'existencia',
                'stock',
            ],
        ],

        'product_reference' => [
            'label' =>
                'Referencia de producto',

            'patterns' => [
                'sku',
                'ean',
                'upc',
                'producto',
                'product',
                'articulo',
                'item',
            ],
        ],

        'financial_terms' => [
            'label' =>
                'Condiciones financieras',

            'patterns' => [
                'credito',
                'credit',
                'limite credito',
                'credit limit',
                'pago',
                'payment',
                'vencido',
                'overdue',
                'cxc',
                'cuenta por cobrar',
                'accounts receivable',
                'cxp',
                'cuenta por pagar',
                'accounts payable',
            ],
        ],

        'classification_status' => [
            'label' =>
                'Clasificación o estado',

            'patterns' => [
                'estado',
                'status',
                'estatus',
                'tipo',
                'type',
                'categoria',
                'category',
                'segmento',
                'segment',
            ],
        ],
    ];

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

        $available =
            ($snapshot['kind'] ?? null)
                === 'source_asset_value_profile'
            && $sheets !== [];

        if (! $available) {
            return [
                'kind' =>
                    'data_bi_structural_semantic_signals',

                'schema_version' =>
                    self::SCHEMA_VERSION,

                'available' =>
                    false,

                'signal_count' =>
                    0,

                'matched_column_count' =>
                    0,

                'unmatched_column_count' =>
                    0,

                'signals' =>
                    [],

                'matched_columns' =>
                    [],
            ];
        }

        /**
         * @var array<string,array{
         *     key:string,
         *     label:string,
         *     columns:list<array<string,mixed>>
         * }>
         */
        $signals = [];

        $matchedColumns = [];

        $totalColumns = 0;

        foreach ($sheets as $sheetPosition => $sheet) {
            if (! is_array($sheet)) {
                continue;
            }

            $sheetIndex =
                isset($sheet['index'])
                    ? (int) $sheet['index']
                    : $sheetPosition;

            $sheetName =
                isset($sheet['name'])
                && $sheet['name'] !== null
                    ? (string) $sheet['name']
                    : null;

            $columns =
                is_array(
                    $sheet['columns']
                    ?? null
                )
                    ? array_values(
                        $sheet['columns']
                    )
                    : [];

            foreach (
                $columns
                as $columnPosition => $column
            ) {
                if (! is_array($column)) {
                    continue;
                }

                $totalColumns++;

                $columnKey =
                    isset($column['key'])
                    && $column['key'] !== null
                        ? trim(
                            (string) $column['key']
                        )
                        : null;

                $header =
                    isset($column['header'])
                    && $column['header'] !== null
                        ? trim(
                            (string) $column['header']
                        )
                        : null;

                $label =
                    $header !== null
                    && $header !== ''
                        ? $header
                        : (
                            $columnKey !== null
                            && $columnKey !== ''
                                ? $columnKey
                                : null
                        );

                if ($label === null) {
                    continue;
                }

                $matches =
                    self::matchesForLabel(
                        $label
                    );

                if ($matches === []) {
                    continue;
                }

                $profiled =
                    self::nonNegativeInteger(
                        $column[
                            'profiled_value_count'
                        ]
                        ?? 0
                    );

                $nonEmpty =
                    self::nonNegativeInteger(
                        $column[
                            'non_empty_count'
                        ]
                        ?? 0
                    );

                $empty =
                    self::nonNegativeInteger(
                        $column[
                            'empty_count'
                        ]
                        ?? 0
                    );

                $columnEvidence = [
                    'sheet_index' =>
                        $sheetIndex,

                    'sheet_name' =>
                        $sheetName,

                    'column_key' =>
                        $columnKey,

                    'column_index' =>
                        isset($column['index'])
                            ? (int) $column['index']
                            : $columnPosition,

                    /*
                     * Column header is structural metadata.
                     * No row values or samples are included.
                     */
                    'header' =>
                        $header,

                    'profiled_value_count' =>
                        $profiled,

                    'non_empty_count' =>
                        $nonEmpty,

                    'empty_count' =>
                        $empty,

                    'non_empty_percent' =>
                        self::percentage(
                            $nonEmpty,
                            $profiled
                        ),

                    'coverage_status' =>
                        self::coverageStatus(
                            $profiled,
                            $nonEmpty,
                            $empty
                        ),

                    'observed_type_families' =>
                        self::observedTypeFamilies(
                            is_array(
                                $column['primitive_types']
                                ?? null
                            )
                                ? $column['primitive_types']
                                : []
                        ),

                    'matches' =>
                        $matches,
                ];

                $matchedColumns[] =
                    $columnEvidence;

                foreach ($matches as $match) {
                    $signalKey =
                        $match['signal_key'];

                    if (! isset($signals[$signalKey])) {
                        $signals[$signalKey] = [
                            'key' =>
                                $signalKey,

                            'label' =>
                                self::SIGNALS[$signalKey]
                                    ['label'],

                            'columns' =>
                                [],
                        ];
                    }

                    $signals[$signalKey]['columns'][] = [
                        'sheet_index' =>
                            $sheetIndex,

                        'sheet_name' =>
                            $sheetName,

                        'column_key' =>
                            $columnKey,

                        'column_index' =>
                            isset($column['index'])
                                ? (int) $column['index']
                                : $columnPosition,

                        'header' =>
                            $header,

                        'matched_term' =>
                            $match['matched_term'],

                        'coverage_status' =>
                            $columnEvidence[
                                'coverage_status'
                            ],

                        'non_empty_percent' =>
                            $columnEvidence[
                                'non_empty_percent'
                            ],

                        'observed_type_families' =>
                            $columnEvidence[
                                'observed_type_families'
                            ],
                    ];
                }
            }
        }

        $signalPayloads =
            array_values(
                array_map(
                    static function (
                        array $signal
                    ): array {
                        $signal['column_count'] =
                            count(
                                $signal['columns']
                            );

                        return $signal;
                    },
                    $signals
                )
            );

        usort(
            $signalPayloads,
            static fn (
                array $left,
                array $right
            ): int =>
                strcmp(
                    (string) $left['key'],
                    (string) $right['key']
                )
        );

        return [
            'kind' =>
                'data_bi_structural_semantic_signals',

            'schema_version' =>
                self::SCHEMA_VERSION,

            'available' =>
                true,

            'signal_count' =>
                count(
                    $signalPayloads
                ),

            'matched_column_count' =>
                count(
                    $matchedColumns
                ),

            'unmatched_column_count' =>
                max(
                    0,
                    $totalColumns
                    - count(
                        $matchedColumns
                    )
                ),

            'signals' =>
                $signalPayloads,

            'matched_columns' =>
                $matchedColumns,
        ];
    }

    /**
     * @return list<array{
     *     signal_key:string,
     *     matched_term:string
     * }>
     */
    private static function matchesForLabel(
        string $label
    ): array {
        $normalized =
            self::normalizeLabel(
                $label
            );

        if ($normalized === '') {
            return [];
        }

        $haystack =
            ' '.$normalized.' ';

        $matches = [];

        foreach (
            self::SIGNALS
            as $signalKey => $definition
        ) {
            foreach (
                $definition['patterns']
                as $pattern
            ) {
                $normalizedPattern =
                    self::normalizeLabel(
                        $pattern
                    );

                if (
                    $normalizedPattern === ''
                    || ! str_contains(
                        $haystack,
                        ' '.$normalizedPattern.' '
                    )
                ) {
                    continue;
                }

                $matches[] = [
                    'signal_key' =>
                        $signalKey,

                    'matched_term' =>
                        $pattern,
                ];

                /*
                 * One explanatory match is enough for this signal.
                 */
                break;
            }
        }

        return $matches;
    }

    private static function normalizeLabel(
        string $value
    ): string {
        $value =
            preg_replace(
                '/([a-z0-9])([A-Z])/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/([^\d\s])(\d)/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/(\d)([^\d\s])/u',
                '$1 $2',
                $value
            )
            ?? $value;

        $value =
            mb_strtolower(
                $value,
                'UTF-8'
            );

        $value =
            strtr(
                $value,
                [
                    'á' => 'a',
                    'é' => 'e',
                    'í' => 'i',
                    'ó' => 'o',
                    'ú' => 'u',
                    'ü' => 'u',
                    'ñ' => 'n',
                ]
            );

        $value =
            preg_replace(
                '/[^a-z0-9]+/',
                ' ',
                $value
            )
            ?? '';

        $value =
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $value
                )
                ?? ''
            );

        /*
         * Common structural spelling after punctuation normalization.
         */
        $value =
            preg_replace(
                '/\be mail\b/',
                'email',
                $value
            )
            ?? $value;

        return $value;
    }

    /**
     * @param array<string,mixed> $types
     *
     * @return list<string>
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
            $families[] =
                'numeric';
        }

        if (
            self::nonNegativeInteger(
                $types['text']
                ?? 0
            ) > 0
        ) {
            $families[] =
                'text';
        }

        if (
            self::nonNegativeInteger(
                $types['boolean']
                ?? 0
            ) > 0
        ) {
            $families[] =
                'boolean';
        }

        if (
            self::nonNegativeInteger(
                $types['other']
                ?? 0
            ) > 0
        ) {
            $families[] =
                'other';
        }

        return $families;
    }

    private static function coverageStatus(
        int $profiled,
        int $nonEmpty,
        int $empty
    ): string {
        if ($profiled <= 0) {
            return 'unprofiled';
        }

        if ($nonEmpty <= 0) {
            return 'empty';
        }

        if ($empty <= 0) {
            return 'complete';
        }

        return 'partial';
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

    private static function nonNegativeInteger(
        mixed $value
    ): int {
        return max(
            0,
            (int) $value
        );
    }
}
