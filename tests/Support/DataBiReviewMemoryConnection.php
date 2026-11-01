<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Database\MySqlConnection;
use LogicException;
use Throwable;

/** Doble PHP de almacenamiento para el servicio real: jamás PDO, SQLite o SQL externo. */
final class DataBiReviewMemoryConnection extends MySqlConnection
{
    public array $queries = [];

    public array $dml = [];

    public bool $pdoAttempted = false;

    public ?Closure $afterParentLock = null;

    public function __construct(public array $rows)
    {
        parent::__construct(function () {
            $this->pdoAttempted = true;
            throw new LogicException('El test puro prohíbe conexiones PDO.');
        }, 'b2_memory', '', ['driver' => 'mysql', 'name' => 'b2_memory']);
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        $before = $this->rows;
        $dml = $this->dml;
        $this->transactions++;
        try {
            return $callback($this);
        } catch (Throwable $e) {
            $this->rows = $before;
            $this->dml = $dml;
            throw $e;
        } finally {
            $this->transactions--;
        }
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if ($this->transactionLevel() < 1 || ! preg_match('/from `([a-z0-9_]+)`/', $query, $table)) {
            throw new LogicException('Lectura inesperada fuera de la operación transaccional.');
        }
        $this->queries[] = [$query, $bindings];
        $conditions = [];
        $offset = 0;
        preg_match_all('/`([a-z0-9_]+)`\s*(= \?|in \([^)]*\))/', $query, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $count = substr_count($match[2], '?');
            $values = array_slice($bindings, $offset, $count);
            $offset += $count;
            $conditions[] = [$match[1], $values];
        }
        $or = str_contains($query, ' or ');
        $rows = array_values(array_filter($this->rows[$table[1]] ?? [], static function (array $row) use ($conditions, $or): bool {
            $results = array_map(static fn (array $condition): bool => in_array($row[$condition[0]] ?? null, $condition[1], true), $conditions);

            return $or ? in_array(true, $results, true) : ! in_array(false, $results, true);
        }));
        if (preg_match('/order by (.+?)(?: limit| for update| lock in share mode|$)/', $query, $order)) {
            preg_match_all('/`([a-z0-9_]+)` (asc|desc)/', $order[1], $columns, PREG_SET_ORDER);
            usort($rows, static function (array $a, array $b) use ($columns): int {
                foreach ($columns as $column) {
                    $diff = $a[$column[1]] <=> $b[$column[1]];
                    if ($diff !== 0) {
                        return $column[2] === 'desc' ? -$diff : $diff;
                    }
                }

                return 0;
            });
        }
        if ($table[1] === 'transformation_implementation_requests' && $this->afterParentLock !== null) {
            ($this->afterParentLock)();
            $this->afterParentLock = null;
        }

        return array_map(static fn (array $row): object => (object) $row, $rows);
    }

    public function insert($query, $bindings = [], $sequence = null)
    {
        if ($this->transactionLevel() < 1 || ! preg_match('/insert into `(data_bi_declaration_clarifications|data_bi_declaration_clarification_events|data_bi_declarative_review_closures)` \(([^)]+)\)/', $query, $match)) {
            throw new LogicException('DML puro fuera de las tres tablas nuevas.');
        }
        preg_match_all('/`([a-z0-9_]+)`/', $match[2], $columns);
        $row = array_combine($columns[1], $bindings);
        $row['id'] ??= count($this->rows[$match[1]] ?? []) + 1;
        $this->lastInsertId = $row['id'];
        $this->rows[$match[1]][] = $row;
        $this->dml[] = [$query, $bindings];

        return true;
    }

    public function update($query, $bindings = [])
    {
        if ($this->transactionLevel() < 1 || ! str_starts_with($query, 'update `data_bi_declaration_clarifications` set ')) {
            throw new LogicException('Solo la proyección de aclaración es mutable en el doble.');
        }
        preg_match_all('/`([a-z0-9_]+)` = \?/', $query, $columns);
        $values = array_combine($columns[1], $bindings);
        $id = array_pop($values);
        foreach ($this->rows['data_bi_declaration_clarifications'] as &$row) {
            if ($row['id'] === $id) {
                $row = [...$row, ...$values];
                $this->dml[] = [$query, $bindings];

                return 1;
            }
        }
        throw new LogicException('La proyección a actualizar no existe.');
    }
}
