<?php

namespace App\Http\Controllers\LaudaErp\Wrapper;

use App\Http\Controllers\Controller;
use App\Services\Dgii\Wrapper\Rfce\RfceExcelToXmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;

final class RfceExcelToXmlController extends Controller
{
    public function convert(Request $request, RfceExcelToXmlService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $company = $this->companyFromErp($request);

        $stored = $request->file('file')->store('uploads'); // disk local
        $inputAbs = Storage::disk('local')->path($stored);

        $zipRelPath = $service->convertToZip($inputAbs, 'compact', $company->id);

        return response()->json([
            'download_url' => route('erp.services.certificacion-emisor.rfce.download', ['path' => $zipRelPath], false),
        ]);
    }

    public function download(Request $request)
    {
        $rel = ltrim((string) $request->query('path', ''), '/');

        if ($rel === '' || str_contains($rel, '..')) abort(404);

        $disk = Storage::disk('local');
        if (!$disk->exists($rel)) abort(404);

        $abs = $disk->path($rel);

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
