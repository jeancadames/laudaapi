<?php

namespace Tests\Unit\Diagnosis;

use App\Http\Controllers\Diagnosis\DigitalDiagnosisController;
use App\Http\Requests\Diagnosis\UpdateDiagnosisAssessmentRequest;
use App\Http\Requests\Diagnosis\SubmitDiagnosisAssessmentRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Policies\DiagnosisAssessmentPolicy;
use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use App\Services\Diagnosis\DiagnosisDraftRevision;
use App\Services\Diagnosis\DiagnosisAssessmentCompletenessService;
use App\Services\Diagnosis\DiagnosisExecutiveSummaryGenerator;
use App\Services\Diagnosis\Lauda360ScoringService;
use App\Services\Diagnosis\InitialDiagnosisCommercialService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/** Real policy, controller and Eloquent against explicit PHP storage; no PDO or app boot. */
final class DiagnosisFunctionalCorrectionsTest extends TestCase
{
    private array $previous;
    private Container $app;
    private DiagnosisCorrectionsMemory $db;
    private array $modelState;

    protected function setUp(): void
    {
        $this->previous = [Container::getInstance(), Facade::getFacadeApplication(),
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue(),
            Model::getConnectionResolver(), Model::getEventDispatcher()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
            $this->modelState[$name] = (new ReflectionProperty(Model::class, $name))->getValue();
        }
        $this->app = new Container;
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();
        $this->app->instance('config', new Repository([
            'lauda360_business_profile' => require dirname(__DIR__, 3).'/config/lauda360_business_profile.php',
            'lauda360_diagnosis' => require dirname(__DIR__, 3).'/config/lauda360_diagnosis.php',
        ]));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $this->app));
        $this->db = new DiagnosisCorrectionsMemory;
        Model::setConnectionResolver(new ConnectionResolver(['pure' => $this->db]));
        Model::getConnectionResolver()->setDefaultConnection('pure');
        Model::unsetEventDispatcher();
        Model::clearBootedModels();
        DB::swap($this->db);
        Gate::swap(new class {
            public function authorize($ability, $assessment): void
            {
                if (! $assessment->isEditable()) throw new \Illuminate\Auth\Access\AuthorizationException;
            }
        });
        $session = new Store('pure', new ArraySessionHandler(120));
        $routes = new \Illuminate\Routing\RouteCollection;
        $routes->add((new \Illuminate\Routing\Route('GET', 'diagnostico/{assessment}', fn () => null))->name('diagnosis.show'));
        $url = new \Illuminate\Routing\UrlGenerator($routes, \Illuminate\Http\Request::create('http://localhost/diagnostico/50'));
        $redirect = new \Illuminate\Routing\Redirector($url);
        $redirect->setSession($session);
        $this->app->instance('redirect', $redirect);
    }

    protected function tearDown(): void
    {
        self::assertFalse($this->db->pdoAttempted);
        Container::setInstance($this->previous[0]);
        Facade::setFacadeApplication($this->previous[1]);
        (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previous[2]);
        $this->previous[3] ? Model::setConnectionResolver($this->previous[3]) : Model::unsetConnectionResolver();
        $this->previous[4] ? Model::setEventDispatcher($this->previous[4]) : Model::unsetEventDispatcher();
        foreach ($this->modelState as $name => $value) {
            (new ReflectionProperty(Model::class, $name))->setValue(null, $value);
        }
    }

    private function assessment(array $overrides = []): DiagnosisAssessment
    {
        $a = (new DiagnosisAssessment)->setRawAttributes(array_replace($this->db->assessment, $overrides), true);
        $a->exists = true;
        return $a;
    }

    private function user(int $id = 20, string $role = 'subscriber'): User
    {
        return (new User)->setRawAttributes(['id' => $id, 'role' => $role], true);
    }

    private function profile(): array
    {
        return (new DiagnosisBusinessProfileService)->normalize([
            'business_activity_type' => 'services', 'business_sector' => 'logistics',
            'customer_market' => 'b2b', 'sales_channels' => ['contracts'],
            'logistics_operation_types' => ['warehousing'],
            'business_activity_description' => 'Operación logística y almacenaje para empresas.',
        ]);
    }

    public function test_admin_profile_payload_contains_all_nine_fields_and_actual_completeness(): void
    {
        $a = $this->assessment();
        $a->fill($this->profile());
        $a->business_profile_completed_at = '2026-11-03 10:00:00';
        $payload = (new DiagnosisBusinessProfileService)->assessmentPayload($a);
        self::assertSame([...DiagnosisBusinessProfileService::FIELDS, 'business_profile_completed_at', 'business_profile_complete'], array_keys($payload));
        self::assertTrue($payload['business_profile_complete']);
        self::assertSame(['warehousing'], $payload['logistics_operation_types']);
        self::assertNotNull($payload['business_profile_completed_at']);
        self::assertArrayNotHasKey('user_id', $payload);
        self::assertArrayNotHasKey('notes', $payload);
    }

    public function test_completed_timestamp_does_not_hide_an_incomplete_profile(): void
    {
        $a = $this->assessment(['business_profile_completed_at' => '2026-11-03 10:00:00']);
        self::assertFalse((new DiagnosisBusinessProfileService)->assessmentPayload($a)['business_profile_complete']);
    }

    public function test_native_assigned_owner_requires_current_active_membership(): void
    {
        $policy = new DiagnosisAssessmentPolicy;
        self::assertTrue($policy->update($this->user(), $this->assessment()));
        $this->db->member = false;
        $this->db->invitation = ['meta' => json_encode(['source' => InitialDiagnosisCommercialService::SOURCE])];
        self::assertFalse($policy->view($this->user(), $this->assessment()));
        self::assertFalse($policy->submit($this->user(), $this->assessment()));
    }

    public function test_unrelated_and_regular_members_cannot_use_assignment_as_bypass(): void
    {
        $this->db->member = false;
        self::assertFalse((new DiagnosisAssessmentPolicy)->update($this->user(21), $this->assessment()));
        self::assertFalse((new DiagnosisAssessmentPolicy)->update($this->user(), $this->assessment()));
    }

    public function test_active_owner_can_access_company_assessment_assigned_to_another_user(): void
    {
        self::assertTrue((new DiagnosisAssessmentPolicy)->view($this->user(), $this->assessment(['user_id' => 21])));
    }

    public function test_inactive_company_and_assessment_are_denied(): void
    {
        $this->db->companyActive = false;
        self::assertFalse((new DiagnosisAssessmentPolicy)->view($this->user(), $this->assessment()));
        self::assertFalse((new DiagnosisAssessmentPolicy)->view($this->user(), $this->assessment(['is_active' => 0])));
    }

    public function test_legitimate_standalone_invitation_remains_individual_access(): void
    {
        $a = $this->assessment(['organization_id' => null]);
        self::assertTrue((new DiagnosisAssessmentPolicy)->update($this->user(), $a));
        self::assertFalse((new DiagnosisAssessmentPolicy)->view($this->user(21), $a));
    }

    public function test_historical_invitation_with_company_is_not_confused_with_native_assignment(): void
    {
        $this->db->member = false;
        $this->db->invitation = ['meta' => json_encode(['source' => 'historical_invitation'])];
        self::assertTrue((new DiagnosisAssessmentPolicy)->view($this->user(), $this->assessment()));
        $this->db->invitation = null; // no active approved/sent/accepted grant
        self::assertFalse((new DiagnosisAssessmentPolicy)->view($this->user(), $this->assessment()));
    }

    public function test_platform_admin_can_review_but_cannot_edit_or_submit(): void
    {
        $p = new DiagnosisAssessmentPolicy;
        $admin = $this->user(99, 'admin');
        self::assertTrue($p->view($admin, $this->assessment()));
        self::assertFalse($p->update($admin, $this->assessment()));
        self::assertFalse($p->submit($admin, $this->assessment()));
    }

    public function test_submitted_assessment_cannot_be_edited(): void
    {
        self::assertFalse((new DiagnosisAssessmentPolicy)->update($this->user(), $this->assessment(['status' => 'submitted'])));
    }

    public function test_revision_detects_same_timestamp_changes_to_answers_notes_profile_step_or_status(): void
    {
        $service = new DiagnosisDraftRevision;
        $a = $this->assessment();
        $expected = $service->forAssessment($a);
        foreach (['answers' => ['STR-01' => 5], 'notes' => ['STR-01' => 'otra nota'], 'business_sector' => 'other', 'current_step' => 2, 'status' => 'submitted'] as $key => $value) {
            $other = clone $a;
            $other->$key = $value;
            self::assertNotSame($expected, $service->forAssessment($other));
        }
        $service->assertCurrent($a, $expected);
        $this->addToAssertionCount(1);
    }

    private function saveDraft(string $expected, int $step = 2): RedirectResponse
    {
        $request = new class extends UpdateDiagnosisAssessmentRequest {
            public array $testData;
            public function validated($key = null, $default = null) { return $this->testData; }
        };
        $request->testData = [
            'answers' => ['STR-01' => 4], 'notes' => ['STR-01' => 'Evidencia conservada'],
            'current_step' => $step, 'expected_revision' => $expected, ...$this->profile(),
        ];
        return (new DigitalDiagnosisController)->update($request, $this->assessment(), new DiagnosisBusinessProfileService, new DiagnosisDraftRevision);
    }

    public function test_controller_saves_destination_and_notes_only_after_locked_version_check(): void
    {
        $revision = (new DiagnosisDraftRevision)->forAssessment($this->assessment());
        self::assertSame(302, $this->saveDraft($revision)->getStatusCode());
        self::assertTrue($this->db->locked);
        self::assertSame(1, $this->db->writes);
        self::assertSame(2, $this->db->assessment['current_step']);
        self::assertSame(['STR-01' => 'Evidencia conservada'], json_decode($this->db->assessment['notes'], true));
        self::assertSame('in_progress', $this->db->assessment['status']);
    }

    public function test_stale_second_writer_cannot_overwrite_the_first_save(): void
    {
        $revision = (new DiagnosisDraftRevision)->forAssessment($this->assessment());
        $this->saveDraft($revision);
        $first = $this->db->assessment;
        try { $this->saveDraft($revision, 3); self::fail('Stale writer accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('expected_revision', $e->errors()); }
        self::assertSame($first, $this->db->assessment);
        self::assertSame(1, $this->db->writes);
    }

    private function submission(array $answers): SubmitDiagnosisAssessmentRequest
    {
        $request = new class extends SubmitDiagnosisAssessmentRequest {
            public array $testData;
            public function validated($key = null, $default = null) { return $this->testData; }
        };
        $request->testData = [
            'answers' => $answers, 'notes' => ['STR-01' => 'Nota al enviar'],
            'expected_revision' => (new DiagnosisDraftRevision)->forAssessment($this->assessment()),
            ...$this->profile(),
        ];
        return $request;
    }

    private function submitRequest(SubmitDiagnosisAssessmentRequest $request): RedirectResponse
    {
        return (new DigitalDiagnosisController)->submit($request, $this->assessment(),
            new Lauda360ScoringService, new DiagnosisBusinessProfileService,
            new DiagnosisExecutiveSummaryGenerator,
            new DiagnosisAssessmentCompletenessService(new DiagnosisBusinessProfileService),
            new DiagnosisDraftRevision);
    }

    public function test_final_submission_uses_51_answers_and_existing_scoring_without_commercial_writes(): void
    {
        $answers = array_fill_keys((new Lauda360ScoringService)->allQuestionIds(), 4);
        self::assertCount(51, $answers);
        self::assertSame(302, $this->submitRequest($this->submission($answers))->getStatusCode());
        self::assertSame('submitted', $this->db->assessment['status']);
        self::assertSame(11, $this->db->assessment['current_step']);
        self::assertSame(75, $this->db->assessment['maturity_score']);
        self::assertSame(1, $this->db->writes);
    }

    public function test_incomplete_final_submission_still_fails_before_any_write(): void
    {
        try { $this->submitRequest($this->submission(['STR-01' => 4])); self::fail('Incomplete submission accepted'); }
        catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        self::assertSame(0, $this->db->writes);
    }

    public function test_submission_conclusions_use_submitted_answers_and_notes_instead_of_old_draft(): void
    {
        $ids = (new Lauda360ScoringService)->allQuestionIds();
        $this->db->assessment['answers'] = json_encode(array_fill_keys($ids, 1));
        $this->db->assessment['notes'] = json_encode(['STR-01' => 'Nota antigua']);
        $this->submitRequest($this->submission(array_fill_keys($ids, 4)));
        $priorities = implode(' ', json_decode($this->db->assessment['review_priorities'], true));
        self::assertStringContainsString('STR-01=4/5', $priorities);
        self::assertStringContainsString('Nota al enviar', $priorities);
        self::assertStringNotContainsString('Nota antigua', $priorities);
        self::assertStringNotContainsString('STR-01=1/5', $priorities);
    }

    public function test_submit_and_update_require_the_loaded_draft_revision(): void
    {
        foreach ([new UpdateDiagnosisAssessmentRequest, new SubmitDiagnosisAssessmentRequest] as $request) {
            self::assertContains('required', $request->rules()['expected_revision']);
            self::assertContains('regex:/\A[0-9a-f]{64}\z/', $request->rules()['expected_revision']);
        }
    }

    public function test_replayed_submission_cannot_write_or_score_a_second_time(): void
    {
        $request = $this->submission(array_fill_keys((new Lauda360ScoringService)->allQuestionIds(), 4));
        $this->submitRequest($request);
        try { $this->submitRequest($request); self::fail('Second submission accepted'); }
        catch (\Illuminate\Auth\Access\AuthorizationException) { self::assertSame(1, $this->db->writes); }
    }

    public function test_questionnaire_routes_are_registered_once_with_auth_verified_and_web_csrf(): void
    {
        $router = new class(new Dispatcher($this->app), $this->app) extends Router {
            public array $registrations = [];
            public function addRoute($methods, $uri, $action) {
                $route = parent::addRoute($methods, $uri, $action);
                $this->registrations[] = $route;
                return $route;
            }
        };
        Route::swap($router);
        $router->middlewareGroup('web', (new \Illuminate\Foundation\Configuration\Middleware)->getMiddlewareGroups()['web']);
        $router->middleware('web')->group(function (): void { require dirname(__DIR__, 3).'/routes/web.php'; });
        $router->getRoutes()->refreshNameLookups();
        foreach (['show' => 'GET', 'update' => 'PATCH', 'submit' => 'POST'] as $action => $method) {
            $routes = array_values(array_filter($router->registrations, fn ($r) => $r->getName() === 'diagnosis.'.$action));
            self::assertCount(1, $routes);
            self::assertContains($method, $routes[0]->methods());
            foreach (['web', 'auth', 'verified'] as $required) self::assertContains($required, $routes[0]->gatherMiddleware());
            self::assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $router->getMiddlewareGroups()['web']);
        }
        $signatures = [];
        foreach ($router->registrations as $route) {
            if (! preg_match('#^diagnostico/\{assessment\}(?:/submit)?$#', $route->uri())) continue;
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $signature = $method.' '.$route->uri();
                self::assertArrayNotHasKey($signature, $signatures, 'Duplicate questionnaire method/URI, regardless of route name.');
                $signatures[$signature] = true;
            }
        }
        self::assertCount(3, $signatures);
    }
}

final class DiagnosisCorrectionsMemory extends MySqlConnection
{
    public bool $pdoAttempted = false;
    public bool $member = true;
    public bool $companyActive = true;
    public ?array $invitation = null;
    public bool $locked = false;
    public int $writes = 0;
    public array $assessment = ['id' => 50, 'user_id' => 20, 'organization_id' => 7, 'is_active' => 1,
        'status' => 'draft', 'current_step' => 1, 'answers' => '{}', 'notes' => '{}', 'updated_at' => '2026-11-03 10:00:00'];

    public function __construct()
    {
        parent::__construct(function () { $this->pdoAttempted = true; throw new LogicException('PDO_FORBIDDEN'); }, 'pure', '', ['driver' => 'mysql']);
    }

    public function transaction(\Closure $callback, $attempts = 1) { return $callback($this); }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (str_starts_with($query, 'select * from `companies`') && $bindings === [7, true]) {
            return $this->companyActive ? [(object) ['id' => 7, 'subscriber_id' => 17, 'active' => 1]] : [];
        }
        if (str_starts_with($query, 'select exists(') && str_contains($query, '`subscribers`') && in_array($bindings[0], [20, 21], true) && $bindings === [$bindings[0], true, 17, 'owner', 'admin']) {
            return [(object) ['exists' => (int) ($bindings[0] === 20 && $this->member)]];
        }
        if (str_starts_with($query, 'select `meta` from `diagnosis_access_requests`') && $bindings === [50, 20, 'approved', 'invited', 'active'] && str_contains($query, '`invitation_sent_at` is not null') && str_contains($query, '`invitation_accepted_at` is not null')) {
            return $this->invitation ? [(object) $this->invitation] : [];
        }
        if ($query === 'select * from `diagnosis_assessments` where `diagnosis_assessments`.`id` = ? limit 1 for update' && $bindings === [50]) {
            $this->locked = true;
            return [(object) $this->assessment];
        }
        throw new LogicException('UNEXPECTED_READ: '.$query.' '.json_encode($bindings));
    }

    public function update($query, $bindings = [])
    {
        if (! $this->locked || !str_starts_with($query, 'update `diagnosis_assessments` set ') || end($bindings) !== 50) throw new LogicException('UNEXPECTED_WRITE');
        preg_match_all('/`([a-z_]+)` = \?/', explode(' where ', $query)[0], $m);
        if (count($m[1]) !== count($bindings) - 1) throw new LogicException('UPDATE_SHAPE');
        foreach ($m[1] as $i => $column) $this->assessment[$column] = $bindings[$i];
        ++$this->writes;
        return 1;
    }
}
