<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiClarificationContract as Contract;
use App\Services\Diagnosis\DataBiClarificationTransitionPolicy as Policy;
use App\Services\Diagnosis\DataBiDeclarativeReviewContext as Context;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;

final class DataBiClarificationContractTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    public function test_valid_request_binds_submission_version_and_requirement(): void
    {
        $context = $this->context();
        $input = $this->question("  ¿Qué período?\r\nIndicar alcance.  ");
        $original = $input;
        $state = Contract::request($context, $this->admin(), $input, 51, '2026-10-30T11:00:00Z');
        self::assertSame($original, $input);
        self::assertSame('requested', $state['status']);
        self::assertSame(1, $state['revision']);
        self::assertSame(51, $state['id']);
        self::assertSame($context->reference(), $state['reference']);
        self::assertSame('req_1', $state['requirement_key']);
        self::assertSame(['action' => 'request', 'actor_user_id' => 9, 'actor_kind' => 'lauda_admin',
            'at' => '2026-10-30T11:00:00Z', 'message' => "¿Qué período?\nIndicar alcance."], $state['events'][0]);
        self::assertSame($state, Contract::validate($context, $state));
    }

    public function test_answer_and_resolution_append_without_changing_history_or_availability(): void
    {
        $context = $this->context(gaps: true);
        $before = $context->declarations();
        $requested = $this->requested($context);
        $answered = $this->answered($context);
        $resolved = $this->resolved($context);
        self::assertSame('answered', $answered['status']);
        self::assertSame('resolved', $resolved['status']);
        self::assertSame(2, $answered['revision']);
        self::assertSame(3, $resolved['revision']);
        self::assertSame($requested['events'], array_slice($answered['events'], 0, 1));
        self::assertSame($answered['events'], array_slice($resolved['events'], 0, 2));
        self::assertSame(21, $answered['events'][1]['actor_user_id']);
        self::assertSame('tenant', $answered['events'][1]['actor_kind']);
        self::assertSame(9, $resolved['events'][2]['actor_user_id']);
        self::assertSame($before, $context->declarations());
        self::assertSame('requested', $requested['status']);
        self::assertSame($resolved, Contract::validate($context, $resolved));
    }

    #[DataProvider('cancellableStates')]
    public function test_admin_cancels_with_a_reason_and_preserves_any_answer(bool $answered): void
    {
        $context = $this->context();
        $before = $answered ? $this->answered($context) : $this->requested($context);
        $cancelled = Contract::transition($context, $before, $this->admin(), 'cancel', [
            'version' => 3, 'message' => 'Se retira la consulta por estar duplicada en otro requerimiento.',
        ], '2026-10-30T14:00:00Z');
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame($before['events'], array_slice($cancelled['events'], 0, count($before['events'])));
        self::assertNotEmpty($cancelled['events'][count($cancelled['events']) - 1]['message']);
    }

    public static function cancellableStates(): iterable
    {
        yield [false];
        yield [true];
    }

    #[DataProvider('transitionMatrix')]
    public function test_exact_transition_and_authorization_matrix(?string $from, string $action, string $actor, ?string $expected): void
    {
        if ($expected === null) {
            $this->expectException(InvalidArgumentException::class);
        }
        self::assertSame($expected, Policy::decide($from, $action, $actor));
    }

    public static function transitionMatrix(): iterable
    {
        $allowed = ['|request|lauda_admin' => 'requested', 'requested|answer|tenant' => 'answered',
            'answered|resolve|lauda_admin' => 'resolved', 'requested|cancel|lauda_admin' => 'cancelled',
            'answered|cancel|lauda_admin' => 'cancelled'];
        foreach ([null, 'requested', 'answered', 'resolved', 'cancelled', 'invalid'] as $from) {
            foreach (['request', 'answer', 'resolve', 'cancel', 'edit'] as $action) {
                foreach (['lauda_admin', 'tenant', 'unknown'] as $actor) {
                    $key = $from.'|'.$action.'|'.$actor;
                    yield $key => [$from, $action, $actor, $allowed[$key] ?? null];
                }
            }
        }
    }

    #[DataProvider('invalidRequests')]
    public function test_unknown_requirements_versions_and_arbitrary_identity_inputs_are_rejected(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->admin(), array_replace($this->question(), $changes), 1, '2026-10-30T11:00:00Z');
    }

    public static function invalidRequests(): iterable
    {
        yield [['requirement_key' => 'foreign']];
        yield [['requirement_key' => 1]];
        yield [['version' => 2]];
        yield [['version' => '3']];
        yield [['message' => '']];
        yield [['message' => str_repeat('a', 801)]];
        foreach (['company_id', 'subscriber_id', 'user_id', 'actor', 'declaration_id', 'information_request_id',
            'reference', 'availability', 'sources', 'files', 'attachments', 'status', 'expected_version'] as $key) {
            yield $key => [[$key => 1]];
        }
    }

    #[DataProvider('invalidTransitionInputs')]
    public function test_response_resolution_and_cancellation_validate_text_and_strict_keys(string $action, array $changes): void
    {
        $context = $this->context();
        $this->expectException(InvalidArgumentException::class);
        Contract::transition($context, $action === 'resolve' ? $this->answered() : $this->requested(),
            $action === 'answer' ? $this->tenant() : $this->admin(), $action,
            array_replace(['version' => 3, 'message' => 'Explicación declarativa.'], $changes), '2026-10-30T14:00:00Z');
    }

    public static function invalidTransitionInputs(): iterable
    {
        foreach (['answer', 'resolve', 'cancel'] as $action) {
            foreach ([['message' => ''], ['message' => 'password=secret'], ['message' => ['file']],
                ['version' => '3'], ['actor_user_id' => 9], ['requirement_key' => 'req_2'],
                ['availability' => 'available'], ['files' => []]] as $i => $changes) {
                yield $action.' '.$i => [$action, $changes];
            }
        }
    }

    public function test_answer_cannot_be_replaced_by_a_second_answer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::transition($this->context(), $this->answered(), $this->tenant(), 'answer', [
            'version' => 3, 'message' => 'Una respuesta distinta.',
        ], '2026-10-30T14:00:00Z');
    }

    public function test_admin_cannot_answer_for_the_tenant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::transition($this->context(), $this->requested(), $this->admin(), 'answer', [
            'version' => 3, 'message' => 'Respuesta suplantada.',
        ], '2026-10-30T14:00:00Z');
    }

    public function test_tenant_cannot_request_an_administrative_clarification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->tenant(), $this->question(), 1, '2026-10-30T11:00:00Z');
    }

    #[DataProvider('duplicates')]
    public function test_duplicate_id_or_question_is_rejected(int $id, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->admin(), $this->question($message), $id,
            '2026-10-30T14:00:00Z', [$this->requested()]);
    }

    public static function duplicates(): iterable
    {
        yield [1, 'Otra pregunta.'];
        yield [2, '  ¿QUÉ PERÍODO CUBRE LA ESTRUCTURA?  '];
    }

    public function test_distinct_follow_up_questions_on_the_same_requirement_are_allowed(): void
    {
        $next = Contract::request($this->context(), $this->admin(), $this->question('¿Cuál es el alcance temporal?'),
            2, '2026-10-30T14:00:00Z', [$this->resolved()]);
        self::assertSame(2, $next['id']);
        self::assertSame('req_1', $next['requirement_key']);
    }

    #[DataProvider('tamperedHistory')]
    public function test_altered_messages_audit_references_types_and_lists_fail_closed(array $path, mixed $value): void
    {
        $state = $this->resolved();
        $node = &$state;
        foreach ($path as $segment) {
            $node = &$node[$segment];
        }
        $node = $value;
        unset($node);
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($this->context(), $state);
    }

    public static function tamperedHistory(): iterable
    {
        yield [['schema_version'], 2];
        yield [['reference', 'company_id'], 8];
        yield [['reference', 'declaration_id'], 204];
        yield [['reference', 'questionnaire_version'], 4];
        yield [['reference', 'declaration_revision'], 6];
        yield [['requirement_key'], 'foreign'];
        yield [['status'], 'answered'];
        yield [['revision'], '3'];
        yield [['content_sha256'], str_repeat('a', 64)];
        yield [['events', 0, 'message'], 'Pregunta editada.'];
        yield [['events', 1, 'message'], 'Respuesta editada.'];
        yield [['events', 1, 'actor_user_id'], null];
        yield [['events', 1, 'actor_kind'], 'lauda_admin'];
        yield [['events', 1, 'at'], '2026-10-30T10:30:00Z'];
        yield [['events', 1, 'at'], '2026-02-30T12:00:00Z'];
        yield [['events', 2, 'at'], 'yesterday'];
        yield [['events', 2, 'internal'], 'unknown'];
        yield [['unexpected'], true];
    }

    public function test_reordered_events_fail_even_when_hash_is_recomputed(): void
    {
        $state = $this->resolved();
        $state['events'] = array_reverse($state['events']);
        unset($state['content_sha256']);
        $state = Context::seal($state);
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($this->context(), $state);
    }

    public function test_semantically_equal_object_keys_preserve_the_hash_and_normalization(): void
    {
        $state = $this->resolved();
        $reordered = array_reverse($state, true);
        $reordered['events'] = array_map(static fn (array $event): array => array_reverse($event, true), $state['events']);
        $reordered['reference'] = array_reverse($state['reference'], true);
        self::assertSame($state, Contract::validate($this->context(), $reordered));
        self::assertSame($state, $this->resolved());
    }

    public function test_duplicate_or_foreign_collection_is_not_eligible_as_history(): void
    {
        $state = $this->requested();
        $this->expectException(InvalidArgumentException::class);
        Contract::collection($this->context(), [$state, $state]);
    }

    public function test_substituted_questionnaire_preserves_history_but_blocks_new_transitions(): void
    {
        $state = $this->requested();
        $context = $this->context(superseded: true);
        self::assertSame($state, Contract::validate($context, $state));
        $this->expectException(InvalidArgumentException::class);
        Contract::transition($context, $state, $this->tenant(), 'answer', [
            'version' => 3, 'message' => 'Respuesta histórica tardía.',
        ], '2026-10-30T14:00:00Z');
    }

    public function test_requests_cannot_precede_submission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->admin(), $this->question(), 1, '2026-10-30T09:59:59Z');
    }

    public function test_a_new_request_on_a_superseded_publication_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(superseded: true), $this->admin(), $this->question(), 2, '2026-10-30T14:00:00Z');
    }

    #[DataProvider('missingInputFields')]
    public function test_required_fields_cannot_be_omitted(string $action, string $field): void
    {
        $input = $action === 'request' ? $this->question() : ['version' => 3, 'message' => 'Descripción.'];
        unset($input[$field]);
        $this->expectException(InvalidArgumentException::class);
        if ($action === 'request') {
            Contract::request($this->context(), $this->admin(), $input, 1, '2026-10-30T11:00:00Z');
        } else {
            Contract::transition($this->context(), $action === 'resolve' ? $this->answered() : $this->requested(),
                $action === 'answer' ? $this->tenant() : $this->admin(), $action, $input, '2026-10-30T14:00:00Z');
        }
    }

    public static function missingInputFields(): iterable
    {
        foreach (['request', 'answer', 'resolve', 'cancel'] as $action) {
            foreach ($action === 'request' ? ['version', 'requirement_key', 'message'] : ['version', 'message'] as $field) {
                yield $action.' '.$field => [$action, $field];
            }
        }
    }

    public function test_changing_original_submission_audit_invalidates_clarification_references(): void
    {
        $declaration = $this->declaration();
        $declaration['submitted_by_user_id'] = 22;
        $context = new Context($this->requestData(), $this->publication(), $declaration, $this->publication());
        $this->expectException(InvalidArgumentException::class);
        Contract::validate($context, $this->requested());
    }

    public function test_collection_accepts_the_limit_and_rejects_an_additional_request(): void
    {
        $state = $this->requested();
        $history = [];
        for ($id = 1; $id <= Contract::MAX_CLARIFICATIONS; $id++) {
            $item = $state;
            $item['id'] = $id;
            $item['events'][0]['message'] = 'Pregunta declarativa '.$id.'.';
            unset($item['content_sha256']);
            $history[] = Context::seal($item);
        }
        self::assertCount(1000, Contract::collection($this->context(), $history));
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->admin(), $this->question(), 1001, '2026-10-30T14:00:00Z', $history);
    }

    #[DataProvider('invalidCollections')]
    public function test_invalid_collection_shapes_are_rejected(array $items): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::collection($this->context(), $items);
    }

    public static function invalidCollections(): iterable
    {
        yield [[null]];
        yield [['object' => []]];
        yield [array_fill(0, 1001, [])];
    }

    public function test_request_id_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Contract::request($this->context(), $this->admin(), $this->question(), 0, '2026-10-30T11:00:00Z');
    }
}
