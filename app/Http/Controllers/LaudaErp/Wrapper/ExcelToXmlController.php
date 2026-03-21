<?php

namespace App\Http\Controllers\LaudaErp\Wrapper;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Dgii\Wrapper\ExcelToXml\ExcelToXmlService;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExcelToXmlController extends Controller
{
    private const DOWNLOAD_ROUTE = 'erp.services.certificacion-emisor.set-ecf.ecf.excel-to-xml.download';

    public function convert(Request $request, ExcelToXmlService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $company = $this->companyFromErp($request);

        $disk = Storage::disk('private');

        $stored = $request->file('file')->store('dgii/uploads', 'private');
        $inputAbs = $disk->path($stored);

        // Wrapper único: procesa ambas hojas (ECF + RFCE)
        $zipRelPath = $service->convertToZip($inputAbs, 'compact', $company->id);

        Log::info('ZIP generado en convert() [wrapper unificado]', [
            'stored_upload_rel' => $stored,
            'stored_upload_abs' => $inputAbs,
            'zipRelPath' => $zipRelPath,
            'zipAbsPath' => $disk->path($zipRelPath),
            'exists' => $disk->exists($zipRelPath),
            'size' => $disk->exists($zipRelPath) ? $disk->size($zipRelPath) : null,
        ]);

        return response()->json([
            'download_url' => route(self::DOWNLOAD_ROUTE, ['path' => $zipRelPath], false),
        ]);
    }

    public function download(Request $request)
    {
        $rel = (string) $request->query('path', '');
        $rel = ltrim($rel, '/');

        if ($rel === '' || str_contains($rel, '..')) {
            Log::warning('DOWNLOAD bloqueado por path inválido', ['path' => $rel]);
            abort(404);
        }

        $disk = Storage::disk('private');
        $exists = $disk->exists($rel);

        Log::info('DOWNLOAD request', [
            'path_param' => $rel,
            'abs_path' => $disk->path($rel),
            'exists' => $exists,
        ]);

        if (!$exists) {
            abort(404);
        }

        return $disk->download(
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