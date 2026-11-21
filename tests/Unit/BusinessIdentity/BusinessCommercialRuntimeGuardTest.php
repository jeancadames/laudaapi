<?php

namespace Tests\Unit\BusinessIdentity;

use RuntimeException;
use Tests\Support\BusinessCommercialTestCase;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessCommercialRuntimeGuardTest extends BusinessCommercialTestCase
{
    public function test_foreign_storage_runtime_is_rejected_before_schema_changes(): void
    {
        $storage = app()->storagePath();
        try {
            app()->useStoragePath('/tmp/unrelated-business-runtime/storage');
            $this->expectException(RuntimeException::class);
            BusinessQuoteFixtures::assertSyntheticDatabase();
        } finally {
            app()->useStoragePath($storage);
        }
    }

    public function test_missing_synthetic_marker_is_rejected(): void
    {
        unlink($this->runtime.'/synthetic-only');
        $this->expectException(RuntimeException::class);
        BusinessQuoteFixtures::assertSyntheticDatabase();
    }

    public function test_mysql_manifest_prevents_silent_fallback_to_the_existing_sqlite_database(): void
    {
        file_put_contents($this->runtime.'/central/mysql.json', '{}');
        $this->expectException(RuntimeException::class);
        BusinessQuoteFixtures::assertSyntheticDatabase();
    }

    public function test_mysql_opt_in_never_accepts_a_sqlite_connection(): void
    {
        $previous = getenv('LAUDA_BUSINESS_MYSQL');
        try {
            putenv('LAUDA_BUSINESS_MYSQL=1');
            $this->expectException(RuntimeException::class);
            BusinessQuoteFixtures::assertSyntheticDatabase();
        } finally {
            putenv($previous === false ? 'LAUDA_BUSINESS_MYSQL' : 'LAUDA_BUSINESS_MYSQL='.$previous);
        }
    }
}
