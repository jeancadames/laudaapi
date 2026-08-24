<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisDetailedRoadmap;
use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapContractTest extends TestCase
{
    public function test_model_has_versioned_review_workflow(): void
    {
        $this->assertSame(
            ['draft', 'under_review', 'published'],
            DiagnosisDetailedRoadmap::STATUSES
        );

        $draft = new DiagnosisDetailedRoadmap([
            'status' => DiagnosisDetailedRoadmap::STATUS_DRAFT,
        ]);

        $published = new DiagnosisDetailedRoadmap([
            'status' => DiagnosisDetailedRoadmap::STATUS_PUBLISHED,
        ]);

        $this->assertTrue($draft->isEditable());
        $this->assertFalse($published->isEditable());
    }

    public function test_foundation_keeps_billing_decoupled(): void
    {
        $root = dirname(__DIR__, 3);

        $source = implode("\n", array_map(
            fn (string $file): string => file_get_contents($file),
            [
                $root . '/app/Models/DiagnosisDetailedRoadmap.php',
                $root . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php',
                $root . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php',
                $root . '/database/migrations/2026_08_24_020000_create_diagnosis_detailed_roadmaps_table.php',
            ]
        ));

        foreach ([
            'invoice_id',
            'company_id',
            'subscriber_id',
            'subscription_id',
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_requires_published_expanded_report(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        $this->assertStringContainsString(
            'DiagnosisExpandedReport::STATUS_PUBLISHED',
            $source
        );

        $this->assertStringContainsString(
            'Debe existir un Informe Ampliado publicado',
            $source
        );
    }

    public function test_agreed_commercial_offer_remains_unchanged(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root . '/config/lauda360_commercial.php'
        );

        foreach ([
            "'subtotal' => 95000.00",
            "'expanded_report_credit' => 29900.00",
            "'expanded_report_credit_window_days' => 30",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }
}
