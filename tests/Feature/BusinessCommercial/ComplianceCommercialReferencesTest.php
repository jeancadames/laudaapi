<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class ComplianceCommercialReferencesTest extends BusinessCommercialTransportTestCase
{
    private function compliance(array $input, int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/cumplimiento-reference.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['action' => 'receive'], JSON_THROW_ON_ERROR));
        $process->setTimeout(120)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], $process->getOutput().' '.@file_get_contents($this->runtime.'/cumplimiento/storage/logs/application.log'));

        return $result;
    }

    private function setupSource(bool $consume = true): array
    {
        $this->startServer();
        $fiscal = $this->compliance(['action' => 'setup'])['fiscal'];
        if ($consume) {
            $operation = $this->operation('origin:purchase', ['fiscal' => $fiscal]);
            $this->exchange('bys', 'reserve', $operation);
            $this->exchange('bys', 'commit', ['operation_key' => $operation['operation_key']]);
        }

        return $fiscal;
    }

    public function test_authenticated_source_versions_and_ecf_webhook_reference_without_another_consumption_or_approval(): void
    {
        $this->setupSource();
        $first = $this->compliance([]);
        $this->assertSame('review_pending', $first['body']['status']);
        $this->assertSame(0, $first['events']);
        $this->assertNotNull($first['references'][0]['referenced_at']);
        $this->assertSame(1, $this->compliance([])['sources']);
        $second = $this->compliance(['version' => 2]);
        $this->assertSame(2, $second['sources']);
        $this->assertSame(0, $second['events']);
        $webhook = $this->compliance(['action' => 'webhook'], 202);
        $this->assertSame(1, $webhook['documents']);
        $this->assertSame(0, $webhook['events']);
        $this->assertCount(3, $webhook['references']);
        foreach ($webhook['operations'] as $operation) {
            $this->assertSame(['action' => 'reference', 'state' => 'referenced'], $operation);
        }
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_unknown_origin_stays_pending_and_can_be_reconciled_after_origin_commits(): void
    {
        $fiscal = $this->setupSource(false);
        $result = $this->compliance([]);
        $this->assertSame(409, $result['references'][0]['last_error_status']);
        $this->assertNull($result['references'][0]['referenced_at']);
        $this->assertSame(1, $result['sources']);
        $this->assertSame(0, DB::table('business_usage_documents')->count());
        $operation = $this->operation('late:origin', ['fiscal' => $fiscal]);
        $this->exchange('bys', 'reserve', $operation);
        $this->exchange('bys', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame(0, $this->compliance(['action' => 'reconcile', 'due' => true])['exit']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_lost_reply_and_missing_first_dispatch_recover_from_durable_native_source(): void
    {
        $this->setupSource();
        $first = $this->compliance(['mode' => 'lost_reference']);
        $this->assertNull($first['references'][0]['referenced_at']);
        $this->assertSame(0, $this->compliance(['action' => 'reconcile', 'due' => true])['exit']);
        $second = $this->compliance(['version' => 2, 'enabled' => false]);
        $this->assertCount(1, $second['references']);
        $discovered = $this->compliance(['action' => 'reconcile', 'discover' => 'bys_source']);
        $this->assertSame(0, $discovered['exit']);
        $this->assertCount(2, $discovered['references']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public static function crashes(): array
    {
        return [['crash_after_stage', 0], ['crash_after_prepare', 1]];
    }

    #[DataProvider('crashes')]
    public function test_crash_before_first_transport_is_recoverable(string $mode, int $operations): void
    {
        $this->setupSource();
        $first = $this->compliance(['mode' => $mode]);
        $this->assertSame(1, $first['sources']);
        $this->assertNull($first['references'][0]['commercial_operation_id']);
        $this->assertCount($operations, $first['operations']);
        if ($operations) {
            $this->assertSame('prepared', $first['operations'][0]['state']);
        }
        $recovered = $this->compliance(['action' => 'reconcile']);
        $this->assertSame(0, $recovered['exit']);
        $this->assertNotNull($recovered['references'][0]['referenced_at']);
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
    }

    public function test_reference_storage_failure_after_commit_preserves_native_success_and_is_recoverable(): void
    {
        $this->setupSource();
        $result = $this->compliance(['mode' => 'reference_storage_fault']);
        $this->assertSame('review_pending', $result['body']['status']);
        $this->assertSame(1, $result['sources']);
        $this->assertSame('prepared', $result['operations'][0]['state']);
        $this->assertSame(0, $this->compliance(['action' => 'reconcile', 'clear_storage_fault' => true])['exit']);
    }

    public function test_pending_reference_cannot_follow_a_changed_company_binding(): void
    {
        $this->setupSource();
        $this->compliance(['mode' => 'crash_after_stage']);
        $db = new \PDO('sqlite:'.$this->runtime.'/cumplimiento/database.sqlite');
        $db->exec('UPDATE business_identity_links SET central_company_id = 999');
        $result = $this->compliance(['action' => 'reconcile']);
        $this->assertSame(1, $result['exit']);
        $this->assertSame(403, $result['references'][0]['last_error_status']);
        $this->assertNull($result['references'][0]['referenced_at']);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'referenced')->count());
    }

    public function test_invalid_authentication_does_not_stage_a_reference(): void
    {
        $this->setupSource();
        $bad = $this->compliance(['bad_auth' => true], 401);
        $this->assertSame(0, $bad['sources']);
        $this->assertSame([], $bad['references']);
    }
}
