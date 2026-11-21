<?php

namespace Tests\Feature\BusinessCommercial;

use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Tests\Support\BusinessCommercialTransportTestCase;

final class CommercialConnectorsTest extends BusinessCommercialTransportTestCase
{
    public function test_four_native_kernels_use_real_verified_https_and_share_one_consumption(): void
    {
        $this->startServer();
        $documentIds = [];
        foreach (['pos', 'ecf', 'bys', 'cumplimiento'] as $service) {
            $snapshot = $this->client($service, 'snapshot');
            $this->assertTrue($snapshot['access']);
            $this->assertSame($this->contract->snapshot_hash, $snapshot['snapshot_hash']);
            $operation = $this->operation($service.':native:1', ['fiscal' => ['issuer' => '101010101', 'ncf' => 'E310000000001']]);
            $action = $service === 'cumplimiento' ? 'reference' : 'reserve';
            $receipt = $this->client($service, $action, $operation);
            $documentIds[] = $receipt['document_id'];
            $this->assertSame($receipt, $this->client($service, $action, $operation));
            if ($action === 'reserve') {
                $receipt = $this->client($service, 'commit', ['operation_key' => $operation['operation_key']]);
            }
            $this->assertSame($receipt, $this->client($service, 'reconcile', ['operation_key' => $operation['operation_key']]));
        }
        $this->assertCount(1, array_unique($documentIds));
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());
        $this->assertSame(4, DB::table('business_usage_reservations')->count());
        $this->assertSame(7, DB::table('business_usage_events')->count());
        $this->assertSame(1, $this->client('pos', 'snapshot')['documents']['consumed']);
        DB::table('payments')->update(['amount' => 0]);
        $snapshot = $this->client('pos', 'snapshot');
        $this->assertTrue($snapshot['managed']);
        $this->assertFalse($snapshot['access']);
        $this->client('pos', 'reserve', $this->operation('refused'), 403);
        DB::table('business_identity_links')->where('service', 'ecf')->update(['active' => false]);
        $this->client('ecf', 'snapshot', [], 403);
    }

    public function test_every_native_connector_fails_closed_for_unverified_or_unavailable_response(): void
    {
        $this->startServer();
        foreach (['pos', 'ecf', 'bys', 'cumplimiento'] as $service) {
            $this->assertSame(['managed' => false, 'access' => false], $this->client($service, 'snapshot', ['company_id' => 999]));
            foreach (['corrupted', 'wrong_request', 'unavailable', 'disabled'] as $mode) {
                $this->client($service, 'snapshot', ['mode' => $mode], 503);
            }
            $this->client($service, 'snapshot', ['mode' => 'revoked'], 403);
        }
        $this->assertSame(0, DB::table('business_usage_documents')->count());
    }

    public function test_shared_wire_and_admission_transport_are_identical_with_native_fiscal_capabilities(): void
    {
        $canonical = hash_file('sha256', app_path('Services/BusinessCommercial/CommercialToken.php'));
        $client = null;
        foreach (['ecflaudaapi', 'poslaudaapi', 'byslaudaapi', 'cumplimientolaudaapi'] as $repository) {
            $root = dirname(base_path()).'/'.$repository;
            $this->assertSame($canonical, hash_file('sha256', $root.'/app/Services/BusinessCommercial/CommercialToken.php'));
            $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse(file_get_contents($root.'/app/Services/BusinessCommercial/CommercialClient.php'));
            $class = (new NodeFinder)->findFirstInstanceOf($statements, Class_::class);
            $this->assertInstanceOf(Class_::class, $class);
            // Fiscal delivery entry points differ by application; admission and transport stay shared.
            $class->stmts = array_values(array_filter($class->stmts, fn ($node) => ! ($node instanceof ClassMethod && $node->name->toString() === 'fiscalHandoff')));
            $source = (new Standard)->prettyPrintFile($statements);
            $client ??= $source;
            $this->assertSame($client, $source, $repository.' shared commercial transport');
        }
    }
}
