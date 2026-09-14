<?php

namespace App\Services\Diagnosis;

use Throwable;

class DataTransformationBiStandardIntakeValidationService
{
    private DataTransformationBiStandardIntakeValidator
        $structuralValidator;

    private DataTransformationBiStandardIntakeFileReader
        $fileReader;

    private DataTransformationBiStandardIntakeRowValidator
        $rowValidator;

    public function __construct(
        ?DataTransformationBiStandardIntakeValidator $structuralValidator = null,
        ?DataTransformationBiStandardIntakeFileReader $fileReader = null,
        ?DataTransformationBiStandardIntakeRowValidator $rowValidator = null
    ) {
        $this->structuralValidator =
            $structuralValidator
            ?? new DataTransformationBiStandardIntakeValidator();

        $this->fileReader =
            $fileReader
            ?? new DataTransformationBiStandardIntakeFileReader();

        $this->rowValidator =
            $rowValidator
            ?? new DataTransformationBiStandardIntakeRowValidator();
    }

    public function validate(
        string $path,
        string $originalName
    ): array {
        $structural =
            $this->structuralValidator
                ->validate(
                    $path,
                    $originalName
                );

        if (
            ($structural['valid'] ?? false)
            !== true
        ) {
            return [
                'valid' => false,

                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,

                'format' =>
                    $structural['format']
                    ?? 'unknown',

                'errors' =>
                    $structural['errors']
                    ?? [],

                'warnings' =>
                    $structural['warnings']
                    ?? [],

                'structural' =>
                    $structural,

                'content' =>
                    $this->skippedContent(),
            ];
        }

        try {
            $payload =
                $this->fileReader
                    ->read(
                        $path,
                        (string) $structural['format']
                    );
        } catch (Throwable $exception) {
            $message =
                $exception instanceof \LengthException
                    ? $exception->getMessage()
                    : (
                        'La estructura fue reconocida, '
                        .'pero no se pudieron leer las filas del intake.'
                    );

            return [
                'valid' => false,

                'schema_version' =>
                    DataTransformationBiStandardIntakeSchema
                        ::VERSION,

                'format' =>
                    $structural['format'],

                'errors' => [
                    $message,
                ],

                'warnings' =>
                    $structural['warnings']
                    ?? [],

                'structural' =>
                    $structural,

                'content' => [
                    ...$this->skippedContent(),
                    'errors' => [
                        $message,
                    ],
                ],
            ];
        }

        $content =
            $this->rowValidator
                ->validate(
                    $payload['rows']
                );

        $errors =
            array_values(
                array_merge(
                    $structural['errors']
                    ?? [],
                    $content['errors']
                    ?? []
                )
            );

        $warnings =
            array_values(
                array_merge(
                    $structural['warnings']
                    ?? [],
                    $content['warnings']
                    ?? []
                )
            );

        return [
            'valid' =>
                $errors === [],

            'schema_version' =>
                DataTransformationBiStandardIntakeSchema
                    ::VERSION,

            'format' =>
                $payload['format'],

            'errors' =>
                $errors,

            'warnings' =>
                $warnings,

            'structural' =>
                $structural,

            'content' => [
                'executed' => true,
                ...$content,
            ],
        ];
    }

    private function skippedContent(): array
    {
        return [
            'executed' => false,
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'domains' => [],
        ];
    }
}
