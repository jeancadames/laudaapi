<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class AppGatewayHubContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_gateway_renders_generic_hub(): void
    {
        $source = $this->read(
            'app/Http/Controllers/AppGatewayController.php'
        );

        foreach ([
            'EcosystemHubService',
            "'App/Hub'",
            "'groups' =>",
            "'company' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'erp.dashboard'",
            $source
        );
    }

    public function test_launch_url_requires_entitlement_and_ready_integration(): void
    {
        $source = $this->read(
            'app/Services/Ecosystem/EcosystemHubService.php'
        );

        foreach ([
            'ServiceAccessResolver',
            'userCanAccess(',
            '$entitled',
            '$ready',
            "'erp.services.open'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Subscription::create',
            'SubscriptionItem::create',
            'Service::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_ui_is_grouped_by_action(): void
    {
        $page = $this->read(
            'resources/js/pages/App/Hub.vue'
        );

        foreach ([
            'v-for="group in props.groups"',
            'v-for="solution in group.solutions"',
            'solution.launch_url',
            'Integración en preparación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }
}
