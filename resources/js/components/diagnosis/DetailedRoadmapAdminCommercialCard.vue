<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, LockKeyhole, ReceiptText } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    commercial: Record<string, any> | null;
    prepareInvoiceUrl: string | null;
    recordPaymentUrl: string | null;
}>();

const paymentForm = useForm({
    method: 'bank_transfer',
    reference: '',
});

function money(value: any, currency = 'DOP') {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function prepareInvoice() {
    if (!props.prepareInvoiceUrl) return;

    router.post(props.prepareInvoiceUrl, {}, { preserveScroll: true });
}

function recordPayment() {
    if (!props.recordPaymentUrl) return;

    paymentForm.post(props.recordPaymentUrl, { preserveScroll: true });
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <CardTitle>Control comercial</CardTitle>
                    <CardDescription>
                        Facturación one-time y habilitación de publicación.
                    </CardDescription>
                </div>

                <Badge v-if="commercial?.paid_access" variant="secondary">
                    Pago confirmado
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="!commercial" class="rounded-xl border p-4 text-sm">
                El cliente todavía no ha solicitado comercialmente el Roadmap.
                Se puede preparar y revisar antes del pago. Publicar requiere
                pago confirmado.
            </div>

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border p-3">
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            Crédito
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                commercial.credit_eligible
                                    ? money(
                                          commercial.credit_amount,
                                          commercial.currency,
                                      )
                                    : 'No aplica'
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border p-3">
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            ITBIS
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                money(
                                    commercial.tax_amount,
                                    commercial.currency,
                                )
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border p-3">
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            Total
                        </p>
                        <p class="mt-1 font-black">
                            {{ money(commercial.total, commercial.currency) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="commercial.status === 'requested'"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                >
                    <div>
                        <p class="font-bold">Solicitud recibida</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Preparar factura one-time del Roadmap.
                        </p>
                    </div>

                    <Button @click="prepareInvoice">
                        <ReceiptText class="mr-2 size-4" />
                        Preparar factura
                    </Button>
                </div>

                <div v-if="commercial.invoice" class="rounded-xl border p-4">
                    <p class="font-bold">
                        Factura {{ commercial.invoice.number }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ commercial.invoice.status }}
                        ·
                        {{
                            money(commercial.invoice.total, commercial.currency)
                        }}
                    </p>
                </div>

                <div
                    v-if="commercial.status === 'invoiced'"
                    class="grid gap-4 rounded-xl border p-4 md:grid-cols-3"
                >
                    <div>
                        <Label for="roadmap-payment-method">Método</Label>
                        <select
                            id="roadmap-payment-method"
                            v-model="paymentForm.method"
                            class="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="bank_transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>

                    <div>
                        <Label for="roadmap-payment-reference"
                            >Referencia</Label
                        >
                        <Input
                            id="roadmap-payment-reference"
                            v-model="paymentForm.reference"
                            class="mt-2"
                            placeholder="Referencia del pago"
                        />
                    </div>

                    <div class="flex items-end">
                        <Button
                            :disabled="paymentForm.processing"
                            @click="recordPayment"
                        >
                            Confirmar pago completo
                        </Button>
                    </div>
                </div>

                <div
                    v-if="commercial.paid_access"
                    class="flex gap-3 rounded-xl border p-4"
                >
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">Acceso comercial habilitado</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            El Roadmap ya puede publicarse cuando termine la
                            revisión.
                        </p>
                    </div>
                </div>

                <div v-else class="flex gap-3 rounded-xl border p-4 text-sm">
                    <LockKeyhole class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">
                            Publicación bloqueada hasta pago
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            Se puede preparar y revisar antes del pago. Publicar
                            requiere pago confirmado.
                        </p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
