<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Real kernel/providers/routes; only task-owned databases, sessions, caches and keys. */
final class BusinessIdentityApplication
{
    public static function create(string $root, string $runtime, array $identity = [], string $url = 'https://hub.example'): Application
    {
        $allowed = array_map(fn ($name) => dirname(__DIR__, 3).'/'.$name, ['laudaapi', 'ecflaudaapi', 'poslaudaapi', 'byslaudaapi', 'cumplimientolaudaapi']);
        if (! in_array(realpath($root), $allowed, true) || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}/[a-z]+$#D', $runtime)
            || ! is_file(dirname($runtime).'/synthetic-only')) {
            throw new \RuntimeException('An explicit synthetic Business runtime is required.');
        }
        require_once $root.'/vendor/autoload.php';
        foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'public/build'] as $directory) {
            if (! is_dir($runtime.'/'.$directory)) {
                mkdir($runtime.'/'.$directory, 0700, true);
            }
        }
        $mysql = getenv('LAUDA_BUSINESS_MYSQL') === '1';
        if ($mysql) {
            require_once __DIR__.'/BusinessIdentityMySql.php';
            $connection = BusinessIdentityMySql::configuration($runtime);
        } else {
            if (is_file($runtime.'/mysql.json')) {
                throw new \RuntimeException('A MySQL runtime cannot fall back to SQLite.');
            }
            $database = $runtime.'/database.sqlite';
            if (! is_file($database)) {
                touch($database);
                chmod($database, 0600);
            }
            $connection = ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true];
        }
        foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_EVENTS_CACHE' => 'events.php', 'APP_PACKAGES_CACHE' => 'packages.php', 'APP_SERVICES_CACHE' => 'services.php'] as $key => $file) {
            putenv($key.'='.$runtime.'/bootstrap/cache/'.$file);
        }
        putenv('APP_ENV=testing');
        copy($root.'/bootstrap/providers.php', $runtime.'/bootstrap/providers.php');
        $app = require $root.'/bootstrap/app.php';
        $app->useEnvironmentPath($runtime);
        $app->useBootstrapPath($runtime.'/bootstrap');
        $app->useStoragePath($runtime.'/storage');
        $app->usePublicPath($runtime.'/public');
        $app->instance(LoadEnvironmentVariables::class, new class extends LoadEnvironmentVariables
        {
            public function bootstrap($app): void {}
        });
        $app->beforeBootstrapping(RegisterProviders::class, function ($app) use ($runtime, $connection, $identity, $url): void {
            $app['config']->set([
                'app.env' => 'local-integration', 'app.debug' => false, 'app.url' => $url,
                'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)), 'app.asset_url' => null,
                'database.default' => $connection['driver'], 'database.connections' => [$connection['driver'] => $connection],
                'cache.default' => 'file', 'cache.stores.file.path' => $runtime.'/storage/framework/cache/data',
                'session.driver' => 'file', 'session.files' => $runtime.'/storage/framework/sessions', 'session.cookie' => 'business_'.basename($runtime),
                'session.secure' => true, 'session.domain' => null, 'session.lottery' => [0, 100],
                'queue.default' => 'sync', 'mail.default' => 'array', 'logging.default' => 'single', 'logging.channels.single.path' => $runtime.'/storage/logs/application.log',
                'filesystems.default' => 'local', 'filesystems.disks.local.root' => $runtime.'/storage/app/private', 'filesystems.disks.public.root' => $runtime.'/storage/app/public',
                'view.compiled' => $runtime.'/storage/framework/views', 'broadcasting.default' => 'null', 'business_identity' => $identity,
            ]);
            $app->instance('env', 'local-integration'); // Real CSRF in HTTP/browser tests.
        });
        $app->make(Kernel::class)->bootstrap();
        if (DB::connection()->getDatabaseName() !== $connection['database'] || DB::connection()->getDriverName() !== $connection['driver']) {
            throw new \RuntimeException('Unexpected database.');
        }
        // SQLite adapter only; this fixture does not certify MySQL ordering/locking.
        if (! $mysql) {
            DB::connection()->getPdo()->sqliteCreateFunction('FIELD', static fn ($value, ...$values) => ($index = array_search($value, $values, true)) === false ? 0 : $index + 1);
        }
        Http::preventStrayRequests();

        return $app;
    }

    public static function centralSchema(): void
    {
        foreach (['0001_01_01_000000_create_users_table', '2025_08_14_170933_add_two_factor_columns_to_users_table',
            '2026_01_12_131400_create_subscribers_table', '2026_01_12_131500_create_subscriptions_table', '2026_01_17_194252_create_services_table',
            '2026_01_24_122210_create_companies_table', '2026_01_25_012613_create_subscriber_user_table', '2026_01_24_122419_subscription_items_table',
            '2026_11_06_000002_create_business_identity_registry', '2026_11_06_000003_add_business_identity_handoffs'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
    }
}
