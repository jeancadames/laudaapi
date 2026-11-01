<?php

declare(strict_types=1);

namespace Tests\Feature\Diagnosis;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\BuildsDataBiTenantDeclarationHttpApplication;

require_once __DIR__.'/DataBiDeclarativeReviewServiceMySqlTest.php';

/**
 * B3 HTTP MySQL PREPARADA, NO ejecutar sin autorización posterior.
 * D2F_T4F_B3_HTTP_MYSQL_QA=rollback-only + flags/credenciales B2 explícitos del arnés aprobado.
 * Reutiliza fixtures B2-M3-R2, transacción exterior, probe fresco, barreras SQL y cleanup.
 * No modifica filas existentes ni ejecuta migraciones; sesión/CSRF del HTTP son en memoria.
 */
#[Group('mysql-dev-t4fb3-http-rollback')]
final class DataBiDeclarativeReviewHttpMySqlTest extends TestCase
{
    use BuildsDataBiTenantDeclarationHttpApplication;

    private DataBiDeclarativeReviewServiceMySqlTest $fixture;

    private bool $ready = false;

    private bool $httpStarted = false;

    private string $adminBase;

    private string $tenantBase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('D2F_T4F_B3_HTTP_MYSQL_QA') !== 'rollback-only') {
            self::markTestSkipped('MYSQL_HTTP_QA=PENDING: requiere autorización B3 explícita.');
        }
        $this->fixture = new DataBiDeclarativeReviewServiceMySqlTest('test_lifecycle_and_all_retries_preserve_events_audit_and_original_data');
        $this->call('setUp'); // B2 valida local/DEV/credenciales/InnoDB/triggers ANTES de permitir fixtures.
        $this->ready = true;
        try {
            $manager = $this->property('capsule')->getDatabaseManager();
            $this->httpStarted = true;
            $this->startHttpApplication();
            $this->httpApp->instance('db', $manager);
            $this->httpApp->bind('db.schema', fn () => $manager->connection()->getSchemaBuilder());
            DB::clearResolvedInstance('db');
            Schema::clearResolvedInstance('db.schema');
            // Conserva el resolver/dispatcher de los modelos y la conexión rollback-only del fixture B2.
            $this->httpApp['router']->middleware('web')->group(dirname(__DIR__, 3).'/routes/admin.php');
            $this->httpApp['router']->getRoutes()->refreshNameLookups();
            $id = $this->property('request')->id;
            $this->adminBase = '/admin/transformation-360/implementation-requests/'.$id.'/declarative-review';
            $this->tenantBase = '/app/transformacion-360/datos-bi/requerimientos/'.$id.'/aclaraciones';
            $this->loginAs($this->property('admin'));
        } catch (\Throwable $e) {
            if ($this->httpStarted) {
                $this->stopHttpApplication();
                $this->httpStarted = false;
            }
            $this->call('tearDown');
            $this->ready = false;
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->httpStarted) {
                $this->stopHttpApplication();
                $this->httpStarted = false;
            }
        } finally {
            try {
                if ($this->ready) {
                    $this->call('tearDown');
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    private function property(string $name): mixed
    {
        return (new ReflectionProperty($this->fixture, $name))->getValue($this->fixture);
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($this->fixture, $method))->invoke($this->fixture, ...$arguments);
    }

    private function loginAs(?User $user): void
    {
        $this->httpApp['auth']->guard()->forgetUser();
        if ($user !== null) {
            $this->httpApp['auth']->guard()->setUser($user);
        }
    }

    private function http(string $method, string $url, array|string $body = '', bool $csrf = true)
    {
        return $this->declarationHttp($method, $url, $body, $csrf);
    }

    private function newQuestion(): array
    {
        $this->loginAs($this->property('admin'));

        return $this->http('POST', $this->adminBase.'/clarifications', [...$this->property('question'), 'expected_revision' => 0])
            ->assertOk()->json('data.clarifications.0');
    }

    public function test_real_binding_session_csrf_lifecycle_and_idempotent_posts(): void
    {
        $question = $this->newQuestion();
        $before = $this->property('dml');
        $this->http('POST', $this->adminBase.'/clarifications', [...$this->property('question'), 'expected_revision' => 0])->assertOk()
            ->assertJsonPath('data.clarifications.0.id', $question['id']);
        self::assertSame($before, $this->property('dml'));
        $version = $this->property('question')['version'];
        $this->loginAs($this->property('tenant'));
        $answerUrl = $this->tenantBase.'/'.$question['id'].'/responder';
        $input = ['version' => $version, 'message' => 'Descripción declarativa aclarada.', 'expected_revision' => 1];
        $answer = $this->http('POST', $answerUrl, $input)->assertOk()->assertJsonPath('data.clarifications.0.status', 'answered')->json();
        $before = $this->property('dml');
        self::assertSame($answer, $this->http('POST', $answerUrl, $input)->assertOk()->json());
        self::assertSame($before, $this->property('dml'));
        $this->loginAs($this->property('admin'));
        $resolveUrl = $this->adminBase.'/clarifications/'.$question['id'].'/resolve';
        $input = ['version' => $version, 'message' => 'Alcance revisado.', 'expected_revision' => 2];
        $resolved = $this->http('POST', $resolveUrl, $input)->assertOk()->assertJsonPath('data.clarifications.0.status', 'resolved')->json();
        $before = $this->property('dml');
        self::assertSame($resolved, $this->http('POST', $resolveUrl, $input)->assertOk()->json());
        self::assertSame($before, $this->property('dml'));
        $input = [...$this->property('conclusion'), 'expected_revision' => 0];
        $closed = $this->http('POST', $this->adminBase.'/close', $input)->assertOk()->assertJsonPath('data.review_status', 'closed')->json();
        self::assertNotEmpty($closed['data']['closure']['declared_gaps']);
        $before = $this->property('dml');
        self::assertSame($closed, $this->http('POST', $this->adminBase.'/close', $input)->assertOk()->json());
        self::assertSame($before, $this->property('dml'));
    }

    public function test_admin_and_tenant_gets_have_no_domain_dml_or_internal_fields(): void
    {
        $this->newQuestion();
        $request = $this->property('request');
        $questionnaire = $this->call('getFixture', 'questionnaire');
        $requestBefore = $request->getRawOriginal();
        $publicationBefore = $questionnaire->getRawOriginal();
        $declaration = (array) DB::table('data_bi_tenant_declarations')->where('data_bi_information_request_id', $questionnaire->id)->first();
        $before = $this->property('dml');
        foreach ([[$this->property('admin'), $this->adminBase], [$this->property('tenant'), $this->tenantBase]] as [$user, $url]) {
            $this->loginAs($user);
            $response = $this->http('GET', $url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            foreach (['content_sha256', 'actor_user_id', 'closed_by_user_id', 'company_id', 'clarification_snapshot', 'closure_snapshot'] as $key) {
                self::assertStringNotContainsString('"'.$key.'"', $response->getContent());
            }
        }
        self::assertSame($before, $this->property('dml'));
        self::assertSame($requestBefore, $request->fresh()->getRawOriginal());
        self::assertSame($publicationBefore, $questionnaire->fresh()->getRawOriginal());
        self::assertSame($declaration, (array) DB::table('data_bi_tenant_declarations')->where('id', $declaration['id'])->first());
    }

    public function test_cancellation_and_revision_conflicts_do_not_append_events(): void
    {
        $question = $this->newQuestion();
        $input = ['version' => $this->property('question')['version'], 'message' => 'Retirada justificada.', 'expected_revision' => 1];
        $url = $this->adminBase.'/clarifications/'.$question['id'].'/cancel';
        $cancelled = $this->http('POST', $url, $input)->assertOk()->assertJsonPath('data.clarifications.0.status', 'cancelled')->json();
        $before = $this->property('dml');
        self::assertSame($cancelled, $this->http('POST', $url, $input)->assertOk()->json());
        $this->assertDeclarationError($this->http('POST', $url, [...$input, 'message' => 'Cambio obsoleto.']), 409, 'revision_conflict');
        self::assertSame($before, $this->property('dml'));
    }

    public function test_permissions_csrf_and_foreign_ids_precede_payload_validation(): void
    {
        $question = $this->newQuestion();
        $before = $this->property('dml');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close', [], false), 419, 'csrf_token_mismatch');
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder', '{bad'), 404, 'not_found');
        $this->loginAs($this->property('tenant'));
        $this->assertDeclarationError($this->http('GET', $this->adminBase), 403, 'forbidden');
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/9223372036854775807/responder', '{bad'), 404, 'not_found');
        self::assertSame($before, $this->property('dml'));
        $this->loginAs(null);
        $this->assertDeclarationError($this->http('GET', $this->tenantBase), 401, 'unauthenticated');
    }

    public function test_open_closure_unknown_attributes_and_stale_revision_are_rejected_without_dml(): void
    {
        $question = $this->newQuestion();
        $before = $this->property('dml');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close', [...$this->property('conclusion'), 'expected_revision' => 0]), 409, 'clarifications_open');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications', [...$this->property('question'), 'expected_revision' => 0, 'actor_id' => 1]), 422, 'validation_failed');
        $this->loginAs($this->property('tenant'));
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder',
            ['version' => $this->property('question')['version'], 'message' => 'Respuesta.', 'expected_revision' => 0]), 409, 'revision_conflict');
        self::assertSame($before, $this->property('dml'));
    }

    public function test_published_replacement_keeps_history_and_blocks_old_operations(): void
    {
        $question = $this->newQuestion();
        $version = $this->property('question')['version'];
        $this->call('invokeFixture', 'publish');
        $this->loginAs($this->property('tenant'));
        $before = $this->property('dml');
        $this->http('GET', $this->tenantBase.'?version='.$version)->assertOk()->assertJsonPath('data.is_current', false)
            ->assertJsonPath('data.clarifications.0.allowed_actions.answer', false);
        $this->assertDeclarationError($this->http('POST', $this->tenantBase.'/'.$question['id'].'/responder',
            ['version' => $version, 'message' => 'Respuesta.', 'expected_revision' => 1]), 409, 'questionnaire_replaced');
        $this->loginAs($this->property('admin'));
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/clarifications',
            [...$this->property('question'), 'expected_revision' => 0]), 409, 'questionnaire_replaced');
        $this->assertDeclarationError($this->http('POST', $this->adminBase.'/close',
            [...$this->property('conclusion'), 'expected_revision' => 0]), 409, 'questionnaire_replaced');
        self::assertSame($before, $this->property('dml'));
    }
}
