<?php

namespace App\Http\Controllers\LaudaErp\Wrapper;

use App\Http\Controllers\Controller;
use App\Services\Dgii\Wrapper\Acecf\AcecfExcelToXmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;

final class AcecfExcelToXmlController extends Controller
{
    public function convert(Request $request, AcecfExcelToXmlService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $company = $this->companyFromErp($request);

        $stored = $request->file('file')->store('uploads'); // disk default (local)
        $zipRelPath = $service->convertToZip(Storage::path($stored), 'compact', $company->id);

        return response()->json([
            'download_url' => route('erp.services.certificacion-emisor.acecf.download', ['path' => $zipRelPath], false),
        ]);
    }

    public function download(Request $request)
    {
        $rel = ltrim((string) $request->query('path', ''), '/');

        if ($rel === '' || str_contains($rel, '..')) abort(404);

        $disk = Storage::disk('local');

        if (!$disk->exists($rel)) abort(404);

        $abs = $disk->path($rel);
        
        logger()->info('ACECF DOWNLOAD request', [
            'path_param' => $request->query('path'),
            'rel' => $rel,
            'abs' => $abs,
            'exists' => $disk->exists($rel),
            'size' => $disk->exists($rel) ? $disk->size($rel) : null,
        ]);

        return response()->download(
            $abs,
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