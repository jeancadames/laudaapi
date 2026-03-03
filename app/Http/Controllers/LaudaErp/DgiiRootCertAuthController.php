<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DgiiCertificate;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DgiiRootCertAuthController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB
                function (string $attribute, mixed $value, \Closure $fail) {
                    /** @var UploadedFile $value */
                    $ext = strtolower($value->getClientOriginalExtension() ?: '');

                    if (!in_array($ext, ['cer', 'crt'], true)) {
                        $fail('Formato no permitido. Usa .cer o .crt.');
                        return;
                    }

                    // Extra opcional: si es crt/cer, permite PEM o DER sin romper uploads
                    // (No bloquea; solo podrías endurecerlo si quieres)
                },
            ],
            'company_id' => ['nullable', 'integer'],
        ]);

        $company = $this->companyFromErp($request);

        $user = $request->user();
        abort_unless($user, 403);

        $disk = Storage::disk('private'); // ✅ storage/app/private
        $dir  = $this->companyCertDir($company->id); // dgii/dgii_certs/company_{id}

        return DB::transaction(function () use ($request, $company, $user, $disk, $dir) {
            // 1) crear carpeta si no existe
            if (!$disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            // 2) SIEMPRE REEMPLAZA:
            //    (a) borra registros previos que apunten a este folder
            $existing = DgiiCertificate::query()
                ->where('company_id', $company->id)
                ->where('file_disk', 'private')
                ->where('file_path', 'like', $dir . '/%')
                ->get();

            foreach ($existing as $row) {
                if ($row->file_path && $disk->exists($row->file_path)) {
                    $disk->delete($row->file_path);
                }
            }

            DgiiCertificate::query()
                ->where('company_id', $company->id)
                ->where('file_disk', 'private')
                ->where('file_path', 'like', $dir . '/%')
                ->delete();

            // (b) por si quedó algún file suelto en el folder
            foreach ($disk->files($dir) as $f) {
                $disk->delete($f);
            }

            // 3) store file
            $uploaded = $request->file('file');
            $ext = strtolower($uploaded->getClientOriginalExtension() ?: $uploaded->extension() ?: 'bin');

            // type = ext (y ahora soporta crt)
            $type = in_array($ext, ['p12', 'pfx', 'cer', 'crt'], true) ? $ext : 'crt';

            $originalName = $uploaded->getClientOriginalName();
            $originalBase = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = Str::slug($originalBase) ?: 'dgii-cert';

            $filename = sprintf(
                '%s_user_%d_%s.%s',
                $safeBase,
                (int) $user->id,
                now()->format('Ymd_His'),
                $type
            );

            $relPath = $uploaded->storeAs($dir, $filename, 'private');

            $absPath = $disk->path($relPath);
            $size = $disk->exists($relPath) ? $disk->size($relPath) : null;
            $sha256 = is_file($absPath) ? hash_file('sha256', $absPath) : null;

            // 4) crear registro DB (unparsed)
            // Nota: sin password, sin parse todavía
            $cert = DgiiCertificate::create([
                'company_id' => $company->id,
                'label' => 'DGIIRootCertificateAuthority',
                'type' => $type,
                'is_default' => true,

                'file_disk' => 'private',
                'file_path' => $relPath,
                'original_name' => $originalName,
                'file_size' => $size,
                'file_sha256' => $sha256,

                'has_private_key' => in_array($type, ['p12', 'pfx'], true),
                'password_ok' => null,
                'status' => 'unparsed',

                'meta' => [
                    'uploaded_by_user_id' => (int) $user->id,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);

            return response()->json([
                'ok' => true,
                'company_id' => $company->id,
                'certificate' => [
                    'id' => $cert->id,
                    'type' => $cert->type,
                    'file_path' => $cert->file_path,
                    'original_name' => $cert->original_name,
                    'file_size' => $cert->file_size,
                    'status' => $cert->status,
                ],
                'download_url' => route('erp.services.certificacion-emisor.dgii-cert.download', ['path' => $relPath], false),
            ]);
        });
    }

    public function list(Request $request)
    {
        $company = $this->companyFromErp($request);

        $items = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->where('file_disk', 'private')
            ->where('file_path', 'like', $this->companyCertDir($company->id) . '/%')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DgiiCertificate $c) => [
                'id' => $c->id,
                'name' => basename($c->file_path),
                'rel_path' => $c->file_path,
                'size' => $c->file_size ?? 0,
                'last_modified_at' => optional($c->updated_at)->toISOString(),
                'download_url' => route('erp.services.certificacion-emisor.dgii-cert.download', ['path' => $c->file_path], false),
                'type' => $c->type,
                'status' => $c->status,
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'company_id' => $company->id,
            'items' => $items,
        ]);
    }

    public function download(Request $request)
    {
        $company = $this->companyFromErp($request);

        $rel = ltrim((string) $request->query('path', ''), '/');
        if ($rel === '' || str_contains($rel, '..')) abort(404);

        $cert = DgiiCertificate::query()
            ->where('company_id', $company->id)
            ->where('file_disk', 'private')
            ->where('file_path', $rel)
            ->firstOrFail();

        $disk = Storage::disk($cert->file_disk ?: 'private');
        if (!$disk->exists($cert->file_path)) abort(404);

        return response()->download(
            $disk->path($cert->file_path),
            basename($cert->file_path)
        );
    }

    private function companyCertDir(int $companyId): string
    {
        // relativo al disk "private" (storage/app/private)
        return "dgii/dgii_certs/company_{$companyId}";
    }

    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }
        abort_unless($subscriberId > 0, 403);

        $requestedCompanyId = (int) $request->input('company_id', 0);

        $q = Company::query()->where('subscriber_id', $subscriberId);

        if ($requestedCompanyId > 0) {
            return $q->where('id', $requestedCompanyId)->firstOrFail();
        }

        return $q->firstOrFail();
    }
}