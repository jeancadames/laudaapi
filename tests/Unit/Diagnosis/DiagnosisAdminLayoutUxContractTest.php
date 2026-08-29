<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisAdminLayoutUxContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );
    }

    public function test_quick_actions_are_first_and_full_width(): void
    {
        $source = $this->source();

        $quick = strpos(
            $source,
            'DIAGNOSIS360_ADMIN_QUICK_ACTIONS_TOP'
        );

        $layout = strpos(
            $source,
            'DIAGNOSIS360_ADMIN_TWO_COLUMN_LAYOUT'
        );

        $this->assertNotFalse($quick);
        $this->assertNotFalse($layout);

        $this->assertLessThan(
            $layout,
            $quick
        );

        $this->assertSame(
            1,
            substr_count(
                $source,
                '<TransformationQuickActions'
            )
        );
    }

    public function test_checklist_is_left_and_sticky_on_desktop(): void
    {
        $source = $this->source();

        foreach ([
            'DIAGNOSIS360_ADMIN_CHECKLIST_LEFT',
            'lg:sticky lg:top-6',
            '<TransformationProgressChecklist',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertSame(
            1,
            substr_count(
                $source,
                '<TransformationProgressChecklist'
            )
        );
    }

    public function test_operational_cards_are_in_right_column(): void
    {
        $source = $this->source();

        $right = strpos(
            $source,
            'DIAGNOSIS360_ADMIN_RIGHT_COLUMN'
        );

        $request = strpos(
            $source,
            '<CardTitle>Solicitud y acceso</CardTitle>'
        );

        $access = strpos(
            $source,
            '<CardTitle>Acceso del cliente</CardTitle>'
        );

        $result = strpos(
            $source,
            'Resultado calculado'
        );

        foreach (
            [$right, $request, $access, $result]
            as $position
        ) {
            $this->assertNotFalse($position);
        }

        $this->assertLessThan(
            $request,
            $right
        );

        $this->assertLessThan(
            $access,
            $right
        );

        $this->assertLessThan(
            $result,
            $right
        );
    }

    public function test_admin_page_uses_wider_responsive_layout(): void
    {
        $source = $this->source();

        foreach ([
            'max-w-[1600px]',
            'space-y-6',
            'grid items-start gap-6',
            'lg:grid-cols-[minmax(340px,0.8fr)_minmax(0,1.2fr)]',
            'min-w-0 space-y-6',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'grid gap-6 lg:grid-cols-[0.8fr_1.2fr]',
            $source
        );
    }
}
