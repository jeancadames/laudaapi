<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Models\User;
use App\Services\Diagnosis\DiagnosisExecutiveSummaryGenerator;
use App\Services\Diagnosis\DiagnosisExpandedReportGenerator;
use App\Services\Diagnosis\DiagnosisExpandedReportService;
use App\Services\Diagnosis\DiagnosisInterpretation;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Pure generators and publication barriers. No Laravel boot, PDO or SQL. */
final class DiagnosisConclusionQualityTest extends TestCase
{
    private Container $previous;
    private mixed $resolver;
    private Container $app;
    private array $facades;
    private array $modelState;

    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
        $this->resolver = Model::getConnectionResolver();
        $this->facades = [Facade::getFacadeApplication(), (new \ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $name) {
            $this->modelState[$name] = (new \ReflectionProperty(Model::class, $name))->getValue();
        }
        $this->app = new Container;
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();
        $this->app->instance('config', new Repository([
            'lauda360_diagnosis' => require dirname(__DIR__, 3).'/config/lauda360_diagnosis.php',
            'lauda360_business_profile' => require dirname(__DIR__, 3).'/config/lauda360_business_profile.php',
        ]));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'es'), $this->app));
        // Eloquent date formatting requires a grammar only; PDO throws if ever requested.
        $connection = new MySqlConnection(fn () => throw new RuntimeException('SQL prohibited in pure QA'));
        $connections = new ConnectionResolver(['pure' => $connection]);
        $connections->setDefaultConnection('pure');
        Model::setConnectionResolver($connections);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previous);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->facades[0]);
        (new \ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->facades[1]);
        foreach ($this->modelState as $name => $value) {
            (new \ReflectionProperty(Model::class, $name))->setValue(null, $value);
        }
        if ($this->resolver) {
            Model::setConnectionResolver($this->resolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    private function assessment(int $answer = 5): DiagnosisAssessment
    {
        $scoring = new Lauda360ScoringService;
        $answers = array_fill_keys($scoring->allQuestionIds(), $answer);

        return new DiagnosisAssessment([
            ...$scoring->calculate($answers),
            'organization_name' => 'Fixture declarada',
            'methodology_version' => '1.0',
            'status' => 'reviewed',
            'answers' => $answers,
            'notes' => ['STR-01' => 'Plan revisado cada trimestre.'],
            'business_activity_type' => 'services',
            'business_sector' => 'professional_services',
        ]);
    }

    public function test_eight_perfect_dimensions_have_no_invented_gaps_and_eight_strengths(): void
    {
        $assessment = $this->assessment();
        $sections = (new DiagnosisExpandedReportGenerator)->generate($assessment)['sections'];
        self::assertSame([], $sections['critical_gaps']['items']);
        self::assertSame([], $sections['improvement_opportunities']['items']);
        self::assertCount(8, $sections['relative_strengths']['items']);
        self::assertStringContainsString('No se identifican brechas críticas', $sections['critical_gaps']['empty_message']);
        $initial = (new DiagnosisExecutiveSummaryGenerator)->generate($assessment->toArray(), [], $assessment->answers, $assessment->notes);
        self::assertStringContainsString('No se identifican brechas de nivel crítico o inicial', $initial['summary']);
        foreach ($initial['priorities'] as $priority) {
            self::assertStringContainsString('Mantener la fortaleza declarada', $priority);
            self::assertStringNotContainsString('Brecha crítica', $priority);
        }
    }

    public function test_low_maturity_lists_all_justified_critical_gaps_not_only_three(): void
    {
        $assessment = $this->assessment(1);
        $sections = (new DiagnosisExpandedReportGenerator)->generate($assessment)['sections'];
        self::assertCount(8, $sections['critical_gaps']['items']);
        self::assertSame([], $sections['relative_strengths']['items']);
        foreach ($sections['critical_gaps']['items'] as $gap) {
            self::assertSame(0.0, $gap['score']);
            self::assertCount(5, $gap['evidence']);
            self::assertSame(1, $gap['evidence'][0]['answer']);
            self::assertStringContainsString('Riesgo potencial', $gap['impact']);
        }
    }

    #[DataProvider('classificationCases')]
    public function test_classification_uses_existing_methodological_levels(int $score, string $classification): void
    {
        $assessment = $this->assessment();
        $assessment->dimension_scores = array_fill_keys(array_keys($assessment->dimension_scores), $score);
        $sections = (new DiagnosisExpandedReportGenerator)->generate($assessment)['sections'];
        self::assertSame($classification, (new DiagnosisInterpretation)->classification($score));
        self::assertSame($classification, $sections['dimension_analysis']['items'][0]['classification']);
        self::assertCount($classification === 'critical' ? 8 : 0, $sections['critical_gaps']['items']);
        self::assertCount($classification === 'strength' ? 8 : 0, $sections['relative_strengths']['items']);
    }

    public static function classificationCases(): array
    {
        return [[0, 'critical'], [20, 'critical'], [21, 'gap'], [40, 'gap'], [41, 'opportunity'], [60, 'opportunity'], [61, 'opportunity'], [80, 'opportunity'], [81, 'strength'], [100, 'strength']];
    }

    #[DataProvider('indicatorCases')]
    public function test_initial_and_expanded_interpret_official_indicator_ranges(string $indicator, int $score, string $label): void
    {
        $assessment = $this->assessment();
        $assessment->{$indicator.'_score'} = $score;
        $assessment->maturity_level = 'Etiqueta anterior incompatible';
        $assessment->urgency_level = 'Etiqueta anterior incompatible';
        $sections = (new DiagnosisExpandedReportGenerator)->generate($assessment)['sections'];
        self::assertSame($label, (new DiagnosisInterpretation)->level($indicator, $score));
        self::assertStringContainsString(mb_strtolower($label), mb_strtolower($sections['maturity_interpretation'][$indicator]['interpretation']));
        $initial = (new DiagnosisExecutiveSummaryGenerator)->generate($assessment->toArray(), []);
        self::assertStringContainsString($label, $initial['summary']);
        self::assertStringNotContainsString('Etiqueta anterior incompatible', $initial['summary']);
    }

    public static function indicatorCases(): array
    {
        return [
            ['capacity', 0, 'Capacidad interna limitada'], ['capacity', 39, 'Capacidad interna limitada'],
            ['capacity', 40, 'Capacidad interna media'], ['capacity', 44, 'Capacidad interna media'],
            ['capacity', 69, 'Capacidad interna media'], ['capacity', 70, 'Capacidad interna alta'], ['capacity', 100, 'Capacidad interna alta'],
            ['urgency', 0, 'Baja'], ['urgency', 24, 'Baja'], ['urgency', 25, 'Media'], ['urgency', 49, 'Media'],
            ['urgency', 50, 'Alta'], ['urgency', 74, 'Alta'], ['urgency', 75, 'Crítica'], ['urgency', 100, 'Crítica'],
        ];
    }

    public function test_recommendations_and_snapshot_trace_actual_answers_and_notes_without_claiming_verification(): void
    {
        $assessment = $this->assessment(1);
        $assessment->notes = ['STR-01' => 'Sin plan; nota declarada. <script>alert(1)</script>', 'UNKNOWN' => 'No incorporar'];
        $before = $assessment->getAttributes();
        $report = (new DiagnosisExpandedReportGenerator)->generate($assessment);
        $initial = (new DiagnosisExecutiveSummaryGenerator)->generate($assessment->toArray(), [], $assessment->answers, $assessment->notes);
        self::assertStringContainsString('STR-01=1/5', $initial['priorities'][0]);
        self::assertStringContainsString('Sin plan; nota declarada.', $initial['priorities'][0]);
        self::assertStringContainsString('no constituye una verificación técnica', $initial['summary']);
        self::assertStringContainsString('no constituye una verificación técnica', $report['sections']['executive_summary']['body']);
        self::assertCount(51, $report['source_snapshot']['declared_evidence']);
        self::assertSame($assessment->notes['STR-01'], $report['source_snapshot']['declared_evidence'][0]['note']);
        self::assertNotContains('UNKNOWN', array_column($report['source_snapshot']['declared_evidence'], 'question_id'));
        self::assertSame($before, $assessment->getAttributes());
        $assessment->notes = ['STR-01' => 'Otra declaración'];
        self::assertNotSame($assessment->notes['STR-01'], $report['source_snapshot']['declared_evidence'][0]['note']);
        foreach ($initial['priorities'] as $priority) self::assertLessThanOrEqual(500, mb_strlen($priority));
    }

    public function test_missing_answers_are_explicitly_unavailable_not_technically_verified(): void
    {
        $assessment = $this->assessment(1);
        $assessment->answers = [];
        $assessment->notes = [];
        $report = (new DiagnosisExpandedReportGenerator)->generate($assessment);
        self::assertSame([], $report['source_snapshot']['declared_evidence']);
        self::assertSame([], $report['sections']['dimension_analysis']['items'][0]['evidence']);
        $initial = (new DiagnosisExecutiveSummaryGenerator)->generate($assessment->toArray(), []);
        self::assertStringContainsString('sin respuestas o notas asociadas disponibles', $initial['priorities'][0]);
    }

    public function test_identical_aggregate_scores_with_different_answers_have_different_traceable_recommendations(): void
    {
        $assessment = $this->assessment(3);
        $first = $assessment->answers;
        $first['STR-01'] = 1;
        $first['STR-02'] = 5;
        $second = $first;
        $second['STR-01'] = 5;
        $second['STR-02'] = 1;
        $scoring = new Lauda360ScoringService;
        self::assertSame($scoring->calculate($first), $scoring->calculate($second));
        $generator = new DiagnosisExecutiveSummaryGenerator;
        $a = $generator->generate($scoring->calculate($first), [], $first);
        $b = $generator->generate($scoring->calculate($second), [], $second);
        self::assertStringContainsString('STR-01=1/5', $a['priorities'][0]);
        self::assertStringContainsString('STR-02=1/5', $b['priorities'][0]);
        self::assertNotSame($a['priorities'], $b['priorities']);
    }

    public function test_scoring_weights_and_review_override_remain_independent_of_narrative(): void
    {
        $scoring = new Lauda360ScoringService;
        $answers = array_fill_keys($scoring->allQuestionIds(), 1);
        foreach (config('lauda360_diagnosis.dimensions.operations.questions') as $id) $answers[$id] = 5;
        foreach (config('lauda360_diagnosis.capacity_questions') as $id) $answers[$id] = 5;
        foreach (config('lauda360_diagnosis.urgency_questions') as $id) $answers[$id] = 5;
        $before = $scoring->calculate($answers);
        self::assertCount(51, $answers);
        self::assertSame(20, $before['maturity_score']);
        self::assertSame(100, $before['capacity_score']);
        self::assertSame(100, $before['urgency_score']);
        self::assertSame('assisted', $before['recommended_modality']);
        self::assertTrue($before['review_required']);
        (new DiagnosisExecutiveSummaryGenerator)->generate($before, [], $answers);
        self::assertSame($before, $scoring->calculate($answers));
    }

    public function test_free_conclusions_do_not_suggest_a_commercial_modality(): void
    {
        $assessment = $this->assessment();
        $initial = (new DiagnosisExecutiveSummaryGenerator)->generate($assessment->toArray(), []);
        $report = (new DiagnosisExpandedReportGenerator)->generate($assessment);
        foreach ([$initial['summary'], $report['sections']['executive_summary']['body']] as $text) {
            self::assertStringContainsString('gratuitos', $text);
            self::assertStringContainsString('fase posterior', $text);
            self::assertStringNotContainsString('LAUDA 360 Asistido', $text);
            self::assertStringNotContainsString('LAUDA 360 Guiado', $text);
        }
        self::assertArrayNotHasKey('modality', $report['source_snapshot']['official_result']);
    }

    public function test_empty_critical_gaps_pass_publication_validation_before_any_storage(): void
    {
        $report = new DiagnosisConclusionPublicationProbe([
            'status' => 'draft',
            'sections' => (new DiagnosisExpandedReportGenerator)->generate($this->assessment())['sections'],
        ]);
        try {
            (new DiagnosisExpandedReportService(new DiagnosisExpandedReportGenerator))->publish($report, new User(['id' => 91]));
            self::fail('Probe must stop before storage');
        } catch (RuntimeException $e) {
            self::assertSame('Publication barriers passed; storage prohibited', $e->getMessage());
            self::assertSame('published', $report->status);
        }
    }

    public function test_missing_critical_section_still_blocks_before_storage(): void
    {
        $sections = (new DiagnosisExpandedReportGenerator)->generate($this->assessment())['sections'];
        unset($sections['critical_gaps']);
        $report = new DiagnosisConclusionPublicationProbe(['status' => 'draft', 'sections' => $sections]);
        try {
            (new DiagnosisExpandedReportService(new DiagnosisExpandedReportGenerator))->publish($report, new User);
            self::fail('Missing section must block');
        } catch (ValidationException $e) {
            self::assertStringContainsString('critical_gaps.items', $e->errors()['report'][0]);
            self::assertSame('draft', $report->status);
        }
    }

    public function test_published_historical_snapshot_cannot_be_regenerated_or_republished(): void
    {
        $report = new DiagnosisExpandedReport([
            'version' => 2, 'status' => 'published',
            'source_snapshot' => ['official_result' => ['summary' => 'Historia validada']],
            'sections' => ['executive_summary' => ['body' => 'Historia validada']],
        ]);
        $before = $report->getAttributes();
        $service = new DiagnosisExpandedReportService(new DiagnosisExpandedReportGenerator);
        foreach (['publish', 'regenerateDraft'] as $method) {
            try {
                $service->{$method}($report, new User);
                self::fail('Historical version must remain immutable');
            } catch (ValidationException) {
                self::assertSame($before, $report->getAttributes());
            }
        }
    }
}

final class DiagnosisConclusionPublicationProbe extends DiagnosisExpandedReport
{
    public function save(array $options = [])
    {
        throw new RuntimeException('Publication barriers passed; storage prohibited');
    }
}
