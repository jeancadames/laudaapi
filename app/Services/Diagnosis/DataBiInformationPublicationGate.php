<?php

namespace App\Services\Diagnosis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * D2F-T3E3.
 * Una misma regla para mostrar el botón y ejecutar el POST.
 * Publicar significa disponibilidad en el portal, no enviar correo.
 */
final class DataBiInformationPublicationGate
{
    public static function allowedEnvironment(
        string $environment,
        string $driver,
        string $database
    ): bool {
        return $driver === 'mysql'
            && (
                (
                    $environment === 'local'
                    && $database === 'laudaapi_dev'
                )
                || (
                    $environment === 'production'
                    && $database === 'laudaapi-prod'
                )
            );
    }

    public static function enabled(): bool
    {
        $db = DB::connection();

        if (!self::allowedEnvironment(
            app()->environment(),
            $db->getDriverName(),
            $db->getDatabaseName()
        )) {
            return false;
        }

        return Schema::hasColumn(
            'data_bi_information_requests',
            'sent_by_user_id'
        ) && Route::has(
            'app.transformation.data_bi.information_requirements.show'
        );
    }

    /**
     * Exige al menos un destinatario con membresía activa.
     */
    public static function hasActiveRecipient(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        return DB::table('companies as c')
            ->join(
                'subscriber_user as su',
                'su.subscriber_id',
                '=',
                'c.subscriber_id'
            )
            ->where('c.id', $companyId)
            ->where('su.active', 1)
            ->where(function ($query): void {
                $query
                    ->whereIn('su.role', ['owner', 'admin'])
                    ->orWhereColumn(
                        'su.user_id',
                        'c.owner_user_id'
                    );
            })
            ->exists();
    }
}
