<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;

trait TransactsDiagnosisDatabase
{
    use DatabaseTransactions { beginDatabaseTransaction as private beginFrameworkTransaction; }

    public function beginDatabaseTransaction(): void
    {
        if (IsolatedAccountDatabase::prepare()) {
            foreach ([
                '2026_01_12_023204_create_audit_logs_table',
                '2026_01_24_123104_create_invoices_table',
                '2026_08_23_130000_add_business_profile_to_diagnosis_assessments_table',
                '2026_08_23_230000_create_diagnosis_expanded_reports_table',
                '2026_08_24_010000_create_diagnosis_expanded_report_orders_table',
                '2026_08_24_020000_create_diagnosis_detailed_roadmaps_table',
                '2026_08_24_030000_create_diagnosis_detailed_roadmap_orders_table',
                '2026_08_24_150000_create_transformation_implementation_plans_table',
                '2026_08_30_150000_create_diagnosis_deliverable_validations_table',
                '2026_08_30_190000_create_transformation_capability_activations_table',
                '2026_08_30_213000_add_active_lifecycle_to_diagnosis_assessments_table',
                '2026_08_30_220100_create_transformation_capability_decisions_table',
                '2026_09_05_000113_create_transformation_implementation_definitions_table',
                '2026_09_05_121500_create_transformation_implementation_requests_tables',
            ] as $migration) {
                (require database_path('migrations/'.$migration.'.php'))->up();
            }
        }

        $this->beginFrameworkTransaction();
    }
}
