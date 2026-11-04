<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // MySQL DDL commits implicitly. Never borrow an application's open transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        $this->changeNullability(true);
    }

    public function down(): void
    {
        $this->changeNullability(false);
    }

    private function changeNullability(bool $nullable): void
    {
        $connection = DB::connection($this->getConnection());
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('This migration requires MySQL.');
        }

        // Use one physical PDO throughout; do not reconnect/retry an ALTER after checking SQL mode.
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Refusing DDL inside an existing transaction.');
        }

        if ((string) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() !== '1') {
            throw new RuntimeException('Foreign key checks must remain enabled; no session setting was changed.');
        }

        $mode = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $modes = array_map('trim', explode(',', strtoupper((string) $mode)));
        if (! array_intersect(['STRICT_ALL_TABLES', 'STRICT_TRANS_TABLES'], $modes)) {
            throw new RuntimeException('Strict SQL mode is required; no session setting was changed.');
        }

        $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles'")->fetchColumn();
        if ($engine !== 'InnoDB') {
            throw new RuntimeException('Expected an InnoDB company_tax_profiles table.');
        }

        $column = $pdo->query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT, EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'")->fetch(PDO::FETCH_ASSOC);
        if (! is_array($column)
            || $column['DATA_TYPE'] !== 'varchar'
            || (string) $column['CHARACTER_MAXIMUM_LENGTH'] !== '255'
            || ! in_array($column['IS_NULLABLE'], ['YES', 'NO'], true)
            || $column['COLUMN_DEFAULT'] !== null
            || $column['COLUMN_COMMENT'] !== ''
            || $column['EXTRA'] !== ''
            || ($column['GENERATION_EXPRESSION'] ?? '') !== '') {
            throw new RuntimeException('Unexpected legal_name definition; review it before altering the column.');
        }

        $charset = $column['CHARACTER_SET_NAME'];
        $collation = $column['COLLATION_NAME'];
        if (! is_string($charset) || ! is_string($collation)
            || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $charset)
            || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $collation)) {
            throw new RuntimeException('Unverifiable character set or collation.');
        }

        $indexed = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'")->fetchColumn();
        $constrained = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'")->fetchColumn();
        if ((string) $indexed !== '0' || (string) $constrained !== '0') {
            throw new RuntimeException('Unexpected legal_name index or key constraint; review before proceeding.');
        }

        if (($column['IS_NULLABLE'] === 'YES') === $nullable) {
            return;
        }

        if (! $nullable) {
            $nulls = $pdo->query('SELECT COUNT(*) FROM `company_tax_profiles` WHERE `legal_name` IS NULL')->fetchColumn();
            if ((string) $nulls !== '0') {
                throw new RuntimeException('Rollback refused: legal_name contains NULL. No rows were replaced or deleted.');
            }
        }

        // Identical type/charset/collation; only NULL/default semantics change. No data DML.
        // Strict mode on this SAME PDO also rejects NULL inserted between the count and ALTER.
        $nullability = $nullable ? 'NULL DEFAULT NULL' : 'NOT NULL';
        $pdo->exec('ALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR(255) CHARACTER SET '
            .$charset.' COLLATE '.$collation.' '.$nullability.', ALGORITHM=INPLACE, LOCK=SHARED');
    }
};
