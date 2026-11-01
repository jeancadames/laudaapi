<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use App\Services\Diagnosis\DataBiTenantDeclarationPersistencePolicy as Policy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiTenantDeclarationPayloads;

final class DataBiTenantDeclarationPersistencePolicyTest extends TestCase
{
    use BuildsDataBiTenantDeclarationPayloads;

    private function state(string $status = 'draft', int $revision = 1): array
    {
        return ['status' => $status, 'revision' => $revision, 'content_sha256' => str_repeat('a', 64)];
    }

    private function sent(): array
    {
        $snapshot = $this->reviewSnapshot();

        return ['id' => 42, 'version' => 3, 'status' => 'sent', 'company_id' => 7,
            'transformation_implementation_request_id' => 11,
            'sent_at' => '2026-10-30 00:00:00', 'sent_by_user_id' => 9,
            'review_snapshot' => $snapshot, 'content_sha256' => $snapshot['content_sha256']];
    }

    private function stored(string $status = 'draft'): array
    {
        $sent = $this->sent();
        $snapshot = DataBiInformationTenantDeclarationContract::normalize($sent,
            $this->declarationInput(3, $sent['review_snapshot']), $status === 'submitted' ? 'submit' : 'draft');

        return ['data_bi_information_request_id' => 42, 'company_id' => 7,
            'revision' => 1, 'status' => $status, 'declaration_snapshot' => $snapshot,
            'content_sha256' => $snapshot['content_sha256'],
            'created_by_user_id' => 20, 'updated_by_user_id' => 21,
            'submitted_at' => $status === 'submitted' ? '2026-10-30 00:00:01' : null,
            'submitted_by_user_id' => $status === 'submitted' ? 21 : null];
    }

    #[DataProvider('creationModes')]
    public function test_first_creation_has_revision_one(string $mode, string $status): void
    {
        self::assertSame(['action' => 'create', 'revision' => 1, 'status' => $status],
            Policy::decide(null, str_repeat('a', 64), 0, $mode));
    }

    public static function creationModes(): iterable
    {
        yield ['draft', 'draft'];
        yield ['submit', 'submitted'];
    }

    public function test_changed_draft_increments_the_revision(): void
    {
        self::assertSame(['action' => 'update', 'revision' => 4, 'status' => 'draft'],
            Policy::decide($this->state('draft', 3), str_repeat('b', 64), 3, 'draft'));
    }

    #[DataProvider('idempotentRevisions')]
    public function test_identical_retries_leave_the_revision_and_state_unchanged(string $mode, string $status, int $revision, int $expected): void
    {
        self::assertSame(['action' => 'unchanged', 'revision' => $revision, 'status' => $status],
            Policy::decide($this->state($status, $revision), str_repeat('a', 64), $expected, $mode));
    }

    public static function idempotentRevisions(): iterable
    {
        yield 'draft creation retry' => ['draft', 'draft', 1, 0];
        yield 'draft unchanged' => ['draft', 'draft', 3, 3];
        yield 'draft update retry' => ['draft', 'draft', 3, 2];
        yield 'submit creation retry' => ['submit', 'submitted', 1, 0];
        yield 'submit unchanged' => ['submit', 'submitted', 3, 3];
        yield 'submit transition retry' => ['submit', 'submitted', 3, 2];
    }

    public function test_submit_changes_state_even_when_the_content_hash_is_unchanged(): void
    {
        self::assertSame(['action' => 'update', 'revision' => 2, 'status' => 'submitted'],
            Policy::decide($this->state(), str_repeat('a', 64), 1, 'submit'));
    }

    #[DataProvider('conflicts')]
    public function test_conflicts_and_submitted_mutations_are_rejected(?array $current, string $hash, int $expected, string $mode): void
    {
        $this->expectException(InvalidArgumentException::class);
        Policy::decide($current, $hash, $expected, $mode);
    }

    public static function conflicts(): iterable
    {
        $a = str_repeat('a', 64);
        $b = str_repeat('b', 64);
        $draft = ['status' => 'draft', 'revision' => 3, 'content_sha256' => $a];
        $submitted = [...$draft, 'status' => 'submitted'];
        yield 'missing row with prior revision' => [null, $a, 1, 'draft'];
        yield 'stale changed draft' => [$draft, $b, 2, 'draft'];
        yield 'very stale identical draft' => [$draft, $a, 1, 'draft'];
        yield 'future revision' => [$draft, $a, 4, 'draft'];
        yield 'stale submit transition' => [$draft, $a, 2, 'submit'];
        yield 'changed submitted content' => [$submitted, $b, 3, 'submit'];
        yield 'submitted to draft' => [$submitted, $a, 3, 'draft'];
        yield 'very stale submit retry' => [$submitted, $a, 1, 'submit'];
        yield 'negative expected' => [null, $a, -1, 'draft'];
        yield 'overflow expected' => [null, $a, Policy::MAX_REVISION + 1, 'draft'];
        yield 'unknown mode' => [null, $a, 0, 'save'];
        yield 'bad hash' => [null, 'invalid', 0, 'draft'];
        yield 'uppercase hash' => [null, str_repeat('A', 64), 0, 'draft'];
        foreach ([null, 0, '3', Policy::MAX_REVISION + 1] as $i => $value) {
            yield 'invalid stored revision '.$i => [[...$draft, 'revision' => $value], $a, 3, 'draft'];
        }
        yield 'bad stored status' => [[...$draft, 'status' => 'deleted'], $a, 3, 'draft'];
        yield 'bad stored hash' => [[...$draft, 'content_sha256' => null], $a, 3, 'draft'];
        yield 'overflow update' => [[...$draft, 'revision' => Policy::MAX_REVISION], $b, Policy::MAX_REVISION, 'draft'];
    }

    public function test_noop_is_allowed_at_the_revision_limit(): void
    {
        self::assertSame('unchanged', Policy::decide($this->state('draft', Policy::MAX_REVISION),
            str_repeat('a', 64), Policy::MAX_REVISION, 'draft')['action']);
    }

    public function test_published_context_belongs_to_the_company_and_implementation_request(): void
    {
        Policy::assertPublished(['id' => 11, 'company_id' => 7, 'capability_key' => 'data_transformation_bi'], $this->sent(), 3);
        self::assertTrue(true);
    }

    #[DataProvider('invalidPublication')]
    public function test_unpublished_stale_or_foreign_questionnaires_are_rejected(string $target, string $field, mixed $value): void
    {
        $request = ['id' => 11, 'company_id' => 7, 'capability_key' => 'data_transformation_bi'];
        $sent = $this->sent();
        if ($target === 'request') {
            $request[$field] = $value;
        } else {
            $sent[$field] = $value;
        }
        $this->expectException(InvalidArgumentException::class);
        Policy::assertPublished($request, $sent, 3);
    }

    public static function invalidPublication(): iterable
    {
        yield ['request', 'id', 0];
        yield ['request', 'company_id', 0];
        yield ['request', 'capability_key', 'other'];
        yield ['sent', 'company_id', 8];
        yield ['sent', 'transformation_implementation_request_id', 12];
        yield ['sent', 'status', 'draft'];
        yield ['sent', 'sent_at', null];
        yield ['sent', 'sent_at', ' '];
        yield ['sent', 'sent_by_user_id', null];
        yield ['sent', 'sent_by_user_id', 0];
        yield ['sent', 'version', 4];
        yield ['sent', 'version', '3'];
        yield ['sent', 'version', 0];
        yield ['sent', 'version', 10000];
    }

    #[DataProvider('storedStatuses')]
    public function test_stored_snapshot_is_verified_without_mutating_it(string $status): void
    {
        $current = $this->stored($status);
        $original = $current;
        $snapshot = Policy::storedSnapshot($this->sent(), $current);
        self::assertSame($current['declaration_snapshot'], $snapshot);
        self::assertSame($original, $current);
        krsort($current['declaration_snapshot']);
        krsort($current['declaration_snapshot']['declarations'][0]);
        self::assertSame($snapshot, Policy::storedSnapshot($this->sent(), $current));
    }

    public static function storedStatuses(): iterable
    {
        yield ['draft'];
        yield ['submitted'];
    }

    #[DataProvider('corruptedStates')]
    public function test_corrupted_stored_snapshots_and_audits_fail_closed(array $path, mixed $value, string $status = 'draft'): void
    {
        $current = $this->stored($status);
        $target = &$current;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
        unset($target);
        $this->expectException(InvalidArgumentException::class);
        Policy::storedSnapshot($this->sent(), $current);
    }

    public static function corruptedStates(): iterable
    {
        yield [['declaration_snapshot'], null];
        yield [['created_by_user_id'], 0];
        yield [['updated_by_user_id'], null];
        yield [['revision'], 0];
        yield [['revision'], []];
        yield [['status'], 'other'];
        yield [['content_sha256'], str_repeat('b', 64)];
        yield [['data_bi_information_request_id'], 43];
        yield [['company_id'], 8];
        yield [['declaration_snapshot', 'information_request_id'], 43];
        yield [['declaration_snapshot', 'information_request_id'], '42'];
        yield [['declaration_snapshot', 'version'], 2];
        yield [['declaration_snapshot', 'questionnaire_sha256'], str_repeat('b', 64)];
        yield [['declaration_snapshot', 'files_received'], true];
        yield [['declaration_snapshot', 'data_inspected'], true];
        yield [['declaration_snapshot', 'extra'], 'injected'];
        yield [['declaration_snapshot', 'declarations', 0, 'notes'], 'Alterado'];
        yield [['submitted_at'], '2026-10-30 00:00:00'];
        yield [['submitted_by_user_id'], 21];
        yield [['submitted_at'], null, 'submitted'];
        yield [['submitted_by_user_id'], null, 'submitted'];
        yield [['declaration_snapshot', 'declarations', 0, 'availability'], 'pending', 'submitted'];
    }
}
