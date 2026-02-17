<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Services\Dgii\DgiiCertificateReader;
use App\Services\Dgii\DgiiXmlSigner;
use App\Services\Subscribers\SubscriberResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class DgiiXmlSignController extends Controller
{
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

    public function sign(Request $request, DgiiXmlSigner $signer, DgiiCertificateReader $certReader)
    {
        try {
            $data = $request->validate([
                'kind' => ['required', 'in:ecf,rfce,acecf'],
                'name' => ['required', 'string', 'max:255'],
            ]);

            $company   = $this->companyFromErp($request);
            $companyId = (int) $company->id;

            // ✅ seguridad name (sin path traversal)
            $name = basename($data['name']);
            abort_unless($name === $data['name'], 422, 'Nombre de archivo inválido.');
            abort_unless(preg_match('/\.xml$/i', $name) === 1, 422, 'Debe ser .xml');
            abort_unless(!str_contains($name, '..'), 422, 'Nombre inválido.');
            // opcional: evita firmar el firmado
            // abort_unless(!str_ends_with(mb_strtolower($name), '_signed.xml'), 422, 'Ese XML ya está firmado.');

            $kindMap = [
                'ecf'   => 'cert-ecf',
                'rfce'  => 'cert-rfce',
                'acecf' => 'cert-acecf',
            ];

            // ✅ XML siempre vive en private
            $xmlDisk = Storage::disk('private');

            $baseDir = "dgii/{$kindMap[$data['kind']]}/company_{$companyId}";
            $inRel   = "{$baseDir}/{$name}";

            abort_unless($xmlDisk->exists($inRel), 404, "No existe el XML: {$name}");

            // ✅ carga P12/PFX EXACTO como tu AuthClient (disk + path + password)
            [$p12Bytes, $p12Password] = $this->loadActiveP12ForCompany($companyId, $certReader);

            $xml = (string) $xmlDisk->get($inRel);

            // ✅ firmar con el mismo flujo Node
            $signedXml = $signer->signAnyXml($xml, $p12Bytes, $p12Password);

            $outName = preg_replace('/\.xml$/i', '_signed.xml', $name) ?? ($name . '_signed.xml');
            $outRel  = "{$baseDir}/{$outName}";

            // ✅ sobrescribir si existe
            $xmlDisk->put($outRel, $signedXml);

            return response()->json([
                'ok' => true,
                'message' => 'XML firmado correctamente.',
                'signed_name' => $outName,
            ]);
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            logger()->error('XML SIGN failed', [
                'status' => $status,
                'kind' => $request->input('kind'),
                'name' => $request->input('name'),
                'user_id' => optional($request->user())->id,
                'msg' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 3000),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Error al firmar XML.',
            ], $status);
        }
    }

    /**
     * Retorna: [$bytes, $password]
     */
    private function loadActiveP12ForCompany(int $companyId, DgiiCertificateReader $certReader): array
    {
        /** @var DgiiCertificate|null $cert */
        $cert = DgiiCertificate::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['p12', 'pfx'])
            ->when(
                Schema::hasColumn('dgii_certificates', 'status'),
                fn ($q) => $q->where('status', 'active')
            )
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$cert) {
            throw new RuntimeException("No existe certificado P12/PFX activo para company_id={$companyId}.");
        }

        // ✅ AQUÍ está la corrección clave vs tu controller anterior:
        $disk = (string) ($cert->file_disk ?: 'private');
        $path = (string) ($cert->file_path ?: '');

        if ($path === '') {
            throw new RuntimeException("Certificado sin file_path (cert_id={$cert->id}).");
        }

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "No se encontró el archivo del certificado en storage (cert_id={$cert->id}): disk={$disk}, path={$path}."
            );
        }

        $bytes = (string) Storage::disk($disk)->get($path);
        if ($bytes === '') {
            throw new RuntimeException("Archivo del certificado vacío (cert_id={$cert->id}): disk={$disk}, path={$path}.");
        }

        // ✅ opcional: evita leer “otro storage”
        $sha = hash('sha256', $bytes);
        if (!empty($cert->file_sha256) && is_string($cert->file_sha256) && $sha !== $cert->file_sha256) {
            throw new RuntimeException(
                "Storage mismatch (cert_id={$cert->id}): sha256 leído NO coincide con DB. leído={$sha}, db={$cert->file_sha256}."
            );
        }

        $meta = $this->normalizeMeta($cert->meta);
        $password = $this->extractP12PasswordFromMeta($meta, (int) $cert->id);

        // ✅ validación real del P12/PFX (password ok, private key, status)
        $info = $certReader->readFromUpload((string) $cert->type, $bytes, $password);

        if (($info['status'] ?? null) !== 'active') {
            $hint = data_get($info, 'meta.hint') ?: 'Certificado no válido.';
            $src  = data_get($info, 'meta.parse_source') ?: 'unknown';
            throw new RuntimeException("No se pudo leer el P12/PFX (cert_id={$cert->id}): {$hint} [parse_source={$src}]");
        }

        if (($info['has_private_key'] ?? false) !== true) {
            throw new RuntimeException("Certificado no contiene private key (cert_id={$cert->id}).");
        }

        $validTo = data_get($info, 'valid_to');
        if (is_string($validTo) && trim($validTo) !== '') {
            try {
                if (now()->greaterThan(Carbon::parse($validTo))) {
                    throw new RuntimeException("Certificado expirado (cert_id={$cert->id}, valid_to={$validTo}).");
                }
            } catch (\Throwable $e) {
                // no rompas si parse falla
            }
        }

        return [$bytes, $password];
    }

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) return $meta;

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function extractP12PasswordFromMeta(array $meta, int $certId): string
    {
        try {
            if (!empty($meta['p12_password_enc']) && is_string($meta['p12_password_enc'])) {
                return trim((string) Crypt::decryptString($meta['p12_password_enc']));
            }
            if (!empty($meta['password_enc']) && is_string($meta['password_enc'])) {
                return trim((string) Crypt::decryptString($meta['password_enc']));
            }
        } catch (DecryptException $e) {
            throw new RuntimeException("No se pudo desencriptar password del certificado (cert_id={$certId}). Probable APP_KEY diferente.");
        }

        if (isset($meta['p12_password']) && is_string($meta['p12_password'])) return trim($meta['p12_password']);
        if (isset($meta['password']) && is_string($meta['password'])) return trim($meta['password']);

        // si tu flujo permite password vacío:
        return '';
        // o si quieres exigirlo:
        // throw new RuntimeException("Certificado sin password en meta (cert_id={$certId}).");
    }
}