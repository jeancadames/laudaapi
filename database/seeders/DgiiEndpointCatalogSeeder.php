<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DgiiEndpointCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Ambientes internos (tú decides cómo lo manejas en settings/UI)
        $envs = ['precert', 'cert', 'prod'];

        // Base hosts
        $EcfBase    = 'https://ecf.dgii.gov.do';
        $FcBase     = 'https://fc.dgii.gov.do';
        $StatusBase = 'https://statusecf.dgii.gov.do';

        // Helper
        $make = function (
            string $key,
            string $name,
            string $baseUrl,
            string $path,
            string $method = 'GET',
            bool $isDefault = false,
            bool $isActive = true,
            array $meta = []
        ) use ($now) {
            return [
                'key'          => $key,
                'name'         => $name,
                'description'  => null,
                'base_url'     => $baseUrl,
                'path'         => $path,
                'method'       => strtoupper($method),
                'is_templated' => str_contains($path, '{') || str_contains($path, '/{cf}/'),
                'is_default'   => $isDefault,
                'is_active'    => $isActive,
                'meta'         => empty($meta) ? null : json_encode($meta),
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        };

        // Endpoints templated (usan {cf} y/o placeholders)
        $templated = [
            // ✅ Auth (ALINEADO con DgiiEndpointCatalog::items(): auth.seed / auth.validate_seed)
            $make(
                key: 'auth.seed',
                name: 'Get Seed (Semilla)',
                baseUrl: $EcfBase,
                path: '/{cf}/autenticacion/api/autenticacion/semilla',
                method: 'GET',
                isDefault: true,
            ),
            $make(
                key: 'auth.validate_seed',
                name: 'Validar Semilla',
                baseUrl: $EcfBase,
                path: '/{cf}/autenticacion/api/autenticacion/validarsemilla',
                method: 'POST',
                isDefault: true,
            ),

            // Recepción / Aprobación / Consultas
            $make('recepcion.facturas_electronicas', 'Recepción e-CF', $EcfBase, '/{cf}/recepcion/api/facturaselectronicas', 'POST'),
            $make('aprobacion_comercial', 'Aprobación Comercial', $EcfBase, '/{cf}/aprobacioncomercial/api/aprobacioncomercial', 'POST'),

            $make('emision.consulta_acuse', 'Consulta Acuse Recibo (Emisor-Receptor)', $EcfBase, '/{cf}/emisorreceptor/api/emision/consultaacuserecibo', 'POST'),

            $make('anulacion_rangos', 'Anulación Rangos', $EcfBase, '/{cf}/anulacionrangos/api/operaciones/anularrango', 'POST'),

            // ✅ FC / FCE (CORREGIDO: host fc.dgii.gov.do)
            $make('recepcion_fc', 'Recepción FC', $FcBase, '/{cf}/recepcionfc/api/recepcion/ecf', 'POST'),
            $make('consultar_fce', 'Consultar FCE', $FcBase, '/{cf}/consultarfce/api/Consultas', 'POST'),

            // Resultado/Estado/TrackId
            $make(
                'consulta_resultado_estado_trackid',
                'Consulta Resultado e-CF (estado?trackid=)',
                $EcfBase,
                '/{cf}/consultaresultado/api/consultas/estado?trackid={trackid}',
                'GET',
                meta: ['placeholders' => ['trackid']]
            ),
            $make('consulta_estado', 'Consulta Estado e-CF', $EcfBase, '/{cf}/consultaestado/api/consultas', 'POST'),
            $make('consulta_trackids', 'Consulta TrackIds', $EcfBase, '/{cf}/consultatrackids/api/consulta', 'POST'),

            // Directorio / Timbres
            $make('directorio_servicios', 'Directorio Servicios', $EcfBase, '/{cf}/consultadirectorio/api/consultas/listado', 'GET'),
            $make('consulta_timbre_qr', 'Consulta Timbre (QR)', $EcfBase, '/{cf}/consultatimbre', 'GET'),
            $make('consulta_timbre_fc_qr', 'Consulta Timbre FC (QR)', $EcfBase, '/{cf}/consultatimbrefc', 'GET'),

            // Emisor-Receptor FE
            $make('com_er.get_seed', 'ComER Get Seed', $EcfBase, '/{cf}/emisorreceptor/fe/autenticacion/api/semilla', 'GET'),
            $make('com_er.test_seed', 'ComER Test/Validación Certificado', $EcfBase, '/{cf}/emisorreceptor/fe/autenticacion/api/validacioncertificado', 'POST'),

            $make('com_er.emision_ncf', 'ComER Emisión NCF', $EcfBase, '/{cf}/emisorreceptor/api/emision/emisioncomprobantes', 'POST'),
            $make('com_er.emision_acuse', 'ComER Emisión Consulta Acuse', $EcfBase, '/{cf}/emisorreceptor/api/emision/consultaacuserecibo', 'POST'),
            $make('com_er.envio_aprobacion', 'ComER Envío Aprobación Comercial', $EcfBase, '/{cf}/emisorreceptor/api/emision/envioaprobacioncomercial', 'POST'),

            $make('com_er.recepcion_ecf', 'ComER Recepción e-CF', $EcfBase, '/{cf}/emisorreceptor/fe/recepcion/api/ecf', 'POST'),
            $make('com_er.recepcion_aprobacion', 'ComER Recepción Aprobación Comercial', $EcfBase, '/{cf}/emisorreceptor/fe/aprobacioncomercial/api/ecf', 'POST'),
        ];

        // Endpoints NO templated (status servicios)
        $status = [
            $make('dgii.estatus_servicios', 'DGII Estatus Servicios', $StatusBase, '/api/estatusservicios/obtenerestatus', 'GET', true),
            $make('dgii.ventanas_mantenimiento', 'DGII Ventanas Mantenimiento', $StatusBase, '/api/estatusservicios/obtenerventanasmantenimiento', 'GET'),
            $make('dgii.verificar_estado', 'DGII Verificar Estado', $StatusBase, '/api/estatusservicios/verificarestado', 'GET'),
        ];

        // Sembrar por ambiente
        DB::transaction(function () use ($envs, $templated, $status, $now) {
            foreach ($envs as $env) {
                foreach (array_merge($templated, $status) as $row) {
                    $payload = array_merge($row, [
                        'environment' => $env,
                        'updated_at'  => $now,
                    ]);

                    DB::table('dgii_endpoint_catalog')->updateOrInsert(
                        ['environment' => $env, 'key' => $row['key']],
                        $payload
                    );
                }
            }
        });
    }
}
