<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Services\Dgii\DgiiCertificateReader;
use App\Services\Dgii\DgiiXmlSigner;
use App\Services\Subscribers\SubscriberResolver;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
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
            $kind      = (string) $data['kind'];

            $name = basename($data['name']);
            abort_unless($name === $data['name'], 422, 'Nombre de archivo inválido.');
            abort_unless(preg_match('/\.xml$/i', $name) === 1, 422, 'Debe ser .xml');
            abort_unless(! str_contains($name, '..'), 422, 'Nombre inválido.');

            $xmlDisk = Storage::disk('private');

            $inRel = $this->xmlRelPathForKindWithFallback($xmlDisk, $kind, $companyId, $name);
            abort_unless($xmlDisk->exists($inRel), 404, "No existe el XML: {$name}");

            [$p12Bytes, $p12Password] = $this->loadActiveP12ForCompany($companyId, $certReader);

            $xml = (string) $xmlDisk->get($inRel);

            // RFCE: antes de firmar, completar CodigoSeguridadeCF
            if ($kind === 'rfce') {
                $xml = $this->prepareRfceXmlBeforeSign(
                    xmlDisk: $xmlDisk,
                    companyId: $companyId,
                    rfceName: $name,
                    rfceXml: $xml
                );
            }

            $signedXml = $signer->signAnyXml($xml, $p12Bytes, $p12Password, $kind);

            // sobrescribe el mismo archivo
            $xmlDisk->put($inRel, $signedXml);

            return response()->json([
                'ok' => true,
                'message' => 'XML firmado correctamente.',
                'signed_name' => $name,
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

    private function xmlBaseDirForKind(string $kind, int $companyId): string
    {
        return match ($kind) {
            'ecf' => "dgii/cert-ecf/company_{$companyId}/ecf",
            'rfce' => "dgii/cert-ecf/company_{$companyId}/rfce",
            'acecf' => "dgii/cert-acecf/company_{$companyId}",
            default => throw new RuntimeException("Kind inválido: {$kind}"),
        };
    }

    private function xmlRelPathForKind(string $kind, int $companyId, string $name): string
    {
        return $this->xmlBaseDirForKind($kind, $companyId) . '/' . ltrim($name, '/');
    }

    private function xmlRelPathForKindWithFallback($disk, string $kind, int $companyId, string $name): string
    {
        $primary = $this->xmlRelPathForKind($kind, $companyId, $name);

        if ($disk->exists($primary)) {
            return $primary;
        }

        $legacy = match ($kind) {
            'ecf' => "dgii/cert-ecf/company_{$companyId}/{$name}",
            'rfce' => "dgii/cert-rfce/company_{$companyId}/{$name}",
            'acecf' => "dgii/cert-acecf/company_{$companyId}/{$name}",
            default => $primary,
        };

        return $disk->exists($legacy) ? $legacy : $primary;
    }

    public function download(Request $request)
    {
        try {
            $data = $request->validate([
                'kind' => ['required', 'in:ecf,rfce,acecf'],
                'name' => ['required', 'string', 'max:255'],
            ]);

            $company   = $this->companyFromErp($request);
            $companyId = (int) $company->id;
            $kind      = (string) $data['kind'];

            $name = basename($data['name']);
            abort_unless($name === $data['name'], 422, 'Nombre de archivo inválido.');
            abort_unless(preg_match('/\.xml$/i', $name) === 1, 422, 'Debe ser .xml');
            abort_unless(! str_contains($name, '..'), 422, 'Nombre inválido.');

            $disk = Storage::disk('private');

            $relPath = $this->xmlRelPathForKindWithFallback(
                $disk,
                $kind,
                $companyId,
                $name
            );

            abort_unless($disk->exists($relPath), 404, "No existe el XML: {$name}");

            // opcional pero recomendado:
            // solo permitir descargar si ya está firmado
            $xml = (string) $disk->get($relPath);
            abort_unless($this->isXmlSigned($xml), 422, 'El XML todavía no está firmado.');

            return $disk->download(
                $relPath,
                $name,
                ['Content-Type' => 'application/xml']
            );
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            logger()->error('XML DOWNLOAD failed', [
                'status' => $status,
                'kind' => $request->input('kind'),
                'name' => $request->input('name'),
                'user_id' => optional($request->user())->id,
                'msg' => $e->getMessage(),
            ]);

            abort($status, $e->getMessage() ?: 'Error descargando XML.');
        }
    }

    private function isXmlSigned(string $xml): bool
    {
        $xml = trim($xml);

        if ($xml === '') {
            return false;
        }

        return str_contains($xml, '<Signature')
            || str_contains($xml, '<ds:Signature')
            || str_contains($xml, 'http://www.w3.org/2000/09/xmldsig#');
    }

    private function prepareRfceXmlBeforeSign($xmlDisk, int $companyId, string $rfceName, string $rfceXml): string
    {
        $rfceMeta = $this->findManifestItem($companyId, 'rfce', $rfceName);

        if (! $rfceMeta) {
            throw new RuntimeException("No se encontró metadata del RFCE en _xml_order.json para {$rfceName}.");
        }

        $pairEncf = trim((string) ($rfceMeta['pair_eNCF'] ?? $rfceMeta['eNCF'] ?? ''));
        if ($pairEncf === '') {
            throw new RuntimeException("El RFCE {$rfceName} no tiene pair_eNCF/eNCF en el manifiesto.");
        }

        $ecfMeta = $this->findManifestItemByPairEncf($companyId, $pairEncf);
        if (! $ecfMeta) {
            throw new RuntimeException("No se encontró el ECF pareado para eNCF {$pairEncf}.");
        }

        $ecfName = trim((string) ($ecfMeta['name'] ?? ''));
        if ($ecfName === '') {
            throw new RuntimeException("El manifiesto del ECF pareado no tiene name para eNCF {$pairEncf}.");
        }

        $ecfRel = $this->xmlRelPathForKindWithFallback($xmlDisk, 'ecf', $companyId, $ecfName);
        if (! $xmlDisk->exists($ecfRel)) {
            throw new RuntimeException("No existe el ECF pareado firmado: {$ecfName}");
        }

        $ecfXml = (string) $xmlDisk->get($ecfRel);
        $signatureValue = $this->extractSignatureValue($ecfXml);

        if ($signatureValue === '') {
            throw new RuntimeException("El ECF pareado {$ecfName} aún no está firmado o no contiene SignatureValue.");
        }

        $securityCode = substr($signatureValue, 0, 6);
        if (strlen($securityCode) < 6) {
            throw new RuntimeException("No se pudo obtener un CodigoSeguridadeCF válido desde SignatureValue de {$ecfName}.");
        }

        return $this->injectCodigoSeguridadeCF($rfceXml, $securityCode);
    }

    private function extractSignatureValue(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('No se pudo parsear el XML firmado para extraer SignatureValue.');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $value = trim((string) $xp->evaluate('string(//*[local-name()="SignatureValue"][1])'));
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return trim($value);
    }

    private function injectCodigoSeguridadeCF(string $xml, string $securityCode): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('No se pudo parsear el RFCE para completar CodigoSeguridadeCF.');
        }

        $nodes = $dom->getElementsByTagName('CodigoSeguridadeCF');
        if ($nodes->length === 0) {
            throw new RuntimeException('El RFCE no contiene el nodo CodigoSeguridadeCF.');
        }

        $node = $nodes->item(0);
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }

        $node->appendChild($dom->createTextNode($securityCode));

        return (string) $dom->saveXML();
    }

    private function manifestRelPath(int $companyId): string
    {
        return "dgii/cert-ecf/company_{$companyId}/_xml_order.json";
    }

    private function loadManifestItems(int $companyId): array
    {
        $disk = Storage::disk('private');
        $rel = $this->manifestRelPath($companyId);

        if (! $disk->exists($rel)) {
            return [];
        }

        $raw = (string) $disk->get($rel);
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? [];
        return is_array($items) ? $items : [];
    }

    private function findManifestItem(int $companyId, string $kind, string $name): ?array
    {
        foreach ($this->loadManifestItems($companyId) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['kind'] ?? null) !== $kind) {
                continue;
            }

            if (trim((string) ($item['name'] ?? '')) !== $name) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function findManifestItemByPairEncf(int $companyId, string $pairEncf): ?array
    {
        foreach ($this->loadManifestItems($companyId) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['kind'] ?? null) !== 'ecf') {
                continue;
            }

            $candidate = trim((string) ($item['pair_eNCF'] ?? $item['eNCF'] ?? ''));
            if ($candidate !== $pairEncf) {
                continue;
            }

            return $item;
        }

        return null;
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
                fn($q) => $q->where('status', 'active')
            )
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (! $cert) {
            throw new RuntimeException("No existe certificado P12/PFX activo para company_id={$companyId}.");
        }

        $disk = (string) ($cert->file_disk ?: 'private');
        $path = (string) ($cert->file_path ?: '');

        if ($path === '') {
            throw new RuntimeException("Certificado sin file_path (cert_id={$cert->id}).");
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "No se encontró el archivo del certificado en storage (cert_id={$cert->id}): disk={$disk}, path={$path}."
            );
        }

        $bytes = (string) Storage::disk($disk)->get($path);
        if ($bytes === '') {
            throw new RuntimeException("Archivo del certificado vacío (cert_id={$cert->id}): disk={$disk}, path={$path}.");
        }

        $sha = hash('sha256', $bytes);
        if (! empty($cert->file_sha256) && is_string($cert->file_sha256) && $sha !== $cert->file_sha256) {
            throw new RuntimeException(
                "Storage mismatch (cert_id={$cert->id}): sha256 leído NO coincide con DB. leído={$sha}, db={$cert->file_sha256}."
            );
        }

        $meta = $this->normalizeMeta($cert->meta);
        $password = $this->extractP12PasswordFromMeta($meta, (int) $cert->id);

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
                // no romper si parse falla
            }
        }

        return [$bytes, $password];
    }

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function extractP12PasswordFromMeta(array $meta, int $certId): string
    {
        try {
            if (! empty($meta['p12_password_enc']) && is_string($meta['p12_password_enc'])) {
                return trim((string) Crypt::decryptString($meta['p12_password_enc']));
            }
            if (! empty($meta['password_enc']) && is_string($meta['password_enc'])) {
                return trim((string) Crypt::decryptString($meta['password_enc']));
            }
        } catch (DecryptException $e) {
            throw new RuntimeException("No se pudo desencriptar password del certificado (cert_id={$certId}). Probable APP_KEY diferente.");
        }

        if (isset($meta['p12_password']) && is_string($meta['p12_password'])) {
            return trim($meta['p12_password']);
        }

        if (isset($meta['password']) && is_string($meta['password'])) {
            return trim($meta['password']);
        }

        return '';
    }
}