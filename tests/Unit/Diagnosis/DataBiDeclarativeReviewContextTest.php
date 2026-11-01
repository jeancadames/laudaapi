<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiClarificationContract;
use App\Services\Diagnosis\DataBiDeclarativeReviewActor as Actor;
use App\Services\Diagnosis\DataBiDeclarativeReviewContext as Context;
use App\Services\Diagnosis\DataBiInformationTenantAccessPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;

final class DataBiDeclarativeReviewContextTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    public function test_reference_is_derived_from_the_immutable_submission_and_version(): void
    {
        $context = $this->context();
        $declaration = $this->declaration();
        self::assertSame(['implementation_request_id' => 11, 'company_id' => 7, 'information_request_id' => 43,
            'questionnaire_version' => 3, 'questionnaire_sha256' => $this->publication()['content_sha256'],
            'questionnaire_published_at' => '2026-10-30T09:00:00Z', 'questionnaire_published_by_user_id' => 9,
            'declaration_id' => 203, 'declaration_revision' => 5, 'declaration_sha256' => $declaration['content_sha256'],
            'declaration_submitted_at' => '2026-10-30T10:00:00Z', 'declaration_submitted_by_user_id' => 21], $context->reference());
        self::assertTrue($context->isCurrent());
        self::assertSame('2026-10-30T10:00:00Z', $context->submittedAt());
        $copy = $context->declarations();
        $copy[0]['availability'] = 'unavailable';
        self::assertSame('available', $context->declarations()[0]['availability']);
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_submissions_publications_audit_and_foreign_references_are_rejected(string $target, string $field, mixed $value): void
    {
        $data = ['request' => $this->requestData(), 'published' => $this->publication(),
            'declaration' => $this->declaration(), 'latest' => $this->publication()];
        $data[$target][$field] = $value;
        $this->expectException(InvalidArgumentException::class);
        new Context($data['request'], $data['published'], $data['declaration'], $data['latest']);
    }

    public static function invalidContexts(): iterable
    {
        yield ['request', 'capability_key', 'other'];
        yield ['request', 'company_id', 8];
        yield ['published', 'status', 'draft'];
        yield ['published', 'version', '3'];
        yield ['published', 'sent_by_user_id', null];
        yield ['published', 'sent_at', 'not a date'];
        yield ['latest', 'company_id', 8];
        yield ['latest', 'transformation_implementation_request_id', 12];
        yield ['latest', 'version', 2];
        yield ['latest', 'sent_at', '2026-02-30 00:00:00'];
        yield ['latest', 'id', 100];
        yield ['latest', 'sent_at', '2026-10-30 09:00:01'];
        yield ['latest', 'sent_by_user_id', 10];
        yield ['declaration', 'id', 0];
        yield ['declaration', 'status', 'draft'];
        yield ['declaration', 'company_id', 8];
        yield ['declaration', 'data_bi_information_request_id', 44];
        yield ['declaration', 'content_sha256', str_repeat('a', 64)];
        yield ['declaration', 'submitted_by_user_id', null];
        yield ['declaration', 'created_by_user_id', null];
        yield ['declaration', 'updated_by_user_id', null];
        yield ['declaration', 'submitted_at', '2026-10-30 08:59:59'];
        yield ['declaration', 'submitted_at', '2026-02-30 11:00:00'];
        yield ['declaration', 'submitted_at', "2026-10-30 10:00:00\0"];
        yield ['declaration', 'revision', '5'];
    }

    public function test_no_submission_cannot_form_a_review_context(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Context($this->requestData(), $this->publication(), null, $this->publication());
    }

    public function test_eloquent_utc_date_serialization_is_compatible_without_loading_models(): void
    {
        $publication = $this->publication();
        $declaration = $this->declaration();
        $publication['sent_at'] = '2026-10-30T09:00:00.000000Z';
        $declaration['submitted_at'] = '2026-10-30T10:00:00.000000Z';
        $context = new Context($this->requestData(), $publication, $declaration, $publication);
        self::assertSame($this->context()->reference(), $context->reference());
        self::assertSame('2026-10-30T10:00:00Z', Context::instant('2026-10-30T10:00:00+00:00'));
    }

    public function test_nonzero_fractional_seconds_are_not_silently_discarded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Context::instant('2026-10-30T10:00:00.123456Z');
    }

    public function test_modified_snapshot_is_rejected_even_with_unchanged_stored_hash(): void
    {
        $declaration = $this->declaration();
        $declaration['declaration_snapshot']['declarations'][0]['notes'] = 'Otra observación.';
        $this->expectException(InvalidArgumentException::class);
        new Context($this->requestData(), $this->publication(), $declaration, $this->publication());
    }

    #[DataProvider('invalidAdmins')]
    public function test_admin_identity_requires_verified_global_admin(int $id, string $role, bool $verified): void
    {
        $this->expectException(InvalidArgumentException::class);
        Actor::admin($id, $role, $verified);
    }

    public static function invalidAdmins(): iterable
    {
        yield [0, 'admin', true];
        yield [9, 'user', true];
        yield [9, 'subscriber', true];
        yield [9, 'admin', false];
    }

    #[DataProvider('invalidTenants')]
    public function test_tenant_identity_requires_real_subscriber_and_active_authorized_membership(array $changes): void
    {
        $args = array_replace([21, true, 'subscriber', 7, 20, 17, 17, 'admin', true], $changes);
        $this->expectException(InvalidArgumentException::class);
        Actor::tenant(...$args);
    }

    public static function invalidTenants(): iterable
    {
        yield [[0 => 0]];
        yield [[1 => false]];
        yield [[2 => 'admin']];
        yield [[3 => 0]];
        yield [[5 => 0]];
        yield [[6 => 18]];
        yield [[7 => 'member']];
        yield [[7 => null]];
        yield [[8 => false]];
        yield 'owner without membership' => [[0 => 20, 8 => false]];
        yield 'platform admin with owner membership' => [[0 => 20, 2 => 'admin', 7 => 'owner']];
    }

    public function test_active_company_owner_is_supported_by_the_existing_tenant_policy(): void
    {
        $actor = Actor::tenant(20, true, 'subscriber', 7, 20, 17, 17, 'member', true);
        $actor->assertScope($this->context());
        self::assertSame('tenant', $actor->kind);
        self::assertSame(20, $actor->userId);
    }

    public function test_other_company_actor_cannot_act_on_the_context(): void
    {
        $actor = Actor::tenant(21, true, 'subscriber', 8, 20, 18, 18, 'admin', true);
        $this->expectException(InvalidArgumentException::class);
        $actor->assertScope($this->context());
    }

    #[DataProvider('authorizedTenantRoles')]
    public function test_an_authorized_t4cd_actor_can_answer_without_changing_the_submission(string $globalRole, string $membershipRole): void
    {
        self::assertTrue(DataBiInformationTenantAccessPolicy::mayRead(21, 20, $membershipRole, true));
        $actor = Actor::tenant(21, true, $globalRole, 7, 20, 17, 17, $membershipRole, true);
        $context = $this->context(gaps: true);
        $declarations = $context->declarations();
        $reference = $context->reference();
        $question = $this->requested($context);
        $answer = DataBiClarificationContract::transition($context, $question, $actor, 'answer', [
            'version' => 3, 'message' => 'Se aclara el alcance de la estructura declarada.',
        ], '2026-10-30T12:00:00Z');

        self::assertSame('tenant', $actor->kind);
        self::assertSame('answered', $answer['status']);
        self::assertSame(2, $answer['revision']);
        self::assertSame(21, $answer['events'][1]['actor_user_id']);
        self::assertSame('tenant', $answer['events'][1]['actor_kind']);
        self::assertSame($question['events'], array_slice($answer['events'], 0, 1));
        self::assertSame($reference, $answer['reference']);
        self::assertSame($reference, $context->reference());
        self::assertSame($declarations, $context->declarations());
    }

    public static function authorizedTenantRoles(): iterable
    {
        foreach (['subscriber', 'user'] as $globalRole) {
            foreach (['owner', 'admin'] as $membershipRole) {
                yield $globalRole.' / '.$membershipRole => [$globalRole, $membershipRole];
            }
        }
    }

    #[DataProvider('tenantMembershipCompatibility')]
    public function test_membership_authorization_matches_the_existing_t4cd_policy(string $globalRole, int $companyOwnerId, ?string $membershipRole, bool $active): void
    {
        $allowed = DataBiInformationTenantAccessPolicy::mayRead(21, $companyOwnerId, $membershipRole, $active);
        if (! $allowed) {
            $this->expectException(InvalidArgumentException::class);
        }
        $actor = Actor::tenant(21, true, $globalRole, 7, $companyOwnerId, 17, 17, $membershipRole, $active);
        $actor->assertScope($this->context());
        self::assertSame('tenant', $actor->kind);
        self::assertSame(21, $actor->userId);
    }

    public static function tenantMembershipCompatibility(): iterable
    {
        foreach (['subscriber', 'user'] as $globalRole) {
            foreach ([20, 21] as $companyOwnerId) {
                foreach (['owner', 'admin', 'member', 'billing', 'unknown', null] as $membershipRole) {
                    foreach ([true, false] as $active) {
                        $name = $globalRole.' / owner='.$companyOwnerId.' / role='.($membershipRole ?? 'null').' / active='.(int) $active;
                        yield $name => [$globalRole, $companyOwnerId, $membershipRole, $active];
                    }
                }
            }
        }
    }

    public function test_no_additional_tenant_global_role_allowlist_is_introduced(): void
    {
        self::assertTrue(DataBiInformationTenantAccessPolicy::mayRead(21, 20, 'admin', true));
        $actor = Actor::tenant(21, true, 'legacy_tenant_role', 7, 20, 17, 17, 'admin', true);
        $actor->assertScope($this->context());
        self::assertSame('tenant', $actor->kind);
    }

    public function test_existing_t4b_text_filter_and_unicode_limits_are_reused(): void
    {
        self::assertSame("Línea uno\nLínea dos", $this->context()->text("  Línea uno\r\nLínea dos  "));
        self::assertSame(str_repeat('á', 800), $this->context()->text(str_repeat('á', 800)));
    }

    #[DataProvider('unsafeTexts')]
    public function test_empty_long_credential_file_and_record_inputs_are_rejected(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->context()->text($value);
    }

    public static function unsafeTexts(): iterable
    {
        foreach (['', '  ', str_repeat('á', 801), null, [], 123, "foo\tbar", "bad\xFF",
            'password=hidden', 'Bearer hidden', 'https://user:pass@example.test', 'mysql:host=example.test',
            'data:text/csv;base64,hidden', '{"customer":"real"}', '[123,456]',
            'SELECT customer FROM clients', "cliente,importe\nEmpresa,100\n", '-----BEGIN PRIVATE KEY-----'] as $i => $value) {
            yield 'unsafe '.$i => [$value];
        }
    }
}
