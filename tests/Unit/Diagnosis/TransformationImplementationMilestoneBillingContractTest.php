<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationMilestoneBillingContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migration_creates_milestones_without_creating_invoices(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_161000_create_transformation_implementation_milestones_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_milestones'",
            $migration
        );

        foreach ([
            "'transformation_implementation_phase_id'",
            "'sequence'",
            "'modality'",
            "'billing_amount'",
            "'currency'",
            "'billing_status'",
            "'invoice_reference'",
            "'payment_reference'",
            "'scope_snapshot'",
        ] as $required) {
            $this->assertStringContainsString($required, $migration);
        }

        $this->assertStringNotContainsString("Schema::create('invoices'", $migration);
        $this->assertStringNotContainsString("Schema::create('payments'", $migration);
        $this->assertStringNotContainsString("Schema::create('subscriptions'", $migration);
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_161000_create_transformation_implementation_milestones_table.php'
        ));

        foreach ([
            'tip_ms_phase_fk',
            'tip_ms_created_fk',
            'tip_ms_updated_fk',
            'tip_ms_phase_seq_uq',
            'tip_ms_phase_status_idx',
            'tip_ms_invoice_ref_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $migration);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_milestones_are_bound_to_selected_modality_and_estimate(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        ));

        $this->assertStringContainsString('selected_modality', $service);
        $this->assertStringContainsString('forSelectedModality', $service);
        $this->assertStringContainsString('estimate_price_amount', $service);
        $this->assertStringContainsString('estimate_scope_snapshot', $service);
    }

    public function test_milestone_allocation_must_equal_phase_price(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        ));

        $this->assertStringContainsString('allocationSummary', $service);
        $this->assertStringContainsString('assertAllocationComplete', $service);
        $this->assertStringContainsString(
            'La suma de los hitos debe coincidir exactamente con el precio de la fase.',
            $service
        );
        $this->assertStringContainsString(
            'La suma de hitos no puede superar el precio de la fase.',
            $service
        );
    }

    public function test_billing_requires_accepted_plan(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        ));

        $this->assertStringContainsString("\$plan->status !== 'accepted'", $service);
        $this->assertStringContainsString('!$plan->accepted_at', $service);
        $this->assertStringContainsString(
            'La implementación solo puede facturarse después de aceptar el Plan de Implementación.',
            $service
        );
    }

    public function test_invoice_and_payment_are_external_references(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        ));

        $this->assertStringContainsString('recordInvoiceReference', $service);
        $this->assertStringContainsString('recordPaymentReference', $service);
        $this->assertStringContainsString("'invoice_reference' => \$invoiceReference", $service);
        $this->assertStringContainsString("'payment_reference' => \$paymentReference", $service);
    }

    public function test_r2f_does_not_start_subscription_or_create_commercial_entities(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationMilestoneBillingService.php'
        ));

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
            'createSubscription',
            'startSubscription',
            'go_live',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
