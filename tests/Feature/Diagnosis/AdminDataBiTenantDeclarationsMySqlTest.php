<?php

namespace Tests\Feature\Diagnosis;

use App\Models\User;
use App\Services\Diagnosis\DataBiTenantDeclarationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

require_once __DIR__.'/DataBiTenantDeclarationHttpMySqlTest.php';

/**
 * T4F-A: preparada, NO ejecutar sin autorización posterior.
 * D2F_T4F_A_MYSQL_QA=rollback-only + APP_ENV=local y D2F_T4D_* del harness aprobado.
 * Composición del fixture privado existente: no ejecuta sus tests ni lo modifica.
 * Conserva conexión rollback-only, fixtures propios, visibilidad TRIGGER y cleanup.
 */
#[Group('mysql-dev-t4fa-rollback')]
final class AdminDataBiTenantDeclarationsMySqlTest extends TestCase
{
    private DataBiTenantDeclarationHttpMySqlTest $fixture;

    private bool $ready = false;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('D2F_T4F_A_MYSQL_QA') !== 'rollback-only') {
            self::markTestSkipped('MYSQL_HTTP_QA=PENDING: requiere autorización explícita T4F-A.');
        }
        $this->fixture = new DataBiTenantDeclarationHttpMySqlTest('test_get_without_declaration_is_explicit_and_performs_no_dml');
        $this->invoke('setUp');
        $this->ready = true;
        $app = $this->property('httpApp');
        $app['router']->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
        $app['router']->getRoutes()->refreshNameLookups();
        $this->admin = $this->invoke('tenant');
        DB::table('users')->where('id', $this->admin->id)->update(['role' => 'admin']);
        DB::table('subscriber_user')->where('user_id', $this->admin->id)->delete();
        $this->admin->refresh();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ready) {
                $this->invoke('tearDown');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function property(string $name): mixed
    {
        return (new ReflectionProperty($this->fixture, $name))->getValue($this->fixture);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod($this->fixture, $method))->invoke($this->fixture, ...$args);
    }

    private function read(string $query = ''): TestResponse
    {
        $this->invoke('authenticate', $this->admin);
        $before = $this->property('dml');
        $response = $this->invoke('declarationHttp', 'GET', '/admin/transformation-360/implementation-requests/'
            .$this->property('request')->getKey().'/information-requirements/tenant-declarations'.$query, '');
        self::assertSame($before, $this->property('dml'), 'El GET no debe ejecutar DML ni cambiar auditoría.');
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));

        return $response;
    }

    private function submit()
    {
        $this->invoke('authenticate', $this->property('actor'));

        return (new DataBiTenantDeclarationService)->submit($this->property('request'), $this->invoke('input'), 0);
    }

    public function test_admin_receives_valid_submission_without_altering_any_row(): void
    {
        $declaration = $this->submit();
        $before = $declaration->fresh()->getRawOriginal();
        $questionnaire = $this->property('questionnaire')->getRawOriginal();
        $requested = count($this->property('questionnaire')->review_snapshot['requirements']);
        $response = $this->read()->assertOk()->assertJsonPath('data.selected.declaration.status', 'submitted')
            ->assertJsonPath('data.selected.coverage.requested', $requested)
            ->assertJsonPath('data.selected.coverage.available', $requested)
            ->assertJsonPath('data.selected.coverage.sources', $requested)
            ->assertJsonPath('data.selected.coverage.structures', $requested);
        foreach (['content_sha256', 'questionnaire_sha256', 'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id', 'declaration_snapshot'] as $field) {
            self::assertStringNotContainsString('"'.$field.'"', $response->getContent());
        }
        self::assertSame($before, $declaration->fresh()->getRawOriginal());
        self::assertSame($questionnaire, $this->property('questionnaire')->fresh()->getRawOriginal());
    }

    public function test_missing_declaration_and_tenant_draft_remain_pending(): void
    {
        $this->read()->assertOk()->assertJsonPath('data.selected.declaration', null);
        $this->invoke('authenticate', $this->property('actor'));
        $input = $this->invoke('input', false);
        $input['declarations'][] = ['requirement_key' => $this->property('questionnaire')->review_snapshot['requirements'][0]['key'], 'notes' => 'PRIVATE_TENANT_DRAFT'];
        $draft = (new DataBiTenantDeclarationService)->saveDraft($this->property('request'), $input, 0);
        $before = $draft->fresh()->getRawOriginal();
        $response = $this->read()->assertOk()->assertJsonPath('data.selected.declaration', null)
            ->assertJsonPath('data.selected.coverage', null)->assertJsonPath('data.versions.0.revision', null);
        self::assertStringNotContainsString('PRIVATE_TENANT_DRAFT', $response->getContent());
        self::assertSame($before, $draft->fresh()->getRawOriginal());
    }

    public function test_tenant_account_cannot_use_admin_endpoint(): void
    {
        $this->invoke('authenticate', $this->property('actor'));
        $before = $this->property('dml');
        $this->invoke('declarationHttp', 'GET', '/admin/transformation-360/implementation-requests/'
            .$this->property('request')->getKey().'/information-requirements/tenant-declarations', '')
            ->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        self::assertSame($before, $this->property('dml'));
    }

    public function test_historical_submission_is_separate_from_new_pending_questionnaire(): void
    {
        $declaration = $this->submit();
        $oldVersion = $this->property('questionnaire')->version;
        $before = $declaration->fresh()->getRawOriginal();
        $new = $this->invoke('publish');
        $this->read()->assertOk()->assertJsonPath('data.current_version', $new->version)
            ->assertJsonPath('data.selected.version', $new->version)->assertJsonPath('data.selected.declaration', null);
        $this->read('?version='.$oldVersion)->assertOk()->assertJsonPath('data.selected.version', $oldVersion)
            ->assertJsonPath('data.selected.declaration.status', 'submitted');
        self::assertSame($before, $declaration->fresh()->getRawOriginal());
    }

    public function test_corrupted_snapshot_fails_closed_without_repairing_it(): void
    {
        $declaration = $this->submit();
        DB::table('data_bi_tenant_declarations')->where('id', $declaration->id)->update(['content_sha256' => str_repeat('f', 64)]);
        $before = $declaration->fresh()->getRawOriginal();
        $response = $this->read()->assertStatus(409)->assertJsonPath('error.code', 'declaration_integrity_conflict');
        self::assertArrayNotHasKey('data', $response->json());
        self::assertSame($before, $declaration->fresh()->getRawOriginal());
    }

    public function test_incomplete_submission_audit_is_not_presented_as_received(): void
    {
        $declaration = $this->submit();
        DB::table('data_bi_tenant_declarations')->where('id', $declaration->id)->update(['submitted_by_user_id' => null]);
        $this->read()->assertStatus(409)->assertJsonPath('error.code', 'declaration_integrity_conflict');
    }

    public function test_wrong_company_and_wrong_capability_are_not_exposed(): void
    {
        $declaration = $this->submit();
        $other = DB::table('companies')->insertGetId(['name' => 'T4F-A otro ámbito sintético', 'slug' => 't4fa-'.bin2hex(random_bytes(12))]);
        DB::table('data_bi_tenant_declarations')->where('id', $declaration->id)->update(['company_id' => $other]);
        $this->read()->assertStatus(409);
        DB::table('transformation_implementation_requests')->where('id', $this->property('request')->getKey())->update(['capability_key' => 'other']);
        $this->read()->assertNotFound();
    }
}
