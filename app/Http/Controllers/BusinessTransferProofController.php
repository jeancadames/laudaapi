<?php

namespace App\Http\Controllers;

use App\Services\Billing\BusinessTransferProofs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class BusinessTransferProofController extends Controller
{
    public function store(Request $request, string $purchase, BusinessTransferProofs $proofs)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'submission_key' => ['required', 'uuid'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $proofs->submit($request->user(), $purchase, $data['proof'], $data['reference'], $data['submission_key']);

        return $request->expectsJson() ? response()->json(['received' => true]) : back(303);
    }

    public function download(int $proof)
    {
        abort_unless(BusinessTransferProofs::available(), 503);
        $path = DB::table('business_transfer_proofs')->where('id', $proof)->value('file_path');
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path, 'comprobante-'.$proof.'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, int $proof, BusinessTransferProofs $proofs)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'amount_cents' => ['required_if:decision,approved', 'nullable', 'integer', 'min:1', 'max:9999999999'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000'],
        ]);
        $proofs->review($request->user(), $proof, $data);

        return $request->expectsJson() ? response()->json(['status' => $data['decision']]) : back(303);
    }
}
