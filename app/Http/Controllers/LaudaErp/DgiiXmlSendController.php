<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Dgii\HttpDgiiXmlSender;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class DgiiXmlSendController extends Controller
{
    /**
     * Map central:
     * - base_dir: carpeta donde están los XML del set (por kind)
     * - endpoint_key: key en dgii_endpoint_catalog
     * - resp_suffix: archivo que vamos a guardar como “respuesta”
     */
    private const KIND_MAP = [
        'ecf' => [
            'base_dir'    => 'dgii/cert-ecf',
            'endpoint_key'=> 'recepcion.facturas_electronicas',           // ✅ AJUSTA si tu key se llama distinto
            'resp_suffix' => '_arecf.xml',
        ],
        'rfce' => [
            'base_dir'    => 'dgii/cert-rfce',
            'endpoint_key'=> 'recepcion_fc',           // ✅ tú lo dijiste
            'resp_suffix' => '_resp_fc.xml',
        ],
        'acecf' => [
            'base_dir'    => 'dgii/cert-acecf',
            'endpoint_key'=> 'aprobacion_comercial',   // ✅ tú lo dijiste
            'resp_suffix' => '_resp_aprob.xml',
        ],
    ];

    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }

    /**
     * Resuelve el environment real.
     * - si ya tienes dgii_company_settings => úsalo
     * - si no existe => fallback a precert (tu estado actual)
     */
    private function resolveEnvironmentForCompany(int $companyId): string
    {
        // ✅ si la tabla existe, úsala (evita romper si aún no has migrado)
        if (Schema::hasTable('dgii_company_settings')) {
            $row = \App\Models\DgiiCompanySetting::query()
                ->where('company_id', $companyId)
                ->first();

            $env = $row?->environment;
            if (is_string($env) && in_array($env, ['precert', 'cert', 'prod'], true)) {
                return $env;
            }
        }

        return 'precert';
    }

    public function send(Request $request, HttpDgiiXmlSender $sender)
    {
        try {
            $data = $request->validate([
                'kind' => ['required', 'in:ecf,rfce,acecf'],
                'name' => ['required', 'string', 'max:255'],
            ]);

            $company = $this->companyFromErp($request);
            $companyId = (int) $company->id;

            // ✅ Seguridad: name debe ser solo filename, sin path traversal
            $name = basename($data['name']);
            abort_unless($name === $data['name'], 422, 'Nombre inválido.');
            abort_unless(preg_match('/\.xml$/i', $name) === 1, 422, 'Debe ser .xml');
            abort_unless(!str_contains($name, '..'), 422, 'Nombre inválido.');

            $kind = $data['kind'];
            $cfg = self::KIND_MAP[$kind];

            $disk = Storage::disk('private');
            $baseDir = $cfg['base_dir'] . "/company_{$companyId}";

            // ✅ Siempre enviamos el firmado
            $signedName = preg_replace('/\.xml$/i', '_signed.xml', $name) ?? ($name . '_signed.xml');
            $signedRel  = "{$baseDir}/{$signedName}";

            abort_unless($disk->exists($signedRel), 422, "No existe firmado: {$signedName}");

            $signedXml = (string) $disk->get($signedRel);

            // ✅ Environment real
            $environment = $this->resolveEnvironmentForCompany($companyId);

            // ✅ Endpoint dinámico por kind
            $endpointKey = (string) $cfg['endpoint_key'];

            // 1) enviar
            $out = $sender->sendFromCatalog(
                companyId: $companyId,
                environment: $environment,
                endpointKey: $endpointKey,
                xml: $signedXml,
                filename: $signedName
            );

            // 2) guardar respuesta en private, mismo folder de la compañía
            $respSuffix = (string) $cfg['resp_suffix'];
            $respName = preg_replace('/\.xml$/i', $respSuffix, $name) ?? ($name . $respSuffix);
            $respRel  = "{$baseDir}/{$respName}";

            $disk->put($respRel, (string) $out['body']);

            return response()->json([
                'ok' => true,
                'message' => 'Enviado correctamente.',
                'env' => $environment,
                'endpoint_key' => $endpointKey,
                'response_name' => $respName,
            ]);

        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            logger()->error('XML SEND failed', [
                'status' => $status,
                'kind' => $request->input('kind'),
                'name' => $request->input('name'),
                'user_id' => optional($request->user())->id,
                'msg' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 3000),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Error enviando XML.',
            ], $status);
        }
    }
}