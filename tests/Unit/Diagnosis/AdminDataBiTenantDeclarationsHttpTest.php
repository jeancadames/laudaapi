<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Middleware\AdminDataBiDeclarationJson;
use App\Models\User;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;
use Tests\Support\BuildsDataBiTenantDeclarationPayloads;

/** Router, binding, middleware y contratos reales; lecturas sintéticas, sin PDO/SQLite. */
final class AdminDataBiTenantDeclarationsHttpTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;
    use BuildsDataBiTenantDeclarationPayloads;

    private T4faReadConnection $connection;

    private array $missing = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->startHttpApplication();
        $this->httpApp['router']->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
        $this->httpApp['router']->getRoutes()->refreshNameLookups();
        $this->connection = new T4faReadConnection([
            'transformation_implementation_requests' => [['id' => 101, 'company_id' => 701, 'capability_key' => 'data_transformation_bi']],
            'companies' => [['id' => 701, 'name' => 'Empresa sintética T4F-A', 'subscriber_id' => 801]],
            'data_bi_information_requests' => [$this->publication()],
            'data_bi_tenant_declarations' => [],
        ]);
        $capsule = new Capsule($this->httpApp);
        $capsule->addConnection(['driver' => 'mysql', 'name' => 't4fa_read'], 't4fa_read');
        $manager = $capsule->getDatabaseManager();
        $manager->extend('mysql', fn () => $this->connection);
        $manager->setDefaultConnection('t4fa_read');
        $this->httpApp->instance('db', $manager);
        DB::clearResolvedInstance('db');
        Model::clearBootedModels();
        Model::setConnectionResolver($manager);
        Model::setEventDispatcher($this->httpApp['events']);
        Schema::swap(new class($this->missing)
        {
            public function __construct(private array &$missing) {}

            public function hasTable(string $table): bool
            {
                return ! in_array($table, $this->missing, true);
            }

            public function hasColumns(string $table, array $columns): bool
            {
                return array_all($columns, fn ($column): bool => ! in_array($table.'.'.$column, $this->missing, true));
            }
        });
        $this->actor();
    }

    protected function tearDown(): void
    {
        $this->stopHttpApplication();
        parent::tearDown();
    }

    private function actor(string $role = 'admin', bool $verified = true): void
    {
        $user = new User;
        $user->setRawAttributes(['id' => 501, 'role' => $role, 'email_verified_at' => $verified ? '2026-10-31 00:00:00' : null]);
        $user->exists = true;
        $this->httpApp['auth']->guard()->setUser($user);
    }

    private function publication(int $version = 1): array
    {
        $snapshot = $this->reviewSnapshot();
        $first = $snapshot['requirements'][0];
        $snapshot['requirements'] = array_map(static fn (int $i): array => [
            ...$first, 'key' => 'req_'.$i, 'label' => 'Requerimiento '.$i,
        ], range(1, 4));
        unset($snapshot['content_sha256']);
        $snapshot['content_sha256'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return ['id' => 200 + $version, 'company_id' => 701, 'transformation_implementation_request_id' => 101,
            'version' => $version, 'status' => 'sent', 'sent_by_user_id' => 501, 'sent_at' => '2026-10-31 10:00:00',
            'content_sha256' => $snapshot['content_sha256'], 'review_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)];
    }

    private function declaration(array $q, string $status = 'submitted', bool $mixed = false): array
    {
        $sent = [...$q, 'review_snapshot' => json_decode($q['review_snapshot'], true)];
        $input = $this->declarationInput($q['version'], $sent['review_snapshot'], $status === 'submitted');
        if ($mixed) {
            foreach (['available', 'partial', 'unavailable', 'unknown'] as $i => $availability) {
                $input['declarations'][$i]['availability'] = $availability;
                $input['declarations'][$i]['notes'] = $availability === 'available' ? '' : 'Motivo declarado.';
                $input['declarations'][$i]['sources'] = in_array($availability, ['unavailable', 'unknown'], true) ? []
                    : [['type' => 'software', 'name' => 'ERP', 'structures' => ['Clientes', 'Ventas'], 'notes' => 'Fuente declarada.']];
            }
        }
        $normalized = DataBiInformationTenantDeclarationContract::normalize($sent, $input, $status === 'submitted' ? 'submit' : 'draft');

        return ['id' => 400 + $q['version'], 'data_bi_information_request_id' => $q['id'], 'company_id' => $q['company_id'],
            'status' => $status, 'revision' => 3, 'declaration_snapshot' => json_encode($normalized, JSON_THROW_ON_ERROR),
            'content_sha256' => $normalized['content_sha256'], 'created_by_user_id' => 601, 'updated_by_user_id' => 602,
            'submitted_by_user_id' => $status === 'submitted' ? 602 : null, 'submitted_at' => $status === 'submitted' ? '2026-10-31 12:00:00' : null,
            'created_at' => '2026-10-31 11:00:00', 'updated_at' => '2026-10-31 12:00:00'];
    }

    private function getReview(string $query = '', int $id = 101)
    {
        $response = $this->declarationHttp('GET', '/admin/transformation-360/implementation-requests/'.$id.'/information-requirements/tenant-declarations'.$query, '');
        self::assertNotSame(500, $response->getStatusCode(), implode("\n", array_map(static fn ($e) => (string) $e, $this->httpReportedErrors)));

        return $response;
    }

    public function test_admin_without_tenant_membership_can_read_pending_and_route_is_get_only(): void
    {
        $this->getReview()->assertOk()->assertJsonPath('data.company.name', 'Empresa sintética T4F-A')
            ->assertJsonPath('data.selected.declaration', null)->assertJsonPath('data.selected.coverage', null)
            ->assertJsonPath('data.versions.0.response_status', 'pending')->assertJsonPath('data.versions.0.revision', null)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $route = $this->httpApp['router']->getRoutes()->getByName('admin.transformation360.implementation_requests.information_requirements.tenant_declarations.show');
        self::assertSame(['GET', 'HEAD'], $route->methods());
        self::assertSame('[0-9]+', $route->wheres['implementationRequest']);
        self::assertContains('auth', $route->middleware());
        self::assertContains('verified', $route->middleware());
        self::assertContains('role:admin', $route->middleware());
        self::assertContains('web', $route->middleware());
        self::assertSame(AdminDataBiDeclarationJson::class, $this->httpApp['router']->gatherRouteMiddleware($route)[0]);
        $response = $this->getReview();
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($this->connection->pdoAttempted);
    }

    public function test_guest_unverified_and_tenant_do_not_reach_publications_or_declarations(): void
    {
        $this->httpApp['auth']->guard()->forgetUser();
        $this->getReview()->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->actor(verified: false);
        $this->getReview()->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        foreach (['subscriber', 'user'] as $role) {
            $this->actor($role);
            $this->getReview()->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        }
        // El binding estándar puede resolver la solicitud antes de verified/role.
        // Ninguna publicación ni respuesta se consulta sin autorización Admin.
        foreach ($this->connection->reads as [$sql]) {
            self::assertStringContainsString('from `transformation_implementation_requests`', $sql);
        }
    }

    public function test_wrong_capability_missing_request_and_company_are_404(): void
    {
        $this->getReview(id: 999)->assertNotFound();
        $this->connection->rows['transformation_implementation_requests'][0]['capability_key'] = 'other';
        $this->getReview()->assertNotFound();
        $this->connection->rows['transformation_implementation_requests'][0]['capability_key'] = 'data_transformation_bi';
        $this->connection->rows['companies'] = [];
        $this->getReview()->assertNotFound();
    }

    public function test_without_publication_no_formal_response_is_invented(): void
    {
        $this->connection->rows['data_bi_information_requests'] = [];
        $this->getReview()->assertOk()->assertJsonPath('data.current_version', null)->assertJsonPath('data.selected', null)->assertJsonCount(0, 'data.versions');
    }

    public function test_private_draft_content_and_revision_are_not_loaded_or_projected(): void
    {
        $draft = $this->declaration($this->publication(), 'draft');
        $draft['declaration_snapshot'] = '{"private":"PRIVATE_TENANT_DRAFT"}';
        $this->connection->rows['data_bi_tenant_declarations'] = [$draft];
        $response = $this->getReview()->assertOk()->assertJsonPath('data.selected.declaration', null)
            ->assertJsonPath('data.versions.0.revision', null);
        self::assertStringNotContainsString('PRIVATE_TENANT_DRAFT', $response->getContent());
        foreach ($this->connection->reads as [$sql, $bindings]) {
            if (str_contains($sql, 'from `data_bi_tenant_declarations`')) {
                self::assertContains('submitted', $bindings);
            }
        }
    }

    public function test_valid_submission_projects_required_fields_and_counts_only_declarative_mentions(): void
    {
        $row = $this->declaration($this->publication(), mixed: true);
        $this->connection->rows['data_bi_tenant_declarations'] = [$row];
        $before = $this->connection->rows;
        $response = $this->getReview()->assertOk()->assertJsonPath('data.selected.declaration.status', 'submitted')
            ->assertJsonPath('data.selected.declaration.revision', 3)->assertJsonPath('data.selected.version', 1)
            ->assertJsonPath('data.selected.coverage', ['requested' => 4, 'available' => 1, 'partial' => 1, 'unavailable' => 1, 'unknown' => 1, 'sources' => 2, 'structures' => 4]);
        self::assertSame(['questionnaire_id', 'version', 'published_at', 'objectives', 'requirements', 'declaration', 'coverage'], array_keys($response->json('data.selected')));
        foreach (['content_sha256', 'questionnaire_sha256', 'review_snapshot', 'declaration_snapshot',
            'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id', 'subscriber_id'] as $private) {
            self::assertStringNotContainsString('"'.$private.'"', $response->getContent());
        }
        self::assertSame($before, $this->connection->rows);
        self::assertFalse($this->connection->pdoAttempted);
    }

    public function test_history_keeps_submitted_answers_on_their_original_version(): void
    {
        $old = $this->publication(1);
        $new = $this->publication(3);
        $draft = [...$this->publication(4), 'status' => 'draft'];
        $this->connection->rows['data_bi_information_requests'] = [$old, $new, $draft];
        $this->connection->rows['data_bi_tenant_declarations'] = [$this->declaration($old)];
        $this->getReview()->assertOk()->assertJsonPath('data.current_version', 3)->assertJsonPath('data.selected.version', 3)
            ->assertJsonPath('data.selected.declaration', null)->assertJsonPath('data.versions.1.response_status', 'submitted');
        $this->getReview('?version=1')->assertOk()->assertJsonPath('data.current_version', 3)->assertJsonPath('data.selected.version', 1)
            ->assertJsonPath('data.selected.declaration.revision', 3);
        $this->getReview('?version=4')->assertNotFound();
    }

    public function test_another_request_submission_cannot_be_selected_by_declaration_id(): void
    {
        $foreign = [...$this->publication(5), 'transformation_implementation_request_id' => 999, 'company_id' => 999];
        $this->connection->rows['data_bi_information_requests'][] = $foreign;
        $this->connection->rows['data_bi_tenant_declarations'] = [$this->declaration($foreign)];
        $this->getReview()->assertOk()->assertJsonPath('data.selected.declaration', null)->assertJsonCount(1, 'data.versions');
        $this->getReview('?version=5')->assertNotFound();
        $this->getReview('?declaration_id=405')->assertStatus(422);
    }

    #[DataProvider('invalidFields')]
    public function test_snapshot_identity_hashes_and_audit_fail_closed(string $table, string $field, mixed $value): void
    {
        $this->connection->rows['data_bi_tenant_declarations'] = [$this->declaration($this->publication())];
        $this->connection->rows[$table][0][$field] = $value;
        $response = $this->getReview()->assertStatus(409)->assertJsonPath('error.code', 'declaration_integrity_conflict');
        self::assertArrayNotHasKey('data', $response->json());
    }

    public static function invalidFields(): iterable
    {
        foreach (['company_id' => 999, 'sent_by_user_id' => null, 'sent_at' => null, 'content_sha256' => str_repeat('f', 64), 'review_snapshot' => '{}'] as $field => $value) {
            yield 'publication '.$field => ['data_bi_information_requests', $field, $value];
        }
        foreach (['company_id' => 999, 'revision' => 0, 'created_by_user_id' => null, 'updated_by_user_id' => null,
            'submitted_by_user_id' => null, 'submitted_at' => null, 'content_sha256' => str_repeat('f', 64), 'declaration_snapshot' => '{}'] as $field => $value) {
            yield 'declaration '.$field => ['data_bi_tenant_declarations', $field, $value];
        }
    }

    public function test_missing_schema_fails_closed_and_inputs_are_bounded(): void
    {
        $this->missing[] = 'data_bi_tenant_declarations.revision';
        $this->getReview()->assertStatus(503)->assertJsonPath('error.code', 'schema_unavailable');
        $this->missing = [];
        foreach (['?version=0', '?version=10000', '?version[]=1', '?before_version=abc', '?company_id=999'] as $query) {
            $this->getReview($query)->assertStatus(422);
        }
    }

    public function test_history_is_paginated_and_does_not_mix_selection_with_page_cursor(): void
    {
        $this->connection->rows['data_bi_information_requests'] = array_map($this->publication(...), range(1, 22));
        $this->getReview()->assertOk()->assertJsonCount(20, 'data.versions')->assertJsonPath('data.next_before_version', 3);
        $this->getReview('?before_version=3&version=1')->assertOk()->assertJsonCount(2, 'data.versions')
            ->assertJsonPath('data.current_version', 22)->assertJsonPath('data.selected.version', 1)->assertJsonPath('data.next_before_version', null);
    }
}

final class T4faReadConnection extends MySqlConnection
{
    public array $reads = [];

    public bool $pdoAttempted = false;

    public function __construct(public array $rows)
    {
        parent::__construct(function () {
            $this->pdoAttempted = true;
            throw new LogicException('PDO y escrituras prohibidos en QA pura.');
        }, 't4fa_read', '', ['name' => 't4fa_read']);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (! preg_match('/\Aselect .* from `([a-z_]+)`/is', $query, $match)) {
            throw new LogicException('SQL no permitido.');
        }
        $this->reads[] = [$query, $bindings];
        $rows = $this->rows[$match[1]] ?? [];
        if (str_contains($query, '0 = 1')) {
            return [];
        }
        preg_match_all('/`([a-z_]+)`\s*(=|<|in)\s*(\?|\([^)]*\))/i', $query, $conditions, PREG_SET_ORDER);
        $offset = 0;
        foreach ($conditions as $condition) {
            $values = array_slice($bindings, $offset, substr_count($condition[3], '?'));
            $offset += count($values);
            $rows = array_values(array_filter($rows, static fn (array $row): bool => match (strtolower($condition[2])) {
                '=' => ($row[$condition[1]] ?? null) == $values[0],
                '<' => ($row[$condition[1]] ?? null) < $values[0],
                'in' => in_array($row[$condition[1]] ?? null, $values),
            }));
        }
        if (str_contains($query, 'order by `version` desc')) {
            usort($rows, static fn ($a, $b) => $b['version'] <=> $a['version']);
        }
        if (preg_match('/limit (\d+)/i', $query, $limit)) {
            $rows = array_slice($rows, 0, (int) $limit[1]);
        }

        return array_map(static fn ($row): object => (object) $row, $rows);
    }
}
