<?php

use App\Models\Bys\BysCompany;
use App\Models\Bys\BysConnection;
use App\Models\Plan;
use App\Models\User;
use App\Services\Bys\BysCompanyService;
use App\Services\Bys\BysDocumentImportService;
use App\Services\Bys\BysEcfInboxService;
use App\Services\Bys\BysLocalFileService;
use App\Services\Bys\BysTabularFileService;
use App\Support\Bys\BysCompanyContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BysFileFixture;

config()->set('bys_connections.ecf.url', 'https://ecf.synthetic.test');
if ($input['action'] === 'add_actor') {
    $actor = User::factory()->create(['id' => 502, 'name' => 'Revisor sintético', 'email' => 'reviewer@example.test']);
    app(BysCompanyService::class)->saveMember(User::findOrFail(501), BysCompany::findOrFail(1), $actor, 'purchasing', true);
    echo json_encode(['status' => 200]);
    exit;
}
if ($input['action'] === 'prepare_inbox') {
    $company = BysCompany::findOrFail(1);
    $company->forceFill(['rnc' => '131234567'])->save();
    $document = app(BysCompanyContext::class)->run($company, User::findOrFail(501), function () use ($input) {
        $connection = BysConnection::first() ?? BysConnection::create(['company_id' => 1, 'user_id' => 501,
            'reference' => (string) Str::uuid(), 'system' => 'ecf', 'environment' => $input['environment'] ?? 'ecf',
            'base_url' => 'https://ecf.synthetic.test', 'remote_installation' => (string) Str::uuid(), 'remote_company' => '1',
            'remote_owner' => '1', 'rnc' => '131234567', 'remote_name' => 'Origen sintético', 'capabilities' => ['ecf:read'],
            'credentials' => ['key' => 'synthetic-key', 'secret' => 'synthetic-secret'], 'generation' => 1, 'verified_at' => now()]);
        $bytes = BysFileFixture::pdf();
        $row = ['document_id' => $input['remote_id'] ?? 'received:1', 'environment' => $connection->environment,
            'encf' => 'E310000000001', 'tipo_ecf' => 'e31', 'rnc_emisor' => '101010101', 'rnc_receptor' => '131234567',
            'fecha_emision' => '2026-11-17', 'status' => 'recibido', 'monto_total' => '118.00', 'itbis_total' => '18.00',
            'moneda' => 'DOP', 'updated_at' => '2026-11-17T01:00:00+00:00', 'deleted_at' => null,
            'pdf_sha256' => hash('sha256', $bytes)];
        $document = DB::transaction(fn () => app(BysEcfInboxService::class)->record($connection, $row, explode(':', $row['document_id'])[0]));
        $path = 'bys/1/ecf/'.$document->id.'/synthetic.pdf';
        app(BysLocalFileService::class)->storePrivate($path, $bytes);
        DB::table('bys_ecf_files')->insert(['ecf_document_id' => $document->id, 'format' => 'pdf', 'sha256' => hash('sha256', $bytes),
            'size_bytes' => strlen($bytes), 'path' => $path, 'usable' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $document;
    });
    echo json_encode(['status' => 200, 'body' => $document]);
    exit;
}
if (($input['mode'] ?? '') === 'rollback_after_file') {
    DB::statement("CREATE TRIGGER synthetic_source_rollback BEFORE UPDATE ON bys_business_purchase_intents WHEN NEW.state = 'executed' BEGIN SELECT RAISE(ABORT, 'synthetic source rollback'); END");
}
if (in_array($input['mode'] ?? '', ['pause_after_begin', 'pause_after_native_commit'], true)) {
    $mode = $input['mode'];
    $paused = false;
    DB::listen(function (QueryExecuted $query) use ($mode, $runtime, &$paused): void {
        $table = $mode === 'pause_after_begin' ? 'business_commercial_operations' : 'bys_business_purchase_intents';
        $state = $mode === 'pause_after_begin' ? 'executing' : 'executed';
        if (! $paused && str_contains($query->sql, 'update') && str_contains($query->sql, $table) && in_array($state, $query->bindings, true)) {
            $paused = true;
            DB::afterCommit(function () use ($runtime): void {
                touch($runtime.'/bys-paused');
                $deadline = microtime(true) + 55;
                while (! is_file($runtime.'/bys-resume')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Synthetic source pause timed out.');
                    }
                    usleep(10000);
                }
            });
        }
    });
}
function bysSourceFault(string $mode, array $input, string $runtime): void
{
    if ($mode === 'cancel_assistant') {
        DB::table('bys_purchase_assistant_drafts')->update(['status' => 'cancelled']);
    } elseif ($mode === 'change_assistant') {
        DB::table('bys_purchase_assistant_drafts')->update(['user_prompt' => 'Revisión posterior']);
    } elseif ($mode === 'disable_assistant_module') {
        $plan = Plan::where('slug', 'business')->firstOrFail();
        $features = $plan->features;
        $features['assistant'] = false;
        $plan->update(['features' => $features]);
    } elseif ($mode === 'change_inbox') {
        DB::table('bys_ecf_documents')->increment('revision');
    } elseif ($mode === 'change_batch') {
        DB::table('bys_document_imports')->update(['sha256' => str_repeat('f', 64)]);
    } elseif ($mode === 'revoke_ecf') {
        DB::table('bys_connections')->update(['generation' => 2, 'status' => 'revoked']);
    } elseif ($mode === 'concurrent_manual') {
        $worker = new Process(['php', __DIR__.'/bys-purchase.php', $runtime]);
        $worker->setInput(json_encode(['action' => 'request', 'key' => 'concurrent-manual', 'payload' => $input['payload']['payload']]));
        $worker->setTimeout(30)->mustRun();
        if (json_decode($worker->getOutput(), true)['status'] !== 201) {
            throw new RuntimeException($worker->getOutput());
        }
    }
}
function bysSourceFiles(array $input): array
{
    if (! isset($input['upload_format'])) {
        return [];
    }
    $format = $input['upload_format'];
    if ($format === 'pdf') {
        $bytes = BysFileFixture::pdf();
    } elseif ($format === 'xlsx') {
        $bytes = app(BysTabularFileService::class)->template('xlsx');
    } elseif ($format === 'xml') {
        $xml = new SimpleXMLElement('<BysPurchaseDocument version="1"><Header/><Lines/></BysPurchaseDocument>');
        foreach (array_intersect_key($input['upload_payload'], array_flip(BysDocumentImportService::HEADER)) as $key => $value) {
            $xml->Header->addChild($key, htmlspecialchars(is_bool($value) ? (string) (int) $value : (string) $value, ENT_XML1));
        }
        foreach ($input['upload_payload']['lines'] as $values) {
            $line = $xml->Lines->addChild('Line');
            foreach ($values as $key => $value) {
                $line->addChild($key, htmlspecialchars((string) $value, ENT_XML1));
            }
        }
        $bytes = $xml->asXML();
    } else {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, BysTabularFileService::COLUMNS, ',', '"', '');
        foreach ($input['upload_rows'] as $row) {
            fputcsv($stream, array_map(fn ($key) => $row[$key] ?? '', BysTabularFileService::COLUMNS), ',', '"', '');
        }
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);
    }

    return ['file' => UploadedFile::fake()->createWithContent('synthetic.'.$format, $bytes)];
}
