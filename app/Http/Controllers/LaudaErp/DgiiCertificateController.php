<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Services\Dgii\DgiiCertificateReader;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Dgii\DgiiCertificateRequirements;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DgiiCertificateController extends Controller
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

    /** ✅ Normaliza strings/arrays a UTF-8 para que JSON no falle */
    private function utf8ize($value)
    {
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }
            $value = iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: '';
            return $value;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $nk = is_string($k) ? $this->utf8ize($k) : $k;
                $out[$nk] = $this->utf8ize($v);
            }
            return $out;
        }

        if (is_object($value)) {
            return $this->utf8ize((array) $value);
        }

        return $value;
    }

    /** ✅ Meta “segura”: sin binarios/keys y siempre UTF-8 */
    private function safeMeta($meta): array
    {
        $meta = (array) ($meta ?? []);

        // Nunca guardes binario o datos sensibles aquí
        unset(
            $meta['p12_bytes'],
            $meta['raw'],
            $meta['cert_der'],
            $meta['private_key'],
            $meta['public_key'],
            $meta['pkcs12'],
            $meta['pem'],
            $meta['pkey'],
            $meta['cert'],
            $meta['extracerts']
        );

        return $this->utf8ize($meta);
    }

    /** ✅ Determina si un certificado sirve para firmar semilla (P12/PFX) */
    private function canSignSeed(
        string $type,
        bool $passwordOk,
        string $status,
        array $infoMeta = [],
        ?bool $hasPrivateKey = null,
        ?array $certMeta = null
    ): bool {
        if (!in_array($type, ['p12', 'pfx'], true)) return false;
        if ($status === 'invalid') return false;
        if (!$passwordOk) return false;

        // Si tenemos señal de llave privada, exigirla
        if ($hasPrivateKey !== null && $hasPrivateKey === false) return false;

        // Si estamos evaluando un registro ya guardado, exigir password en meta
        if (is_array($certMeta)) {
            if (empty($certMeta['p12_password_enc'])) return false;
        }

        return true;
    }

    public function index(Request $request)
    {
        $company = $this->companyFromErp($request);

        $certs = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'type' => $c->type,
                'is_default' => (bool) $c->is_default,

                'subject_cn' => $c->subject_cn,
                'subject_rnc' => $c->subject_rnc,
                'issuer_cn' => $c->issuer_cn,
                'serial_number' => $c->serial_number,
                'valid_from' => optional($c->valid_from)->toISOString(),
                'valid_to' => optional($c->valid_to)->toISOString(),

                'has_private_key' => (bool) $c->has_private_key,
                'password_ok' => (bool) $c->password_ok,
                'status' => $c->status,

                'original_name' => $c->original_name,
                'file_size' => $c->file_size,

                // ojo: si alguna vez meta trae bytes raros, quítalo de props.
                'meta' => $c->meta ?? null,
            ]);

        // ✅ CLAVE: esto es lo que tu layout necesita
        $certCheck = app(DgiiCertificateRequirements::class)->checkForCompany($company->id);

        return Inertia::render('LaudaERP/CertificacionEmisor/Certificados', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name ?? $company->business_name ?? null,
                'rnc' => $company->rnc ?? null,
            ],
            'certs' => $certs,

            // ✅ NUEVO
            'cert_requirements' => $certCheck,
        ]);
    }


    public function store(Request $request)
    {
        $company = $this->companyFromErp($request);

        // ✅ No usar mimes:* porque .cer/.p12 suelen venir como application/octet-stream
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'file'  => ['required', 'file', 'max:5120'],
            'password' => ['nullable', 'string', 'max:200'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['file'];

        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext, ['p12', 'pfx', 'cer', 'crt'], true), 422);

        if (in_array($ext, ['cer', 'crt'], true)) {
            $data['password'] = null;
        }

        $realPath = $file->getRealPath();
        $bytes = $realPath ? file_get_contents($realPath) : $file->get();

        if ($bytes === false || $bytes === null || $bytes === '') {
            abort(422, 'No se pudo leer el archivo subido.');
        }

        $sha = hash('sha256', $bytes);

        /** @var DgiiCertificateReader $reader */
        $reader = app(DgiiCertificateReader::class);

        // ✅ Password: trim + '' para PFX/P12 (mejor compat con OpenSSL)
        $pwd = array_key_exists('password', $data) ? trim((string) $data['password']) : null;
        if (in_array($ext, ['p12', 'pfx'], true) && ($pwd === null || $pwd === '')) {
            $pwd = '';
        }

        // ✅ Parsear ANTES de guardar (evita basura en disk si falla)
        $info = [];
        $status = 'invalid';
        $passwordOk = false;

        try {
            $extForReader = ($ext === 'crt') ? 'cer' : $ext;
            $info = $reader->readFromUpload($extForReader, $bytes, $pwd);
            $status = (string) ($info['status'] ?? 'active');
            $passwordOk = (bool) ($info['password_ok'] ?? true);

            $info['meta'] = array_merge((array)($info['meta'] ?? []), [
                'parse_source' => 'reader',
                'openssl_present' => function_exists('openssl_pkcs12_read'),
            ]);
        } catch (\Throwable $e) {
            $info = [
                'subject_cn' => null,
                'subject_rnc' => null,
                'issuer_cn' => null,
                'serial_number' => null,
                'valid_from' => null,
                'valid_to' => null,
                'has_private_key' => in_array($ext, ['p12', 'pfx'], true),
                'password_ok' => false,
                'status' => 'invalid',
                'meta' => [
                    'error' => $e->getMessage(),
                    'parse_source' => 'reader_exception',
                    'openssl_present' => function_exists('openssl_pkcs12_read'),
                ],
            ];
            $status = 'invalid';
            $passwordOk = false;
        }

        // ✅ Meta segura (UTF-8) + datos útiles
        $meta = $this->safeMeta(array_merge(
            (array) ($info['meta'] ?? []),
            [
                'mime' => $file->getMimeType(),
                'client_mime' => method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : null,
                'ext' => $ext,
            ]
        ));

        /**
         * ✅ CRÍTICO: NO "envenenar" p12_password_enc.
         * Guardar password_enc SOLO si:
         * - es p12/pfx
         * - el campo password vino en request (aunque sea '')
         * - y passwordOk === true
         */
        if (in_array($ext, ['p12', 'pfx'], true)) {
            $passwordFieldWasSent = array_key_exists('password', $data);
            if ($passwordFieldWasSent && $passwordOk === true) {
                $meta['p12_password_enc'] = Crypt::encryptString((string) $pwd);
            }
        }

        // ✅ Fallback: extraer RNC si viene embebido en CN
        $subjectCn = isset($info['subject_cn']) ? $this->utf8ize($info['subject_cn']) : null;
        $subjectRnc = isset($info['subject_rnc']) ? $this->utf8ize($info['subject_rnc']) : null;

        if (!$subjectRnc && is_string($subjectCn)) {
            if (preg_match('/\b(\d{9,11})\b/', $subjectCn, $m)) {
                $subjectRnc = $m[1];
            }
        }

        // ✅ Guardar en disk privado
        $disk = 'private';
        $path = $file->store("dgii/certs/company_{$company->id}", $disk);

        try {
            $cert = DB::transaction(function () use (
                $company,
                $data,
                $ext,
                $disk,
                $path,
                $file,
                $sha,
                $info,
                $meta,
                $subjectCn,
                $subjectRnc,
                $status,
                $passwordOk
            ) {
                // 🔒 lock para evitar doble-default por concurrencia
                $hasAny = DgiiCertificate::where('company_id', $company->id)->lockForUpdate()->exists();

                $hasPrivateKey = (bool) ($info['has_private_key'] ?? in_array($ext, ['p12', 'pfx'], true));

                // ✅ default solo si sirve para firmar (y es el primero)
                $canBeDefaultForSigning = $this->canSignSeed(
                    $ext,
                    $passwordOk,
                    $status,
                    (array) ($info['meta'] ?? []),
                    $hasPrivateKey,
                    $meta
                );

                $isFirst = !$hasAny && $canBeDefaultForSigning;

                if ($isFirst) {
                    DgiiCertificate::where('company_id', $company->id)->update(['is_default' => false]);
                }

                return DgiiCertificate::create([
                    'company_id' => $company->id,
                    'label' => $ext === 'crt'
                        ? 'DGIIRootCertificateAuthority'
                        : ($data['label'] ?? null),
                    'type' => $ext,

                    'file_disk' => $disk,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_sha256' => $sha,

                    'subject_cn' => $subjectCn,
                    'subject_rnc' => $subjectRnc,
                    'issuer_cn' => isset($info['issuer_cn']) ? $this->utf8ize($info['issuer_cn']) : null,
                    'serial_number' => isset($info['serial_number']) ? $this->utf8ize($info['serial_number']) : null,

                    'valid_from' => !empty($info['valid_from']) ? Carbon::parse($info['valid_from']) : null,
                    'valid_to' => !empty($info['valid_to']) ? Carbon::parse($info['valid_to']) : null,

                    'has_private_key' => $hasPrivateKey,
                    'password_ok' => $passwordOk,
                    'status' => $status,

                    'meta' => $meta,
                    'is_default' => $isFirst,
                ]);
            });
        } catch (\Throwable $e) {
            // ✅ Si algo falla luego de guardar, limpia archivo
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        if (!$cert->password_ok && in_array($cert->type, ['p12', 'pfx'], true)) {
            return back()->with('error', 'Certificado cargado, pero NO se pudo validar (password/archivo/compatibilidad). Revisa el archivo o la contraseña.');
        }

        if ($cert->status === 'expired') {
            return back()->with('error', 'Certificado cargado y validado, pero está VENCIDO.');
        }

        if (!$cert->is_default && in_array($cert->type, ['p12', 'pfx'], true) && $cert->password_ok && $cert->status !== 'invalid') {
            return back()->with('success', 'Certificado cargado y validado correctamente. (No se marcó como default porque ya existe uno usable para firmar).');
        }

        return back()->with(
            'success',
            $cert->is_default
                ? 'Certificado cargado, validado y marcado como predeterminado para firmar.'
                : 'Certificado cargado y validado correctamente.'
        );
    }

    /**
     * ✅ REFRESH: re-parse SIN perder meta.p12_password_enc.
     * - Si NO envías password, usa el guardado en meta.
     * - Si envías password y valida, reemplaza el enc.
     * - Si envías password y NO valida, conserva el enc previo (si existía).
     */
    public function refresh(Request $request, DgiiCertificate $cert)
    {
        $company = $this->companyFromErp($request);
        abort_unless((int) $cert->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:200'],
        ]);

        $disk = (string) ($cert->file_disk ?? 'private');
        $path = (string) ($cert->file_path ?? '');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'No se encontró el archivo del certificado para refrescar.');
        }

        $bytes = Storage::disk($disk)->get($path);

        $metaOld = (array) ($cert->meta ?? []);

        $passwordFieldWasSent = array_key_exists('password', $data);

        if ($passwordFieldWasSent) {
            $pwd = trim((string) $data['password']);
            if (in_array($cert->type, ['p12', 'pfx'], true) && $pwd === '') {
                $pwd = '';
            }
        } else {
            $pwd = '';
            if (!empty($metaOld['p12_password_enc']) && is_string($metaOld['p12_password_enc'])) {
                try {
                    $pwd = Crypt::decryptString($metaOld['p12_password_enc']);
                } catch (\Throwable $e) {
                    $pwd = '';
                }
            }
        }

        /** @var DgiiCertificateReader $reader */
        $reader = app(DgiiCertificateReader::class);

        $type = (string) $cert->type;
        $typeForReader = ($type === 'crt') ? 'cer' : $type;

        $info = $reader->readFromUpload($typeForReader, $bytes, $pwd);

        $status     = (string) ($info['status'] ?? 'invalid');
        $passwordOk = (bool) ($info['password_ok'] ?? false);
        $hasPrivate = (bool) ($info['has_private_key'] ?? false);

        // ✅ meta nueva (segura) pero preserva password_enc
        $metaNew = $this->safeMeta((array) ($info['meta'] ?? []));
        $metaNew['ext'] = $cert->type;

        // Preserva password_enc previo si NO enviaron password
        if (!$passwordFieldWasSent && !empty($metaOld['p12_password_enc'])) {
            $metaNew['p12_password_enc'] = $metaOld['p12_password_enc'];
        }

        // Si enviaron password y VALIDÓ, guardamos el nuevo
        if ($passwordFieldWasSent && in_array($cert->type, ['p12', 'pfx'], true) && $passwordOk === true) {
            $metaNew['p12_password_enc'] = Crypt::encryptString((string) $pwd);
        }

        // Si enviaron password y NO validó, NO pises el enc que ya tenías
        if ($passwordFieldWasSent && $passwordOk === false && !empty($metaOld['p12_password_enc'])) {
            $metaNew['p12_password_enc'] = $metaOld['p12_password_enc'];
        }

        $cert->update([
            'subject_cn'      => $info['subject_cn'] ?? null,
            'issuer_cn'       => $info['issuer_cn'] ?? null,
            'serial_number'   => $info['serial_number'] ?? null,
            'valid_from'      => !empty($info['valid_from']) ? Carbon::parse($info['valid_from']) : null,
            'valid_to'        => !empty($info['valid_to']) ? Carbon::parse($info['valid_to']) : null,
            'has_private_key' => $hasPrivate,
            'password_ok'     => $passwordOk,
            'status'          => $status,
            'meta'            => $metaNew,
        ]);

        if ($status !== 'active') {
            $hint = data_get($info, 'meta.hint') ?: 'No se pudo refrescar/validar el certificado.';
            return back()->with('error', $hint);
        }

        return back()->with('success', 'Certificado refrescado correctamente.');
    }

    public function setDefault(Request $request, DgiiCertificate $cert)
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $cert->company_id === (int) $company->id, 403);

        // ✅ no permitas default que rompa “Generar Token”
        if (!in_array($cert->type, ['p12', 'pfx'], true)) {
            return back()->with('error', 'Solo un P12/PFX puede ser predeterminado para firmar.');
        }
        if (!$cert->has_private_key) {
            return back()->with('error', 'Este certificado no tiene llave privada.');
        }
        if (!$cert->password_ok) {
            return back()->with('error', 'Este certificado no tiene password válido/configurado.');
        }

        $meta = (array) ($cert->meta ?? []);
        if (empty($meta['p12_password_enc'])) {
            return back()->with('error', 'Este certificado no tiene password guardado para firmar. Re-súbelo o usa Refresh con password.');
        }

        DB::transaction(function () use ($company, $cert) {
            DgiiCertificate::where('company_id', $company->id)->update(['is_default' => false]);
            $cert->update(['is_default' => true]);
        });

        return back()->with('success', 'Certificado marcado como predeterminado para firmar.');
    }

    public function destroy(Request $request, DgiiCertificate $cert)
    {
        $company = $this->companyFromErp($request);

        abort_unless((int) $cert->company_id === (int) $company->id, 403);

        $wasDefault = (bool) $cert->is_default;

        Storage::disk($cert->file_disk)->delete($cert->file_path);
        $cert->delete();

        if ($wasDefault) {
            // ✅ intenta promover el más reciente que sea usable para firmar
            $next = DgiiCertificate::query()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->get()
                ->first(function (DgiiCertificate $c) {
                    $meta = (array) ($c->meta ?? []);
                    return in_array($c->type, ['p12', 'pfx'], true)
                        && $c->password_ok
                        && $c->has_private_key
                        && $c->status !== 'invalid'
                        && !empty($meta['p12_password_enc']);
                });

            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return back()->with('success', 'Certificado eliminado.');
    }
}
