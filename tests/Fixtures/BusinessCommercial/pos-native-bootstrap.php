<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BusinessIdentityApplication;

/** Run existing POS Pest tests with the shared private kernel, never operational configuration. */
require_once dirname(__DIR__, 2).'/Support/BusinessIdentityApplication.php';

$root = dirname(__DIR__, 4).'/poslaudaapi';
require_once $root.'/vendor/autoload.php';
if (getenv('LAUDA_BUSINESS_MYSQL') === '1') {
    throw new RuntimeException('This native regression adapter is SQLite only.');
}
$runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
mkdir($runtime, 0700);
file_put_contents($runtime.'/synthetic-only', 'POS native regression');

function businessCapacityPosTestApplication(): Application
{
    global $root, $runtime;
    $app = BusinessIdentityApplication::create($root, $runtime.'/pos');
    $app->instance('env', 'testing');
    $app['config']->set(['app.env' => 'testing', 'cache.default' => 'array', 'session.driver' => 'array',
        'inertia.ssr.enabled' => false, 'business_commercial_bridge.capacities_enabled' => false]);
    if (! Schema::hasTable('users')) {
        DB::statement('PRAGMA synchronous = OFF');
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', static fn (...$values) => max($values));
        foreach (glob($root.'/database/migrations/*.php') as $migration) {
            if (basename($migration) === '2026_08_30_171500_make_payment_notice_case_nullable_for_integrations.php') {
                // Explicit SQLite equivalent of this MySQL-only nullable-column migration.
                Schema::table('accounts_receivable_payment_notices', fn ($table) => $table->unsignedBigInteger('accounts_receivable_case_id')->nullable()->change());
            } else {
                (require $migration)->up();
            }
        }
        DB::statement('PRAGMA synchronous = FULL');
    }
    RefreshDatabaseState::$migrated = true;

    return $app;
}

// Keep every native TestCase setup hook; substitute only application creation in this process.
$source = file_get_contents($root.'/tests/TestCase.php');
$anchor = "abstract class TestCase extends BaseTestCase\n{";
if (substr_count($source, $anchor) !== 1 || str_contains($source, 'function createApplication(')) {
    throw new RuntimeException('POS TestCase changed; review the isolated adapter.');
}
$method = '\n    public function createApplication(): \\Illuminate\\Foundation\\Application\n    {\n        $this->traitsUsedByTest = class_uses_recursive(static::class);\n        return \\businessCapacityPosTestApplication();\n    }\n';
$source = str_replace($anchor, $anchor.str_replace('\\n', "\n", $method), $source);
file_put_contents($runtime.'/TestCase.php', $source);
require $runtime.'/TestCase.php';
