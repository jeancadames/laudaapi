<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

final class IsolatedAccountDatabase
{
    public static function prepare(): bool
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            return false;
        }

        // Keep the account fixture separate from the shared RefreshDatabase in-memory PDO.
        config(['database.connections.account_test' => config('database.connections.sqlite')]);
        DB::setDefaultConnection('account_test');
        if (! config('app.key')) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }
        BusinessIdentityApplication::centralSchema();
        foreach ([
            '2026_01_11_204200_create_contact_requests_table',
            '2026_08_22_000001_create_diagnosis_assessments_table',
            '2026_08_22_000002_create_diagnosis_access_requests_table',
            '2026_08_22_000003_add_review_publication_fields_to_diagnosis_assessments_table',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }

        return true;
    }
}
