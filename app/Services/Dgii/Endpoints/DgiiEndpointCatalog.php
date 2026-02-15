<?php

namespace App\Services\Dgii\Endpoints;

final class DgiiEndpointCatalog
{
    /**
     * Cada key es el “nombre lógico” del endpoint.
     * path admite:
     * - {cf} -> testcf|certecf|ecf (o el que definas)
     * - placeholders adicionales: {trackid}, {rnc}, etc
     */
    public static function items(): array
    {
        return [
            // Auth (siempre se usa)
            'auth.seed' => [
                'label' => 'Get Seed (Semilla)',
                'host_key' => 'ecf', // base host (ej: ecf.dgii.gov.do)
                'path' => '/{cf}/autenticacion/api/autenticacion/semilla',
                'method' => 'GET',
            ],
            'auth.validate_seed' => [
                'label' => 'Validar Semilla (Token)',
                'host_key' => 'ecf',
                'path' => '/{cf}/autenticacion/api/autenticacion/validarsemilla',
                'method' => 'POST',
            ],

            // Consultas (ejemplos con placeholders)
            'consulta.resultado' => [
                'label' => 'Resultado e-CF (estado?trackid=...)',
                'host_key' => 'ecf',
                'path' => '/{cf}/consultaresultado/api/consultas/estado?trackid={trackid}',
                'method' => 'GET',
                'placeholders' => ['trackid'],
            ],

            // Directorio
            'consulta.directorio' => [
                'label' => 'Directorio de Servicios',
                'host_key' => 'ecf',
                'path' => '/{cf}/consultadirectorio/api/consultas/listado',
                'method' => 'GET',
            ],

            // Status servicios (host statusecf)
            'status.obtener' => [
                'label' => 'Estatus Servicios (obtenerestatus)',
                'host_key' => 'status',
                'path' => '/api/estatusservicios/obtenerestatus',
                'method' => 'GET',
            ],
        ];
    }
}
