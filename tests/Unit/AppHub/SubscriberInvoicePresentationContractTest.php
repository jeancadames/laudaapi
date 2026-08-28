<?php

function b11ProjectRoot(): string
{
    return dirname(__DIR__, 3);
}

function b11Read(string $relative): string
{
    $path = b11ProjectRoot().'/'.ltrim($relative, '/');

    if (! is_file($path)) {
        throw new RuntimeException(
            'Archivo de contrato no encontrado: '.$relative
        );
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException(
            'No se pudo leer archivo de contrato: '.$relative
        );
    }

    return $contents;
}

test('subscriber invoice ui hides raw technical payloads', function (): void {
    $vue = b11Read(
        'resources/js/pages/Subscriber/Invoices/Show.vue'
    );

    expect($vue)
        ->not->toContain('billing_snapshot')
        ->not->toContain('fiscal_meta')
        ->not->toContain('provider_invoice_id')
        ->not->toContain('activation_request_id')
        ->not->toContain('activation_request_service_id')
        ->not->toContain('entitlement_granted')
        ->not->toContain('prettyJson');
});

test('subscriber invoice ui renders a real commercial invoice', function (): void {
    $vue = b11Read(
        'resources/js/pages/Subscriber/Invoices/Show.vue'
    );

    foreach ([
        'Facturado a',
        'Fecha de emisión',
        'Concepto',
        'Precio',
        'Subtotal',
        'Impuestos',
        'Balance pendiente',
        'Imprimir',
        'Comprobante fiscal',
    ] as $required) {
        expect($vue)->toContain($required);
    }
});

test('subscriber invoice payment action is conditional', function (): void {
    $vue = b11Read(
        'resources/js/pages/Subscriber/Invoices/Show.vue'
    );

    expect($vue)
        ->toContain('const canPay = computed')
        ->toContain('Boolean(props.invoice.payment_url)')
        ->toContain('balanceNumber.value > 0')
        ->toContain('v-if="canPay"');
});

test('subscriber invoice controller returns sanitized items', function (): void {
    $controller = b11Read(
        'app/Http/Controllers/Subscriber/'
        .'SubscriberInvoiceController.php'
    );

    foreach ([
        "'items.service'",
        "'items.servicePlan'",
        "'description' =>",
        "'unit_price' =>",
        "'line_total' =>",
        "'plan_name' =>",
        "'billing_cycle' =>",
        "'payment_url' =>",
        "'hosted_invoice_url' =>",
    ] as $required) {
        expect($controller)->toContain($required);
    }

    expect($controller)
        ->not->toContain(
            "'billing_snapshot' => \$invoice->billing_snapshot"
        )
        ->not->toContain(
            "'fiscal_meta' => \$invoice->fiscal_meta"
        )
        ->not->toContain(
            "'provider_invoice_id' => \$invoice->provider_invoice_id"
        );
});
