<?php

namespace App\Services\Dgii;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DgiiXmlSigner
{
    public function signAnyXml(string $xml, string $p12Binary, string $p12Password): string
    {
        $xml = $this->stripBom($xml);
        if (trim($xml) === '') {
            throw new RuntimeException('XML vacío.');
        }

        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'dgii_xml_' . Str::random(12);

        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal para firmar.');
        }

        $passFile   = $tmpDir . DIRECTORY_SEPARATOR . 'pass.txt';
        $p12File    = $tmpDir . DIRECTORY_SEPARATOR . 'cert.p12';
        $xmlFile    = $tmpDir . DIRECTORY_SEPARATOR . 'input.xml';
        $signedFile = $tmpDir . DIRECTORY_SEPARATOR . 'output_signed.xml';

        try {
            file_put_contents($passFile, (string) $p12Password);
            file_put_contents($p12File, $this->normalizePkcs12($p12Binary));
            file_put_contents($xmlFile, $xml);

            $nodeBin = config('dgii.node_bin', 'node');
            $script  = base_path('node_scripts/signCert.js');

            if (!is_file($script)) {
                throw new RuntimeException("No se encontró el script de firma Node: {$script}");
            }

            $cmd = [$nodeBin, $script, $passFile, $p12File, $xmlFile, $signedFile];

            $process = new Process($cmd, base_path());
            $process->setTimeout((float) config('dgii.node_sign_timeout', 25));
            $process->run();

            if (!$process->isSuccessful()) {
                $err = trim($process->getErrorOutput() ?: $process->getOutput());
                $err = $err !== '' ? $err : 'Node signing failed (sin salida).';
                throw new RuntimeException($err);
            }

            if (!is_file($signedFile)) {
                throw new RuntimeException('Node no generó el archivo signed XML.');
            }

            $signedXml = (string) file_get_contents($signedFile);
            $signedXml = $this->stripBom($signedXml);

            if (trim($signedXml) === '') {
                throw new RuntimeException('XML firmado final vacío.');
            }

            return $signedXml;

        } finally {
            $this->safeCleanup($tmpDir);
        }
    }

    private function normalizePkcs12(string $raw): string
    {
        $trim = trim($raw);

        $looksBase64 =
            $trim !== '' &&
            preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $trim) &&
            (strlen($trim) % 4 === 0);

        if ($looksBase64) {
            $decoded = base64_decode($trim, true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $raw;
    }

    private function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    private function safeCleanup(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = @scandir($dir);
        if (is_array($files)) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                @unlink($dir . DIRECTORY_SEPARATOR . $f);
            }
        }
        @rmdir($dir);
    }
}