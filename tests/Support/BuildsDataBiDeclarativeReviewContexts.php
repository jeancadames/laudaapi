<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Diagnosis\DataBiClarificationContract as Clarification;
use App\Services\Diagnosis\DataBiDeclarativeReviewActor as Actor;
use App\Services\Diagnosis\DataBiDeclarativeReviewContext as Context;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;

/** Fixtures sintéticos PHP; no arranca Laravel ni resuelve una conexión. */
trait BuildsDataBiDeclarativeReviewContexts
{
    use BuildsDataBiTenantDeclarationPayloads;

    private function requestData(): array
    {
        return ['id' => 11, 'company_id' => 7, 'capability_key' => 'data_transformation_bi'];
    }

    private function publication(int $version = 3): array
    {
        $snapshot = $this->reviewSnapshot();
        $first = $snapshot['requirements'][0];
        $snapshot['requirements'] = array_map(static fn (int $i): array => [
            ...$first, 'key' => 'req_'.$i, 'label' => 'Requerimiento '.$i,
        ], range(1, 4));
        unset($snapshot['content_sha256']);
        $snapshot['content_sha256'] = hash('sha256', json_encode($snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ['id' => 40 + $version, 'version' => $version, 'status' => 'sent', 'company_id' => 7,
            'transformation_implementation_request_id' => 11, 'sent_at' => '2026-10-30 09:00:00',
            'sent_by_user_id' => 9, 'review_snapshot' => $snapshot, 'content_sha256' => $snapshot['content_sha256']];
    }

    private function declaration(?array $published = null, bool $gaps = false): array
    {
        $published ??= $this->publication();
        $input = $this->declarationInput($published['version'], $published['review_snapshot']);
        if ($gaps) {
            foreach (['available', 'partial', 'unavailable', 'unknown'] as $i => $availability) {
                $input['declarations'][$i]['availability'] = $availability;
                $input['declarations'][$i]['notes'] = 'Limitación declarada '.$i.'.';
                if (in_array($availability, ['unavailable', 'unknown'], true)) {
                    $input['declarations'][$i]['sources'] = [];
                }
            }
        }
        $snapshot = DataBiInformationTenantDeclarationContract::normalize($published, $input, 'submit');

        return ['id' => 200 + $published['version'], 'data_bi_information_request_id' => $published['id'],
            'company_id' => 7, 'status' => 'submitted', 'revision' => 5, 'declaration_snapshot' => $snapshot,
            'content_sha256' => $snapshot['content_sha256'], 'created_by_user_id' => 20,
            'updated_by_user_id' => 21, 'submitted_by_user_id' => 21, 'submitted_at' => '2026-10-30 10:00:00'];
    }

    private function context(bool $gaps = false, bool $superseded = false): Context
    {
        $published = $this->publication();

        return new Context($this->requestData(), $published, $this->declaration($published, $gaps),
            $superseded ? $this->publication(4) : $published);
    }

    private function admin(): Actor
    {
        return Actor::admin(9, 'admin', true);
    }

    private function tenant(): Actor
    {
        return Actor::tenant(21, true, 'user', 7, 20, 17, 17, 'admin', true);
    }

    private function question(string $message = '¿Qué período cubre la estructura?', string $key = 'req_1'): array
    {
        return ['version' => 3, 'requirement_key' => $key, 'message' => $message];
    }

    private function requested(?Context $context = null, int $id = 1): array
    {
        return Clarification::request($context ?? $this->context(), $this->admin(), $this->question(), $id, '2026-10-30T11:00:00Z');
    }

    private function answered(?Context $context = null): array
    {
        $context ??= $this->context();

        return Clarification::transition($context, $this->requested($context), $this->tenant(), 'answer', [
            'version' => 3, 'message' => 'La estructura incluye el período del año anterior.',
        ], '2026-10-30T12:00:00Z');
    }

    private function resolved(?Context $context = null): array
    {
        $context ??= $this->context();

        return Clarification::transition($context, $this->answered($context), $this->admin(), 'resolve', [
            'version' => 3, 'message' => 'Alcance declarativo aclarado; sin verificación técnica.',
        ], '2026-10-30T13:00:00Z');
    }

    private function conclusion(): array
    {
        return ['version' => 3, 'conclusion' => 'Examen declarativo concluido con las brechas informadas.',
            'limitations' => 'Fuentes y estructuras declaradas por el Tenant, sin inspección técnica.'];
    }
}
