<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    proof: {
        company: string;
        invoice: string;
        currency: string;
        reference: string;
        file_url: string;
        review_url: string;
    };
}>();
const amount = ref('');
const form = useForm({
    decision: 'approved',
    reason: '',
    amount_cents: null as number | null,
});
const approving = computed(() => form.decision === 'approved');
function submit() {
    form.amount_cents = approving.value
        ? Math.round(Number(amount.value) * 100)
        : null;
    form.post(props.proof.review_url, { preserveScroll: true });
}
</script>

<template>
    <form class="space-y-3 border-t py-4 text-sm" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-x-4">
            <div class="min-w-0 break-words">
                <h3 class="font-semibold">
                    {{ proof.company }} · {{ proof.invoice }}
                </h3>
                <p>Referencia {{ proof.reference }}</p>
            </div>
            <a
                class="inline-flex min-h-11 items-center underline"
                :href="proof.file_url"
                >Descargar comprobante</a
            >
        </div>
        <div
            class="grid items-end gap-3 sm:grid-cols-[12rem_minmax(0,1fr)_auto]"
        >
            <label class="grid gap-1"
                >Decisión
                <select
                    v-model="form.decision"
                    class="min-h-11 rounded-md border bg-background px-2"
                >
                    <option value="approved">Confirmar ingreso</option>
                    <option value="rejected">Rechazar comprobante</option>
                </select>
            </label>
            <label v-if="approving" class="grid gap-1"
                >Importe verificado en el banco ({{ proof.currency }})
                <input
                    v-model="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    inputmode="decimal"
                    class="min-h-11 rounded-md border bg-background px-3"
                />
            </label>
            <label v-else class="grid gap-1"
                >Motivo para el cliente
                <input
                    v-model="form.reason"
                    maxlength="1000"
                    required
                    class="min-h-11 rounded-md border bg-background px-3"
                />
            </label>
            <Button
                type="submit"
                class="min-h-11"
                :disabled="form.processing"
                >{{ approving ? 'Confirmar pago' : 'Enviar rechazo' }}</Button
            >
        </div>
        <p
            v-for="message in form.errors"
            :key="message"
            role="alert"
            class="text-destructive"
        >
            {{ message }}
        </p>
    </form>
</template>
