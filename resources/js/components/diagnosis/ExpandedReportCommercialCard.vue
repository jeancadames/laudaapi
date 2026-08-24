<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

type CommercialState = {
    id: number;
    status: 'requested' | 'invoiced' | 'paid' | 'cancelled';
    currency: string;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    total: string;
    paid_access: boolean;
    invoice: {
        id: number;
        number: string;
        status: string;
        total: string;
    } | null;
} | null;

const props = defineProps<{
    commercial: CommercialState;
    requestUrl: string;
    reportAvailable: boolean;
}>();

function requestReport() {
    router.post(props.requestUrl, {}, { preserveScroll: true });
}
</script>

<template>
    <div class="mt-4 space-y-3">
        <div v-if="!commercial" class="rounded-2xl border bg-muted/20 p-4">
            <p class="text-sm font-bold">RD$29,900 + ITBIS</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Solicita la continuación. LAUDA preparará la facturación
                one-time sin activar una suscripción.
            </p>
            <Button class="mt-3" @click="requestReport">
                Solicitar Informe Ampliado
            </Button>
        </div>

        <div
            v-else-if="commercial.status === 'requested'"
            class="rounded-2xl border bg-muted/20 p-4"
        >
            <p class="text-sm font-bold">Solicitud recibida</p>
            <p class="mt-1 text-sm text-muted-foreground">
                LAUDA está preparando la facturación del Informe Ampliado.
            </p>
        </div>

        <div
            v-else-if="commercial.status === 'invoiced'"
            class="rounded-2xl border bg-muted/20 p-4"
        >
            <p class="text-sm font-bold">Factura preparada</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ commercial.invoice?.number }} · RD${{ commercial.total }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                El acceso se habilita cuando LAUDA confirma el pago.
            </p>
        </div>

        <div
            v-else-if="commercial.paid_access && !reportAvailable"
            class="rounded-2xl border bg-emerald-50 p-4 text-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-100"
        >
            <p class="text-sm font-bold">Pago confirmado</p>
            <p class="mt-1 text-sm">
                Tu Informe Ampliado está en preparación. Aparecerá aquí cuando
                LAUDA publique la versión oficial.
            </p>
        </div>
    </div>
</template>
