<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class AppHubOnboardingWidthContractTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );
    }

    public function test_auth_simple_keeps_compact_default_and_adds_wide_mode(): void
    {
        $source = $this->read(
            'resources/js/layouts/auth/AuthSimpleLayout.vue'
        );

        $this->assertStringContainsString(
            'wide?: boolean;',
            $source
        );

        $this->assertStringContainsString(
            "wide ? 'max-w-6xl' : 'max-w-sm'",
            $source
        );

        $this->assertStringContainsString(
            "'w-full'",
            $source
        );
    }

    public function test_auth_wrapper_forwards_optional_wide_mode(): void
    {
        $source = $this->read(
            'resources/js/layouts/AuthLayout.vue'
        );

        $this->assertStringContainsString(
            'wide?: boolean;',
            $source
        );

        $this->assertStringContainsString(
            ':wide="wide"',
            $source
        );
    }

    public function test_only_apphub_onboarding_requests_wide_auth_layout(): void
    {
        $source = $this->read(
            'resources/js/pages/Onboarding/AppHub.vue'
        );

        $this->assertMatchesRegularExpression(
            '/<AuthBase\s+wide\s+title="Configura tu empresa"/',
            $source
        );

        $this->assertStringContainsString(
            '<CompanyProfileForm',
            $source
        );

        $this->assertStringContainsString(
            'onboarding',
            $source
        );
    }
}
