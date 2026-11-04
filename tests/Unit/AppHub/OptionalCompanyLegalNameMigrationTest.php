<?php

declare(strict_types=1);

namespace Tests\Unit\AppHub;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class OptionalCompanyLegalNameMigrationTest extends TestCase
{
    private array $previous;
    private LegalNameDdlMemoryPdo $pdo;
    private object $migration;

    protected function setUp(): void
    {
        $this->previous=['container'=>Container::getInstance(),'app'=>Facade::getFacadeApplication(),
            'roots'=>(new ReflectionProperty(Facade::class,'resolvedInstance'))->getValue()];
        $app=new Container; Container::setInstance($app); Facade::setFacadeApplication($app); Facade::clearResolvedInstances();
        $this->pdo=new LegalNameDdlMemoryPdo;
        DB::swap(new class($this->pdo) {
            public string $driver='mysql';
            public function __construct(private PDO $pdo) {}
            public function connection($name=null): object { return $this; }
            public function getDriverName(): string { return $this->driver; }
            public function getPdo(): PDO { return $this->pdo; }
        });
        // Unit execution against an uninitialized-native PDO double. No migrator, framework bootstrap or DB.
        $this->migration=require dirname(__DIR__,3).'/database/migrations/2026_11_02_000001_make_company_tax_profiles_legal_name_nullable.php';
    }
    protected function tearDown(): void
    {
        try {
            foreach($this->pdo->attempts as $sql) self::assertStringStartsWith('ALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR(255) CHARACTER SET ',$sql);
            foreach($this->pdo->queries as $sql) self::assertStringStartsWith('SELECT ',$sql);
        } finally {
            Container::setInstance($this->previous['container']); Facade::setFacadeApplication($this->previous['app']);
            (new ReflectionProperty(Facade::class,'resolvedInstance'))->setValue(null,$this->previous['roots']);
        }
    }

    public function test_up_changes_only_nullability_with_the_original_type_and_collation(): void
    {
        $this->migration->up();
        self::assertSame(['ALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, ALGORITHM=INPLACE, LOCK=SHARED'],$this->pdo->applied);
        self::assertContains('SELECT @@SESSION.foreign_key_checks',$this->pdo->queries);
        self::assertSame('1',$this->pdo->fkChecks);
        self::assertSame(0,$this->pdo->businessAggregateReads);
        self::assertFalse($this->migration->withinTransaction);
    }
    public function test_down_restores_not_null_only_when_no_nulls_exist(): void
    {
        $this->pdo->nullable=true; $this->migration->down();
        self::assertSame(['ALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, ALGORITHM=INPLACE, LOCK=SHARED'],$this->pdo->applied);
        self::assertSame(1,$this->pdo->businessAggregateReads);
    }
    public function test_down_refuses_nulls_without_filling_or_deleting_rows(): void
    {
        $this->pdo->nullable=true; $this->pdo->nulls=2;
        try { $this->migration->down(); self::fail('Unsafe rollback accepted.'); }
        catch(RuntimeException $e) { self::assertStringContainsString('Rollback refused',$e->getMessage()); }
        self::assertSame([],$this->pdo->attempts); self::assertSame(2,$this->pdo->nulls);
    }
    public function test_strict_engine_failure_on_a_concurrent_null_is_propagated(): void
    {
        $this->pdo->nullable=true; $this->pdo->concurrentNull=true;
        try { $this->migration->down(); self::fail('Concurrent failure swallowed.'); }
        catch(PDOException) {}
        self::assertSame(1,count($this->pdo->attempts)); self::assertSame([],$this->pdo->applied);
        self::assertSame(1,$this->pdo->nulls);
    }
    public function test_non_strict_session_is_refused_without_changing_sql_mode(): void
    {
        $this->pdo->mode='NO_ENGINE_SUBSTITUTION'; $this->expectException(RuntimeException::class); $this->migration->down();
    }
    public function test_an_existing_transaction_is_never_committed_by_ddl(): void
    {
        $this->pdo->inside=true;
        try { $this->migration->up(); self::fail('Outer transaction accepted.'); } catch(RuntimeException) {}
        self::assertSame([],$this->pdo->queries); self::assertSame([],$this->pdo->attempts);
    }
    public function test_other_drivers_are_refused_before_accessing_pdo(): void
    {
        DB::getFacadeRoot()->driver='unsupported'; $this->expectException(RuntimeException::class); $this->migration->up();
    }
    public function test_disabled_foreign_key_checks_are_rejected_without_enabling_them_or_running_ddl(): void
    {
        $this->pdo->fkChecks='0';
        foreach (['up','down'] as $direction) {
            $this->pdo->nullable=$direction==='down';
            try { $this->migration->$direction(); self::fail('Disabled FK checks accepted.'); }
            catch(RuntimeException $e) { self::assertStringContainsString('Foreign key checks must remain enabled',$e->getMessage()); }
        }
        self::assertSame('0',$this->pdo->fkChecks);
        self::assertSame(['SELECT @@SESSION.foreign_key_checks','SELECT @@SESSION.foreign_key_checks'],$this->pdo->queries);
        self::assertSame([],$this->pdo->attempts);
    }
    public function test_shared_nullability_changes_preserve_the_fixture_fk_rules_and_indexes(): void
    {
        // Scope contract only: this PDO double does not emulate MySQL's FK/lock engine.
        $foreignKeys=$this->pdo->foreignKeys; $indexes=$this->pdo->indexes;
        self::assertSame(['CASCADE','SET NULL'],array_column($foreignKeys,'DELETE_RULE'));
        self::assertSame(['NO ACTION','NO ACTION'],array_column($foreignKeys,'UPDATE_RULE'));
        $this->migration->up(); $this->migration->down();
        self::assertSame($foreignKeys,$this->pdo->foreignKeys);
        self::assertSame($indexes,$this->pdo->indexes);
        self::assertSame(2,count($this->pdo->applied));
    }
    public function test_transport_refuses_all_unapproved_ddl_strategies(): void
    {
        $approved='ALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, ALGORITHM=INPLACE, LOCK=SHARED';
        $unapproved=[str_replace('ALGORITHM=INPLACE','ALGORITHM=COPY',$approved)];
        foreach (['NONE','DEFAULT','EXCLUSIVE'] as $lock) $unapproved[]=str_replace('LOCK=SHARED','LOCK='.$lock,$approved);
        foreach ($unapproved as $sql) {
            try { $this->pdo->exec($sql); self::fail('Unapproved strategy accepted.'); }
            catch(LogicException $e) { self::assertSame('UNKNOWN_DDL_OR_DML',$e->getMessage()); }
        }
        self::assertSame([],$this->pdo->attempts);
    }
    public function test_unknown_queries_fk_changes_and_business_writes_never_get_automatic_success(): void
    {
        foreach (['SELECT 1','SELECT * FROM companies'] as $sql) {
            try { $this->pdo->query($sql); self::fail('Unknown query accepted.'); }
            catch(LogicException $e) { self::assertSame('UNEXPECTED_METADATA_QUERY',$e->getMessage()); }
        }
        foreach ([
            'ALTER TABLE `company_tax_profiles` DROP FOREIGN KEY `company_tax_profiles_company_id_foreign`',
            'ALTER TABLE `company_tax_profiles` DROP FOREIGN KEY `company_tax_profiles_fiscal_year_end_id_foreign`',
            'ALTER TABLE `company_tax_profiles` DROP INDEX `idx_company_fye`',
            'ALTER TABLE `companies` ADD COLUMN `unexpected` int',
            'UPDATE `company_tax_profiles` SET `legal_name` = \'invented\'',
            'INSERT INTO `invoices` (`id`) VALUES (1)',
            'DELETE FROM `company_tax_profiles`',
            'SET FOREIGN_KEY_CHECKS=0',
            'SET SESSION sql_mode=\'\'',
            'COMMIT',
        ] as $sql) {
            try { $this->pdo->exec($sql); self::fail('Unknown mutation accepted.'); }
            catch(LogicException $e) { self::assertSame('UNKNOWN_DDL_OR_DML',$e->getMessage()); }
        }
        self::assertSame([],$this->pdo->attempts);
    }
    public static function invalidMetadata(): array
    {
        return [['DATA_TYPE','text'],['CHARACTER_MAXIMUM_LENGTH',256],['COLUMN_DEFAULT','unexpected'],['COLUMN_COMMENT','preserve this comment'],['EXTRA','STORED GENERATED'],['GENERATION_EXPRESSION','unexpected'],['COLLATION_NAME','bad collation'],['IS_NULLABLE','unknown']];
    }
    #[DataProvider('invalidMetadata')]
    public function test_unknown_column_attributes_fail_closed(string $key,mixed $value): void
    {
        $this->pdo->column[$key]=$value; $this->expectException(RuntimeException::class); $this->migration->up();
    }
    public function test_an_unexpected_index_is_preserved_by_refusing_the_migration(): void
    {
        $this->pdo->indexed=1; $this->expectException(RuntimeException::class); $this->migration->up();
    }
    public function test_an_unexpected_key_constraint_is_not_dropped(): void
    {
        $this->pdo->constrained=1; $this->expectException(RuntimeException::class); $this->migration->up();
    }
    public function test_nontransactional_engine_is_rejected(): void
    {
        $this->pdo->engine='MyISAM'; $this->expectException(RuntimeException::class); $this->migration->up();
    }
    public function test_same_state_retries_are_idempotent(): void
    {
        $this->pdo->nullable=true; $this->migration->up(); self::assertSame([],$this->pdo->attempts);
        $this->pdo->nullable=false; $this->migration->down(); self::assertSame([],$this->pdo->attempts);
    }
    public function test_a_different_existing_collation_is_reused_exactly(): void
    {
        $this->pdo->column['COLLATION_NAME']='utf8mb4_spanish_ci'; $this->migration->up();
        self::assertStringContainsString('COLLATE utf8mb4_spanish_ci NULL DEFAULT NULL',$this->pdo->applied[0]);
    }
}

/** No native constructor/connection. Only fixed metadata, an aggregate and one strict ALTER shape are modeled. */
final class LegalNameDdlMemoryPdo extends PDO
{
    public bool $inside=false;
    public string $fkChecks='1';
    public bool $nullable=false;
    public bool $concurrentNull=false;
    public int $nulls=0;
    public int $indexed=0;
    public int $constrained=0;
    public int $businessAggregateReads=0;
    public string $mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
    public string $engine='InnoDB';
    public array $queries=[];
    public array $attempts=[];
    public array $applied=[];
    public array $foreignKeys=[
        ['CONSTRAINT_NAME'=>'company_tax_profiles_company_id_foreign','DELETE_RULE'=>'CASCADE','UPDATE_RULE'=>'NO ACTION'],
        ['CONSTRAINT_NAME'=>'company_tax_profiles_fiscal_year_end_id_foreign','DELETE_RULE'=>'SET NULL','UPDATE_RULE'=>'NO ACTION'],
    ];
    public array $indexes=[
        'PRIMARY'=>['id'],
        'company_id_unique'=>['company_id'],
        'fiscal_year_end_id_index'=>['fiscal_year_end_id'],
        'idx_company_fye'=>['country_code','tax_id','company_id','fiscal_year_end_id'],
    ];
    public array $column=['DATA_TYPE'=>'varchar','CHARACTER_MAXIMUM_LENGTH'=>255,'IS_NULLABLE'=>'NO','COLUMN_DEFAULT'=>null,'CHARACTER_SET_NAME'=>'utf8mb4','COLLATION_NAME'=>'utf8mb4_unicode_ci','COLUMN_COMMENT'=>'','EXTRA'=>'','GENERATION_EXPRESSION'=>''];
    public function __construct() {}
    public function inTransaction(): bool { return $this->inside; }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[]=$query;
        if($query==='SELECT @@SESSION.foreign_key_checks') return new LegalNameDdlMemoryStatement($this->fkChecks);
        if($query==='SELECT @@SESSION.sql_mode') return new LegalNameDdlMemoryStatement($this->mode);
        if($query==="SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles'") return new LegalNameDdlMemoryStatement($this->engine);
        if($query==="SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT, EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'") return new LegalNameDdlMemoryStatement(array_replace($this->column,['IS_NULLABLE'=>$this->column['IS_NULLABLE']==='NO' && $this->nullable ? 'YES' : $this->column['IS_NULLABLE']]));
        if($query==="SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'") return new LegalNameDdlMemoryStatement($this->indexed);
        if($query==="SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_tax_profiles' AND COLUMN_NAME = 'legal_name'") return new LegalNameDdlMemoryStatement($this->constrained);
        if($query==='SELECT COUNT(*) FROM `company_tax_profiles` WHERE `legal_name` IS NULL') { ++$this->businessAggregateReads; return new LegalNameDdlMemoryStatement($this->nulls); }
        throw new LogicException('UNEXPECTED_METADATA_QUERY');
    }
    public function exec(string $statement): int|false
    {
        if(!preg_match('/\AALTER TABLE `company_tax_profiles` MODIFY COLUMN `legal_name` VARCHAR\(255\) CHARACTER SET utf8mb4 COLLATE utf8mb4_(?:unicode|spanish)_ci (NULL DEFAULT NULL|NOT NULL), ALGORITHM=INPLACE, LOCK=SHARED\z/',$statement,$m)) throw new LogicException('UNKNOWN_DDL_OR_DML');
        $this->attempts[]=$statement;
        if($m[1]==='NOT NULL' && $this->concurrentNull) { $this->nulls=1; throw new PDOException('SIMULATED_STRICT_DDL_FAILURE'); }
        $this->applied[]=$statement; $this->nullable=$m[1]==='NULL DEFAULT NULL'; return 0;
    }
    public function commit(): bool { throw new LogicException('PHYSICAL_COMMIT_FORBIDDEN'); }
}
final class LegalNameDdlMemoryStatement extends PDOStatement
{
    public function __construct(private mixed $result) {}
    public function fetchColumn(int $column=0): mixed { return $this->result; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->result; }
}
