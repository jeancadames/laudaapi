<?php

namespace App\Services\Fiscal;

use App\Models\Company;
use App\Models\DgiiCompanySetting;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentEvent;
use App\Models\FiscalDocumentLine;
use App\Models\FiscalDocumentType;
use App\Services\Dgii\DgiiDocumentSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiscalDocumentIssuerService
{
    public function __construct(
        private readonly FiscalDocumentTotals $totals,
        private readonly DgiiDocumentSequenceService $seqService,
    ) {}

    /**
     * Crea draft (y líneas) de forma segura.
     */
    public function createDraft(Company $company, array $data, ?int $actorUserId = null): FiscalDocument
    {
        return DB::transaction(function () use ($company, $data, $actorUserId) {

            $type = $this->resolveType($data);

            $doc = new FiscalDocument();
            $doc->company_id = $company->id;
            $doc->document_type_id = $type->id;
            $doc->buyer_party_id = $data['buyer_party_id'] ?? null;
            $doc->external_ref = $data['external_ref'] ?? null;

            $doc->currency = $data['currency'] ?? $company->currency ?? 'DOP';
            $doc->exchange_rate = $data['exchange_rate'] ?? null;

            $doc->status = 'draft';
            $doc->payload = $data['payload'] ?? null;
            $doc->meta = $data['meta'] ?? null;

            $doc->save();

            $linesInput = $data['lines'] ?? [];
            $this->replaceLinesAndRecalc($doc, $linesInput, (float)($data['default_tax_rate'] ?? 0.18));

            $this->event($doc, $actorUserId, 'draft_created', 'Draft creado', [
                'document_type' => $type->code,
                'lines' => count($linesInput),
            ]);

            return $doc->fresh(['type', 'buyer', 'lines']);
        }, 3);
    }

    /**
     * Actualiza un draft (reemplaza líneas completo).
     */
    public function updateDraft(FiscalDocument $doc, array $data, ?int $actorUserId = null): FiscalDocument
    {
        return DB::transaction(function () use ($doc, $data, $actorUserId) {

            $doc = FiscalDocument::query()
                ->where('id', $doc->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($doc->status !== 'draft') {
                throw new RuntimeException("Solo se puede editar un draft. Status actual: {$doc->status}");
            }

            if (isset($data['document_type_code'])) {
                $type = $this->resolveType($data);
                $doc->document_type_id = $type->id;
            } else {
                $type = $doc->type()->first();
            }

            $doc->buyer_party_id = $data['buyer_party_id'] ?? $doc->buyer_party_id;
            $doc->external_ref = $data['external_ref'] ?? $doc->external_ref;
            $doc->currency = $data['currency'] ?? $doc->currency;
            $doc->exchange_rate = $data['exchange_rate'] ?? $doc->exchange_rate;

            if (array_key_exists('payload', $data)) $doc->payload = $data['payload'];
            if (array_key_exists('meta', $data)) $doc->meta = $data['meta'];

            $doc->save();

            $linesInput = $data['lines'] ?? [];
            $this->replaceLinesAndRecalc($doc, $linesInput, (float)($data['default_tax_rate'] ?? 0.18));

            $this->event($doc, $actorUserId, 'draft_updated', 'Draft actualizado', [
                'document_type' => $type?->code,
                'lines' => count($linesInput),
            ]);

            return $doc->fresh(['type', 'buyer', 'lines']);
        }, 3);
    }

    /**
     * Emite (issue):
     * - valida reglas
     * - reserva número DGII con lockForUpdate en dgii_document_sequences
     * - set number, issued_at, issue_date, status=issued, dgii_sequence_id
     */
    public function issue(FiscalDocument $doc, Company $company, ?int $actorUserId = null): FiscalDocument
    {
        return DB::transaction(function () use ($doc, $company, $actorUserId) {

            $doc = FiscalDocument::query()
                ->where('id', $doc->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($doc->status === 'issued' || $doc->status === 'signed' || $doc->status === 'submitted' || $doc->status === 'accepted') {
                // idempotente: ya está emitido
                return $doc->fresh(['type', 'buyer', 'lines']);
            }

            if ($doc->status !== 'draft') {
                throw new RuntimeException("Solo se puede emitir desde draft. Status actual: {$doc->status}");
            }

            $type = $doc->type()->firstOrFail();

            // ✅ Validación buyer si el tipo lo requiere
            if ($type->requires_buyer_tax_id) {
                $buyer = $doc->buyer()->first();
                $taxId = $buyer?->tax_id;
                if (!$buyer || !$taxId) {
                    throw new RuntimeException("Este tipo requiere comprador con tax_id (RNC/Cédula).");
                }
            }

            // ✅ Validación líneas
            $lineCount = FiscalDocumentLine::query()->where('document_id', $doc->id)->count();
            if ($lineCount <= 0) {
                throw new RuntimeException("No se puede emitir sin líneas.");
            }

            // ✅ determinar environment (DGII setting) o default
            $env = DgiiCompanySetting::query()
                ->where('company_id', $company->id)
                ->value('environment') ?: 'precert';

            // ✅ map tipo a clase DGII (NCF/ECF) según kind
            $docClass = ($type->kind === 'ecf') ? 'ECF' : 'NCF';

            // ✅ reserva número DGII
            $reservation = $this->seqService->reserveNext(
                companyId: (int) $company->id,
                environment: (string) $env,
                documentClass: (string) $docClass,
                documentType: (string) $type->code,
                series: '' // por ahora vacío
            );

            $now = CarbonImmutable::now();

            $doc->dgii_sequence_id = (int) $reservation['sequence_id'];
            $doc->number = (string) $reservation['formatted'];

            $doc->issue_date = $doc->issue_date ?: $now->toDateString();
            $doc->issued_at = $now;
            $doc->status = 'issued';

            $doc->save();

            $this->event($doc, $actorUserId, 'issued', 'Documento emitido', [
                'environment' => $env,
                'class' => $docClass,
                'type' => $type->code,
                'number' => $doc->number,
                'sequence_id' => $doc->dgii_sequence_id,
                'next_number' => $reservation['next_number'],
            ]);

            return $doc->fresh(['type', 'buyer', 'lines']);
        }, 3);
    }

    // =========================
    // Internals
    // =========================

    private function resolveType(array $data): FiscalDocumentType
    {
        $code = (string) ($data['document_type_code'] ?? '');
        if ($code === '') throw new RuntimeException('document_type_code es requerido.');

        return FiscalDocumentType::query()
            ->where('country_code', 'DO')
            ->where('code', $code)
            ->where('active', true)
            ->firstOrFail();
    }

    private function replaceLinesAndRecalc(FiscalDocument $doc, array $linesInput, float $defaultTaxRate): void
    {
        // 🔥 replace completo (simple, confiable)
        FiscalDocumentLine::query()->where('document_id', $doc->id)->delete();

        $computedLines = [];
        $lineNo = 1;

        foreach ($linesInput as $li) {
            $base = [
                'document_id' => $doc->id,
                'line_no' => $lineNo++,
                'sku' => $li['sku'] ?? null,
                'description' => (string) ($li['description'] ?? ''),
                'quantity' => $li['quantity'] ?? 1,
                'uom' => $li['uom'] ?? null,
                'unit_price' => $li['unit_price'] ?? 0,
                'discount' => $li['discount'] ?? 0,
                'taxes' => $li['taxes'] ?? null,
                'meta' => $li['meta'] ?? null,
            ];

            if (trim($base['description']) === '') {
                throw new RuntimeException('Cada línea requiere description.');
            }

            $calc = $this->totals->computeLine($base, $defaultTaxRate);

            $row = array_merge($base, [
                'discount' => $calc['discount'],
                'taxable_base' => $calc['taxable_base'],
                'tax_amount' => $calc['tax_amount'],
                'line_total' => $calc['line_total'],
                'taxes' => $calc['taxes'],
            ]);

            FiscalDocumentLine::create($row);
            $computedLines[] = $row;
        }

        $docTotals = $this->totals->computeDocumentTotals($computedLines);

        $doc->subtotal = $docTotals['subtotal'];
        $doc->discount_total = $docTotals['discount_total'];
        $doc->tax_total = $docTotals['tax_total'];
        $doc->grand_total = $docTotals['grand_total'];
        $doc->balance_due = $docTotals['balance_due'];

        $doc->save();
    }

    private function event(FiscalDocument $doc, ?int $actorUserId, string $type, string $summary, array $payload = []): void
    {
        FiscalDocumentEvent::create([
            'document_id' => $doc->id,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'summary' => $summary,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
