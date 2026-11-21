<?php

namespace Tests\Feature\Billing;

use App\Models\BusinessImplementationOrder;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\BusinessIdentity\IdentityToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteCheckoutFixtures;

final class BusinessFixtureSchemaTest extends TestCase
{
    private string $runtime;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business schema dependency tests only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    public static function fixtures(): array
    {
        return ['checkout' => [BusinessQuoteCheckoutFixtures::class], 'contract' => [BusinessContractFixtures::class]];
    }

    #[DataProvider('fixtures')]
    public function test_foreign_key_parents_exist_before_their_references_are_created(string $fixture): void
    {
        $references = 0;
        // SQLite permits forward FK references; assert their creation order explicitly.
        // This checks the real fixture DDL without claiming MySQL execution.
        DB::connection()->beforeExecuting(function (string $sql) use (&$references): void {
            if (! preg_match('/^\s*(?:create|alter)\s+table\s+["`]([^"`]+)["`]/i', $sql, $table)) {
                return;
            }
            preg_match_all('/\breferences\s+["`]([^"`]+)["`]\s*\(/i', $sql, $parents);
            foreach ($parents[1] as $parent) {
                $references++;
                $this->assertTrue($parent === $table[1] || Schema::hasTable($parent), $table[1].' references missing parent '.$parent);
            }
        });

        $fixture::schema();

        $this->assertGreaterThan(0, $references);
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $keys = Schema::getForeignKeys('standalone_service_settlements');
        $parent = array_values(array_filter($keys, fn (array $key): bool => $key['columns'] === ['activation_request_service_id']));
        $this->assertCount(1, $parent);
        $this->assertSame('activation_request_service', $parent[0]['foreign_table']);
        $this->assertSame('restrict', $parent[0]['on_delete']);
    }

    public function test_commercial_seed_respects_declared_string_lengths_enums_and_identity_bindings(): void
    {
        $blueprints = [];
        // Retain the actual migration definitions: SQLite does not enforce CHAR/VARCHAR lengths.
        $this->app->bind(Blueprint::class, function ($app, array $parameters) use (&$blueprints): Blueprint {
            $blueprint = new Blueprint($parameters['connection'], $parameters['table'], $parameters['callback'] ?? null);
            $blueprints[] = $blueprint;

            return $blueprint;
        });
        BusinessContractFixtures::schema();
        Mail::fake();
        Notification::fake();
        $quote = BusinessContractFixtures::seed();
        app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, [
            'confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote),
            'idempotency_key' => (string) Str::uuid(),
            'implementation_version' => BusinessImplementationOrder::sole()->lock_version,
            'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']), 'Preparación sintética de concurrencia.'),
        ]);

        $definitions = [];
        foreach ($blueprints as $blueprint) {
            foreach ($blueprint->getColumns() as $column) {
                $definitions[$blueprint->getTable()][$column->name] = $column;
            }
        }
        $checked = 0;
        foreach ($definitions as $table => $columns) {
            foreach (DB::table($table)->get() as $row) {
                foreach ($columns as $name => $column) {
                    $value = $row->{$name} ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (in_array($column->type, ['char', 'string', 'uuid'], true)) {
                        $limit = $column->type === 'uuid' ? 36 : $column->length;
                        $this->assertLessThanOrEqual($limit, mb_strlen((string) $value, 'UTF-8'), $table.'.'.$name.' exceeds its declared length');
                        $checked++;
                    } elseif ($column->type === 'enum') {
                        $this->assertContains((string) $value, $column->allowed, $table.'.'.$name);
                        $checked++;
                    } elseif ($column->type === 'json') {
                        $this->assertTrue(json_validate((string) $value), $table.'.'.$name);
                        $checked++;
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $checked);
        $this->assertSame(32, $definitions['business_identity_members']['consent']->length);
        $members = DB::table('business_identity_members as member')
            ->join('business_identity_links as link', 'link.id', '=', 'member.link_id')
            ->get(['link.service', 'link.central_company_id', 'link.central_subscriber_id', 'link.local_company_id',
                'member.link_id', 'member.central_user_id', 'member.local_user_id', 'member.consent', 'member.generation']);
        $this->assertCount(4, $members);
        $this->assertCount(4, $members->pluck('consent')->unique());
        foreach ($members as $member) {
            IdentityToken::assertBinding((array) $member);
        }
    }
}
