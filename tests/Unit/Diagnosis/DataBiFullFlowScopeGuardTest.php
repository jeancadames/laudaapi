<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use App\Models\DataBiDeclarationClarification;
use App\Models\DataBiDeclarationClarificationEvent;
use App\Models\DataBiDeclarativeReviewClosure;
use App\Models\DataBiInformationRequest;
use App\Models\DataBiTenantDeclaration;
use App\Services\Diagnosis\DataBiDeclarativeReviewPersistencePolicy as ReviewPolicy;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract as DeclarationContract;
use App\Services\Diagnosis\DataBiTenantDeclarationPersistencePolicy as DeclarationPolicy;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MySqlConnection;
use Illuminate\Events\Dispatcher;
use LogicException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\BuildsDataBiDeclarativeReviewContexts;
use Tests\Support\DataBiDeclarativeReviewRollbackConnection;
use Tests\Support\DataBiFullFlowScopeGuard;

final class DataBiFullFlowScopeGuardTest extends TestCase
{
    use BuildsDataBiDeclarativeReviewContexts;

    private const OWNED = ['users' => [11 => 11], 'companies' => [22 => 22], 'data_bi_information_requests' => [33 => 33]];

    private const PUBLICATION_SQL = 'update `data_bi_information_requests` set `status` = ?, `sent_at` = ?, `sent_by_user_id` = ?, `data_bi_information_requests`.`updated_at` = ? where `id` = ?';

    private const PUBLICATION_VALUES = ['sent', '2026-11-01 14:00:00', 11, '2026-11-01 14:00:00', 33];

    private const JOURNEY_OWNED = ['users' => [9, 20, 21], 'companies' => [7],
        'transformation_implementation_requests' => [11], 'data_bi_information_requests' => [43],
        'data_bi_tenant_declarations' => [203], 'data_bi_declaration_clarifications' => [1],
        'data_bi_declaration_clarification_events' => [501], 'data_bi_declarative_review_closures' => [601]];

    #[DataProvider('allowed')]
    public function test_safe_statements_are_accepted_without_connection(string $sql, array $bindings): void
    {
        DataBiFullFlowScopeGuard::assertSql($sql, $bindings, self::OWNED);
        self::assertTrue(true);
    }

    public static function allowed(): array
    {
        return [
            ['select * from `companies` where `id` = ?', [22]],
            ['show columns from `companies`', []],
            ['insert into `companies` (`name`, `owner_user_id`) values (?, ?)', ['Sintético', 11]],
            ['update `companies` set `name` = ? where `id` = ?', ['Sintético', 22]],
            ['update `companies` set `owner_user_id` = ? where `id` = ?', [11, 22]],
            ['insert into `data_bi_tenant_declarations` (`submitted_by_user_id`) values (?)', [null]],
            [self::PUBLICATION_SQL, self::PUBLICATION_VALUES],
            [str_replace('`data_bi_information_requests`.`updated_at`', '`updated_at`', self::PUBLICATION_SQL), self::PUBLICATION_VALUES],
            ['update `data_bi_information_requests` set `status` = ?, `sent_at` = ?, `sent_by_user_id` = ? where `id` = ?', ['sent', '2026-11-01 14:00:00', 11, 33]],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_unsafe_dml_is_rejected_before_any_connection(string $sql, array $bindings): void
    {
        $this->expectException(LogicException::class);
        DataBiFullFlowScopeGuard::assertSql($sql, $bindings, self::OWNED);
    }

    public static function forbidden(): array
    {
        return [
            ['update `companies` set `name` = ? where `id` = ?', ['Real', 99]],
            ['update `companies` set `name` = ?', ['Real']],
            ['update `companies` set `name` = ? where `id` = ? or `id` = ?', ['Real', 22, 99]],
            ['update `companies` set `owner_user_id` = ? where `id` = ?', [99, 22]],
            ['update `companies` set `id` = ? where `id` = ?', [99, 22]],
            ['insert into `companies` (`owner_user_id`) values (?)', [99]],
            ['insert into `companies` (`name`) values (?), (?)', ['Uno', 'Dos']],
            ['insert into `companies` (`name`) select `name` from `companies`', []],
            ['insert into `companies` (`name`) values (?) on duplicate key update `name` = ?', ['Uno', 'Dos']],
            ['insert into `companies` (`name`) values (?)', []],
            ['insert into `transformation_implementation_definitions` (`id`) values (?)', [11]],
            ['delete from `companies` where `id` = ?', [22]],
            ['truncate table `companies`', []],
            ['commit', []],
            ['select 1; delete from `companies`', []],
        ];
    }

    #[DataProvider('forbiddenPublicationUpdates')]
    public function test_publication_recognition_does_not_allow_additional_writes(string $sql, array $bindings, array $owned): void
    {
        $this->expectException(LogicException::class);
        DataBiFullFlowScopeGuard::assertSql($sql, $bindings, $owned);
    }

    public static function forbiddenPublicationUpdates(): iterable
    {
        yield 'foreign questionnaire' => [self::PUBLICATION_SQL, [...array_slice(self::PUBLICATION_VALUES, 0, -1), 99], self::OWNED];
        yield 'untracked questionnaire' => [self::PUBLICATION_SQL, self::PUBLICATION_VALUES, ['users' => [11]]];
        yield 'foreign audit actor' => [self::PUBLICATION_SQL, ['sent', '2026-11-01 14:00:00', 99, '2026-11-01 14:00:00', 33], self::OWNED];
        yield 'missing WHERE' => [str_replace(' where `id` = ?', '', self::PUBLICATION_SQL), array_slice(self::PUBLICATION_VALUES, 0, -1), self::OWNED];
        yield 'OR predicate' => [self::PUBLICATION_SQL.' or `id` = ?', [...self::PUBLICATION_VALUES, 99], self::OWNED];
        yield 'multiple IDs' => [str_replace('where `id` = ?', 'where `id` in (?, ?)', self::PUBLICATION_SQL), [...self::PUBLICATION_VALUES, 99], self::OWNED];
        yield 'literal ID' => [str_replace('where `id` = ?', 'where `id` = 33', self::PUBLICATION_SQL), array_slice(self::PUBLICATION_VALUES, 0, -1), self::OWNED];
        yield 'expression' => [str_replace('`sent_at` = ?', '`sent_at` = now()', self::PUBLICATION_SQL), self::PUBLICATION_VALUES, self::OWNED];
        yield 'comment' => [self::PUBLICATION_SQL.' -- ignored', self::PUBLICATION_VALUES, self::OWNED];
        yield 'second statement' => [self::PUBLICATION_SQL.'; commit', self::PUBLICATION_VALUES, self::OWNED];
        yield 'extra binding' => [self::PUBLICATION_SQL, [...self::PUBLICATION_VALUES, 33], self::OWNED];
        yield 'missing binding' => [self::PUBLICATION_SQL, array_slice(self::PUBLICATION_VALUES, 1), self::OWNED];
        yield 'foreign timestamp qualifier' => [str_replace('`data_bi_information_requests`.`updated_at`', '`companies`.`updated_at`', self::PUBLICATION_SQL), self::PUBLICATION_VALUES, self::OWNED];
        yield 'duplicate timestamp' => [str_replace(' where', ', `updated_at` = ? where', self::PUBLICATION_SQL), ['sent', '2026-11-01 14:00:00', 11, '2026-11-01 14:00:00', '2026-11-01 14:00:00', 33], self::OWNED];
        foreach (['status', 'sent_at', 'sent_by_user_id'] as $column) {
            yield 'no new qualification for '.$column => [str_replace('`'.$column.'` = ?', '`data_bi_information_requests`.`'.$column.'` = ?', self::PUBLICATION_SQL), self::PUBLICATION_VALUES, self::OWNED];
        }
        yield 'no timestamp exception for fixture companies' => ['update `companies` set `name` = ?, `companies`.`updated_at` = ? where `id` = ?', ['Sintético', '2026-11-01 14:00:00', 22], self::OWNED];
        foreach (['id', 'company_id', 'transformation_implementation_request_id', 'version', 'review_snapshot', 'content_sha256', 'created_by_user_id', 'created_at', 'internal_notes'] as $column) {
            $value = match ($column) {
                'company_id' => 22,
                'transformation_implementation_request_id' => 44,
                default => 11,
            };
            foreach (['`updated_at`', '`data_bi_information_requests`.`updated_at`'] as $timestamp) {
                yield 'immutable '.$column.' with '.$timestamp => [
                    'update `data_bi_information_requests` set `'.$column.'` = ?, '.$timestamp.' = ? where `id` = ?',
                    [$value, '2026-11-01 14:00:00', 33], [...self::OWNED, 'transformation_implementation_requests' => [44]],
                ];
            }
        }
        foreach (['companies', 'users', 'transformation_implementation_requests', 'data_bi_tenant_declarations', 'data_bi_declaration_clarifications', 'data_bi_declarative_review_closures', 'subscriptions', 'commercial_engagements'] as $table) {
            yield 'publication shape on '.$table => [
                str_replace('update `data_bi_information_requests`', 'update `'.$table.'`', self::PUBLICATION_SQL),
                self::PUBLICATION_VALUES, [...self::OWNED, $table => [33]],
            ];
        }
        foreach (['subscriptions', 'commercial_engagements', 'transformation_implementation_definitions'] as $table) {
            yield 'commercial table '.$table => ['update `'.$table.'` set `status` = ? where `id` = ?', ['sent', 33], [...self::OWNED, $table => [33]]];
        }
        foreach (['delete from `data_bi_information_requests` where `id` = ?', 'replace into `data_bi_information_requests` (`status`) values (?)', 'insert ignore into `data_bi_information_requests` (`status`) values (?)', 'alter table `data_bi_information_requests` add `unsafe` int', 'commit', 'rollback'] as $sql) {
            yield 'unnecessary DML/control '.$sql => [$sql, [], self::OWNED];
        }
    }

    public function test_real_eloquent_save_generates_the_narrow_publication_shape_without_pdo(): void
    {
        $previous = Model::getConnectionResolver();
        $pdoAttempted = false;
        $captured = [];
        $stop = new RuntimeException('Pure SQL capture completed.');
        $connection = new MySqlConnection(function () use (&$pdoAttempted): void {
            $pdoAttempted = true;
            throw new LogicException('PDO prohibido en la compilación pura.');
        }, 'b5_pure', '', ['driver' => 'mysql', 'name' => 'b5_pure']);
        $connection->beforeExecuting(function (string $sql, array $bindings) use (&$captured, $stop): void {
            $captured = [$sql, $bindings];
            throw $stop; // Stop before PDO, including query-log binding interpolation.
        });
        $resolver = new ConnectionResolver(['b5_pure' => $connection]);
        $resolver->setDefaultConnection('b5_pure');
        Model::setConnectionResolver($resolver);
        try {
            $publication = (new DataBiInformationRequest)->setRawAttributes([
                'id' => 33, 'status' => 'draft', 'sent_at' => null, 'sent_by_user_id' => null,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ], true);
            $publication->exists = true;
            // The same mutations as SendService::send(); Model and MySQL grammar are real.
            $publication->setAttribute('status', DataBiInformationRequest::STATUS_SENT);
            $publication->setAttribute('sent_at', now());
            $publication->setAttribute('sent_by_user_id', 11);
            try {
                $publication->save();
                self::fail('La captura debe detenerse antes de PDO.');
            } catch (RuntimeException $error) {
                self::assertSame($stop, $error);
            }
            self::assertFalse($pdoAttempted);
            self::assertSame(self::PUBLICATION_SQL, $captured[0]);
            self::assertCount(5, $captured[1]);
            DataBiFullFlowScopeGuard::assertSql($captured[0], $captured[1], self::OWNED);
        } finally {
            $previous === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($previous);
        }
    }

    public function test_b5_owned_set_excludes_the_initial_b2_control_questionnaire(): void
    {
        $owned = [...self::OWNED, 'data_bi_information_requests' => array_diff([31 => 31, 33 => 33], [31 => 31])];
        DataBiFullFlowScopeGuard::assertSql(self::PUBLICATION_SQL, self::PUBLICATION_VALUES, $owned);
        $this->expectException(LogicException::class);
        DataBiFullFlowScopeGuard::assertSql(self::PUBLICATION_SQL, [...array_slice(self::PUBLICATION_VALUES, 0, -1), 31], $owned);
    }

    private function rollbackConnection(bool $readOnly = false, ?object $scope = null): array
    {
        $state = (object) ['active' => false];
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturnCallback(static function () use ($state): bool {
            $state->active = true;

            return true;
        });
        $pdo->method('inTransaction')->willReturnCallback(static fn (): bool => $state->active);
        $pdo->method('rollBack')->willReturnCallback(static function () use ($state): bool {
            $state->active = false;

            return true;
        });
        $pdo->method('exec')->willReturnCallback(static function (string $sql): int {
            if (! preg_match('/\A(?:SAVEPOINT trans[0-9]+|ROLLBACK TO SAVEPOINT trans[0-9]+|SET TRANSACTION READ ONLY)\z/', $sql)) {
                throw new LogicException('Control de transacción simulado desconocido.');
            }

            return 0;
        });
        $pdo->expects(self::never())->method('commit');
        $connection = new DataBiDeclarativeReviewRollbackConnection($pdo, 'b5_unit_primary', $readOnly);
        $connection->beforeExecuting(fn (string $sql, array $bindings) => DataBiFullFlowScopeGuard::assertSql($sql, $bindings, $scope->owned ?? self::OWNED));
        $connection->openOuterTransaction();

        return [$connection, $pdo, $state];
    }

    public function test_publication_keeps_the_same_outer_pdo_and_never_commits_root(): void
    {
        [$connection, $pdo, $state] = $this->rollbackConnection();
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('bindValue')->willReturn(true);
        $statement->expects(self::once())->method('execute')->willReturn(true);
        $statement->method('rowCount')->willReturn(1);
        $pdo->expects(self::once())->method('prepare')->with(self::PUBLICATION_SQL)->willReturn($statement);
        $pdo->expects(self::once())->method('rollBack');
        $connection->allowFixtures();
        try {
            self::assertSame(1, $connection->transaction(function ($primary) use ($pdo): int {
                self::assertSame($pdo, $primary->getRawPdo());
                self::assertSame(2, $primary->transactionLevel());

                return $primary->update(self::PUBLICATION_SQL, self::PUBLICATION_VALUES);
            }));
            self::assertSame($pdo, $connection->getRawPdo());
            self::assertSame(1, $connection->transactionLevel());
            $connection->assertOuterTransaction();
            try {
                $connection->commit();
                self::fail('Se prohíbe confirmar fixtures.');
            } catch (LogicException $error) {
                self::assertSame('Se prohíbe confirmar la transacción exterior de fixtures.', $error->getMessage());
            }
        } finally {
            $connection->rollbackOuterTransaction();
        }
        self::assertSame(0, $connection->transactionLevel());
        self::assertFalse($state->active);
    }

    #[DataProvider('closedConnectionContexts')]
    public function test_recognized_publication_cannot_bypass_connection_guards(string $context): void
    {
        [$connection, $pdo, $state] = $this->rollbackConnection($context === 'read_only_probe');
        $pdo->expects(self::never())->method('prepare');
        try {
            if ($context === 'lost_outer_transaction') {
                $connection->allowFixtures();
                $state->active = false;
            }
            try {
                $connection->update(self::PUBLICATION_SQL, self::PUBLICATION_VALUES);
                self::fail('La sintaxis válida no sustituye la autorización de conexión.');
            } catch (LogicException $error) {
                self::assertSame($context === 'lost_outer_transaction'
                    ? 'Se perdió la transacción exterior QA; no se permite continuar.'
                    : 'DML bloqueado fuera del ámbito sintético autorizado.', $error->getMessage());
            }
        } finally {
            if ($context === 'lost_outer_transaction') {
                try {
                    $connection->rollbackOuterTransaction();
                    self::fail('Cleanup debe detectar la pérdida de transacción.');
                } catch (LogicException $error) {
                    self::assertSame('Se perdió la transacción exterior QA; no se permite continuar.', $error->getMessage());
                }
            } else {
                $connection->rollbackOuterTransaction();
            }
        }
        self::assertSame(0, $connection->transactionLevel());
        self::assertFalse($state->active);
    }

    public static function closedConnectionContexts(): iterable
    {
        yield ['fixtures_not_authorized'];
        yield ['read_only_probe'];
        yield ['lost_outer_transaction'];
    }

    /** Real Model::save()/MySQL grammar; every write stops before PDO, never fabricates success. */
    private function compileModelWrite(string $class, array $attributes, ?array $before = null): array
    {
        $previous = ['resolver' => Model::getConnectionResolver(), 'dispatcher' => Model::getEventDispatcher()];
        foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
            $previous[$field] = (new ReflectionProperty(Model::class, $field))->getValue();
        }
        $captured = [];
        $stop = new RuntimeException('Pure Eloquent write captured before PDO.');
        $pdoAttempted = false;
        $connection = new MySqlConnection(function () use (&$pdoAttempted): void {
            $pdoAttempted = true;
            throw new LogicException('PDO prohibido en la auditoría pura B5.');
        }, 'b5_matrix', '', ['driver' => 'mysql', 'name' => 'b5_matrix']);
        $connection->beforeExecuting(function (string $sql, array $bindings) use (&$captured, $stop): void {
            $captured = ['sql' => $sql, 'bindings' => $bindings];
            throw $stop;
        });
        $resolver = new ConnectionResolver(['b5_matrix' => $connection]);
        $resolver->setDefaultConnection('b5_matrix');
        Model::setConnectionResolver($resolver);
        Model::clearBootedModels();
        Model::setEventDispatcher(new Dispatcher(new Container));
        try {
            $model = new $class;
            if ($before !== null) {
                $model->forceFill($before)->syncOriginal();
                $model->exists = true;
            }
            $model->forceFill($attributes);
            try {
                $model->save();
                self::fail('El modelo debe producir una escritura capturada antes de PDO.');
            } catch (RuntimeException $error) {
                self::assertSame($stop, $error);
            }
            self::assertFalse($pdoAttempted);
            self::assertNotEmpty($captured);

            return $captured;
        } finally {
            $previous['resolver'] === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($previous['resolver']);
            $previous['dispatcher'] === null ? Model::unsetEventDispatcher() : Model::setEventDispatcher($previous['dispatcher']);
            foreach (['booted', 'bootedCallbacks', 'globalScopes'] as $field) {
                (new ReflectionProperty(Model::class, $field))->setValue(null, $previous[$field]);
            }
        }
    }

    /** Inputs/rows come from the existing contracts and the exact service persistence assignments. */
    private function journeySql(): array
    {
        $published = $this->publication();
        $draft = ['transformation_implementation_request_id' => 11, 'company_id' => 7, 'version' => 3,
            'status' => 'draft', 'review_snapshot' => $published['review_snapshot'],
            'content_sha256' => $published['content_sha256'], 'created_by_user_id' => 9];
        $timestamps = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
        $writes['publication.create_draft'] = $this->compileModelWrite(DataBiInformationRequest::class, $draft);
        $writes['publication.send'] = $this->compileModelWrite(DataBiInformationRequest::class,
            ['status' => 'sent', 'sent_at' => '2026-10-30 09:00:00', 'sent_by_user_id' => 9],
            ['id' => 43, ...$draft, 'sent_at' => null, 'sent_by_user_id' => null, ...$timestamps]);
        // The fifth B5 scenario creates supersession through this existing synthetic fixture INSERT.
        $writes['publication.fixture_supersession'] = $this->compileModelWrite(DataBiInformationRequest::class,
            [...$draft, 'version' => 4, 'status' => 'sent', 'sent_at' => '2026-10-30 09:00:00', 'sent_by_user_id' => 9]);

        $empty = DeclarationContract::normalize($published, $this->declarationInput(3, $published['review_snapshot'], false), 'draft');
        $complete = DeclarationContract::normalize($published, $this->declarationInput(3, $published['review_snapshot']), 'draft');
        $submit = DeclarationContract::normalize($published, $this->declarationInput(3, $published['review_snapshot']), 'submit');
        $row = static function (array $snapshot, array $decision): array {
            return ['status' => $decision['status'], 'revision' => $decision['revision'], 'declaration_snapshot' => $snapshot,
                'content_sha256' => $snapshot['content_sha256'], 'updated_by_user_id' => 21,
                'submitted_by_user_id' => $decision['status'] === 'submitted' ? 21 : null,
                'submitted_at' => $decision['status'] === 'submitted' ? '2026-10-30 10:00:00' : null];
        };
        $identity = ['data_bi_information_request_id' => 43, 'company_id' => 7, 'created_by_user_id' => 21];
        $initial = [...$identity, ...$row($empty, DeclarationPolicy::decide(null, $empty['content_sha256'], 0, 'draft'))];
        $edited = $row($complete, DeclarationPolicy::decide($initial, $complete['content_sha256'], 1, 'draft'));
        $submitted = $row($submit, DeclarationPolicy::decide($edited, $submit['content_sha256'], 2, 'submit'));
        $writes['declaration.create_draft'] = $this->compileModelWrite(DataBiTenantDeclaration::class, $initial);
        $writes['declaration.edit_draft'] = $this->compileModelWrite(DataBiTenantDeclaration::class, $edited, ['id' => 203, ...$initial, ...$timestamps]);
        $writes['declaration.submit_existing'] = $this->compileModelWrite(DataBiTenantDeclaration::class, $submitted,
            ['id' => 203, ...$initial, ...$edited, ...$timestamps]);
        $writes['declaration.submit_new'] = $this->compileModelWrite(DataBiTenantDeclaration::class,
            [...$identity, ...$row($submit, DeclarationPolicy::decide(null, $submit['content_sha256'], 0, 'submit'))]);

        $context = $this->context(gaps: true);
        $requested = $this->requested($context);
        $answered = $this->answered($context);
        $resolved = $this->resolved($context);
        $writes['clarification.create'] = $this->compileModelWrite(DataBiDeclarationClarification::class, ReviewPolicy::clarificationRow($requested));
        $writes['clarification.answer'] = $this->compileModelWrite(DataBiDeclarationClarification::class,
            ReviewPolicy::clarificationRow($answered), ReviewPolicy::clarificationRow($requested));
        $writes['clarification.resolve'] = $this->compileModelWrite(DataBiDeclarationClarification::class,
            ReviewPolicy::clarificationRow($resolved), ReviewPolicy::clarificationRow($answered));
        foreach (ReviewPolicy::eventRows($context, $resolved) as $event) {
            $writes['event.'.$event['action']] = $this->compileModelWrite(DataBiDeclarationClarificationEvent::class, $event);
        }
        $closure = ReviewPolicy::close($context, $this->admin(), $this->conclusion(), 0, '2026-10-30T14:00:00Z', [$resolved], null);
        $writes['closure.create'] = $this->compileModelWrite(DataBiDeclarativeReviewClosure::class, ReviewPolicy::closureRow($closure['snapshot']));

        return $writes;
    }

    public function test_real_eloquent_declaration_edit_is_scoped_and_accepted(): void
    {
        $write = $this->journeySql()['declaration.edit_draft'];
        self::assertSame('update `data_bi_tenant_declarations` set `revision` = ?, `declaration_snapshot` = ?, `content_sha256` = ?, `data_bi_tenant_declarations`.`updated_at` = ? where `id` = ?', $write['sql']);
        DataBiFullFlowScopeGuard::assertSql($write['sql'], $write['bindings'], self::JOURNEY_OWNED);
    }

    public function test_every_demonstrated_journey_write_is_accepted_without_pdo(): void
    {
        $writes = $this->journeySql();
        self::assertCount(14, $writes);
        foreach ($writes as $operation => $write) {
            self::assertMatchesRegularExpression('/\A(?:insert into|update) `data_bi_[a-z_]+` /', $write['sql'], $operation);
            DataBiFullFlowScopeGuard::assertSql($write['sql'], $write['bindings'], self::JOURNEY_OWNED);
        }
    }

    private function rejectWrite(array $write, array $owned, string $reason): void
    {
        try {
            DataBiFullFlowScopeGuard::assertSql($write['sql'], $write['bindings'], $owned);
            self::fail('La escritura debe rechazarse: '.$reason);
        } catch (LogicException $error) {
            self::assertSame(LogicException::class, get_class($error), $reason);
        }
    }

    public function test_real_journey_sql_rejects_foreign_references_and_ambiguous_updates(): void
    {
        $references = ['company_id', 'transformation_implementation_request_id', 'data_bi_information_request_id',
            'data_bi_tenant_declaration_id', 'data_bi_declaration_clarification_id', 'created_by_user_id',
            'updated_by_user_id', 'submitted_by_user_id', 'actor_user_id', 'closed_by_user_id'];
        foreach ($this->journeySql() as $operation => $write) {
            if (str_starts_with($write['sql'], 'insert into')) {
                self::assertSame(1, preg_match('/\Ainsert into `([a-z_]+)` \(([^)]+)\) values \(([^)]+)\)\z/', $write['sql'], $match));
                preg_match_all('/`([a-z0-9_]+)`/', $match[2], $columns);
                foreach ($columns[1] as $index => $column) {
                    if (in_array($column, $references, true)) {
                        $foreign = $write;
                        $foreign['bindings'][$index] = 999999;
                        $this->rejectWrite($foreign, self::JOURNEY_OWNED, $operation.' foreign '.$column);
                    }
                }
                foreach (['external_tenant_id', 'internal_notes', 'commercial_status'] as $column) {
                    $this->rejectWrite([
                        'sql' => 'insert into `'.$match[1].'` ('.$match[2].', `'.$column.'`) values ('.$match[3].', ?)',
                        'bindings' => [...$write['bindings'], 'synthetic'],
                    ], self::JOURNEY_OWNED, $operation.' additional '.$column);
                }
                // Duplicate attributes cannot mask a foreign identity through array_combine().
                $column = $columns[1][0];
                $this->rejectWrite([
                    'sql' => 'insert into `'.$match[1].'` (`'.$column.'`, '.$match[2].') values (?, '.$match[3].')',
                    'bindings' => [999999, ...$write['bindings']],
                ], self::JOURNEY_OWNED, $operation.' duplicate attribute');
            } else {
                $foreign = $write;
                $foreign['bindings'][count($foreign['bindings']) - 1] = 999999;
                $this->rejectWrite($foreign, self::JOURNEY_OWNED, $operation.' foreign ID');
                $this->rejectWrite([
                    'sql' => str_replace(' where `id` = ?', '', $write['sql']),
                    'bindings' => array_slice($write['bindings'], 0, -1),
                ], self::JOURNEY_OWNED, $operation.' without WHERE');
                foreach ([' or `id` = ?', ' and `company_id` = ?', ' limit 1', ' -- comment', '; commit'] as $suffix) {
                    $this->rejectWrite(['sql' => $write['sql'].$suffix, 'bindings' => [...$write['bindings'], 999999]],
                        self::JOURNEY_OWNED, $operation.' ambiguous WHERE');
                }
                self::assertSame(1, preg_match('/\Aupdate `([a-z_]+)` set (.+) where `id` = \?\z/', $write['sql'], $match));
                foreach (['company_id', 'created_by_user_id', 'created_at', 'external_tenant_id', 'commercial_status'] as $column) {
                    $value = $column === 'company_id' ? 7 : 21; // Own references still cannot change identity.
                    $this->rejectWrite([
                        'sql' => 'update `'.$match[1].'` set '.$match[2].', `'.$column.'` = ? where `id` = ?',
                        'bindings' => [...array_slice($write['bindings'], 0, -1), $value, $write['bindings'][count($write['bindings']) - 1]],
                    ], self::JOURNEY_OWNED, $operation.' forbidden column '.$column);
                }
            }
            foreach (['commercial_engagements', 'subscriptions', 'transformation_implementation_definitions'] as $table) {
                $sql = preg_replace('/\A(insert into|update) `[a-z_]+`/', '$1 `'.$table.'`', $write['sql']);
                $this->rejectWrite(['sql' => $sql, 'bindings' => $write['bindings']],
                    [...self::JOURNEY_OWNED, $table => [1, 7, 11, 43, 203]], $operation.' commercial/implementation table');
            }
        }
    }

    public function test_histories_and_closures_are_insert_only_even_for_owned_ids(): void
    {
        foreach (['data_bi_declaration_clarification_events' => ['message', 501],
            'data_bi_declarative_review_closures' => ['conclusion', 601]] as $table => [$column, $id]) {
            $this->rejectWrite(['sql' => 'update `'.$table.'` set `'.$column.'` = ? where `id` = ?', 'bindings' => ['replacement', $id]],
                self::JOURNEY_OWNED, 'append-only '.$table);
            $this->rejectWrite(['sql' => 'delete from `'.$table.'` where `id` = ?', 'bindings' => [$id]],
                self::JOURNEY_OWNED, 'delete history '.$table);
        }
    }

    #[DataProvider('declarationForbiddenShapes')]
    public function test_declaration_parser_keeps_a_closed_column_and_where_shape(string $sql, array $bindings): void
    {
        $this->expectException(LogicException::class);
        DataBiFullFlowScopeGuard::assertSql($sql, $bindings, self::JOURNEY_OWNED);
    }

    public static function declarationForbiddenShapes(): iterable
    {
        foreach (['data_bi_information_request_id' => 43, 'company_id' => 7, 'created_by_user_id' => 21,
            'created_at' => '2026-01-01 00:00:00', 'id' => 203, 'version' => 3] as $column => $value) {
            yield 'immutable '.$column => ['update `data_bi_tenant_declarations` set `'.$column.'` = ?, `data_bi_tenant_declarations`.`updated_at` = ? where `id` = ?', [$value, '2026-11-01 14:00:00', 203]];
        }
        yield 'foreign qualifier' => ['update `data_bi_tenant_declarations` set `revision` = ?, `data_bi_information_requests`.`updated_at` = ? where `id` = ?', [2, '2026-11-01 14:00:00', 203]];
        yield 'duplicate timestamp' => ['update `data_bi_tenant_declarations` set `updated_at` = ?, `data_bi_tenant_declarations`.`updated_at` = ? where `id` = ?', ['2026-11-01 14:00:00', '2026-11-01 14:00:00', 203]];
        yield 'multiple IDs' => ['update `data_bi_tenant_declarations` set `revision` = ? where `id` in (?, ?)', [2, 203, 999999]];
        yield 'literal ID' => ['update `data_bi_tenant_declarations` set `revision` = ? where `id` = 203', [2]];
        yield 'expression' => ['update `data_bi_tenant_declarations` set `revision` = `revision` + 1 where `id` = ?', [203]];
        yield 'named bindings' => ['update `data_bi_tenant_declarations` set `revision` = ? where `id` = ?', ['revision' => 2, 'id' => 203]];
        foreach (['status', 'revision', 'submitted_at'] as $column) {
            yield 'no general qualification '.$column => ['update `data_bi_tenant_declarations` set `data_bi_tenant_declarations`.`'.$column.'` = ? where `id` = ?', [2, 203]];
        }
        foreach (['drop table `data_bi_tenant_declarations`', 'truncate table `data_bi_tenant_declarations`', 'delete from `data_bi_tenant_declarations` where `id` = ?', 'commit', 'rollback'] as $sql) {
            yield 'non-journey '.$sql => [$sql, []];
        }
    }

    #[DataProvider('primaryInsertCases')]
    public function test_id_is_not_owned_until_successful_primary_insert_is_observed(string $create, string $edit, string $table, int $id, bool $failInsert): void
    {
        $writes = $this->journeySql();
        $insert = $writes[$create];
        $update = $writes[$edit];
        $scope = (object) ['owned' => array_diff_key(self::JOURNEY_OWNED, [$table => true])];
        $this->rejectWrite($update, $scope->owned, 'ID not yet created by the fixture');
        [$connection, $pdo, $state] = $this->rollbackConnection(scope: $scope);
        $statement = $this->createMock(PDOStatement::class);
        $failure = new RuntimeException('Pure INSERT failed before completion.');
        $statement->method('bindValue')->willReturn(true);
        $statement->expects(self::exactly($failInsert ? 1 : 2))->method('execute')->willReturnCallback(static function () use ($failInsert, $failure): bool {
            if ($failInsert) {
                throw $failure;
            }

            return true;
        });
        $statement->method('rowCount')->willReturn(1);
        $expectedSql = $failInsert ? [$insert['sql']] : [$insert['sql'], $update['sql']];
        $pdo->expects(self::exactly(count($expectedSql)))->method('prepare')->willReturnCallback(function (string $sql) use (&$expectedSql, $statement): PDOStatement {
            self::assertSame(array_shift($expectedSql), $sql, 'El PDO simulado rechaza SQL desconocido o fuera de orden.');

            return $statement;
        });
        $pdo->method('lastInsertId')->willReturn((string) $id);
        $dispatcher = new Dispatcher(new Container);
        $dispatcher->listen(QueryExecuted::class, static function (QueryExecuted $event) use ($connection, $scope): void {
            // Same successful-INSERT provenance as B2's observer, confined to the primary.
            if ($event->connection !== $connection || $event->connection->getName() !== 'b5_unit_primary'
                || ! preg_match('/\Ainsert into `([a-z0-9_]+)` \(([^)]+)\)/', $event->sql, $match)) {
                return;
            }
            preg_match_all('/`([a-z0-9_]+)`/', $match[2], $columns);
            $position = array_search('id', $columns[1], true);
            $id = $position === false ? (int) $event->connection->getPdo()->lastInsertId() : (int) $event->bindings[$position];
            self::assertGreaterThan(0, $id);
            $scope->owned[$match[1]][$id] = $id;
        });
        $connection->setEventDispatcher($dispatcher);
        $connection->allowFixtures();
        try {
            try {
                $connection->transaction(function ($primary) use ($insert, $update, $scope, $pdo, $table, $id): void {
                    self::assertSame(2, $primary->transactionLevel());
                    self::assertSame($pdo, $primary->getRawPdo());
                    self::assertTrue($primary->insert($insert['sql'], $insert['bindings']));
                    self::assertSame([$id => $id], $scope->owned[$table]);
                    self::assertSame(1, $primary->update($update['sql'], $update['bindings']));
                });
                self::assertFalse($failInsert);
            } catch (\Illuminate\Database\QueryException $error) {
                self::assertTrue($failInsert);
                self::assertSame($failure, $error->getPrevious());
                self::assertArrayNotHasKey($table, $scope->owned);
                $this->rejectWrite($update, $scope->owned, 'Failed INSERT cannot register an ID');
            }
            self::assertSame(1, $connection->transactionLevel());
            $connection->assertOuterTransaction();
        } finally {
            $connection->rollbackOuterTransaction();
        }
        self::assertSame(0, $connection->transactionLevel());
        self::assertFalse($state->active);
    }

    public static function primaryInsertCases(): iterable
    {
        yield 'auto ID' => ['declaration.create_draft', 'declaration.edit_draft', 'data_bi_tenant_declarations', 203, false];
        yield 'server generated explicit ID' => ['clarification.create', 'clarification.answer', 'data_bi_declaration_clarifications', 1, false];
        yield 'failed INSERT' => ['declaration.create_draft', 'declaration.edit_draft', 'data_bi_tenant_declarations', 203, true];
    }

    public function test_changed_pdo_and_reconnection_are_rejected_before_declaration_write(): void
    {
        $write = $this->journeySql()['declaration.edit_draft'];
        [$connection, $pdo, $state] = $this->rollbackConnection(scope: (object) ['owned' => self::JOURNEY_OWNED]);
        $pdo->expects(self::never())->method('prepare');
        $foreign = $this->createMock(PDO::class);
        $foreign->expects(self::never())->method('prepare');
        $foreign->expects(self::never())->method('commit');
        $connection->allowFixtures();
        try {
            try {
                $connection->reconnect();
                self::fail('Se prohíbe reconectar.');
            } catch (LogicException $error) {
                self::assertSame('QA no reconecta sesiones: podría perder el rollback exterior.', $error->getMessage());
            }
            $level = $connection->transactionLevel();
            $connection->setPdo($foreign);
            try {
                $connection->update($write['sql'], $write['bindings']);
                self::fail('Se prohíbe cambiar el PDO exterior.');
            } catch (LogicException $error) {
                self::assertSame('Se perdió la transacción exterior QA; no se permite continuar.', $error->getMessage());
            } finally {
                // Restore only the artificial test swap so cleanup can roll back its original PDO.
                $connection->setPdo($pdo);
                (new ReflectionProperty($connection, 'transactions'))->setValue($connection, $level);
            }
        } finally {
            $connection->rollbackOuterTransaction();
        }
        self::assertFalse($state->active);
        self::assertSame(0, $connection->transactionLevel());
    }
}
