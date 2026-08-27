<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

class EcosystemHubCatalogContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_identity_group_contains_presence_social_and_crm(): void
    {
        $catalog = require $this->root()
            .'/config/ecosystem_hub.php';

        $group = $catalog['groups'][
            'identity_relationship'
        ];

        $this->assertSame(
            'Identidad y Relación Digital',
            $group['title']
        );

        $this->assertSame(
            ['digital_presence', 'social', 'crm'],
            array_keys($group['solutions'])
        );

        $this->assertTrue(
            $group['solutions']['social']['first_wave']
        );

        $this->assertTrue(
            $group['solutions']['crm']['first_wave']
        );
    }

    public function test_independent_domains_are_catalogued(): void
    {
        $source = file_get_contents(
            $this->root().'/config/ecosystem_hub.php'
        );

        foreach ([
            'https://social.laudaapi.com',
            'https://crm.laudaapi.com',
            'https://pos.laudaapi.com',
            'https://bys.laudaapi.com',
            'https://ecf.laudaapi.com',
            'https://cumplimiento.laudaapi.com',
        ] as $domain) {
            $this->assertStringContainsString(
                $domain,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'LaudaOne',
            $source
        );

        $this->assertStringNotContainsString(
            'LaudaGo',
            $source
        );
    }
}
