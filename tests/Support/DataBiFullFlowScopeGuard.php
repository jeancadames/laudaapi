<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;

/** Additional B5 barrier, before SQL execution. No production code or connection. */
final class DataBiFullFlowScopeGuard
{
    // These five existing domain tables are populated only by the audited B5 writes.
    private const INSERT_COLUMNS = [
        'data_bi_information_requests' => ['transformation_implementation_request_id', 'company_id', 'version',
            'status', 'review_snapshot', 'content_sha256', 'created_by_user_id', 'sent_at', 'sent_by_user_id', 'created_at', 'updated_at'],
        'data_bi_tenant_declarations' => ['data_bi_information_request_id', 'company_id', 'created_by_user_id',
            'status', 'revision', 'declaration_snapshot', 'content_sha256', 'updated_by_user_id',
            'submitted_by_user_id', 'submitted_at', 'created_at', 'updated_at'],
        'data_bi_declaration_clarifications' => ['transformation_implementation_request_id', 'company_id',
            'data_bi_information_request_id', 'data_bi_tenant_declaration_id', 'id', 'requirement_key', 'question_sha256',
            'status', 'revision', 'clarification_snapshot', 'content_sha256', 'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at'],
        'data_bi_declaration_clarification_events' => ['data_bi_declaration_clarification_id', 'sequence', 'action',
            'message', 'actor_user_id', 'actor_kind', 'occurred_at', 'previous_sha256', 'projection_sha256', 'content_sha256'],
        'data_bi_declarative_review_closures' => ['transformation_implementation_request_id', 'company_id',
            'data_bi_information_request_id', 'data_bi_tenant_declaration_id', 'conclusion', 'limitations', 'declared_gaps',
            'declared_limitations', 'clarification_references', 'closure_snapshot', 'content_sha256', 'closed_by_user_id', 'closed_at'],
    ];

    private const UPDATE_COLUMNS = [
        'data_bi_information_requests' => ['status', 'sent_at', 'sent_by_user_id', 'updated_at'],
        'data_bi_tenant_declarations' => ['status', 'revision', 'declaration_snapshot', 'content_sha256',
            'updated_by_user_id', 'submitted_by_user_id', 'submitted_at', 'updated_at'],
        'data_bi_declaration_clarifications' => ['status', 'revision', 'clarification_snapshot', 'content_sha256',
            'updated_by_user_id', 'updated_at'],
        'data_bi_declaration_clarification_events' => [], // Append-only history.
        'data_bi_declarative_review_closures' => [], // Immutable closure.
    ];

    private const REFERENCES = [
        'company_id' => 'companies', 'organization_id' => 'companies',
        'subscriber_id' => 'subscribers', 'user_id' => 'users', 'owner_user_id' => 'users',
        'created_by_user_id' => 'users', 'updated_by_user_id' => 'users',
        'requested_by_user_id' => 'users', 'generated_by_user_id' => 'users',
        'sent_by_user_id' => 'users', 'submitted_by_user_id' => 'users',
        'actor_user_id' => 'users', 'closed_by_user_id' => 'users',
        'diagnosis_assessment_id' => 'diagnosis_assessments',
        'diagnosis_detailed_roadmap_id' => 'diagnosis_detailed_roadmaps',
        'transformation_implementation_plan_id' => 'transformation_implementation_plans',
        'transformation_implementation_phase_id' => 'transformation_implementation_phases',
        'transformation_implementation_phase_capability_id' => 'transformation_implementation_phase_capabilities',
        'transformation_implementation_request_id' => 'transformation_implementation_requests',
        'data_bi_information_request_id' => 'data_bi_information_requests',
        'data_bi_tenant_declaration_id' => 'data_bi_tenant_declarations',
        'data_bi_declaration_clarification_id' => 'data_bi_declaration_clarifications',
    ];

    public static function assertSql(string $sql, array $bindings, array $owned): void
    {
        if (str_contains($sql, ';')) {
            throw new LogicException('B5: SQL múltiple no autorizado.');
        }
        if (preg_match('/\A\s*(select|show)\b/i', $sql)) {
            return; // The approved rollback connection also checks these statements.
        }
        if (preg_match('/\Ainsert into `([a-z0-9_]+)` \((`[a-z0-9_]+`(?:, `[a-z0-9_]+`)*)\) values \((\?(?:, \?)*)\)\z/i', $sql, $m)) {
            $table = $m[1];
            preg_match_all('/`([a-z0-9_]+)`/', $m[2], $columns);
            self::table($table);
            if (! array_is_list($bindings) || count($columns[1]) !== count($bindings)) {
                throw new LogicException('B5: INSERT no verificable.');
            }
            self::columns($table, $columns[1], self::INSERT_COLUMNS);
            self::references(array_combine($columns[1], $bindings), $owned);

            return;
        }
        // Model::save() qualifies updated_at on these two demonstrated timestamped
        // models only. Normalize for recognition; the executed SQL stays untouched.
        $updateSql = preg_match('/\Aupdate `(data_bi_information_requests|data_bi_tenant_declarations)` set /i', $sql, $target)
            ? str_replace('`'.$target[1].'`.`updated_at`', '`updated_at`', $sql)
            : $sql;
        if (preg_match('/\Aupdate `([a-z0-9_]+)` set (`[a-z0-9_]+` = \?(?:, `[a-z0-9_]+` = \?)*) where `id` = \?\z/i', $updateSql, $m)) {
            self::table($m[1]);
            self::owned($m[1], $bindings[count($bindings) - 1] ?? null, $owned);
            preg_match_all('/`([a-z0-9_]+)` = \?/', $m[2], $columns);
            if (! array_is_list($bindings) || count($columns[1]) + 1 !== count($bindings) || in_array('id', $columns[1], true)) {
                throw new LogicException('B5: UPDATE no verificable.');
            }
            self::columns($m[1], $columns[1], self::UPDATE_COLUMNS);
            self::references(array_combine($columns[1], array_slice($bindings, 0, -1)), $owned);

            return;
        }
        throw new LogicException('B5: DML bloqueado; solo INSERT único o UPDATE por ID sintético.');
    }

    private static function table(string $table): void
    {
        if (! in_array($table, DataBiDeclarativeReviewRollbackConnection::FIXTURE_TABLES, true)) {
            throw new LogicException('B5: tabla fuera de los fixtures autorizados.');
        }
    }

    private static function references(array $values, array $owned): void
    {
        foreach (self::REFERENCES as $column => $table) {
            if (array_key_exists($column, $values) && $values[$column] !== null) {
                self::owned($table, $values[$column], $owned);
            }
        }
    }

    private static function columns(string $table, array $columns, array $allowlist): void
    {
        if (count(array_unique($columns)) !== count($columns)
            || (array_key_exists($table, $allowlist) && array_diff($columns, $allowlist[$table]) !== [])) {
            throw new LogicException('B5: columnas no autorizadas o repetidas.');
        }
    }

    private static function owned(string $table, mixed $id, array $owned): void
    {
        if ((! is_int($id) && ! (is_string($id) && ctype_digit($id)))
            || ! in_array((int) $id, array_values($owned[$table] ?? []), true)) {
            throw new LogicException('B5: referencia o UPDATE ajeno al ámbito sintético.');
        }
    }
}
