<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Services\Billing\BusinessCatalogService;
use Database\Seeders\BusinessCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class BusinessQuoteFixtures
{
    public static function schema(): void
    {
        self::assertSyntheticDatabase();
        BusinessIdentityApplication::centralSchema();
        foreach ([
            '2026_01_12_023204_create_audit_logs_table.php',
            '2026_01_21_203948_create_fiscal_year_end_catalog_table.php.php',
            '2026_01_24_123011_company_tax_profiles_table.php',
            '2026_01_24_123104_create_invoices_table.php',
            '2026_01_24_123214_create_payments_table.php',
            '2026_08_22_000001_create_diagnosis_assessments_table.php',
            '2026_08_30_190000_create_transformation_capability_activations_table.php',
            '2026_11_06_000001_create_service_catalog_versions.php',
            '2026_11_06_000004_create_service_quote_acceptances.php',
        ] as $migration) {
            (require base_path('database/migrations/'.$migration))->up();
        }
    }

    public static function assertSyntheticDatabase(): void
    {
        $runtime = dirname(app()->storagePath());
        if (! app()->environment('local-integration')
            || ! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}/central$#D', $runtime)
            || ! is_file(dirname($runtime).'/synthetic-only')) {
            throw new \RuntimeException('Synthetic Business runtime required.');
        }
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === $runtime.'/database.sqlite'
            && ! is_file($runtime.'/mysql.json') && getenv('LAUDA_BUSINESS_MYSQL') !== '1') {
            return;
        }
        if ($connection->getDriverName() === 'mysql' && getenv('LAUDA_BUSINESS_MYSQL') === '1') {
            $expected = BusinessIdentityMySql::configuration($runtime);
            foreach (['driver', 'database', 'username', 'unix_socket'] as $key) {
                if ($connection->getConfig($key) !== $expected[$key]) {
                    throw new \RuntimeException('Unexpected synthetic MySQL connection.');
                }
            }

            return;
        }
        throw new \RuntimeException('Synthetic Business database required; no fallback is allowed.');
    }

    public static function seed(float $taxRate = 0.18): ServiceCommercialQuote
    {
        config()->set('business_commercial.enabled', true);
        $password = Hash::make('business-synthetic-only', ['rounds' => 4]);
        foreach ([1 => 'Ana Rivera', 2 => 'Otra empresa', 3 => 'Miembro', 4 => 'Admin de plataforma'] as $id => $name) {
            DB::table('users')->insert(['id' => $id, 'name' => $name, 'email' => "quote-$id@business.invalid",
                'role' => $id === 4 ? 'admin' : 'subscriber', 'password' => $password, 'email_verified_at' => now()]);
        }
        foreach ([1 => 'Comercial Rivera', 2 => 'Empresa ajena'] as $id => $name) {
            DB::table('subscribers')->insert(['id' => $id, 'name' => $name, 'slug' => 'quote-'.$id, 'currency' => 'DOP']);
            DB::table('companies')->insert(['id' => $id, 'name' => $name, 'slug' => 'quote-'.$id, 'subscriber_id' => $id, 'currency' => 'DOP']);
            DB::table('subscriber_user')->insert(['subscriber_id' => $id, 'user_id' => $id, 'role' => 'owner', 'active' => true]);
        }
        DB::table('subscriber_user')->insert(['subscriber_id' => 1, 'user_id' => 3, 'role' => 'member', 'active' => true]);
        $payload = BusinessCatalogSeeder::initialPayload();
        foreach ($payload['products'] as $code => &$product) {
            $product['service_id'] = DB::table('services')->insertGetId(['title' => $product['name'], 'slug' => $code, 'service_key' => $code, 'currency' => 'DOP']);
        }
        unset($product);
        $payload['terms'] = 'Condiciones ficticias de QA. La aceptación conserva el precio y el alcance aquí indicados. Facturación y activación se tramitan por separado.';
        $payload['quote_valid_days'] = 15;
        $payload['tax_rate'] = $taxRate;
        $catalog = ServiceCatalog::create(['code' => 'business']);
        $version = ServiceCatalogVersion::create(['service_catalog_id' => $catalog->id, 'version' => 1, 'payload' => $payload, 'status' => 'draft', 'created_by' => 4]);
        $version = app(BusinessCatalogService::class)->publish($version, 1, null, 4);

        return self::issue($version);
    }

    public static function issue(?ServiceCatalogVersion $version = null, int $companyId = 1): ServiceCommercialQuote
    {
        $version ??= ServiceCatalogVersion::firstOrFail();

        return app(BusinessCatalogService::class)->issue($version, $version->lock_version, Company::findOrFail($companyId),
            ['offer' => 'business', 'level' => 'initial', 'cycle' => 'monthly', 'implementation' => 'assisted', 'addons' => [], 'discount' => null],
            (string) Str::uuid(), 4);
    }
}
