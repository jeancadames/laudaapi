<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiClarificationContract as Clarification;
use App\Services\Diagnosis\DataBiDeclarativeReviewClosureContract as Contract;
use App\Services\Diagnosis\DataBiDeclarativeReviewContext as Context;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;

final class DataBiDeclarativeReviewClosureContractTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    public function test_closure_without_clarifications_is_declarative_and_preserves_inputs(): void
    {
        $context = $this->context();
        $before = $context->declarations();
        $input = $this->conclusion();
        $closure = Contract::close($context, $this->admin(), $input, '2026-10-30T14:00:00Z', []);
        self::assertSame('declarative_review_closure', $closure['purpose']);
        self::assertSame('closed', $closure['status']);
        self::assertSame(9, $closure['closed_by_user_id']);
        self::assertSame('2026-10-30T14:00:00Z', $closure['closed_at']);
        self::assertSame($context->reference(), $closure['reference']);
        self::assertSame($input['conclusion'], $closure['conclusion']);
        self::assertSame($input['limitations'], $closure['limitations']);
        self::assertFalse($closure['data_inspected']);
        self::assertFalse($closure['technical_validation_performed']);
        self::assertFalse($closure['commercial_approval']);
        self::assertSame([], $closure['declared_gaps']);
        self::assertSame([], $closure['clarifications']);
        self::assertSame($input, $this->conclusion());
        self::assertSame($before, $context->declarations());
        self::assertTrue(Contract::appliesToCurrentVersion($context, $closure, []));
    }

    #[DataProvider('notSubmitted')]
    public function test_closure_cannot_exist_without_a_valid_submitted_declaration(bool $missing): void
    {
        $declaration = $missing ? null : [...$this->declaration(), 'status' => 'draft'];
        $this->expectException(InvalidArgumentException::class);
        $context = new Context($this->requestData(), $this->publication(), $declaration, $this->publication());
        Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
    }

    public static function notSubmitted(): iterable
    {
        yield [true];
        yield [false];
    }

    #[DataProvider('openStates')]
    public function test_requested_and_answered_clarifications_both_block_closure(bool $answered): void
    {
        $context = $this->context();
        $this->expectException(InvalidArgumentException::class);
        Contract::assertEligible($context, [$answered ? $this->answered() : $this->requested()]);
    }

    public static function openStates(): iterable
    {
        yield [false];
        yield [true];
    }

    public function test_resolved_and_justifiably_cancelled_clarifications_allow_closure(): void
    {
        $context = $this->context();
        $resolved = $this->resolved();
        $other = Clarification::request($context, $this->admin(), $this->question('¿Existe un catálogo descriptivo?', 'req_2'),
            2, '2026-10-30T11:00:00Z', [$resolved]);
        $cancelled = Clarification::transition($context, $other, $this->admin(), 'cancel', [
            'version' => 3, 'message' => 'La consulta no corresponde al alcance declarativo.',
        ], '2026-10-30T13:00:00Z');
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', [$cancelled, $resolved]);
        self::assertSame([1, 2], array_column($closure['clarifications'], 'id'));
        self::assertSame(['resolved', 'cancelled'], array_column($closure['clarifications'], 'status'));
        self::assertSame([3, 2], array_column($closure['clarifications'], 'revision'));
        self::assertSame($closure, Contract::validate($context, $closure, [$resolved, $cancelled]));
        self::assertSame($closure, Contract::close($context, $this->admin(), array_reverse($this->conclusion(), true),
            '2026-10-30T14:00:00Z', [$resolved, $cancelled]));
    }

    public function test_unavailable_partial_and_unknown_information_does_not_prevent_documented_closure(): void
    {
        $context = $this->context(gaps: true);
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        self::assertSame(['partial', 'unavailable', 'unknown'], array_column($closure['declared_gaps'], 'availability'));
        self::assertSame(['req_2', 'req_3', 'req_4'], array_column($closure['declared_gaps'], 'requirement_key'));
        self::assertSame(['Limitación declarada 1.', 'Limitación declarada 2.', 'Limitación declarada 3.'],
            array_column($closure['declared_gaps'], 'notes'));
        self::assertCount(4, $closure['declared_limitations']);
        self::assertSame('Limitación declarada 0.', $closure['declared_limitations'][0]['notes']);
        self::assertSame('Solo descripción de la fuente.', $closure['declared_limitations'][0]['source_notes'][0]['notes']);
        self::assertSame([], $closure['declared_limitations'][2]['source_notes']);
        self::assertSame('unavailable', $context->declarations()[2]['availability']);
    }

    public function test_tenant_cannot_close_review(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::close($this->context(), $this->tenant(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
    }

    #[DataProvider('invalidConclusions')]
    public function test_closure_rejects_invalid_conclusions_identities_and_commercial_actions(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::close($this->context(), $this->admin(), array_replace($this->conclusion(), $changes), '2026-10-30T14:00:00Z', []);
    }

    public static function invalidConclusions(): iterable
    {
        yield [['version' => 4]];
        yield [['version' => '3']];
        yield [['conclusion' => '']];
        yield [['limitations' => '']];
        yield [['conclusion' => str_repeat('a', 801)]];
        yield [['limitations' => 'password=secret']];
        yield [['conclusion' => ['invalid']]];
        foreach (['company_id', 'subscriber_id', 'user_id', 'closed_by_user_id', 'closed_at', 'reference',
            'declared_gaps', 'clarifications', 'ready_for_commercial', 'definition_v1', 'candidate_id', 'commercial_approval',
            'fees', 'attachments', 'files', 'data_inspected'] as $key) {
            yield $key => [[$key => 1]];
        }
    }

    public function test_closure_and_clarifications_are_immutable_after_closure(): void
    {
        $context = $this->context();
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $this->expectException(InvalidArgumentException::class);
        Contract::close($context, $this->admin(), [...$this->conclusion(), 'conclusion' => 'Otra conclusión.'],
            '2026-10-30T15:00:00Z', [], $closure);
    }

    public function test_new_clarifications_are_blocked_after_closure(): void
    {
        $context = $this->context();
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $this->expectException(InvalidArgumentException::class);
        Clarification::request($context, $this->admin(), $this->question(), 1, '2026-10-30T15:00:00Z', [], $closure);
    }

    public function test_transitions_are_blocked_when_a_closure_is_present(): void
    {
        $context = $this->context();
        $resolved = $this->resolved();
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', [$resolved]);
        $this->expectException(InvalidArgumentException::class);
        Clarification::transition($context, $resolved, $this->admin(), 'cancel', ['version' => 3, 'message' => 'Cancelar.'],
            '2026-10-30T15:00:00Z', $closure);
    }

    public function test_new_publication_preserves_historical_closure_but_does_not_inherit_it(): void
    {
        $context = $this->context();
        $resolved = $this->resolved();
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', [$resolved]);
        $historical = $this->context(superseded: true);
        self::assertSame($closure, Contract::validate($historical, $closure, [$resolved]));
        self::assertFalse(Contract::appliesToCurrentVersion($historical, $closure, [$resolved]));
        $this->expectException(InvalidArgumentException::class);
        Contract::close($historical, $this->admin(), $this->conclusion(), '2026-10-30T15:00:00Z', [$resolved]);
    }

    public function test_old_closure_cannot_be_attached_to_a_new_submission(): void
    {
        $closure = Contract::close($this->context(), $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $new = $this->publication(4);
        $context = new Context($this->requestData(), $new, $this->declaration($new), $new);
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($context, $closure, []);
    }

    public function test_omitting_a_previously_resolved_clarification_breaks_the_historical_closure(): void
    {
        $resolved = $this->resolved();
        $closure = Contract::close($this->context(), $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', [$resolved]);
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($this->context(), $closure, []);
    }

    #[DataProvider('tamperedClosures')]
    public function test_tampering_cannot_hide_gaps_change_audit_or_claim_technical_or_commercial_approval(string $key, mixed $value): void
    {
        $closure = Contract::close($this->context(gaps: true), $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $closure[$key] = $value;
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($this->context(gaps: true), $closure, []);
    }

    public static function tamperedClosures(): iterable
    {
        yield ['schema_version', 2];
        yield ['conclusion', 'Cambio silencioso.'];
        yield ['limitations', 'Cambio silencioso.'];
        yield ['declared_gaps', []];
        yield ['declared_limitations', []];
        yield ['closed_by_user_id', null];
        yield ['closed_by_user_id', 10];
        yield ['closed_at', null];
        yield ['closed_at', '2026-10-30T15:00:00Z'];
        yield ['data_inspected', true];
        yield ['technical_validation_performed', true];
        yield ['commercial_approval', true];
        yield ['content_sha256', str_repeat('a', 64)];
        yield ['unknown', true];
    }

    public function test_gap_list_order_is_strict_even_when_hash_is_recomputed(): void
    {
        $closure = Contract::close($this->context(gaps: true), $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $closure['declared_gaps'] = array_reverse($closure['declared_gaps']);
        unset($closure['content_sha256']);
        $closure = Context::seal($closure);
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($this->context(gaps: true), $closure, []);
    }

    public function test_closure_cannot_precede_resolution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::close($this->context(), $this->admin(), $this->conclusion(), '2026-10-30T12:59:59Z', [$this->resolved()]);
    }

    public function test_foreign_clarification_cannot_be_ignored_or_mixed_with_current_submission(): void
    {
        $state = $this->resolved();
        $state['reference']['declaration_id']++;
        $this->expectException(InvalidArgumentException::class);
        Contract::assertEligible($this->context(), [$state]);
    }

    public function test_closure_is_deterministic_with_reordered_object_keys(): void
    {
        $context = $this->context(gaps: true);
        $input = $this->conclusion();
        $closure = Contract::close($context, $this->admin(), $input, '2026-10-30T14:00:00Z', []);
        self::assertSame($closure, Contract::close($context, $this->admin(), array_reverse($input, true), '2026-10-30T14:00:00Z', []));
        self::assertSame($closure, Contract::validate($context, array_reverse($closure, true), []));
        self::assertSame($closure, Contract::validate($context, $closure, []));
    }

    #[DataProvider('requiredConclusionFields')]
    public function test_required_conclusion_fields_cannot_be_omitted(string $field): void
    {
        $input = $this->conclusion();
        unset($input[$field]);
        $this->expectException(InvalidArgumentException::class);
        Contract::close($this->context(), $this->admin(), $input, '2026-10-30T14:00:00Z', []);
    }

    public static function requiredConclusionFields(): iterable
    {
        yield ['version'];
        yield ['conclusion'];
        yield ['limitations'];
    }

    public function test_changing_original_submission_date_invalidates_historical_closure(): void
    {
        $closure = Contract::close($this->context(), $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        $declaration = $this->declaration();
        $declaration['submitted_at'] = '2026-10-30 10:00:01';
        $context = new Context($this->requestData(), $this->publication(), $declaration, $this->publication());
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($context, $closure, []);
    }

    public function test_all_unavailable_sources_with_documented_gaps_can_complete_review(): void
    {
        $published = $this->publication();
        $declaration = $this->declaration();
        $input = ['version' => 3, 'declarations' => array_map(static fn (array $item): array => [
            'requirement_key' => $item['requirement_key'], 'availability' => 'unavailable',
            'sources' => [], 'notes' => 'La empresa no dispone de esta información.',
        ], $declaration['declaration_snapshot']['declarations'])];
        $snapshot = DataBiInformationTenantDeclarationContract::normalize($published, $input, 'submit');
        $declaration['declaration_snapshot'] = $snapshot;
        $declaration['content_sha256'] = $snapshot['content_sha256'];
        $context = new Context($this->requestData(), $published, $declaration, $published);
        $closure = Contract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', []);
        self::assertCount(4, $closure['declared_gaps']);
        self::assertSame(['unavailable'], array_values(array_unique(array_column($closure['declared_gaps'], 'availability'))));
        self::assertFalse($closure['technical_validation_performed']);
    }
}
