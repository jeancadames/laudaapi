<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationAuthorization;
use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationRequest;

final class TransformationImplementationCommercialProjection
{
    private const CAPABILITY = 'data_transformation_bi';

    public function forAdmin(
        TransformationImplementationRequest $request
    ): array {
        if ($request->capability_key !== self::CAPABILITY) {
            return [
                'engagement' => null,
                'authorization' => null,
                'history' => [],
                'actions' => [
                    'can_create' => false,
                    'can_present' => false,
                    'can_authorize' => false,
                    'create_endpoint' => null,
                    'present_endpoint' => null,
                    'authorize_endpoint' => null,
                ],
            ];
        }

        $engagement = $this->latestEngagement($request);

        $authorization = $engagement
            ? $this->authorizationFor($request, $engagement)
            : null;

        $base = '/admin/transformation-360/'
            . 'implementation-requests/'
            . $request->id
            . '/commercial-engagements';

        $canCreate = $request->status === 'ready_for_commercial'
            && (
                $engagement === null
                || ! in_array(
                    $engagement->status,
                    ['draft', 'presented'],
                    true
                )
            );

        $canPresent = $request->status === 'ready_for_commercial'
            && $engagement?->status === 'draft';

        $canAuthorize = $request->status === 'ready_for_commercial'
            && $engagement?->status === 'accepted'
            && $authorization === null;

        return [
            'engagement' => $engagement
                ? $this->engagementPayload($engagement, true)
                : null,
            'authorization' => $authorization
                ? $this->authorizationPayload($authorization)
                : null,
            'history' => $this->historyForAdmin(
                $request,
                $engagement
            ),
            'actions' => [
                'can_create' => $canCreate,
                'can_present' => $canPresent,
                'can_authorize' => $canAuthorize,
                'create_endpoint' => $canCreate ? $base : null,
                'present_endpoint' => $canPresent
                    ? $base . '/' . $engagement->id . '/present'
                    : null,
                'authorize_endpoint' => $canAuthorize
                    ? $base . '/' . $engagement->id . '/authorize'
                    : null,
            ],
        ];
    }

    public function forTenant(int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        // The same current-request ordering as R115 acceptance.
        $request = TransformationImplementationRequest::query()
            ->where('company_id', $companyId)
            ->where('capability_key', self::CAPABILITY)
            ->orderByDesc('attempt')
            ->orderByDesc('id')
            ->first();

        // R116-E4H: only the latest attempt may be commercial.
        if (
            $request === null
            || $request->status !== 'ready_for_commercial'
        ) {
            return null;
        }

        $engagement = $this->latestEngagement($request);

        // A draft, rejected, superseded or cancelled proposal
        // must never be presented as an actionable tenant offer.
        if (
            $engagement === null
            || ! in_array(
                $engagement->status,
                ['presented', 'accepted'],
                true
            )
        ) {
            return null;
        }

        $authorization = $this->authorizationFor(
            $request,
            $engagement
        );

        return [
            'engagement' => $this->engagementPayload(
                $engagement,
                false
            ),
            'authorization' => $authorization
                ? $this->authorizationPayload($authorization)
                : null,
            'actions' => [
                'can_accept' => $engagement->status === 'presented',
                'accept_endpoint' => $engagement->status === 'presented'
                    ? '/app/transformacion-360/datos-bi/'
                        . 'propuesta-comercial/aceptar'
                    : null,
            ],
        ];
    }

    // R116-E4L1_ADMIN_HISTORY
    // Read-only history. No mutation endpoints.
    private function historyForAdmin(
        TransformationImplementationRequest $request,
        ?TransformationImplementationCommercialEngagement $current
    ): array {
        return TransformationImplementationCommercialEngagement::query()
            ->where(
                'transformation_implementation_request_id',
                $request->id
            )
            ->where('company_id', $request->company_id)
            ->where(
                'transformation_implementation_phase_capability_id',
                $request->transformation_implementation_phase_capability_id
            )
            ->where('capability_key', self::CAPABILITY)
            ->when(
                $current !== null,
                fn ($query) => $query->where('id', '!=', $current->id)
            )
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get()
            ->map(function (
                TransformationImplementationCommercialEngagement $engagement
            ) use ($request): array {
                $authorization = $this->authorizationFor(
                    $request,
                    $engagement
                );

                return [
                    'engagement' => $this->engagementPayload(
                        $engagement,
                        true
                    ),
                    'authorization' => $authorization
                        ? $this->authorizationPayload($authorization)
                        : null,
                ];
            })
            ->all();
    }

    private function latestEngagement(
        TransformationImplementationRequest $request
    ): ?TransformationImplementationCommercialEngagement {
        return TransformationImplementationCommercialEngagement::query()
            ->where(
                'transformation_implementation_request_id',
                $request->id
            )
            ->where('company_id', $request->company_id)
            ->where(
                'transformation_implementation_phase_capability_id',
                $request->transformation_implementation_phase_capability_id
            )
            ->where('capability_key', self::CAPABILITY)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    private function authorizationFor(
        TransformationImplementationRequest $request,
        TransformationImplementationCommercialEngagement $engagement
    ): ?TransformationImplementationAuthorization {
        return TransformationImplementationAuthorization::query()
            ->where(
                'transformation_implementation_commercial_engagement_id',
                $engagement->id
            )
            ->where(
                'transformation_implementation_request_id',
                $request->id
            )
            ->where(
                'transformation_implementation_definition_id',
                $engagement->transformation_implementation_definition_id
            )
            ->where('company_id', $request->company_id)
            ->where(
                'transformation_implementation_phase_capability_id',
                $request->transformation_implementation_phase_capability_id
            )
            ->where('capability_key', self::CAPABILITY)
            ->first();
    }

    private function engagementPayload(
        TransformationImplementationCommercialEngagement $engagement,
        bool $admin
    ): array {
        $payload = [
            'id' => (int) $engagement->id,
            'version' => (int) $engagement->version,
            'status' => (string) $engagement->status,
            'currency' => (string) $engagement->currency,
            'price_amount' => $engagement->price_amount,
            'duration_days' => $engagement->duration_days,
            'scope_snapshot' => $engagement->scope_snapshot,
            'deliverables_snapshot' => $engagement->deliverables_snapshot,
            'contract_scope_schema_version' => $engagement->contract_scope_schema_version,
            'contracted_scope_snapshot' => $engagement->contracted_scope_snapshot,
            'contracted_deliverables_snapshot' => $engagement->contracted_deliverables_snapshot,
            'commercial_terms_snapshot' =>
                $engagement->commercial_terms_snapshot,
            'presented_at' => $engagement->presented_at?->toISOString(),
            'accepted_at' => $engagement->accepted_at?->toISOString(),
        ];

        if ($admin) {
            $payload['internal_notes'] = $engagement->internal_notes;
            $payload['definition_id'] = (int) (
                $engagement->transformation_implementation_definition_id
            );
        }

        return $payload;
    }

    private function authorizationPayload(
        TransformationImplementationAuthorization $authorization
    ): array {
        return [
            'status' => (string) $authorization->status,
            'active' => $authorization->isActive(),
            'authorized_at' =>
                $authorization->authorized_at?->toISOString(),
            'revoked_at' =>
                $authorization->revoked_at?->toISOString(),
        ];
    }
}
