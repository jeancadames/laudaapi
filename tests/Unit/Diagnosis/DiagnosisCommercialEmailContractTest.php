<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisCommercialEmailContractTest extends TestCase
{
    public function test_mailable_is_queued_and_has_all_milestones(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Mail/DiagnosisCommercialMilestoneMail.php'
        );

        foreach ([
            'implements ShouldQueue',
            'expanded_report:invoice_required',
            'expanded_report:payment_confirmed',
            'expanded_report:published',
            'detailed_roadmap:invoice_required',
            'detailed_roadmap:payment_confirmed',
            'detailed_roadmap:published',
            'diagnosis_commercial_email_delivery_failed',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_notification_service_is_non_blocking_and_audited(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisCommercialNotificationService.php'
        );

        foreach ([
            'Mail::to($recipient->email)',
            '->queue(',
            'catch (Throwable $e)',
            'diagnosis_commercial_email_attempted',
            'diagnosis_commercial_email_queued',
            'diagnosis_commercial_email_skipped',
            'diagnosis_commercial_email_queue_failed',
            'report($e);',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_controllers_notify_invoice_payment_and_publication(): void
    {
        $root = dirname(__DIR__, 3);

        $files = [
            '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportCommercialController.php',
            '/app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php',
            '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapCommercialController.php',
            '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php',
        ];

        $source = implode(
            "\n",
            array_map(
                fn (string $file): string =>
                    file_get_contents(
                        $root . $file
                    ),
                $files
            )
        );

        foreach ([
            'invoiceRequired(',
            'paymentConfirmed(',
            'deliverablePublished(',
            "'expanded_report'",
            "'detailed_roadmap'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_email_copy_includes_scope_credit_and_exclusions(): void
    {
        $root = dirname(__DIR__, 3);

        $view = file_get_contents(
            $root
            . '/resources/views/emails/diagnosis-commercial-milestone.blade.php'
        );

        foreach ([
            'Alcance',
            'No incluye automáticamente',
            'Pago requerido',
            'Pago confirmado',
            'Entregable disponible',
            'Crédito Informe Ampliado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $view
            );
        }
    }
}
