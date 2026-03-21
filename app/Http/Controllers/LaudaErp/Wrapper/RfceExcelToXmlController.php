<?php

namespace App\Http\Controllers\LaudaErp\Wrapper;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Dgii\Wrapper\Rfce\RfceExcelToXmlService;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class RfceExcelToXmlController extends Controller
{
    private const DOWNLOAD_ROUTE = 'erp.services.certificacion-emisor.set-ecf.rfce.download';

    public function convert(Request $request, RfceExcelToXmlService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $company = $this->companyFromErp($request);

        // ✅ TODO en private (mismo disk que el ZIP)
        $storedRel = $request->file('file')->store('dgii/uploads', 'private');
        $inputAbs  = Storage::disk('private')->path($storedRel);

        $zipRelPath = $service->convertToZip($inputAbs, 'compact', $company->id);

        return response()->json([
            'download_url' => route(self::DOWNLOAD_ROUTE, ['path' => $zipRelPath], false),
        ]);
    }

    public function download(Request $request)
    {
        $rel = ltrim((string) $request->query('path', ''), '/');

        if ($rel === '' || str_contains($rel, '..')) abort(404);

        $disk = Storage::disk('private');
        if (!$disk->exists($rel)) abort(404);

        return $disk->download($rel, basename($rel), [
            'Content-Type' => 'application/zip',
        ]);
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