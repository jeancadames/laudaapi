<?php

namespace Tests\Unit\BusinessIdentity;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\BusinessIdentityMySql;

final class BusinessIdentityMySqlGuardTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->base, 0700);
        mkdir($this->base.'/central', 0700);
        $this->write('mysql-run.json', ['purpose' => 'business-f2-mysql', 'base' => $this->base, 'server' => '/tmp/bys-mysql-check-'.str_repeat('0', 16)]);
    }

    private function write(string $file, array $data): void
    {
        file_put_contents($this->base.'/'.$file, json_encode($data, JSON_THROW_ON_ERROR));
        chmod($this->base.'/'.$file, 0600);
    }

    protected function tearDown(): void
    {
        foreach ([$this->base.'/central/mysql.json', $this->base.'/mysql-run.json'] as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
        rmdir($this->base.'/central');
        rmdir($this->base);
    }

    public function test_operational_directory_is_rejected_before_access(): void
    {
        $this->expectException(RuntimeException::class);
        BusinessIdentityMySql::marker(dirname(__DIR__, 3));
    }

    public function test_commercial_marker_preserves_the_explicit_scope_and_private_directory_rules(): void
    {
        $this->write('mysql-run.json', ['purpose' => 'business-f5-mysql', 'base' => $this->base, 'server' => '/tmp/bys-mysql-check-'.str_repeat('0', 16)]);
        $this->assertSame('business-f5-mysql', BusinessIdentityMySql::marker($this->base)['purpose']);
        $this->write('mysql-run.json', ['purpose' => 'unknown', 'base' => $this->base]);
        $this->expectException(RuntimeException::class);
        BusinessIdentityMySql::marker($this->base);
    }

    public function test_public_runtime_is_rejected(): void
    {
        chmod($this->base, 0755);
        clearstatcache();
        $this->expectException(RuntimeException::class);
        BusinessIdentityMySql::marker($this->base);
    }

    public function test_symlink_manifest_is_rejected_before_reading(): void
    {
        unlink($this->base.'/mysql-run.json');
        symlink($this->base.'/nonexistent-target', $this->base.'/mysql-run.json');
        $this->expectException(RuntimeException::class);
        BusinessIdentityMySql::marker($this->base);
    }

    public function test_foreign_schema_is_rejected_before_connecting(): void
    {
        $this->write('central/mysql.json', ['database' => 'business_test_'.str_repeat('a', 16),
            'server' => '/tmp/bys-mysql-check-'.str_repeat('0', 16), 'password' => str_repeat('1', 64)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fuera del alcance');
        BusinessIdentityMySql::configuration($this->base.'/central');
    }

    public function test_missing_manifest_never_selects_another_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifiesto privado');
        BusinessIdentityMySql::configuration($this->base.'/central');
    }

    public function test_wrong_server_is_rejected_before_connecting(): void
    {
        $this->write('central/mysql.json', ['database' => 'business_test_'.substr(hash('sha256', $this->base.'/central'), 0, 16),
            'server' => '/tmp/bys-mysql-check-'.str_repeat('a', 16), 'password' => str_repeat('1', 64)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fuera del alcance');
        BusinessIdentityMySql::configuration($this->base.'/central');
    }
}
