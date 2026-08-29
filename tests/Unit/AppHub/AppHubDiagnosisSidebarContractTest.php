<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppHubDiagnosisSidebarContractTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );
    }

    public function test_subscriber_admin_navigation_defines_diagnosis_360(): void
    {
        $source = $this->read(
            'resources/js/config/navigationByRole.ts'
        );

        $this->assertStringContainsString(
            "title: 'Diagnóstico 360'",
            $source
        );

        $this->assertStringContainsString(
            "href: '/app/diagnostico-360'",
            $source
        );
    }

    public function test_app_sidebar_exposes_lauda_360_to_tenant_admin(): void
    {
        $source = $this->read(
            'resources/js/components/AppSidebar.vue'
        );

        $this->assertStringContainsString(
            "title: 'LAUDA 360'",
            $source
        );

        $this->assertStringContainsString(
            "'/app/diagnostico-360'",
            $source
        );

        $this->assertStringContainsString(
            "if (tenantMode.value === 'subscriber.admin') return tenantAdminSections.value;",
            $source
        );
    }

    public function test_regular_tenant_user_does_not_receive_diagnosis_section(): void
    {
        $source = $this->read(
            'resources/js/components/AppSidebar.vue'
        );

        $this->assertStringContainsString(
            "const tenantUserSections",
            $source
        );

        $this->assertStringContainsString(
            "items: byHrefs(tenantUserMain, ['/app'])",
            $source
        );
    }

    public function test_admin_diagnosis_navigation_remains_preserved(): void
    {
        $source = $this->read(
            'resources/js/components/AppSidebar.vue'
        );

        $this->assertStringContainsString(
            "items: byHrefs(adminMain, ['/admin/diagnosis-requests'])",
            $source
        );
    }
}
