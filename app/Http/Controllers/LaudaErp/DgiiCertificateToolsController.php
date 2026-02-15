<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Services\Dgii\DgiiCertificateReader;
use App\Services\Dgii\DgiiXmlSigner;
use App\Services\Subscribers\SubscriberResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DgiiCertificateToolsController extends Controller
{
    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }

    private function assertSameCompany(Company $company, DgiiCertificate $cert): void
    {
        abort_unless((int) $cert->company_id === (int) $company->id, 403);
    }

    /**
     * ✅ Health: valida el pipeline del certificado DEFAULT (o fallback)
     * - has_private_key (para firma)
     * - password_ok (si existe)
     * - vigencia (valid_to)
     * - RSA + bits (si están en meta)
     */
    public function health(Request $request)
    {
        $company = $this->companyFromErp($request);

        // 1) intentamos el DEFAULT real
        $default = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->first();

        // 2) fallback: el más reciente
        if (!$default) {
            $default = DgiiCertificate::query()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->first();
        }

        $issues = [];
        $ok = true;

        if (!$default) {
            $ok = false;
            $issues[] = 'No hay certificados cargados.';
        } else {
            if (!(bool) $default->is_default) {
                // info: no bloquea, pero útil
                $issues[] = 'No hay un certificado marcado como DEFAULT (se tomó el más reciente).';
            }

            // Para firmar: requiere llave privada (P12/PFX)
            if (!(bool) $default->has_private_key) {
                $ok = false;
                $issues[] = 'El certificado seleccionado no tiene llave privada (debe ser P12/PFX para firmar).';
            }

            // Password ok: solo aplica a P12/PFX. Si es CER, password_ok no es relevante.
            $type = strtolower((string) $default->type);
            if (in_array($type, ['p12', 'pfx'], true) && !(bool) $default->password_ok) {
                $ok = false;
                $issues[] = 'El certificado no pasó validación de password (password_ok=false).';
            }

            // Vigencia (NO confíes ciegamente en status; valida por fecha)
            if ($default->valid_to) {
                $to = $default->valid_to instanceof Carbon
                    ? $default->valid_to
                    : Carbon::parse((string) $default->valid_to);

                if ($to->isPast()) {
                    $ok = false;
                    $issues[] = 'El certificado está vencido (valid_to < hoy).';
                }
            } else {
                // si no hay fecha, es señal de que no se pudo parsear bien
                $issues[] = 'No se detectó valid_to (recomiendo Refresh).';
            }

            // Status guardado (informativo)
            if (($default->status ?? '') === 'invalid') {
                $ok = false;
                $issues[] = 'El certificado está marcado como invalid en BD (recomiendo Refresh o re-subir).';
            }

            // RSA + bits (si existe meta)
            $keyType = strtoupper((string) data_get($default->meta, 'key_type', ''));
            $bits = (int) data_get($default->meta, 'key_bits', 0);

            if ($keyType !== '' && $keyType !== 'RSA') {
                $ok = false;
                $issues[] = "Llave incompatible: DGII requiere RSA (detectado {$keyType}).";
            }

            if ($bits > 0 && $bits < 2048) {
                $ok = false;
                $issues[] = "Llave débil: {$bits} bits. Recomendado 2048+.";
            }
        }

        return response()->json([
            'ok' => $ok,
            'company' => [
                'id' => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc' => $company->rnc ?? null,
            ],
            'default_cert' => $default ? [
                'id' => $default->id,
                'label' => $default->label,
                'type' => $default->type,
                'is_default' => (bool) $default->is_default,
                'subject_cn' => $default->subject_cn,
                'issuer_cn' => $default->issuer_cn,
                'serial_number' => $default->serial_number,
                'valid_from' => $default->valid_from
                    ? ($default->valid_from instanceof Carbon ? $default->valid_from->toISOString() : Carbon::parse((string) $default->valid_from)->toISOString())
                    : null,
                'valid_to' => $default->valid_to
                    ? ($default->valid_to instanceof Carbon ? $default->valid_to->toISOString() : Carbon::parse((string) $default->valid_to)->toISOString())
                    : null,
                'has_private_key' => (bool) $default->has_private_key,
                'password_ok' => (bool) $default->password_ok,
                'status' => $default->status,
                'meta' => $default->meta,
            ] : null,
            'issues' => $issues,
        ]);
    }

    /**
     * ✅ TestSign: firma XML con reglas DGII usando el certificado seleccionado.
     * Devuelve shape compatible con UI:
     * { ok, digest, signature_method, reference_uri, signed_xml }
     */
    public function testSign(Request $request, DgiiCertificate $cert, DgiiXmlSigner $signer)
    {
        $company = $this->companyFromErp($request);
        $this->assertSameCompany($company, $cert);

        // Reglas: solo firmar con P12/PFX
        abort_unless(in_array(strtolower((string) $cert->type), ['p12', 'pfx'], true), 422);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:200'],
            'xml' => ['nullable', 'string', 'max:200000'], // 200KB
        ]);

        // XML default controlado (mínimo) si no lo envían
        $xml = trim((string) ($data['xml'] ?? ''));
        if ($xml === '') {
            // ✅ Mantén orden xmlns:xsi primero y xmlns:xsd después (tu regla)
            $xml = '<SemillaModel xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><Semilla>TEST</Semilla></SemillaModel>';
        }

        try {
            $signed = $signer->signXmlDgii(
                xml: $xml,
                certDisk: $cert->file_disk,
                certPath: $cert->file_path,
                password: $data['password'] ?? null,
            );

            return response()->json([
                'ok' => true,
                'digest' => 'SHA256',
                'signature_method' => 'RSA-SHA256',
                'reference_uri' => '',
                'signed_xml' => $signed,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * ✅ Refresh: re-parse del archivo guardado y actualiza campos.
     * - Fix: named argument correcto (bytes: ...)
     * - Si el certificado es P12/PFX y no se provee password, NO lo marca invalid por eso.
     * - Manejo claro de error OpenSSL 3 "unsupported" sin ensuciar status si no estás firmando ahora.
     */
    public function refresh(Request $request, DgiiCertificate $cert, DgiiCertificateReader $reader)
    {
        $company = $this->companyFromErp($request);
        $this->assertSameCompany($company, $cert);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $bytes = Storage::disk($cert->file_disk)->get($cert->file_path);
            $sha = hash('sha256', $bytes);

            $type = strtolower((string) $cert->type);
            $password = $data['password'] ?? null;

            // Si es P12/PFX y no hay password, NO intentamos abrir para evitar falsos "invalid"
            if (in_array($type, ['p12', 'pfx'], true) && ($password === null || $password === '')) {
                $cert->update([
                    'file_sha256' => $sha,
                    // no tocamos password_ok/status/meta porque no pudimos leerlo
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'Refresh parcial: recalculado SHA256. Para re-parsear P12/PFX, envía password.',
                    'cert' => [
                        'id' => $cert->id,
                        'status' => $cert->status,
                        'password_ok' => (bool) $cert->password_ok,
                        'file_sha256' => $cert->file_sha256,
                    ],
                ]);
            }

            // ✅ FIX: el reader recibe "bytes", no "contents"
            try {
                $info = $reader->readFromUpload(
                    ext: (string) $cert->type,
                    bytes: $bytes,
                    password: $password
                );
            } catch (\Throwable $e) {
                // ✅ Mejora: OpenSSL 3 puede tirar "unsupported" aunque el password sea correcto
                $msg = $e->getMessage();

                // guardamos SHA siempre
                $cert->update([
                    'file_sha256' => $sha,
                ]);

                // Si es "unsupported", no lo marques invalid automáticamente (modo "solo quitar errores")
                if (
                    in_array($type, ['p12', 'pfx'], true) &&
                    (str_contains($msg, 'unsupported') || str_contains($msg, 'digital envelope routines'))
                ) {
                    $meta = is_array($cert->meta) ? $cert->meta : (json_decode((string) $cert->meta, true) ?: []);
                    $meta['openssl_error'] = $msg;
                    $meta['hint'] = 'OpenSSL del servidor no soporta este PKCS#12 (probable OpenSSL 3 sin legacy provider).';

                    $cert->update([
                        'meta' => $meta,
                        // NO tocamos status/password_ok
                    ]);

                    return response()->json([
                        'ok' => true,
                        'message' => 'Refresh parcial: SHA actualizado. PKCS#12 no parseable en este runtime (OpenSSL unsupported).',
                        'cert' => [
                            'id' => $cert->id,
                            'status' => $cert->status,
                            'password_ok' => (bool) $cert->password_ok,
                            'file_sha256' => $cert->file_sha256,
                            'meta' => $cert->meta,
                        ],
                    ]);
                }

                // Otros errores: sí devolvemos 422
                throw $e;
            }

            $cert->update([
                'file_sha256' => $sha,

                'subject_cn' => $info['subject_cn'] ?? null,
                'subject_rnc' => $info['subject_rnc'] ?? null,
                'issuer_cn' => $info['issuer_cn'] ?? null,
                'serial_number' => $info['serial_number'] ?? null,
                'valid_from' => $info['valid_from'] ?? null,
                'valid_to' => $info['valid_to'] ?? null,

                'has_private_key' => (bool) ($info['has_private_key'] ?? $cert->has_private_key),
                'password_ok' => (bool) ($info['password_ok'] ?? false),
                'status' => (string) ($info['status'] ?? 'invalid'),
                'meta' => $info['meta'] ?? $cert->meta,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Certificado refrescado.',
                'cert' => [
                    'id' => $cert->id,
                    'status' => $cert->status,
                    'password_ok' => (bool) $cert->password_ok,
                    'file_sha256' => $cert->file_sha256,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
