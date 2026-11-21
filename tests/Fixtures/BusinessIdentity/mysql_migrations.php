<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/bootstrap.php';
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(2);
});
$names = ['2026_11_06_000002_create_business_identity_registry', '2026_11_06_000003_add_business_identity_handoffs'];
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$migrate = static function (string $name) use ($check): void {
    $code = Artisan::call('migrate', ['--path' => 'database/migrations/'.$name.'.php', '--force' => true]);
    $check($code === 0, 'Migración fallida: '.Artisan::output());
};
$foreignKeyPreserved = static function () use ($check): void {
    $check(Schema::hasIndex('business_identity_intents', ['link_id']), 'Falta el índice independiente de la clave foránea.');
    $foreignKeys = Schema::getForeignKeys('business_identity_intents');
    $check(count(array_filter($foreignKeys, static fn (array $key): bool => $key['columns'] === ['link_id']
        && $key['foreign_table'] === 'business_identity_links' && $key['foreign_columns'] === ['id'])) === 1, 'La clave foránea cambió durante el rollback.');
    $orphanRejected = false;
    try {
        DB::table('business_identity_intents')->insert(['nonce' => str_repeat('f', 32), 'link_id' => (string) Str::uuid(),
            'central_user_id' => 1, 'payload' => '{}', 'expires_at' => time() + 600, 'created_at' => now()]);
    } catch (QueryException $error) {
        $orphanRejected = ($error->errorInfo[0] ?? '') === '23000'
            && in_array((int) ($error->errorInfo[1] ?? 0), [19, 1452], true);
    }
    $check($orphanRejected, 'El rollback dejó de rechazar solicitudes sin vínculo.');
};
$check(! Schema::hasTable('business_identity_links'), 'Se requiere esquema nuevo.');
$migrate($names[0]);
$link = (string) Str::uuid();
DB::table('business_identity_links')->insert(['id' => $link, 'service' => 'bys', 'central_company_id' => 1, 'central_subscriber_id' => 1]);
foreach ([str_repeat('a', 32), str_repeat('b', 32)] as $nonce) {
    DB::table('business_identity_intents')->insert(['nonce' => $nonce, 'link_id' => $link, 'central_user_id' => 1,
        'payload' => '{}', 'expires_at' => time() + 600, 'created_at' => now()]);
}
$migrate($names[1]);
$check(DB::table('business_identity_intents')->whereNull('generation')->count() === 2, 'Se perdieron intents históricos.');
$check(Schema::hasColumns('business_identity_members', ['generation', 'ready_at']) && Schema::hasTable('business_identity_proofs'), 'Esquema incompleto.');
foreach ($names as $name) {
    $migrate($name);
    $check(DB::table('migrations')->where('migration', $name)->count() === 1, 'Migración registrada más de una vez.');
}
DB::table('business_identity_intents')->where('nonce', str_repeat('a', 32))->update(['generation' => 1]);
$duplicateRejected = false;
try {
    DB::table('business_identity_intents')->where('nonce', str_repeat('b', 32))->update(['generation' => 1]);
} catch (UniqueConstraintViolationException) {
    $duplicateRejected = true;
}
$check($duplicateRejected, 'Falta la unicidad de generación.');
foreach (array_reverse($names) as $name) {
    $blocked = false;
    try {
        (require base_path('database/migrations/'.$name.'.php'))->down();
    } catch (RuntimeException $error) {
        $blocked = str_starts_with($error->getMessage(), 'No se puede eliminar');
    }
    $check($blocked, 'Rollback con historial no protegido.');
    $check(Schema::hasTable('business_identity_proofs') && Schema::hasColumn('business_identity_members', 'generation')
        && Schema::hasIndex('business_identity_intents', 'business_intent_generation'), 'El rechazo del rollback alteró el esquema.');
}
DB::table('business_identity_intents')->delete();
DB::table('business_identity_links')->delete();
// Only the empty disposable registry is removed; then test Laravel reapplication.
foreach (array_reverse($names) as $name) {
    (require base_path('database/migrations/'.$name.'.php'))->down();
    if ($name === $names[1]) {
        $foreignKeyPreserved();
    }
    DB::table('migrations')->where('migration', $name)->delete();
}
foreach ($names as $name) {
    $migrate($name);
}
foreach (array_reverse($names) as $name) {
    (require base_path('database/migrations/'.$name.'.php'))->down();
    if ($name === $names[1]) {
        $foreignKeyPreserved();
    }
    DB::table('migrations')->where('migration', $name)->delete();
}
echo json_encode(['driver' => DB::connection()->getDriverName(), 'passed' => true,
    'checks' => ['fresh_install', 'legacy_null_generations', 'columns', 'migration_idempotency', 'generation_unique', 'history_rollback_guard', 'empty_rollback_reapply']], JSON_THROW_ON_ERROR).PHP_EOL;
