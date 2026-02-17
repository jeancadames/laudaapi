<?php

namespace App\Services\Dgii;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class CertProcessStore
{
    /**
     * Guarda el ZIP (local) y extrae los XML hacia:
     * private/dgii/cert_process/{companyId}/{processId}/xml/*.xml
     *
     * @return array{process_id:string, base_dir:string, zip_private_rel:string, xml_count:int}
     */
    public function storeFromLocalZip(string $zipRelPath, int $companyId, string $wrapperKey): array
    {
        $local = Storage::disk('local');
        $private = Storage::disk('private');

        $zipRelPath = ltrim($zipRelPath, '/');

        if (!$local->exists($zipRelPath)) {
            throw new RuntimeException("ZIP no existe en disk local: {$zipRelPath}");
        }

        $processId = now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4));

        $baseDir = "dgii/cert_process/{$companyId}/{$processId}";
        $xmlDir  = "{$baseDir}/xml";
        $metaDir = "{$baseDir}/meta";

        // ✅ crear carpetas
        $private->makeDirectory($xmlDir);
        $private->makeDirectory($metaDir);

        // ✅ copiar ZIP (stream) al disk privado
        $srcAbs = $local->path($zipRelPath);
        $zipPrivateRel = "{$baseDir}/{$wrapperKey}.zip";

        $stream = @fopen($srcAbs, 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException("No se pudo abrir ZIP para copiar: {$srcAbs}");
        }
        try {
            // put() acepta resource y lo escribe como stream en Laravel
            $private->put($zipPrivateRel, $stream);
        } finally {
            @fclose($stream);
        }

        // ✅ extraer SOLO .xml del ZIP y guardarlos en private
        $zip = new ZipArchive();
        if ($zip->open($srcAbs) !== true) {
            throw new RuntimeException("No se pudo abrir ZIP: {$srcAbs}");
        }

        $xmlFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '') continue;

            // Seguridad: evitar paths dentro del zip
            $baseName = basename(str_replace('\\', '/', $name));

            // Solo XML
            if (!str_ends_with(mb_strtolower($baseName), '.xml')) continue;

            $content = $zip->getFromIndex($i);
            if ($content === false) continue;

            $dest = "{$xmlDir}/{$baseName}";
            $private->put($dest, $content);

            $xmlFiles[] = $baseName;
        }
        $zip->close();

        // ✅ manifest para el front
        $manifest = [
            'company_id'       => $companyId,
            'process_id'       => $processId,
            'wrapper'          => $wrapperKey,
            'created_at'       => now()->toISOString(),
            'zip_local_rel'    => $zipRelPath,
            'zip_private_rel'  => $zipPrivateRel,
            'xml_dir'          => $xmlDir,
            'xml_files'        => $xmlFiles,
        ];

        $private->put(
            "{$metaDir}/manifest.json",
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return [
            'process_id'       => $processId,
            'base_dir'         => $baseDir,
            'zip_private_rel'  => $zipPrivateRel,
            'xml_count'        => count($xmlFiles),
        ];
    }
}