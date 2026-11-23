<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { BusinessTransfer } from '@/types/businessQuoteCheckout';

const props = defineProps<{ transfer: BusinessTransfer; purchaseId: string }>();
const form = useForm({
    reference: '',
    proof: null as File | null,
    submission_key: crypto.randomUUID(),
});
const fileInput = ref<HTMLInputElement>();
watch(
    () => props.purchaseId,
    () => {
        form.reset();
        form.clearErrors();
        form.submission_key = crypto.randomUUID();
        if (fileInput.value) fileInput.value.value = '';
    },
);
function submit() {
    form.post(props.transfer.submit_url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.submission_key = crypto.randomUUID();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <section
        class="space-y-3 border-t pt-4 text-sm"
        aria-labelledby="transfer-title"
    >
        <h3 id="transfer-title" class="font-semibold">
            Transferencia bancaria
        </h3>
        <p v-if="transfer.status === 'pending'" role="status">
            Recibimos tu comprobante. LAUDA revisará el ingreso y actualizará el
            saldo de esta factura.
        </p>
        <form v-else class="space-y-4" @submit.prevent="submit">
            <p
                v-if="transfer.status === 'rejected'"
                role="status"
                class="break-words text-destructive"
            >
                Comprobante rechazado. {{ transfer.reason }}
            </p>
            <p
                v-if="transfer.instructions"
                class="break-words whitespace-pre-line"
            >
                {{ transfer.instructions }}
            </p>
            <p v-else>
                Solicita los datos de la cuenta a LAUDA si aún no los tienes.
            </p>
            <label class="block space-y-1">
                <span>Referencia de la transacción</span>
                <input
                    v-model="form.reference"
                    required
                    maxlength="100"
                    class="min-h-11 w-full rounded-md border bg-background px-3"
                />
            </label>
            <label class="block space-y-1">
                <span>Comprobante · PDF, JPG o PNG, hasta 5 MB</span>
                <input
                    ref="fileInput"
                    type="file"
                    required
                    accept="application/pdf,image/jpeg,image/png"
                    class="block min-h-11 w-full text-sm"
                    @change="
                        form.proof =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
            </label>
            <p
                v-for="message in form.errors"
                :key="message"
                role="alert"
                class="text-destructive"
            >
                {{ message }}
            </p>
            <Button
                class="min-h-11 w-full"
                type="submit"
                :disabled="form.processing"
                >{{
                    form.processing ? 'Enviando…' : 'Enviar comprobante'
                }}</Button
            >
        </form>
    </section>
</template>
