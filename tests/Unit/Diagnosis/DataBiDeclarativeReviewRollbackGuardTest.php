<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\DataBiDeclarativeReviewRollbackConnection;

/** PDO simulado únicamente: demuestra las barreras del arnés sin abrir sesiones. */
final class DataBiDeclarativeReviewRollbackGuardTest extends TestCase
{
    private function connection(bool $readOnly = false): DataBiDeclarativeReviewRollbackConnection
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
        $pdo->method('exec')->willReturn(0);
        $pdo->expects(self::never())->method('commit');
        $connection = new DataBiDeclarativeReviewRollbackConnection($pdo, 'b2_guard_fake', $readOnly);
        $connection->openOuterTransaction();

        return $connection;
    }

    #[DataProvider('blockedSql')]
    public function test_harness_blocks_non_reversible_or_out_of_scope_sql(string $sql): void
    {
        $connection = $this->connection();
        $connection->allowFixtures();
        try {
            $this->expectException(LogicException::class);
            $connection->guardSql($sql);
        } finally {
            $connection->rollbackOuterTransaction();
        }
    }

    public static function blockedSql(): iterable
    {
        foreach (['COMMIT', 'ROLLBACK', 'TRUNCATE users', 'CREATE TABLE unsafe (id INT)', 'SET foreign_key_checks=0',
            'SELECT 1; COMMIT', "SELECT 1 INTO OUTFILE '/tmp/unsafe'", 'UPDATE subscriptions SET status=?'] as $sql) {
            yield [$sql];
        }
    }

    public function test_new_tables_are_allowed_only_in_the_owned_outer_transaction(): void
    {
        $connection = $this->connection();
        $connection->allowFixtures();
        foreach (['data_bi_declaration_clarifications', 'data_bi_declaration_clarification_events', 'data_bi_declarative_review_closures'] as $table) {
            $connection->guardSql('insert into `'.$table.'` (`id`) values (?)');
        }
        $connection->transaction(function () use ($connection): void {
            self::assertSame(2, $connection->transactionLevel());
        });
        self::assertSame(1, $connection->transactionLevel());
        try {
            $connection->commit();
            self::fail('No puede confirmar la raíz.');
        } catch (LogicException) {
            self::assertSame(1, $connection->transactionLevel());
        }
        $connection->rollbackOuterTransaction();
        self::assertSame(0, $connection->transactionLevel());
    }

    public function test_secondary_connection_never_allows_dml(): void
    {
        $connection = $this->connection(true);
        try {
            $this->expectException(LogicException::class);
            $connection->guardSql('insert into `users` (`id`) values (?)');
        } finally {
            $connection->rollbackOuterTransaction();
        }
    }
}
