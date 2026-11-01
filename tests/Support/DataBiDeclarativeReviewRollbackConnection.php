<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Database\MySqlConnection;
use LogicException;
use PDO;
use Throwable;

/**
 * Arnés T4C-M5: misma barrera de rollback exterior/savepoints, sin modificar el original.
 * Solo amplía las tablas sintéticas permitidas para T4F-B2. No reconecta ni confirma PDO.
 */
final class DataBiDeclarativeReviewRollbackConnection extends MySqlConnection
{
    public const FIXTURE_TABLES = [
        'users', 'subscribers', 'companies', 'subscriber_user', 'diagnosis_assessments',
        'diagnosis_detailed_roadmaps', 'transformation_implementation_plans',
        'transformation_implementation_phases', 'transformation_implementation_phase_capabilities',
        'transformation_implementation_requests', 'data_bi_information_requests', 'data_bi_tenant_declarations',
        'data_bi_declaration_clarifications', 'data_bi_declaration_clarification_events', 'data_bi_declarative_review_closures',
    ];

    private ?PDO $outerPdo = null;

    private bool $fixturesAllowed = false;

    public function __construct(PDO $pdo, string $name, private bool $readOnly = false)
    {
        parent::__construct($pdo, 'laudaapi_dev', '', ['driver' => 'mysql', 'name' => $name]);
        $this->beforeExecuting(fn (string $sql) => $this->guardSql($sql));
    }

    public function openOuterTransaction(): void
    {
        if ($this->outerPdo !== null || $this->transactionLevel() !== 0 || $this->getPdo()->inTransaction()) {
            throw new LogicException('La conexión debe ser nueva y no puede reutilizar otra transacción.');
        }
        if ($this->readOnly) {
            $this->getPdo()->exec('SET TRANSACTION READ ONLY');
        }
        parent::beginTransaction();
        $this->outerPdo = $this->getPdo();
        $this->assertOuterTransaction();
    }

    public function assertOuterTransaction(): void
    {
        if ($this->outerPdo === null || $this->getRawPdo() !== $this->outerPdo
            || $this->transactionLevel() < 1 || ! $this->outerPdo->inTransaction()) {
            throw new LogicException('Se perdió la transacción exterior QA; no se permite continuar.');
        }
    }

    public function allowFixtures(): void
    {
        $this->assertOuterTransaction();
        if ($this->readOnly) {
            throw new LogicException('La conexión secundaria nunca admite fixtures ni DML.');
        }
        $this->fixturesAllowed = true;
    }

    public function guardSql(string $sql): void
    {
        $this->assertOuterTransaction();
        if (str_contains($sql, ';') || preg_match('/\b(?:outfile|dumpfile|load_file)\b/i', $sql)
            || ! preg_match('/\A\s*(select|show|insert|update|delete)\b/i', $sql, $verb)) {
            throw new LogicException('SQL no autorizado: sin DDL, cambios de sesión, commits ni operaciones externas.');
        }
        if (in_array(strtolower($verb[1]), ['select', 'show'], true)) {
            return;
        }
        if ($this->readOnly || ! $this->fixturesAllowed
            || ! preg_match('/\A\s*(?:insert\s+into|update|delete\s+from)\s+`?([a-z0-9_]+)`?\b/i', $sql, $table)
            || ! in_array($table[1], self::FIXTURE_TABLES, true)) {
            throw new LogicException('DML bloqueado fuera del ámbito sintético autorizado.');
        }
    }

    public function beginTransaction()
    {
        $this->assertOuterTransaction();
        parent::beginTransaction();
    }

    /** Solo savepoints: nunca entra en la rama PDO::commit() de una transacción raíz. */
    public function transaction(Closure $callback, $attempts = 1)
    {
        $this->assertOuterTransaction();
        $level = $this->transactionLevel();
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->assertOuterTransaction();
            if ($this->transactionLevel() !== $level + 1) {
                throw new LogicException('El servicio alteró el nivel de transacción esperado.');
            }
            $this->commit(); // Nivel >= 2: confirma únicamente el savepoint lógico de Laravel.

            return $result;
        } catch (Throwable $e) {
            if ($this->getRawPdo() === $this->outerPdo && $this->outerPdo->inTransaction()
                && $this->transactionLevel() > $level) {
                parent::rollBack($level);
            }
            // Sin reintentos tras una pérdida de transacción: el caso debe fallar y limpiar.
            throw $e;
        }
    }

    public function commit()
    {
        $this->assertOuterTransaction();
        if ($this->transactionLevel() <= 1) {
            throw new LogicException('Se prohíbe confirmar la transacción exterior de fixtures.');
        }
        parent::commit();
    }

    public function rollBack($toLevel = null)
    {
        $this->assertOuterTransaction();
        if (($toLevel ?? $this->transactionLevel() - 1) < 1) {
            throw new LogicException('Solo cleanup puede revertir la transacción exterior QA.');
        }
        parent::rollBack($toLevel);
    }

    public function rollbackOuterTransaction(): void
    {
        if ($this->outerPdo === null) {
            return;
        }
        try {
            $this->assertOuterTransaction();
            parent::rollBack(0);
            if ($this->transactionLevel() !== 0 || $this->outerPdo->inTransaction()) {
                throw new LogicException('No se completó el rollback exterior.');
            }
        } finally {
            $this->fixturesAllowed = false;
            $this->outerPdo = null;
            $this->transactions = 0;
        }
    }

    public function reconnect()
    {
        throw new LogicException('QA no reconecta sesiones: podría perder el rollback exterior.');
    }
}
