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

        $envs = ['precert', 'cert', 'prod'];

        $EcfBase    = 'https://ecf.dgii.gov.do';
        $FcBase     = 'https://fc.dgii.gov.do';
        $StatusBase = 'https://statusecf.dgii.gov.do';

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
            $isTemplated = str_contains($path, '{');

            return [
                'key'          => $key,
                'name'         => $name,
                'description'  => null,
                'base_url'     => $baseUrl,
                'path'         => $path,
                'method'       => strtoupper($method),
                'is_templated' => $isTemplated,
                'is_default'   => $isDefault,
                'is_active'    => $isActive,
                // JSON column: usa json_encode para evitar inconsistencias entre drivers
                'meta'         => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        };

        // =========================
        // Core Auth (alineado UI)
        // =========================
        $auth = [
            $make('auth.seed', 'Get Seed (Semilla)', $EcfBase, '/{cf}/autenticacion/api/autenticacion/semilla', 'GET', true),
            $make('auth.validate_seed', 'Validar Semilla', $EcfBase, '/{cf}/autenticacion/api/autenticacion/validarsemilla', 'POST', true),
        ];

        // =========================
        // Consultas / Directorio (alias UI)
        // =========================
        $consultas = [
            // ✅ alias estable para tu mapping actual en front: consulta.resultado -> UrlConsultaResultado
            $make(
                'consulta.resultado',
                'Consulta Resultado e-CF (estado?trackid=)',
                $EcfBase,
                '/{cf}/consultaresultado/api/consultas/estado?trackid={trackid}',
                'GET',
                true,
                true,
                ['placeholders' => ['trackid']]
            ),
            // Mantén tu key previa si ya la usas en código legacy
            $make(
                'consulta_resultado_estado_trackid',
                'Consulta Resultado e-CF (legacy key)',
                $EcfBase,
                '/{cf}/consultaresultado/api/consultas/estado?trackid={trackid}',
                'GET',
                false,
                true,
                ['placeholders' => ['trackid']]
            ),

            $make('consulta.estado', 'Consulta Estado e-CF', $EcfBase, '/{cf}/consultaestado/api/consultas', 'POST'),
            $make('consulta_trackids', 'Consulta TrackIds (legacy)', $EcfBase, '/{cf}/consultatrackids/api/consulta', 'POST'),
            $make('consulta.trackids', 'Consulta TrackIds', $EcfBase, '/{cf}/consultatrackids/api/consulta', 'POST'),

            // ✅ alias estable para tu mapping actual en front: consulta.directorio -> UrlDirectorioServicios
            $make('consulta.directorio', 'Directorio Servicios', $EcfBase, '/{cf}/consultadirectorio/api/consultas/listado', 'GET', true),
            $make('directorio_servicios', 'Directorio Servicios (legacy key)', $EcfBase, '/{cf}/consultadirectorio/api/consultas/listado', 'GET'),
        ];

        // =========================
        // Status servicios (alias UI)
        // =========================
        $status = [
            // ✅ alias estable para tu mapping actual en front: status.obtener -> UrlStatusServicios
            $make('status.obtener', 'DGII Estatus Servicios', $StatusBase, '/api/estatusservicios/obtenerestatus', 'GET', true),
            // Mantén tus keys anteriores
            $make('dgii.estatus_servicios', 'DGII Estatus Servicios (legacy key)', $StatusBase, '/api/estatusservicios/obtenerestatus', 'GET', false),
            $make('dgii.ventanas_mantenimiento', 'DGII Ventanas Mantenimiento', $StatusBase, '/api/estatusservicios/obtenerventanasmantenimiento', 'GET'),
            $make('dgii.verificar_estado', 'DGII Verificar Estado', $StatusBase, '/api/estatusservicios/verificarestado', 'GET'),
        ];

        // =========================
        // Recepción / Aprobación (general)
        // =========================
        $operaciones = [
            $make('recepcion.facturas_electronicas', 'Recepción e-CF', $EcfBase, '/{cf}/recepcion/api/facturaselectronicas', 'POST'),
            $make('aprobacion_comercial', 'Aprobación Comercial', $EcfBase, '/{cf}/aprobacioncomercial/api/aprobacioncomercial', 'POST'),
            $make('emision.consulta_acuse', 'Consulta Acuse Recibo (Emisor-Receptor)', $EcfBase, '/{cf}/emisorreceptor/api/emision/consultaacuserecibo', 'POST'),
            $make('anulacion_rangos', 'Anulación Rangos', $EcfBase, '/{cf}/anulacionrangos/api/operaciones/anularrango', 'POST'),
        ];

        // =========================
        // FC / FCE (host fc.dgii.gov.do)
        // =========================
        $fc = [
            $make('recepcion_fc', 'Recepción FC', $FcBase, '/{cf}/recepcionfc/api/recepcion/ecf', 'POST'),
            $make('consultar_fce', 'Consultar FCE', $FcBase, '/{cf}/consultarfce/api/Consultas', 'POST'),
        ];

        // =========================
        // Emisor-Receptor FE (ComER)
        // =========================
        $comEr = [
            $make('com_er.get_seed', 'ComER Get Seed', $EcfBase, '/{cf}/emisorreceptor/fe/autenticacion/api/semilla', 'GET'),
            $make('com_er.test_seed', 'ComER Test/Validación Certificado', $EcfBase, '/{cf}/emisorreceptor/fe/autenticacion/api/validacioncertificado', 'POST'),

            $make('com_er.emision_ncf', 'ComER Emisión NCF', $EcfBase, '/{cf}/emisorreceptor/api/emision/emisioncomprobantes', 'POST'),
            $make('com_er.emision_acuse', 'ComER Emisión Consulta Acuse', $EcfBase, '/{cf}/emisorreceptor/api/emision/consultaacuserecibo', 'POST'),
            $make('com_er.envio_aprobacion', 'ComER Envío Aprobación Comercial', $EcfBase, '/{cf}/emisorreceptor/api/emision/envioaprobacioncomercial', 'POST'),

            $make('com_er.recepcion_ecf', 'ComER Recepción e-CF', $EcfBase, '/{cf}/emisorreceptor/fe/recepcion/api/ecf', 'POST'),
            $make('com_er.recepcion_aprobacion', 'ComER Recepción Aprobación Comercial', $EcfBase, '/{cf}/emisorreceptor/fe/aprobacioncomercial/api/ecf', 'POST'),

            // ✅ alias “bonitos” para tu código (puntos 8–11)
            $make('ws.recepcion_ecf', 'WS Recepción e-CF (alias)', $EcfBase, '/{cf}/emisorreceptor/fe/recepcion/api/ecf', 'POST'),
            $make('ws.envio_aprobacion_comercial', 'WS Envío Aprobación Comercial (alias)', $EcfBase, '/{cf}/emisorreceptor/api/emision/envioaprobacioncomercial', 'POST'),
        ];

        $all = array_merge($auth, $consultas, $status, $operaciones, $fc, $comEr);

        DB::transaction(function () use ($envs, $all, $now) {
            $rows = [];

            foreach ($envs as $env) {
                foreach ($all as $row) {
                    $rows[] = array_merge($row, [
                        'environment' => $env,
                        'updated_at'  => $now,
                    ]);
                }
            }

            // upsert por unique(environment, key)
            DB::table('dgii_endpoint_catalog')->upsert(
                $rows,
                ['environment', 'key'],
                [
                    'name',
                    'description',
                    'base_url',
                    'path',
                    'method',
                    'is_templated',
                    'is_default',
                    'is_active',
                    'meta',
                    'updated_at',
                    // created_at NO se toca en updates
                ]
            );
        });
    }
}
