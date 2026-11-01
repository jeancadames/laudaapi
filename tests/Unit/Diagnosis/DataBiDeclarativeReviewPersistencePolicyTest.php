<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiDeclarativeReviewActor;
use App\Services\Diagnosis\DataBiDeclarativeReviewClosureContract;
use App\Services\Diagnosis\DataBiDeclarativeReviewPersistencePolicy as Policy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;

final class DataBiDeclarativeReviewPersistencePolicyTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    public function test_initial_request_and_its_identical_retry_preserve_the_original_id_actor_and_time(): void
    {
        $context = $this->context();
        $first = Policy::request($context, $this->admin(), $this->question(), 0, 1, '2026-10-30T11:00:00Z', [], null);
        self::assertSame('create', $first['action']);
        $retry = Policy::request($context, $this->admin(), $this->question(), 0, 999, '2026-10-31T11:00:00Z', [$first['snapshot']], null);
        self::assertSame('unchanged', $retry['action']);
        self::assertSame($first['snapshot'], $retry['snapshot']);
    }

    #[DataProvider('operations')]
    public function test_transition_retry_is_identical_and_events_are_append_only(string $action): void
    {
        $context = $this->context();
        $before = $action === 'resolve' ? $this->answered() : $this->requested();
        $actor = $action === 'answer' ? $this->tenant() : $this->admin();
        $input = ['version' => 3, 'message' => 'Explicación declarativa.'];
        $result = Policy::transition($context, $actor, [$before], 1, $action, $input, $before['revision'], '2026-10-30T14:00:00Z', null);
        self::assertSame('append', $result['action']);
        self::assertSame($before['events'], array_slice($result['snapshot']['events'], 0, count($before['events'])));
        foreach ([$before['revision'], $result['snapshot']['revision']] as $expected) {
            $retry = Policy::transition($context, $actor, [$result['snapshot']], 1, $action, $input, $expected, '2026-10-31T14:00:00Z', null);
            self::assertSame('unchanged', $retry['action']);
            self::assertSame($result['snapshot'], $retry['snapshot']);
        }
    }

    public static function operations(): iterable
    {
        yield ['answer'];
        yield ['resolve'];
        yield ['cancel'];
    }

    public function test_an_old_exact_retry_after_resolution_and_closure_does_not_reopen_anything(): void
    {
        $context = $this->context();
        $state = $this->resolved();
        $closure = DataBiDeclarativeReviewClosureContract::close($context, $this->admin(), $this->conclusion(), '2026-10-30T14:00:00Z', [$state]);
        $answer = $state['events'][1];
        $result = Policy::transition($context, $this->tenant(), [$state], 1, 'answer', ['version' => 3, 'message' => $answer['message']],
            1, '2026-10-31T14:00:00Z', $closure);
        self::assertSame('unchanged', $result['action']);
        self::assertSame($state, $result['snapshot']);
        self::assertSame('unchanged', Policy::request($context, $this->admin(), $this->question(), 0, 999,
            '2026-10-31T14:00:00Z', [$state], $closure)['action']);
    }

    #[DataProvider('conflicts')]
    public function test_changed_stale_foreign_or_unauthorized_operations_fail(string $case): void
    {
        $context = $this->context();
        $this->expectException(InvalidArgumentException::class);
        match ($case) {
            'stale answer' => Policy::transition($context, $this->tenant(), [$this->answered()], 1, 'answer', ['version' => 3, 'message' => 'Otra respuesta.'], 1, '2026-10-31T14:00:00Z', null),
            'other actor retry' => Policy::transition($context, DataBiDeclarativeReviewActor::tenant(22, true, 'subscriber', 7, 20, 17, 17, 'admin', true), [$this->answered()], 1, 'answer', ['version' => 3, 'message' => $this->answered()['events'][1]['message']], 1, '2026-10-31T14:00:00Z', null),
            'admin impersonation' => Policy::transition($context, $this->admin(), [$this->requested()], 1, 'answer', ['version' => 3, 'message' => 'Respuesta.'], 1, '2026-10-31T14:00:00Z', null),
            'missing clarification' => Policy::transition($context, $this->tenant(), [$this->requested()], 999, 'answer', ['version' => 3, 'message' => 'Respuesta.'], 1, '2026-10-31T14:00:00Z', null),
            'terminal' => Policy::transition($context, $this->admin(), [$this->resolved()], 1, 'cancel', ['version' => 3, 'message' => 'Cancelar.'], 3, '2026-10-31T14:00:00Z', null),
            'duplicate case' => Policy::request($context, $this->admin(), $this->question('¿QUÉ PERÍODO CUBRE LA ESTRUCTURA?'), 0, 2, '2026-10-31T14:00:00Z', [$this->requested()], null),
            'different admin' => Policy::request($context, DataBiDeclarativeReviewActor::admin(10, 'admin', true), $this->question(), 0, 2, '2026-10-31T14:00:00Z', [$this->requested()], null),
            'wrong version' => Policy::request($context, $this->admin(), [...$this->question(), 'version' => '3'], 0, 2, '2026-10-31T14:00:00Z', [], null),
            'additional identity' => Policy::request($context, $this->admin(), [...$this->question(), 'actor_id' => 10], 0, 2, '2026-10-31T14:00:00Z', [], null),
            'superseded' => Policy::request($this->context(superseded: true), $this->admin(), $this->question(), 0, 2, '2026-10-31T14:00:00Z', [], null),
            'revision negative' => Policy::request($context, $this->admin(), $this->question(), -1, 2, '2026-10-31T14:00:00Z', [], null),
            'revision future' => Policy::request($context, $this->admin(), $this->question(), 4, 2, '2026-10-31T14:00:00Z', [], null),
        };
    }

    public static function conflicts(): iterable
    {
        foreach (['stale answer', 'other actor retry', 'admin impersonation', 'missing clarification', 'terminal',
            'duplicate case', 'different admin', 'wrong version', 'additional identity', 'superseded', 'revision negative', 'revision future'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_rows_and_chained_events_round_trip_without_changing_b1(): void
    {
        $context = $this->context();
        $state = $this->resolved();
        $rows = Policy::eventRows($context, $state);
        self::assertCount(3, $rows);
        self::assertNull($rows[0]['previous_sha256']);
        self::assertSame($rows[0]['content_sha256'], $rows[1]['previous_sha256']);
        self::assertSame($rows[1]['content_sha256'], $rows[2]['previous_sha256']);
        self::assertSame($state['content_sha256'], $rows[2]['projection_sha256']);
        self::assertSame($state, Policy::storedClarification($context, Policy::clarificationRow($state), $rows));
        $reordered = array_reverse(Policy::clarificationRow($state), true);
        $reordered['clarification_snapshot'] = array_reverse($state, true);
        self::assertSame($state, Policy::storedClarification($context, $reordered, $rows));
        self::assertSame($this->requested()['content_sha256'], $rows[0]['projection_sha256']);
    }

    #[DataProvider('tamperedRows')]
    public function test_inconsistent_persisted_projection_and_events_are_never_repaired(string $target, string $key, mixed $value): void
    {
        $state = $this->resolved();
        $row = Policy::clarificationRow($state);
        $events = Policy::eventRows($this->context(), $state);
        if ($target === 'row') {
            $row[$key] = $value;
        } else {
            $events[1][$key] = $value;
        }
        $this->expectException(InvalidArgumentException::class);
        Policy::storedClarification($this->context(), $row, $events);
    }

    public static function tamperedRows(): iterable
    {
        foreach (['id' => 2, 'company_id' => 8, 'transformation_implementation_request_id' => 12,
            'data_bi_information_request_id' => 44, 'data_bi_tenant_declaration_id' => 204,
            'status' => 'requested', 'revision' => '3', 'requirement_key' => 'req_2',
            'question_sha256' => str_repeat('a', 64), 'content_sha256' => str_repeat('a', 64),
            'created_by_user_id' => 10, 'updated_by_user_id' => 10, 'created_at' => '2026-10-31T11:00:00Z',
            'updated_at' => null, 'clarification_snapshot' => null] as $key => $value) {
            yield 'row '.$key => ['row', $key, $value];
        }
        foreach (['data_bi_declaration_clarification_id' => 2, 'sequence' => 3, 'action' => 'cancel', 'message' => 'Cambio.',
            'actor_user_id' => 10, 'actor_kind' => 'lauda_admin', 'occurred_at' => '2026-10-31T11:00:00Z',
            'previous_sha256' => null, 'projection_sha256' => str_repeat('a', 64), 'content_sha256' => str_repeat('a', 64)] as $key => $value) {
            yield 'event '.$key => ['event', $key, $value];
        }
    }

    public function test_missing_or_reordered_event_lists_are_rejected(): void
    {
        $state = $this->resolved();
        $events = Policy::eventRows($this->context(), $state);
        $this->expectException(InvalidArgumentException::class);
        Policy::storedClarification($this->context(), Policy::clarificationRow($state), array_reverse($events));
    }

    public function test_missing_event_is_rejected(): void
    {
        $state = $this->resolved();
        $events = Policy::eventRows($this->context(), $state);
        array_pop($events);
        $this->expectException(InvalidArgumentException::class);
        Policy::storedClarification($this->context(), Policy::clarificationRow($state), $events);
    }

    public function test_closure_with_gaps_and_its_retry_preserve_all_audit_and_references(): void
    {
        $context = $this->context(gaps: true);
        $resolved = $this->resolved($context);
        $result = Policy::close($context, $this->admin(), $this->conclusion(), 0, '2026-10-30T14:00:00Z', [$resolved], null);
        self::assertSame('create', $result['action']);
        self::assertCount(3, $result['snapshot']['declared_gaps']);
        self::assertSame([$resolved['content_sha256']], array_column($result['snapshot']['clarifications'], 'content_sha256'));
        foreach ([0, 1] as $expected) {
            $retry = Policy::close($context, $this->admin(), $this->conclusion(), $expected, '2026-10-31T14:00:00Z', [$resolved], $result['snapshot']);
            self::assertSame('unchanged', $retry['action']);
            self::assertSame($result['snapshot'], $retry['snapshot']);
        }
        self::assertSame($result['snapshot'], Policy::storedClosure($context, Policy::closureRow($result['snapshot']), [$resolved]));
    }

    #[DataProvider('closureConflicts')]
    public function test_closure_rejects_open_work_changed_conclusions_foreign_audit_and_bad_revisions(string $case): void
    {
        $context = $this->context();
        $closure = Policy::close($context, $this->admin(), $this->conclusion(), 0, '2026-10-30T14:00:00Z', [], null)['snapshot'];
        $this->expectException(InvalidArgumentException::class);
        match ($case) {
            'open' => Policy::close($context, $this->admin(), $this->conclusion(), 0, '2026-10-31T14:00:00Z', [$this->requested()], null),
            'changed' => Policy::close($context, $this->admin(), [...$this->conclusion(), 'conclusion' => 'Otra conclusión.'], 0, '2026-10-31T14:00:00Z', [], $closure),
            'other admin' => Policy::close($context, DataBiDeclarativeReviewActor::admin(10, 'admin', true), $this->conclusion(), 0, '2026-10-31T14:00:00Z', [], $closure),
            'stale' => Policy::close($context, $this->admin(), $this->conclusion(), 2, '2026-10-31T14:00:00Z', [], $closure),
            'missing' => Policy::close($context, $this->admin(), $this->conclusion(), 1, '2026-10-31T14:00:00Z', [], null),
            'tampered' => Policy::storedClosure($context, [...Policy::closureRow($closure), 'closed_by_user_id' => 10], []),
        };
    }

    public static function closureConflicts(): iterable
    {
        foreach (['open', 'changed', 'other admin', 'stale', 'missing', 'tampered'] as $case) {
            yield [$case];
        }
    }
}
