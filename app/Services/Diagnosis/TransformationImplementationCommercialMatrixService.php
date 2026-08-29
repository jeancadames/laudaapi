<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCommercialRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransformationImplementationCommercialMatrixService
{
    public const TYPE_INITIATIVE_EFFORT =
        'initiative_effort';

    public const TYPE_PROFESSIONAL_CAPABILITY =
        'professional_capability';

    public function base(): array
    {
        $matrix = config(
            'lauda360_implementation',
            []
        );

        return is_array($matrix)
            ? $matrix
            : [];
    }

    public function current(): array
    {
        $matrix = $this->base();

        if (
            ! Schema::hasTable(
                'transformation_implementation_commercial_rates'
            )
        ) {
            return $matrix;
        }

        $version = trim(
            (string) (
                $matrix['version']
                ?? ''
            )
        );

        $rows =
            TransformationImplementationCommercialRate::query()
                ->where(
                    'matrix_version',
                    $version
                )
                ->get();

        foreach ($rows as $row) {
            $path =
                $this->matrixPath(
                    (string) $row->modality,
                    (string) $row->component_type,
                    (string) $row->component_key
                );

            if ($path === null) {
                continue;
            }

            if (
                data_get(
                    $matrix,
                    $path
                ) === null
            ) {
                continue;
            }

            data_set(
                $matrix,
                "{$path}.price_amount",
                $row->price_amount !== null
                    ? (float) $row->price_amount
                    : null
            );

            data_set(
                $matrix,
                "{$path}.duration_days",
                $row->duration_days !== null
                    ? (int) $row->duration_days
                    : null
            );
        }

        return $matrix;
    }

    public function readiness(
        ?array $matrix = null
    ): array {
        $matrix ??= $this->current();

        $schema = $this->base();
        $missing = [];

        foreach (
            array_keys(
                $schema['modalities']
                ?? []
            )
            as $modality
        ) {
            foreach (
                array_keys(
                    data_get(
                        $schema,
                        "modalities.{$modality}.initiative_effort",
                        []
                    )
                )
                as $effort
            ) {
                $path =
                    "modalities.{$modality}."
                    ."initiative_effort.{$effort}";

                $this->collectMissing(
                    $matrix,
                    $path,
                    $missing
                );
            }

            foreach (
                array_keys(
                    data_get(
                        $schema,
                        "modalities.{$modality}.professional_capabilities",
                        []
                    )
                )
                as $capability
            ) {
                $path =
                    "modalities.{$modality}."
                    ."professional_capabilities.{$capability}";

                $this->collectMissing(
                    $matrix,
                    $path,
                    $missing
                );
            }
        }

        $missing =
            array_values(
                array_unique($missing)
            );

        return [
            'ready' =>
                $missing === [],

            'version' =>
                $matrix['version']
                ?? null,

            'currency' =>
                $matrix['currency']
                ?? 'DOP',

            'missing' =>
                $missing,

            'missing_count' =>
                count($missing),
        ];
    }

    public function normalizeAdminPayload(
        array $payload
    ): array {
        $matrix = $this->base();

        foreach (
            array_keys(
                $matrix['modalities']
                ?? []
            )
            as $modality
        ) {
            foreach (
                array_keys(
                    data_get(
                        $matrix,
                        "modalities.{$modality}.initiative_effort",
                        []
                    )
                )
                as $effort
            ) {
                $sourcePath =
                    "{$modality}."
                    ."initiative_effort."
                    ."{$effort}";

                $targetPath =
                    "modalities.{$sourcePath}";

                [
                    $price,
                    $duration,
                ] = $this->normalizePair(
                    data_get(
                        $payload,
                        $sourcePath,
                        []
                    ),
                    $targetPath
                );

                data_set(
                    $matrix,
                    "{$targetPath}.price_amount",
                    $price
                );

                data_set(
                    $matrix,
                    "{$targetPath}.duration_days",
                    $duration
                );
            }

            foreach (
                array_keys(
                    data_get(
                        $matrix,
                        "modalities.{$modality}.professional_capabilities",
                        []
                    )
                )
                as $capability
            ) {
                $sourcePath =
                    "{$modality}."
                    ."professional_capabilities."
                    ."{$capability}";

                $targetPath =
                    "modalities.{$sourcePath}";

                [
                    $price,
                    $duration,
                ] = $this->normalizePair(
                    data_get(
                        $payload,
                        $sourcePath,
                        []
                    ),
                    $targetPath
                );

                data_set(
                    $matrix,
                    "{$targetPath}.price_amount",
                    $price
                );

                data_set(
                    $matrix,
                    "{$targetPath}.duration_days",
                    $duration
                );
            }
        }

        return $matrix;
    }

    public function save(
        array $payload,
        ?int $userId = null
    ): array {
        if (
            ! Schema::hasTable(
                'transformation_implementation_commercial_rates'
            )
        ) {
            throw ValidationException::withMessages([
                'commercial_matrix' =>
                    'La tabla de configuración comercial aún no está instalada.',
            ]);
        }

        $matrix =
            $this->normalizeAdminPayload(
                $payload
            );

        $version =
            trim(
                (string) (
                    $matrix['version']
                    ?? ''
                )
            );

        $currency =
            strtoupper(
                trim(
                    (string) (
                        $matrix['currency']
                        ?? 'DOP'
                    )
                )
            );

        DB::transaction(
            function () use (
                $matrix,
                $version,
                $currency,
                $userId
            ): void {
                foreach (
                    $matrix['modalities']
                    as $modality => $definition
                ) {
                    foreach (
                        $definition['initiative_effort']
                        as $effort => $row
                    ) {
                        $this->persistSlot(
                            $version,
                            $modality,
                            self::TYPE_INITIATIVE_EFFORT,
                            $effort,
                            $row,
                            $currency,
                            $userId
                        );
                    }

                    foreach (
                        $definition['professional_capabilities']
                        as $capability => $row
                    ) {
                        $this->persistSlot(
                            $version,
                            $modality,
                            self::TYPE_PROFESSIONAL_CAPABILITY,
                            $capability,
                            $row,
                            $currency,
                            $userId
                        );
                    }
                }
            }
        );

        $fresh = $this->current();

        return [
            'matrix' => $fresh,
            'readiness' =>
                $this->readiness($fresh),
        ];
    }

    private function normalizePair(
        mixed $raw,
        string $path
    ): array {
        $raw =
            is_array($raw)
                ? $raw
                : [];

        $priceRaw =
            $raw['price_amount']
            ?? null;

        $durationRaw =
            $raw['duration_days']
            ?? null;

        $priceEmpty =
            $priceRaw === null
            || (
                is_string($priceRaw)
                && trim($priceRaw) === ''
            );

        $durationEmpty =
            $durationRaw === null
            || (
                is_string($durationRaw)
                && trim($durationRaw) === ''
            );

        if (
            $priceEmpty
            && $durationEmpty
        ) {
            return [
                null,
                null,
            ];
        }

        if (
            $priceEmpty
            xor $durationEmpty
        ) {
            throw ValidationException::withMessages([
                $path =>
                    'Precio y duración deben configurarse juntos o dejarse ambos vacíos.',
            ]);
        }

        if (
            ! is_numeric($priceRaw)
            || (float) $priceRaw < 0
        ) {
            throw ValidationException::withMessages([
                "{$path}.price_amount" =>
                    'El precio debe ser un monto mayor o igual a cero.',
            ]);
        }

        $duration =
            filter_var(
                $durationRaw,
                FILTER_VALIDATE_INT
            );

        if (
            $duration === false
            || $duration < 1
        ) {
            throw ValidationException::withMessages([
                "{$path}.duration_days" =>
                    'La duración debe ser un número entero de días mayor o igual a 1.',
            ]);
        }

        return [
            round(
                (float) $priceRaw,
                2
            ),
            (int) $duration,
        ];
    }

    private function collectMissing(
        array $matrix,
        string $path,
        array &$missing
    ): void {
        $price =
            data_get(
                $matrix,
                "{$path}.price_amount"
            );

        $duration =
            data_get(
                $matrix,
                "{$path}.duration_days"
            );

        if (
            ! is_numeric($price)
            || (float) $price < 0
        ) {
            $missing[] =
                "{$path}.price_amount";
        }

        if (
            ! is_numeric($duration)
            || (int) $duration < 1
        ) {
            $missing[] =
                "{$path}.duration_days";
        }
    }

    private function persistSlot(
        string $version,
        string $modality,
        string $type,
        string $key,
        array $row,
        string $currency,
        ?int $userId
    ): void {
        $model =
            TransformationImplementationCommercialRate::query()
                ->firstOrNew([
                    'matrix_version' =>
                        $version,

                    'modality' =>
                        $modality,

                    'component_type' =>
                        $type,

                    'component_key' =>
                        $key,
                ]);

        if (! $model->exists) {
            $model->created_by_user_id =
                $userId;
        }

        $model->forceFill([
            'price_amount' =>
                $row['price_amount']
                ?? null,

            'duration_days' =>
                $row['duration_days']
                ?? null,

            'currency' =>
                $currency,

            'updated_by_user_id' =>
                $userId,
        ])->save();
    }

    private function matrixPath(
        string $modality,
        string $type,
        string $key
    ): ?string {
        if (
            $type
            === self::TYPE_INITIATIVE_EFFORT
        ) {
            return
                "modalities.{$modality}."
                ."initiative_effort.{$key}";
        }

        if (
            $type
            === self::TYPE_PROFESSIONAL_CAPABILITY
        ) {
            return
                "modalities.{$modality}."
                ."professional_capabilities.{$key}";
        }

        return null;
    }
}
