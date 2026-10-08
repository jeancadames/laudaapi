<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationAuthorizationService;
use App\Services\Diagnosis\TransformationImplementationCommercialEngagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminTransformationImplementationCommercialEngagementController
    extends Controller
{
    public function store(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationCommercialEngagementService $commercial
    ): RedirectResponse {
        $this->assertAdmin(
            $request
        );

        $this->assertDataBiRequest(
            $implementationRequest
        );

        $validated =
            $request->validate([
                'currency' => [
                    'required',
                    'string',
                    'in:DOP,USD,EUR',
                ],

                'price_amount' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'duration_days' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                /*
                 * The domain service intentionally owns no arbitrary
                 * commercial-terms schema.
                 *
                 * HTTP only requires a non-empty structured snapshot.
                 * The UI layer may later define its explicit fields.
                 */
                'commercial_terms_snapshot' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'internal_notes' => [
                    'nullable',
                    'string',
                    'max:10000',
                ],
            ]);

        $commercial->createDraft(
            $implementationRequest,
            $request->user(),
            [
                'currency' =>
                    strtoupper(
                        trim(
                            (string) $validated['currency']
                        )
                    ),

                'price_amount' =>
                    $validated['price_amount'],

                'duration_days' =>
                    (int) $validated['duration_days'],

                'commercial_terms_snapshot' =>
                    $validated[
                        'commercial_terms_snapshot'
                    ],

                'internal_notes' =>
                    $validated['internal_notes']
                    ?? null,
            ]
        );

        return back()->with(
            'success',
            'Propuesta comercial creada en borrador.'
        );
    }

    public function present(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationCommercialEngagement $engagement,
        TransformationImplementationCommercialEngagementService $commercial
    ): RedirectResponse {
        $this->assertAdmin(
            $request
        );

        $this->assertDataBiRequest(
            $implementationRequest
        );

        $this->assertEngagementContext(
            $implementationRequest,
            $engagement
        );

        $commercial->present(
            $engagement,
            $request->user()
        );

        return back()->with(
            'success',
            'Propuesta comercial presentada al tenant.'
        );
    }

    public function authorizeImplementation(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationCommercialEngagement $engagement,
        TransformationImplementationAuthorizationService $authorizations
    ): RedirectResponse {
        $this->assertAdmin(
            $request
        );

        $this->assertDataBiRequest(
            $implementationRequest
        );

        $this->assertEngagementContext(
            $implementationRequest,
            $engagement
        );

        $authorizations->authorize(
            $engagement,
            $request->user()
        );

        return back()->with(
            'success',
            'Implementación autorizada por LAUDA.'
        );
    }

    private function assertAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && (
                $request->user()->role
                ?? null
            ) === 'admin',
            403
        );
    }

    private function assertDataBiRequest(
        TransformationImplementationRequest $implementationRequest
    ): void {
        abort_unless(
            (string) $implementationRequest->capability_key
                === 'data_transformation_bi',
            404
        );
    }

    private function assertEngagementContext(
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationCommercialEngagement $engagement
    ): void {
        abort_unless(
            (int) $engagement
                ->transformation_implementation_request_id
                === (int) $implementationRequest->id

            && (int) $engagement->company_id
                === (int) $implementationRequest->company_id

            && (int) $engagement
                ->transformation_implementation_phase_capability_id
                === (int) $implementationRequest
                    ->transformation_implementation_phase_capability_id

            && (string) $engagement->capability_key
                === (string) $implementationRequest->capability_key,
            404
        );
    }
}
