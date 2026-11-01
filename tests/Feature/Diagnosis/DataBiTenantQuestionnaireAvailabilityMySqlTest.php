<?php

namespace Tests\Feature\Diagnosis;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

require_once __DIR__.'/DataBiTenantDeclarationHttpMySqlTest.php';

/**
 * T4E: preparada, NO ejecutada. Reutiliza por composición únicamente el fixture
 * rollback-only de T4D ya aprobado; no ejecuta sus métodos de prueba ni lo modifica.
 * Requiere D2F_T4E_HTTP_MYSQL_QA=rollback-only MÁS los guards/credenciales explícitos
 * D2F_T4D_HTTP_MYSQL_QA, APP_ENV y D2F_T4D_QA_* del harness original.
 * Reflection queda confinada al adaptador del fixture privado de QA existente.
 */
#[Group('mysql-dev-t4e-rollback')]
final class DataBiTenantQuestionnaireAvailabilityMySqlTest extends TestCase
{
    private DataBiTenantDeclarationHttpMySqlTest $fixture;

    private bool $fixtureReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('D2F_T4E_HTTP_MYSQL_QA') !== 'rollback-only') {
            self::markTestSkipped('HTTP_MYSQL_QA=PENDING: autorización T4E explícita requerida.');
        }
        $this->fixture = new DataBiTenantDeclarationHttpMySqlTest('test_get_without_declaration_is_explicit_and_performs_no_dml');
        (new ReflectionMethod($this->fixture, 'setUp'))->invoke($this->fixture);
        $this->fixtureReady = true;
        // Las props de Inertia no forman parte del contrato JSON del cuestionario.
        $this->fixtureProperty('httpApp')['router']->getRoutes()
            ->getByName('app.transformation.data_bi.information_requirements.show')
            ->withoutMiddleware(HandleInertiaRequests::class);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->fixtureReady) {
                (new ReflectionMethod($this->fixture, 'tearDown'))->invoke($this->fixture);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function fixtureProperty(string $name): mixed
    {
        return (new ReflectionProperty($this->fixture, $name))->getValue($this->fixture);
    }

    private function questionnaire(): TestResponse
    {
        $id = $this->fixtureProperty('request')->getKey();

        return (new ReflectionMethod($this->fixture, 'declarationHttp'))->invoke($this->fixture,
            'GET', '/app/transformacion-360/datos-bi/requerimientos/'.$id);
    }

    public function test_ready_schema_enables_response_without_any_dml(): void
    {
        $before = $this->fixtureProperty('dml');
        $this->questionnaire()->assertOk()->assertJsonPath('can_respond', true);
        self::assertSame($before, $this->fixtureProperty('dml'));
    }

    public function test_missing_declaration_table_or_columns_preserves_readonly_questionnaire_without_ddl(): void
    {
        $schema = Schema::getFacadeRoot();
        $missing = '';
        try {
            Schema::swap(new class($schema, $missing)
            {
                public function __construct(private object $schema, private string &$missing) {}

                public function hasTable(string $table): bool
                {
                    return ! ($table === 'data_bi_tenant_declarations' && $this->missing === 'table')
                        && $this->schema->hasTable($table);
                }

                public function hasColumns(string $table, array $columns): bool
                {
                    return ! ($table === 'data_bi_tenant_declarations' && in_array($this->missing, $columns, true))
                        && $this->schema->hasColumns($table, $columns);
                }

                public function __call(string $method, array $arguments): mixed
                {
                    return $this->schema->{$method}(...$arguments);
                }
            });
            foreach (['table', 'id', 'data_bi_information_request_id', 'company_id', 'status', 'revision',
                'declaration_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id',
                'submitted_by_user_id', 'submitted_at', 'created_at', 'updated_at'] as $missing) {
                $before = $this->fixtureProperty('dml');
                $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false);
                self::assertSame($before, $this->fixtureProperty('dml'));
            }
        } finally {
            Schema::swap($schema);
        }
    }

    public function test_missing_submit_endpoint_preserves_readonly_questionnaire(): void
    {
        $routes = $this->fixtureProperty('httpApp')['router']->getRoutes();
        $routes->getByName('app.transformation.data_bi.information_requirements.declaration.submit')->name('.disabled');
        $routes->refreshNameLookups();
        $before = $this->fixtureProperty('dml');
        $this->questionnaire()->assertOk()->assertJsonPath('can_respond', false);
        self::assertSame($before, $this->fixtureProperty('dml'));
    }
}
