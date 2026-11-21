<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class PosBranchCapacityAuditTest extends BusinessCommercialTransportTestCase
{
    private function pos(array $input = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/pos-branches-audit.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(90)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], json_encode($result));
        if (($input['mode'] ?? '') !== 'rebind') {
            $this->assertTrue($result['unchanged']);
        }

        return $result;
    }

    private function receiver(): PDO
    {
        return new PDO('sqlite:'.$this->runtime.'/pos/database.sqlite');
    }

    public function test_signed_contract_is_compared_to_existing_branches_without_writes_or_document_consumption(): void
    {
        $this->startServer();
        $result = $this->pos();
        $report = $result['report'];
        $this->assertSame(1, $result['http_calls']);
        $this->assertSame($this->contract->id, $report['contract_id']);
        $this->assertSame($this->contract->snapshot_hash, $report['snapshot_hash']);
        $limit = $this->contract->snapshot['capacity']['branches'];
        $this->assertSame(['comparison_basis' => 'active_branches', 'limit' => $limit, 'total' => 2,
            'active' => 1, 'inactive' => 1, 'remaining' => $limit - 1, 'excess' => 0], $report['branches']);
        $this->assertSame('within_capacity', $report['status']);
        $this->assertFalse($report['enforced']);
        $command = $this->pos(['action' => 'command']);
        $this->assertSame(0, $command['code']);
        $this->assertSame($report['branches'], json_decode($command['output'], true, flags: JSON_THROW_ON_ERROR)['branches']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $this->assertSame(0, (int) $this->receiver()->query('SELECT count(*) FROM pos_business_companies')->fetchColumn());
    }

    public function test_excess_is_reported_without_disabling_or_deleting_existing_branches(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['branches'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $report = $this->pos()['report'];
        $this->assertSame('over_capacity', $report['status']);
        $this->assertSame(1, $report['branches']['excess']);
        $this->assertSame(0, $report['branches']['remaining']);
        $this->assertSame(1, $this->pos(['action' => 'command'])['code']);
        $this->assertSame(1, (int) $this->receiver()->query('SELECT is_active FROM branches WHERE id = 501')->fetchColumn());
    }

    #[DataProvider('invalidResponses')]
    public function test_missing_trust_never_becomes_an_unmanaged_or_zero_usage_report(string $mode, int $status): void
    {
        $this->startServer();
        $result = $this->pos(['mode' => $mode], $status);
        $this->assertArrayNotHasKey('report', $result);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public static function invalidResponses(): array
    {
        return [['unavailable', 503], ['corrupted', 503], ['disabled', 503], ['wrong_service', 503], ['transaction', 409], ['rebind', 409]];
    }

    public function test_unlinked_native_company_has_no_invented_limit_and_known_ownership_cannot_fall_back(): void
    {
        $this->startServer();
        $this->pos();
        $db = $this->receiver();
        $link = $db->query('SELECT id FROM business_identity_links')->fetchColumn();
        $db->exec('DELETE FROM business_identity_links');
        $result = $this->pos(['mode' => 'disabled']);
        $this->assertSame(0, $result['http_calls']);
        $this->assertSame('unlinked', $result['report']['status']);
        $this->assertNull($result['report']['managed']);
        $this->assertNull($result['report']['branches']['limit']);
        $this->assertNull($result['report']['branches']['remaining']);
        $this->assertSame(1, $result['report']['branches']['active']);
        $db->prepare('INSERT INTO pos_business_companies (company_id, identity_link_id, central_contract_id, created_at) VALUES (501, ?, ?, CURRENT_TIMESTAMP)')
            ->execute([$link, $this->contract->id]);
        $this->pos([], 409);
    }

    public function test_revocation_inactive_company_and_expired_coverage_are_not_reported_ready(): void
    {
        $this->startServer();
        $this->pos();
        $db = $this->receiver();
        $db->exec('UPDATE business_identity_links SET active = 0');
        $this->pos([], 403);
        $db->exec('UPDATE business_identity_links SET active = 1');
        $db->exec('UPDATE companies SET is_active = 0 WHERE id = 501');
        $this->assertSame('company_inactive', $this->pos()['report']['status']);
        $db->exec('UPDATE companies SET is_active = 1 WHERE id = 501');
        DB::table('subscription_items')->update(['status' => 'cancelled']);
        $report = $this->pos()['report'];
        $this->assertSame('coverage_inactive', $report['status']);
        $this->assertFalse($report['coverage_active']);
        $this->assertSame(1, $report['branches']['active']);
    }

    public function test_existing_ownership_must_match_the_signed_contract_and_binding(): void
    {
        $this->startServer();
        $this->pos();
        $db = $this->receiver();
        $link = $db->query('SELECT id FROM business_identity_links')->fetchColumn();
        $db->prepare('INSERT INTO pos_business_companies (company_id, identity_link_id, central_contract_id, created_at) VALUES (501, ?, ?, CURRENT_TIMESTAMP)')
            ->execute([$link, $this->contract->id]);
        $this->assertSame('within_capacity', $this->pos()['report']['status']);
        $db->exec('UPDATE pos_business_companies SET central_contract_id = central_contract_id + 1');
        $this->pos([], 409);
    }

    public function test_only_an_explicit_central_response_marks_a_linked_company_unmanaged(): void
    {
        $link = DB::table('business_identity_links')->where('service', 'pos')->value('id');
        DB::table('business_contract_items')->where('identity_link_id', $link)->delete();
        DB::table('business_identity_links')->where('id', $link)->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startServer();
        $result = $this->pos();
        $this->assertSame(1, $result['http_calls']);
        $this->assertSame('unmanaged', $result['report']['status']);
        $this->assertFalse($result['report']['managed']);
        $this->assertNull($result['report']['branches']['limit']);
    }

    public function test_invalid_contract_capacity_and_command_arguments_fail_explicitly(): void
    {
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['branches'] = 'unlimited';
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->startServer();
        $this->pos([], 503);
        foreach ([[], ['--company' => 0], ['--company' => 'all']] as $options) {
            $command = $this->pos(['action' => 'command', 'options' => $options]);
            $this->assertSame(2, $command['code']);
            $this->assertSame(0, $command['http_calls']);
        }
        $missing = $this->pos(['action' => 'command', 'options' => ['--company' => 999]]);
        $this->assertSame(1, $missing['code']);
        $this->assertSame(0, $missing['http_calls']);
        $error = $this->pos(['action' => 'command']);
        $this->assertSame(1, $error['code']);
        $this->assertStringContainsString('503', $error['output']);
    }
}
