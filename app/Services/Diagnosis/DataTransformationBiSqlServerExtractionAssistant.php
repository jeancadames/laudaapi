<?php

namespace App\Services\Diagnosis;

use Illuminate\Validation\ValidationException;

final class DataTransformationBiSqlServerExtractionAssistant
{
    public const MAX_STRUCTURE_LENGTH = 50000;

    public const MAX_FIELDS = 500;

    /**
     * @return array{
     *     source_type:string,
     *     schema_name:string,
     *     table_name:string,
     *     field_count:int,
     *     fields:array<int,array{name:string,quoted:string}>,
     *     query:string,
     *     export:array{
     *         csv:array{label:string,extension:string,instructions:array<int,string>},
     *         xlsx:array{label:string,extension:string,instructions:array<int,string>}
     *     }
     * }
     */
    public function preview(
        string $schemaName,
        string $tableName,
        string $structureText
    ): array {
        $schemaName =
            $this->validateIdentifier(
                $schemaName,
                'schema_name'
            );

        $tableName =
            $this->validateIdentifier(
                $tableName,
                'table_name'
            );

        $structureText =
            $this->normalizeStructureText(
                $structureText
            );

        $this->assertReadOnlyStructure(
            $structureText
        );

        $fields =
            $this->extractFields(
                $structureText
            );

        if ($fields === []) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'No se pudieron detectar campos en la estructura suministrada.',
                ],
            ]);
        }

        if (count($fields) > self::MAX_FIELDS) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'La estructura contiene demasiados campos para esta vista previa.',
                ],
            ]);
        }

        return [
            'source_type' =>
                'sql_server',

            'schema_name' =>
                $schemaName,

            'table_name' =>
                $tableName,

            'field_count' =>
                count(
                    $fields
                ),

            'fields' =>
                array_map(
                    fn (string $field): array => [
                        'name' =>
                            $field,

                        'quoted' =>
                            $this->quoteIdentifier(
                                $field
                            ),
                    ],
                    $fields
                ),

            'query' =>
                $this->buildSelect(
                    $schemaName,
                    $tableName,
                    $fields
                ),

            'export' =>
                $this->exportInstructions(),
        ];
    }

    private function normalizeStructureText(
        string $value
    ): string {
        $value =
            str_replace(
                [
                    "\r\n",
                    "\r",
                ],
                "\n",
                trim(
                    $value
                )
            );

        if ($value === '') {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'Pega la estructura de la tabla antes de generar el query.',
                ],
            ]);
        }

        if (strlen($value) > self::MAX_STRUCTURE_LENGTH) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'La estructura pegada supera el tamaño permitido.',
                ],
            ]);
        }

        return $value;
    }

    private function validateIdentifier(
        string $value,
        string $field
    ): string {
        $value =
            trim(
                $value
            );

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => [
                    'Este identificador es obligatorio.',
                ],
            ]);
        }

        if (mb_strlen($value) > 128) {
            throw ValidationException::withMessages([
                $field => [
                    'El identificador no puede superar 128 caracteres.',
                ],
            ]);
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/u',
                $value
            ) === 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'El identificador contiene caracteres no permitidos.',
                ],
            ]);
        }

        if (
            preg_match(
                '/(;|--|\/\*|\*\/)/u',
                $value
            ) === 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'El identificador contiene sintaxis SQL no permitida.',
                ],
            ]);
        }

        return $value;
    }

    private function assertReadOnlyStructure(
        string $structure
    ): void {
        $forbidden = [
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'ALTER',
            'TRUNCATE',
            'MERGE',
            'EXEC',
            'EXECUTE',
            'GRANT',
            'REVOKE',
            'DENY',
            'OPENROWSET',
            'OPENDATASOURCE',
            'BULK INSERT',
            'XP_CMDSHELL',
        ];

        foreach ($forbidden as $keyword) {
            if (
                preg_match(
                    '/\b'
                    .preg_quote(
                        $keyword,
                        '/'
                    )
                    .'\b/iu',
                    $structure
                ) === 1
            ) {
                throw ValidationException::withMessages([
                    'structure_text' => [
                        'La entrada contiene una instrucción SQL ejecutable no permitida: '
                        .$keyword
                        .'. Pega únicamente la estructura de la tabla.',
                    ],
                ]);
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private function extractFields(
        string $structure
    ): array {
        $candidate =
            $this->extractCreateTableBody(
                $structure
            )
            ?? $structure;

        $fields = [];

        foreach (
            preg_split(
                '/\n/u',
                $candidate
            ) ?: [] as $line
        ) {
            $line =
                trim(
                    $line
                );

            if (
                $line === ''
                || preg_match(
                    '/^[-=+|]+$/u',
                    $line
                ) === 1
            ) {
                continue;
            }

            $line =
                rtrim(
                    $line,
                    ", \t"
                );

            if (
                preg_match(
                    '/^(CONSTRAINT|PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|INDEX)\b/iu',
                    $line
                ) === 1
            ) {
                continue;
            }

            $field =
                $this->extractFieldName(
                    $line
                );

            if ($field === null) {
                continue;
            }

            $key =
                mb_strtolower(
                    $field
                );

            if (
                !array_key_exists(
                    $key,
                    $fields
                )
            ) {
                $fields[$key] =
                    $field;
            }
        }

        return array_values(
            $fields
        );
    }

    private function extractCreateTableBody(
        string $structure
    ): ?string {
        if (
            preg_match(
                '/\bCREATE\s+TABLE\b/iu',
                $structure
            ) !== 1
        ) {
            return null;
        }

        $open =
            strpos(
                $structure,
                '('
            );

        $close =
            strrpos(
                $structure,
                ')'
            );

        if (
            $open === false
            || $close === false
            || $close <= $open
        ) {
            throw ValidationException::withMessages([
                'structure_text' => [
                    'La sentencia CREATE TABLE no contiene una definición de columnas válida.',
                ],
            ]);
        }

        return substr(
            $structure,
            $open + 1,
            $close - $open - 1
        );
    }

    private function extractFieldName(
        string $line
    ): ?string {
        if (
            preg_match(
                '/^\[([^\]]+)\]\s+/u',
                $line,
                $matches
            ) === 1
        ) {
            return $this->cleanFieldName(
                $matches[1]
            );
        }

        if (
            preg_match(
                '/^"([^"]+)"\s+/u',
                $line,
                $matches
            ) === 1
        ) {
            return $this->cleanFieldName(
                $matches[1]
            );
        }

        if (
            preg_match(
                '/^([^\s,;()]+)\s+/u',
                $line,
                $matches
            ) === 1
        ) {
            $candidate =
                trim(
                    $matches[1]
                );

            if (
                in_array(
                    mb_strtoupper(
                        $candidate
                    ),
                    [
                        'COLUMN_NAME',
                        'COLUMN',
                        'CAMPO',
                        'FIELD',
                        'NAME',
                    ],
                    true
                )
            ) {
                return null;
            }

            return $this->cleanFieldName(
                $candidate
            );
        }

        return null;
    }

    private function cleanFieldName(
        string $value
    ): ?string {
        $value =
            trim(
                $value
            );

        if (
            $value === ''
            || mb_strlen($value) > 128
        ) {
            return null;
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/u',
                $value
            ) === 1
        ) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<int,string> $fields
     */
    private function buildSelect(
        string $schema,
        string $table,
        array $fields
    ): string {
        $columns =
            implode(
                ",\n",
                array_map(
                    fn (string $field): string =>
                        '    '
                        .$this->quoteIdentifier(
                            $field
                        ),
                    $fields
                )
            );

        return "SELECT\n"
            .$columns
            ."\nFROM "
            .$this->quoteIdentifier(
                $schema
            )
            .'.'
            .$this->quoteIdentifier(
                $table
            )
            .';';
    }

    private function quoteIdentifier(
        string $identifier
    ): string {
        return '['
            .str_replace(
                ']',
                ']]',
                $identifier
            )
            .']';
    }

    /**
     * @return array{
     *     csv:array{label:string,extension:string,instructions:array<int,string>},
     *     xlsx:array{label:string,extension:string,instructions:array<int,string>}
     * }
     */
    private function exportInstructions(): array
    {
        return [
            'csv' => [
                'label' =>
                    'CSV',

                'extension' =>
                    '.csv',

                'instructions' => [
                    'Ejecuta el SELECT en SQL Server Management Studio o en la herramienta habitual del cliente.',
                    'Exporta el resultado completo conservando los nombres originales de las columnas.',
                    'Guarda el archivo como CSV con encabezados.',
                    'Regresa a LAUDA y súbelo como archivo de esta fuente.',
                ],
            ],

            'xlsx' => [
                'label' =>
                    'Excel XLSX',

                'extension' =>
                    '.xlsx',

                'instructions' => [
                    'Ejecuta el SELECT en SQL Server Management Studio o en la herramienta habitual del cliente.',
                    'Copia o exporta el resultado completo con encabezados.',
                    'Guarda el resultado en un archivo Excel XLSX sin renombrar las columnas.',
                    'Regresa a LAUDA y súbelo como archivo de esta fuente.',
                ],
            ],
        ];
    }
}
