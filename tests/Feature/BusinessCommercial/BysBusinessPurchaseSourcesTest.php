<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BusinessCommercialTransportTestCase;

final class BysBusinessPurchaseSourcesTest extends BusinessCommercialTransportTestCase
{
    private function startBys(int $localLimit = 0): void
    {
        DB::table('business_identity_links')->where('service', 'bys')->update(['local_company_id' => 1]);
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->update(['local_company_id' => 1]);
        $this->startServer();
        $this->bys(['action' => 'setup', 'local_limit' => $localLimit]);
    }

    private function worker(array $input): Process
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/bys-purchase.php'), $this->runtime]);
        $process->setInput(json_encode($input + ['action' => 'request', 'imports_enabled' => true, 'inbox_enabled' => true], JSON_THROW_ON_ERROR));

        return $process->setTimeout(90);
    }

    private function bys(array $input, int $status = 200): array
    {
        $process = $this->worker($input)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], $process->getOutput().' '.@file_get_contents($this->runtime.'/bys/storage/logs/application.log'));
        if (isset($result['context_clear'])) {
            $this->assertTrue($result['context_clear']);
        }

        return $result;
    }

    private function db(): \PDO
    {
        return new \PDO('sqlite:'.$this->runtime.'/bys/database.sqlite');
    }

    private function payload(string $ncf = 'E310000000001'): array
    {
        return ['supplier_id' => 1, 'document_type' => 'local_fiscal_invoice', 'purchase_kind' => 'service', 'ncf' => $ncf,
            'issue_date' => '2026-11-17', 'currency' => 'DOP', 'exchange_rate' => '1', 'is_credit' => false,
            'lines' => [['description' => 'Servicio revisado', 'quantity' => '1', 'unit_price_original' => '100', 'tax_rate' => '18']]];
    }

    private function import(string $format = 'xml'): array
    {
        $payload = $this->payload();
        $batch = $this->bys(['path' => '/bys/document-imports', 'payload' => [], 'upload_format' => $format,
            'upload_payload' => $payload, 'upload_rows' => [['document_key' => 'ONE', ...$payload, 'is_credit' => '0', ...$payload['lines'][0]]]], 201)['body'];

        return ['path' => '/bys/document-imports/'.$batch['id'].'/confirm', 'payload' => ['reviewed' => true,
            'rows' => [['id' => $batch['rows'][0]['id'], 'payload' => $payload]]]];
    }

    private function inbox(): array
    {
        $inbox = $this->bys(['action' => 'prepare_inbox'])['body'];

        return ['path' => '/bys/ecf-inbox/'.$inbox['id'].'/confirm', 'payload' => ['expected_revision' => $inbox['revision'],
            'reviewed' => true, 'confirmed_recipient' => true, 'reason' => 'Evidencia sintética revisada por el operador.', 'payload' => $this->payload()]];
    }

    private function counts(int $documents, int $consumed): void
    {
        $this->assertSame($documents, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame($consumed, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
    }

    private function attachments(int $count): void
    {
        $rows = $this->db()->query('SELECT path, sha256 FROM bys_purchase_document_attachments')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount($count, $rows);
        foreach ($rows as $row) {
            $this->assertSame($row['sha256'], hash_file('sha256', $this->runtime.'/bys/storage/app/private/'.$row['path']));
        }
    }

    private function knownFiscal(): void
    {
        $operation = (string) Str::uuid();
        $this->client('ecf', 'reserve', ['operation_key' => $operation,
            'document' => ['source_reference' => 'synthetic:known-source', 'fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]]);
        $this->client('ecf', 'commit', ['operation_key' => $operation]);
    }

    public static function formats(): array
    {
        return [['xml'], ['pdf'], ['csv'], ['xlsx']];
    }

    #[DataProvider('formats')]
    public function test_upload_and_review_preserve_the_native_format_and_consume_only_on_confirmation(string $format): void
    {
        $this->startBys();
        $input = $this->import($format);
        $this->counts(0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->knownFiscal();
        $first = $this->bys($input)['body'];
        $this->assertSame('imported', $first['rows'][0]['status']);
        $this->bys($input + ['mode' => 'lost_snapshot', 'imports_enabled' => false, 'enabled' => false]);
        $this->counts(1, 1);
        $this->attachments(in_array($format, ['xml', 'pdf'], true) ? 1 : 0);
        $this->assertSame('draft', $this->db()->query('SELECT status FROM bys_purchase_documents')->fetchColumn());
    }

    public function test_inbox_confirmation_keeps_review_originals_and_known_fiscal_consumption(): void
    {
        $this->startBys();
        $input = $this->inbox();
        $this->counts(0, 0);
        $this->knownFiscal();
        $first = $this->bys($input)['body'];
        $this->assertSame('linked', $first['document']['status']);
        $this->bys($input + ['mode' => 'lost_snapshot', 'inbox_enabled' => false, 'enabled' => false]);
        $this->counts(1, 1);
        $this->attachments(1);
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_accounts_payable_documents')->fetchColumn());
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_receipts')->fetchColumn());
    }

    public function test_inbox_links_a_historical_purchase_without_reserving_or_changing_it(): void
    {
        $this->startBys(5);
        $document = $this->bys(['enabled' => false, 'imports_enabled' => false, 'inbox_enabled' => false, 'payload' => $this->payload()], 201)['body'];
        $before = $this->db()->query('SELECT * FROM bys_purchase_documents')->fetchAll(\PDO::FETCH_ASSOC);
        $input = $this->inbox();
        $result = $this->bys($input + ['mode' => 'lost_snapshot'])['body'];
        $this->assertSame($document['id'], $result['document']['purchase_document_id']);
        $this->assertSame($before, $this->db()->query('SELECT * FROM bys_purchase_documents')->fetchAll(\PDO::FETCH_ASSOC));
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->assertSame(0, (int) $this->db()->query('SELECT count(*) FROM bys_business_purchase_intents')->fetchColumn());
    }

    public function test_xml_then_inbox_links_one_purchase_and_preserves_later_source_changes(): void
    {
        $this->startBys();
        $this->bys($this->import());
        $input = $this->inbox();
        $this->bys($input);
        $this->bys($input);
        $this->counts(1, 1);
        $this->attachments(1);
        $this->assertSame(1, DB::table('business_usage_reservations')->count());
        $this->db()->exec("UPDATE bys_ecf_documents SET revision = 2, status = 'changed'");
        $input['payload']['expected_revision'] = 2;
        $this->bys($input, 409);
        $this->counts(1, 1);
    }

    public static function channels(): array
    {
        return [['import'], ['inbox']];
    }

    #[DataProvider('channels')]
    public function test_quota_rejection_preserves_review_state_and_can_retry_the_same_source(string $channel): void
    {
        $this->startBys();
        $input = $this->$channel();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $table = $channel === 'import' ? 'bys_document_import_rows' : 'bys_ecf_documents';
        $before = $this->db()->query('SELECT status FROM '.$table)->fetchColumn();
        $this->bys($input, 429);
        $this->assertSame($before, $this->db()->query('SELECT status FROM '.$table)->fetchColumn());
        $this->counts(0, 0);
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input);
        $this->counts(1, 1);
    }

    public static function losses(): array
    {
        return [['import', 'lost_reserve', 503], ['inbox', 'lost_reserve', 503], ['import', 'lost_commit', 200], ['inbox', 'lost_commit', 200]];
    }

    #[DataProvider('losses')]
    public function test_lost_replies_reconcile_without_recopying_or_deleting_originals(string $channel, string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->$channel();
        $this->bys($input + ['mode' => $mode], $status);
        $count = $mode === 'lost_commit' ? 1 : 0;
        $this->attachments($count);
        $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
        $this->bys($input);
        $this->counts(1, 1);
        $this->attachments(1);
    }

    #[DataProvider('channels')]
    public function test_failure_after_copy_rolls_back_native_records_and_compensates_files(string $channel): void
    {
        $this->startBys();
        $input = $this->$channel();
        $root = $this->runtime.'/bys/storage/app/private/';
        $files = fn () => iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)));
        $before = array_keys($files());
        $this->bys($input + ['mode' => 'rollback_after_file'], 500);
        $this->counts(0, 0);
        $this->attachments(0);
        $this->assertSame($before, array_keys($files()));
        $this->assertSame('retryable', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        $this->db()->exec('DROP TRIGGER synthetic_source_rollback');
        $this->bys($input);
        $this->counts(1, 1);
        $this->attachments(1);
    }

    public static function revocations(): array
    {
        return [['import', 'revoke_user', 403], ['inbox', 'revoke_user', 403], ['import', 'change_batch', 409], ['inbox', 'change_inbox', 409], ['inbox', 'revoke_ecf', 403]];
    }

    #[DataProvider('revocations')]
    public function test_authority_and_source_are_rechecked_after_reservation(string $channel, string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->$channel();
        $this->bys($input + ['mode' => $mode], $status);
        $this->counts(0, 0);
        $this->attachments(0);
        $this->assertSame(0, DB::table('business_usage_reservations')->where('state', 'reserved')->count());
    }

    public function test_import_batch_keeps_partial_commits_and_resumes_remaining_rows(): void
    {
        $this->startBys();
        $payload = $this->payload();
        $rows = [];
        foreach ([1, 2] as $number) {
            $rows[] = ['document_key' => 'ROW-'.$number, ...$payload, 'is_credit' => '0', 'ncf' => 'E31000000000'.$number, ...$payload['lines'][0]];
        }
        $batch = $this->bys(['path' => '/bys/document-imports', 'payload' => [], 'upload_format' => 'csv', 'upload_rows' => $rows], 201)['body'];
        $input = ['path' => '/bys/document-imports/'.$batch['id'].'/confirm', 'payload' => ['reviewed' => true, 'rows' => []]];
        foreach ($batch['rows'] as $index => $row) {
            $input['payload']['rows'][] = ['id' => $row['id'], 'payload' => $this->payload('E31000000000'.($index + 1))];
        }
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 1;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input, 429);
        $this->counts(1, 1);
        $snapshot['capacity']['documents'] = 2;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input);
        $this->counts(2, 2);
    }

    public function test_concurrent_existing_candidate_cancels_new_admission_and_links_on_review(): void
    {
        $this->startBys();
        $input = $this->inbox();
        $this->bys($input + ['mode' => 'concurrent_manual'], 409);
        $this->counts(1, 1);
        $this->bys($input);
        $this->counts(1, 1);
        $this->assertSame(1, DB::table('business_usage_reservations')->where('state', 'released')->count());
    }

    public static function interruptions(): array
    {
        return [['pause_after_begin', 409], ['pause_after_native_commit', 200]];
    }

    #[DataProvider('interruptions')]
    public function test_interrupted_workers_recover_only_the_durable_native_result(string $mode, int $status): void
    {
        $this->startBys();
        $input = $this->import();
        $worker = $this->worker($input + ['mode' => $mode]);
        $worker->start();
        try {
            $deadline = microtime(true) + 35;
            while (! is_file($this->runtime.'/bys-paused')) {
                if (! $worker->isRunning() || microtime(true) > $deadline) {
                    $this->fail('No pause: '.$worker->getOutput().$worker->getErrorOutput());
                }
                usleep(10000);
            }
            if ($mode === 'pause_after_begin') {
                $this->bys($input, 409);
                $this->db()->exec("UPDATE bys_business_purchase_intents SET leased_until = datetime('now', '-1 minute')");
            }
            $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
            $this->bys($input);
            touch($this->runtime.'/bys-resume');
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($status, $result['status'], $worker->getOutput());
            $this->counts(1, 1);
            $this->attachments(1);
            $this->assertSame('confirmed', $this->db()->query('SELECT state FROM bys_business_purchase_intents')->fetchColumn());
        } finally {
            touch($this->runtime.'/bys-resume');
            $worker->stop(1);
        }
    }

    #[DataProvider('channels')]
    public function test_each_source_flag_admits_business_even_when_manual_creation_is_disabled(string $channel): void
    {
        $this->startBys();
        $input = $this->$channel();
        $this->bys($input + ['enabled' => false]);
        $this->counts(1, 1);
        $this->attachments(1);
    }

    #[DataProvider('channels')]
    public function test_standalone_keeps_source_workflow_and_its_native_quota(string $channel): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'bys')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startBys(2);
        $input = $this->$channel();
        $input += ['enabled' => false, $channel === 'import' ? 'imports_enabled' : 'inbox_enabled' => false];
        $this->bys($input);
        $this->bys($input);
        $this->assertSame(1, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame(1, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->attachments(1);
    }

    #[DataProvider('channels')]
    public function test_flags_review_session_csrf_and_company_are_required(string $channel): void
    {
        $this->startBys();
        $input = $this->$channel();
        $this->bys($input + ['guest' => true], 401);
        $this->bys($input + ['bad_csrf' => true], 419);
        $this->bys($input + ['company' => 999], 404);
        $this->bys($input + [$channel === 'import' ? 'imports_enabled' : 'inbox_enabled' => false], 503);
        $input['payload']['reviewed'] = false;
        $this->bys($input, 422);
        $this->counts(0, 0);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
    }

    public function test_existing_link_waits_for_the_same_source_pending_admission(): void
    {
        $this->startBys();
        $input = $this->inbox();
        $worker = $this->worker($input + ['mode' => 'pause_after_begin']);
        $worker->start();
        try {
            $deadline = microtime(true) + 35;
            while (! is_file($this->runtime.'/bys-paused')) {
                if (! $worker->isRunning() || microtime(true) > $deadline) {
                    $this->fail('No pause: '.$worker->getOutput().$worker->getErrorOutput());
                }
                usleep(10000);
            }
            $this->bys(['key' => 'parallel-manual', 'payload' => $this->payload()], 201);
            $this->bys($input, 409);
            $this->assertNull($this->db()->query('SELECT purchase_document_id FROM bys_ecf_documents')->fetchColumn());
            $this->db()->exec("UPDATE bys_business_purchase_intents SET leased_until = datetime('now', '-1 minute') WHERE source_type = 'ecf'");
            $this->assertSame(0, $this->bys(['action' => 'reconcile', 'due' => true])['exit']);
            $this->bys($input);
            touch($this->runtime.'/bys-resume');
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame(409, json_decode($worker->getOutput(), true)['status']);
            $this->counts(1, 1);
            $this->assertSame('retryable', $this->db()->query("SELECT state FROM bys_business_purchase_intents WHERE source_type = 'ecf'")->fetchColumn());
        } finally {
            touch($this->runtime.'/bys-resume');
            $worker->stop(1);
        }
    }

    public function test_standalone_manual_and_orders_keep_working_when_only_imports_are_enabled(): void
    {
        DB::table('business_contract_items')->where('identity_link_id', DB::table('business_identity_links')->where('service', 'bys')->value('id'))->delete();
        DB::table('business_identity_links')->where('service', 'bys')->update(['central_company_id' => 2, 'central_subscriber_id' => 2]);
        $this->startBys(2);
        $this->bys(['enabled' => false, 'inbox_enabled' => false, 'payload' => $this->payload()], 201);
        $this->bys(['enabled' => false, 'inbox_enabled' => false, 'path' => '/bys/purchase-orders', 'payload' => ['supplier_id' => 1,
            'purchase_kind' => 'service', 'currency' => 'DOP', 'lines' => [['description' => 'Servicio', 'quantity_ordered' => 1, 'unit_price_original' => 100]]]], 201);
        $this->assertSame(0, DB::table('business_usage_reservations')->count());
        $this->assertSame(2, (int) $this->db()->query('SELECT count(*) FROM bys_purchase_usages')->fetchColumn());
    }

    #[DataProvider('channels')]
    public function test_another_authorized_reviewer_can_resume_a_definitively_cancelled_source(string $channel): void
    {
        $this->startBys();
        $this->bys(['action' => 'add_actor']);
        $input = $this->$channel();
        $snapshot = $this->contract->snapshot;
        $snapshot['capacity']['documents'] = 0;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input, 429);
        $snapshot['capacity']['documents'] = 5;
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->bys($input + ['actor' => 502]);
        $this->counts(1, 1);
        $this->assertSame(502, (int) $this->db()->query('SELECT created_by_user_id FROM bys_purchase_documents')->fetchColumn());
        $this->assertSame(2, (int) $this->db()->query('SELECT generation FROM bys_business_purchase_intents')->fetchColumn());
    }
}
