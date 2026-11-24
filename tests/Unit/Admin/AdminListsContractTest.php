<?php

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Listados del panel de administración: los totales no mezclan monedas y los
 * filtros usan rutas que existen.
 */
class AdminListsContractTest extends TestCase
{
    private function read(string $relative): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$relative);
    }

    public function test_invoice_totals_are_split_by_currency(): void
    {
        $controller = $this->read('app/Http/Controllers/Admin/AdminInvoiceController.php');
        $page = $this->read('resources/js/pages/Admin/Invoices/Index.vue');

        $this->assertStringContainsString("'totals_by_currency' => \$totalsByCurrency", $controller);
        $this->assertStringContainsString("groupByRaw(\"COALESCE(currency, 'DOP')\")", $controller);
        $this->assertStringContainsString('v-for="t in totalsByCurrency"', $page);
        $this->assertStringNotContainsString("computed(() => 'USD')", $page);
    }

    public function test_subscription_filters_use_the_imported_route_helper(): void
    {
        $page = $this->read('resources/js/pages/Admin/Subscriptions/Index.vue');

        $this->assertStringContainsString("import subscriptionsRoutes from '@/routes/admin/subscriptions'", $page);
        $this->assertStringNotContainsString('subscriptions.index(', $page);
    }
}
