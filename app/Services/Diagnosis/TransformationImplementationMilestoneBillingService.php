<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationMilestone;
use App\Models\TransformationImplementationPhase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationMilestoneBillingService
{
    public function __construct(
        private readonly TransformationImplementationPricingService $pricing,
        private readonly TransformationImplementationModalityCatalog $catalog
    ) {
    }

    public function upsertMilestone(
        TransformationImplementationPhase $phase,
        array $data,
        ?int $userId = null
    ): TransformationImplementationMilestone {
        $phase->loadMissing('plan');

        if (!$phase->plan) {
            throw ValidationException::withMessages([
                'phase' => 'La fase no pertenece a un Plan de Implementación válido.',
            ]);
        }

        if (!in_array($phase->plan->status, ['draft', 'presented'], true)) {
            throw ValidationException::withMessages([
                'milestone' =>
                    'Los hitos solo pueden definirse antes de aceptar el Plan de Implementación.',
            ]);
        }

        $selectedModality = $phase->plan->selected_modality;

        if (!$selectedModality || !$this->catalog->exists($selectedModality)) {
            throw ValidationException::withMessages([
                'selected_modality' =>
                    'Seleccione una modalidad válida antes de definir los hitos de la fase.',
            ]);
        }

        $estimate = $this->pricing->forSelectedModality($phase);

        if (!$estimate) {
            throw ValidationException::withMessages([
                'estimate' =>
                    'La fase no tiene precio/tiempo definido para la modalidad seleccionada.',
            ]);
        }

        $sequence = (int) ($data['sequence'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $amount = $data['billing_amount'] ?? null;

        if ($sequence < 1) {
            throw ValidationException::withMessages([
                'sequence' => 'El hito debe tener una secuencia mayor o igual a 1.',
            ]);
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'El hito debe tener un nombre.',
            ]);
        }

        if (!is_numeric($amount) || (float) $amount < 0) {
            throw ValidationException::withMessages([
                'billing_amount' => 'El monto del hito debe ser mayor o igual a cero.',
            ]);
        }

        $amount = round((float) $amount, 2);
        $estimateAmount = round((float) $estimate->price_amount, 2);

        if ($amount > $estimateAmount) {
            throw ValidationException::withMessages([
                'billing_amount' =>
                    'Un hito individual no puede superar el precio total de la fase.',
            ]);
        }

        $definition = $this->catalog->get($selectedModality);
        $existing = TransformationImplementationMilestone::query()
            ->where('transformation_implementation_phase_id', $phase->id)
            ->where('sequence', $sequence)
            ->first();

        $creator = $existing?->created_by_user_id ?? $userId;

        $milestone = TransformationImplementationMilestone::query()->updateOrCreate(
            [
                'transformation_implementation_phase_id' => $phase->id,
                'sequence' => $sequence,
            ],
            [
                'name' => $name,
                'description' => isset($data['description'])
                    ? trim((string) $data['description'])
                    : null,
                'modality' => $selectedModality,
                'modality_label' => $definition['label'],
                'billing_percentage' => $estimateAmount > 0
                    ? round(($amount / $estimateAmount) * 100, 4)
                    : 0,
                'billing_amount' => $amount,
                'currency' => $estimate->currency,
                'billing_status' => TransformationImplementationMilestone::STATUS_DRAFT,
                'due_at' => $data['due_at'] ?? null,
                'scope_snapshot' => [
                    'phase_id' => $phase->id,
                    'phase_sequence' => $phase->sequence,
                    'phase_name' => $phase->name,
                    'modality' => $selectedModality,
                    'estimate_id' => $estimate->id,
                    'estimate_price_amount' => $estimate->price_amount,
                    'currency' => $estimate->currency,
                    'estimate_scope_snapshot' => $estimate->scope_snapshot,
                ],
                'internal_notes' => isset($data['internal_notes'])
                    ? trim((string) $data['internal_notes'])
                    : null,
                'created_by_user_id' => $creator,
                'updated_by_user_id' => $userId,
            ]
        );

        $this->assertAllocationDoesNotExceedEstimate($phase);

        return $milestone->refresh();
    }

    public function allocationSummary(
        TransformationImplementationPhase $phase
    ): array {
        $phase->loadMissing('plan');

        $estimate = $this->pricing->forSelectedModality($phase);

        if (!$estimate) {
            throw ValidationException::withMessages([
                'estimate' =>
                    'La fase no tiene una estimación para la modalidad seleccionada.',
            ]);
        }

        $milestones = TransformationImplementationMilestone::query()
            ->where('transformation_implementation_phase_id', $phase->id)
            ->where('modality', $phase->plan->selected_modality)
            ->where('billing_status', '!=', TransformationImplementationMilestone::STATUS_CANCELLED)
            ->orderBy('sequence')
            ->get();

        $allocated = round((float) $milestones->sum(
            fn (TransformationImplementationMilestone $milestone) =>
                (float) $milestone->billing_amount
        ), 2);

        $estimateAmount = round((float) $estimate->price_amount, 2);

        return [
            'estimate_amount' => $estimateAmount,
            'allocated_amount' => $allocated,
            'remaining_amount' => round($estimateAmount - $allocated, 2),
            'currency' => $estimate->currency,
            'is_complete' => abs($estimateAmount - $allocated) < 0.005,
            'milestones_count' => $milestones->count(),
        ];
    }

    public function assertAllocationComplete(
        TransformationImplementationPhase $phase
    ): array {
        $summary = $this->allocationSummary($phase);

        if (!$summary['is_complete']) {
            throw ValidationException::withMessages([
                'milestones' =>
                    'La suma de los hitos debe coincidir exactamente con el precio de la fase.',
            ]);
        }

        return $summary;
    }

    public function markReadyToInvoice(
        TransformationImplementationMilestone $milestone,
        ?int $userId = null
    ): TransformationImplementationMilestone {
        $milestone->loadMissing('phase.plan');

        $plan = $milestone->phase?->plan;

        if (!$plan || $plan->status !== 'accepted' || !$plan->accepted_at) {
            throw ValidationException::withMessages([
                'billing' =>
                    'La implementación solo puede facturarse después de aceptar el Plan de Implementación.',
            ]);
        }

        if ($milestone->modality !== $plan->selected_modality) {
            throw ValidationException::withMessages([
                'billing' =>
                    'El hito no corresponde a la modalidad seleccionada del plan aceptado.',
            ]);
        }

        $this->assertAllocationComplete($milestone->phase);

        $milestone->forceFill([
            'billing_status' => TransformationImplementationMilestone::STATUS_READY,
            'ready_to_invoice_at' => now(),
            'updated_by_user_id' => $userId,
        ])->save();

        return $milestone->refresh();
    }

    public function recordInvoiceReference(
        TransformationImplementationMilestone $milestone,
        string $invoiceReference,
        ?int $userId = null
    ): TransformationImplementationMilestone {
        $invoiceReference = trim($invoiceReference);

        if ($invoiceReference === '') {
            throw ValidationException::withMessages([
                'invoice_reference' => 'La referencia de factura es obligatoria.',
            ]);
        }

        if (!in_array(
            $milestone->billing_status,
            [
                TransformationImplementationMilestone::STATUS_READY,
                TransformationImplementationMilestone::STATUS_INVOICED,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'billing_status' =>
                    'El hito debe estar listo para facturar antes de registrar la factura.',
            ]);
        }

        $milestone->forceFill([
            'invoice_reference' => $invoiceReference,
            'invoice_issued_at' => $milestone->invoice_issued_at ?? now(),
            'billing_status' => TransformationImplementationMilestone::STATUS_INVOICED,
            'updated_by_user_id' => $userId,
        ])->save();

        return $milestone->refresh();
    }

    public function recordPaymentReference(
        TransformationImplementationMilestone $milestone,
        string $paymentReference,
        ?int $userId = null
    ): TransformationImplementationMilestone {
        $paymentReference = trim($paymentReference);

        if (!$milestone->invoice_reference) {
            throw ValidationException::withMessages([
                'invoice_reference' =>
                    'No se puede registrar un pago sin una factura asociada al hito.',
            ]);
        }

        if ($paymentReference === '') {
            throw ValidationException::withMessages([
                'payment_reference' => 'La referencia de pago es obligatoria.',
            ]);
        }

        $milestone->forceFill([
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
            'billing_status' => TransformationImplementationMilestone::STATUS_PAID,
            'updated_by_user_id' => $userId,
        ])->save();

        return $milestone->refresh();
    }

    private function assertAllocationDoesNotExceedEstimate(
        TransformationImplementationPhase $phase
    ): void {
        $summary = $this->allocationSummary($phase);

        if ($summary['remaining_amount'] < -0.005) {
            throw ValidationException::withMessages([
                'milestones' =>
                    'La suma de hitos no puede superar el precio de la fase.',
            ]);
        }
    }
}
