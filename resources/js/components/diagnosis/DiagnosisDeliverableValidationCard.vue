<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, MessageSquareWarning } from 'lucide-vue-next';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    validation: {
        status: 'presented' | 'reviewed' | 'validated' | 'adjustment_requested';
        reviewed_at: string | null;
        validated_at: string | null;
        adjustment_requested_at: string | null;
        adjustment_note: string | null;
    };
    endpoints: {
        review: string;
        validate: string;
        request_adjustment: string;
    };
}>();

const adjustmentForm = useForm({
    adjustment_note: props.validation.adjustment_note ?? '',
});

const statusLabel = computed(() => {
    return {
        presented: 'Presentado',
        reviewed: 'Revisado por tenant',
        validated: 'Validado',
        adjustment_requested: 'Ajuste solicitado',
    }[props.validation.status];
});

function markReviewed(): void {
    router.post(props.endpoints.review, {}, { preserveScroll: true });
}

function validateDocument(): void {
    if (
        !window.confirm(
            'Confirmas que revisaste este documento y que refleja adecuadamente la situación, prioridades y contexto de tu empresa? Esta validación no constituye contratación de servicios ni aceptación de una propuesta comercial.',
        )
    ) {
        return;
    }

    router.post(props.endpoints.validate, {}, { preserveScroll: true });
}

function requestAdjustment(): void {
    adjustmentForm.post(props.endpoints.request_adjustment, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Card class="border-primary/20 bg-primary/5">
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <CardTitle>Revisión y validación del tenant</CardTitle>
                    <CardDescription class="mt-2">
                        Verifica que el documento represente adecuadamente la realidad y prioridades de tu empresa.
                    </CardDescription>
                </div>
                <Badge variant="outline">{{ statusLabel }}</Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <div
                v-if="validation.status === 'validated'"
                class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                <p>
                    Documento validado. Esta validación confirma su revisión y no constituye contratación de servicios ni aceptación de una propuesta comercial.
                </p>
            </div>

            <div
                v-else-if="validation.status === 'adjustment_requested'"
                class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <MessageSquareWarning class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-bold">Ajuste solicitado para esta versión</p>
                    <p class="mt-1">{{ validation.adjustment_note }}</p>
                </div>
            </div>

            <template v-else>
                <p class="text-sm leading-6 text-muted-foreground">
                    He revisado este documento y confirmo que refleja adecuadamente la situación, prioridades y contexto de mi empresa. Esta validación no constituye contratación de servicios ni aceptación de una propuesta comercial.
                </p>

                <div class="flex flex-wrap gap-3">
                    <Button
                        v-if="validation.status === 'presented'"
                        variant="outline"
                        @click="markReviewed"
                    >
                        <Clock3 class="mr-2 size-4" />
                        Marcar como revisado
                    </Button>

                    <Button
                        v-if="validation.status === 'reviewed'"
                        @click="validateDocument"
                    >
                        <CheckCircle2 class="mr-2 size-4" />
                        Validar documento
                    </Button>
                </div>

                <div class="space-y-2 rounded-xl border p-4">
                    <p class="text-sm font-bold">¿Necesitas que LAUDA ajuste esta versión?</p>
                    <textarea
                        v-model="adjustmentForm.adjustment_note"
                        rows="3"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        placeholder="Describe el ajuste que necesitas antes de validar."
                    />
                    <p
                        v-if="adjustmentForm.errors.adjustment_note"
                        class="text-xs text-destructive"
                    >
                        {{ adjustmentForm.errors.adjustment_note }}
                    </p>
                    <Button
                        variant="outline"
                        :disabled="adjustmentForm.processing"
                        @click="requestAdjustment"
                    >
                        Solicitar ajuste
                    </Button>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
