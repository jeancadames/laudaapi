<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\UpsertFiscalDraftRequest;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Services\Fiscal\FiscalDocumentIssuerService;
use Illuminate\Http\Request;

class FiscalDocumentController extends Controller
{
    public function store(UpsertFiscalDraftRequest $request, FiscalDocumentIssuerService $svc)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->resolveCompanyForUser($user);
        abort_unless($company, 403);

        $data = $request->validated();

        // En create sí exigimos document_type_code
        abort_unless(!empty($data['document_type_code']), 422);

        $doc = $svc->createDraft($company, $data, $user->id);

        return response()->json(['data' => $doc]);
    }

    public function update(UpsertFiscalDraftRequest $request, FiscalDocumentIssuerService $svc, string $publicId)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->resolveCompanyForUser($user);
        abort_unless($company, 403);

        $doc = FiscalDocument::query()
            ->where('company_id', $company->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $doc = $svc->updateDraft($doc, $request->validated(), $user->id);

        return response()->json(['data' => $doc]);
    }

    public function issue(Request $request, FiscalDocumentIssuerService $svc, string $publicId)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->resolveCompanyForUser($user);
        abort_unless($company, 403);

        $doc = FiscalDocument::query()
            ->where('company_id', $company->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $doc = $svc->issue($doc, $company, $user->id);

        return response()->json(['data' => $doc]);
    }

    private function resolveCompanyForUser($user): ?Company
    {
        // mismo patrón que usas: company_id si existe, sino por subscriber
        return Company::query()
            ->when(
                !empty($user->company_id),
                fn($q) => $q->where('id', (int) $user->company_id),
                fn($q) => $q->where('subscriber_id', (int) ($user->subscriber_id ?? 0))->orderByDesc('id')
            )
            ->first();
    }
}
