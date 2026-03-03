<?php

namespace App\Http\Controllers\LaudaErp\Wrapper;

use App\Http\Controllers\Controller;
use App\Services\Dgii\Wrapper\ExcelToXml\ExcelToXmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;

class ExcelToXmlController extends Controller
{
    public function convert(Request $request, ExcelToXmlService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $company = $this->companyFromErp($request);

        $stored = $request->file('file')->store('uploads'); // disk "local" por defecto
        $inputAbs = Storage::disk('local')->path($stored);

        // genera ZIP y devuelve ruta relativa dentro de storage/app, ej:
        // "output/xml_20260117_035725_864ef349.zip"
        $zipRelPath = $service->convertToZip($inputAbs, 'compact', $company->id);

        // ✅ LOG: confirma que existe y dónde
        Log::info('ZIP generado en convert()', [
            'stored_upload_rel' => $stored,
            'stored_upload_abs' => $inputAbs,
            'zipRelPath' => $zipRelPath,
            'zipAbsPath' => Storage::disk('local')->path($zipRelPath),
            'exists' => Storage::disk('local')->exists($zipRelPath),
            'size' => Storage::disk('local')->exists($zipRelPath) ? Storage::disk('local')->size($zipRelPath) : null,
        ]);

        return response()->json([
            // relativa para que te funcione igual en localhost / prod
            'download_url' => route('erp.services.certificacion-emisor.excel-to-xml.download', ['path' => $zipRelPath], false),
        ]);
    }

    public function download(Request $request)
    {
        $rel = (string) $request->query('path', '');
        $rel = ltrim($rel, '/');

        // Seguridad: evitar ../
        if ($rel === '' || str_contains($rel, '..')) {
            Log::warning('DOWNLOAD bloqueado por path inválido', ['path' => $rel]);
            abort(404);
        }

        $exists = Storage::disk('local')->exists($rel);

        // ✅ LOG: ver qué llegó y dónde lo está buscando Laravel
        Log::info('DOWNLOAD request', [
            'path_param' => $rel,
            'abs_path' => Storage::disk('local')->path($rel),
            'exists' => $exists,
        ]);

        if (!$exists) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $rel,
            basename($rel),
            ['Content-Type' => 'application/zip']
        );
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

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }
}
