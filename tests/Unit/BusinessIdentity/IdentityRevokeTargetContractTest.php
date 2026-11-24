<?php

namespace Tests\Unit\BusinessIdentity;

use PHPUnit\Framework\TestCase;

/**
 * «Desconectar la empresa» nunca actúa sobre una empresa distinta de la que se ve:
 * la confirmación pertenece a la combinación empresa + aplicación.
 */
class IdentityRevokeTargetContractTest extends TestCase
{
    private function page(): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/Business/Identity.vue');
    }

    public function test_changing_company_cancels_the_pending_confirmation(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/watch\(selectedCompany, \(\) => \{\s*revoke\.value = null;\s*\}\);/', $page);
    }

    public function test_inline_confirmation_matches_company_and_application(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('v-if="revoke && revokeTargets(service.key)"', $page);
        $this->assertStringContainsString('revoke.value.serviceKey === serviceKey &&', $page);
        $this->assertStringContainsString('revoke.value.company.id === selected.value.id', $page);
        $this->assertStringNotContainsString('revoke.service === service.name', $page);
        $this->assertSame(2, substr_count($page, 'serviceKey: service.key,'));
    }

    public function test_confirming_refuses_a_company_that_is_no_longer_shown(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression(
            "/props\.identity\.side === 'central' &&\s*revoke\.value\.company\.id !== selected\.value\?\.id\s*\) \{\s*revoke\.value = null;\s*return;/",
            $page,
        );
    }
}
