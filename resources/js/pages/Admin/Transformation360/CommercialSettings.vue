<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    Save,
    ShieldAlert,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type RateValue = {
    price_amount: number | string | null;
    duration_days: number | string | null;
};

type ModalityMatrix = {
    initiative_effort: Record<string, RateValue>;
    professional_capabilities: Record<string, RateValue>;
};

type Matrix = {
    version: string;
    currency: string;
    duration_unit: string;
    modalities: Record<string, ModalityMatrix>;
};

const props = defineProps<{
    matrix: Matrix;
    readiness: {
        ready: boolean;
        version: string | null;
        currency: string;
        missing: string[];
        missing_count: number;
    };
    modality_options: Array<{
        key: string;
        label: string;
        summary: string | null;
    }>;
    effort_labels: Record<string, string>;
    professional_labels: Record<string, string>;
    endpoints: {
        update: string;
    };
}>();

const form = useForm({
    modalities: JSON.parse(JSON.stringify(props.matrix.modalities)) as Record<
        string,
        ModalityMatrix
    >,
});

const effortKeys = ['low', 'medium', 'high'];

const professionalKeys = ['procedures_guide', 'branding_identity'];

const matrixStatus = computed(() =>
    props.readiness.ready ? 'Lista para cotizar' : 'Configuración incompleta',
);

function save() {
    form.patch(props.endpoints.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Tarifas Transformación 360" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Administración', href: '/admin' },
            { title: 'Transformación 360' },
            { title: 'Matriz comercial' },
        ]"
    >
        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline"> Transformación 360 </Badge>

                        <Badge
                            :variant="readiness.ready ? 'secondary' : 'outline'"
                        >
                            {{ matrixStatus }}
                        </Badge>
                    </div>

                    <h1 class="mt-3 text-2xl font-black">
                        Matriz comercial de implementación
                    </h1>

                    <p
                        class="mt-1 max-w-4xl text-sm leading-6 text-muted-foreground"
                    >
                        Define cuánto cuesta y cuánto tiempo representa cada
                        unidad de esfuerzo del Plan según la modalidad de
                        ejecución.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/admin/diagnosis-requests">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver
                    </Link>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p
                                class="text-xs font-bold text-muted-foreground uppercase"
                            >
                                Versión
                            </p>

                            <p class="mt-1 font-black">
                                {{ matrix.version }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-xs font-bold text-muted-foreground uppercase"
                            >
                                Moneda
                            </p>

                            <p class="mt-1 font-black">
                                {{ matrix.currency }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-xs font-bold text-muted-foreground uppercase"
                            >
                                Campos pendientes
                            </p>

                            <p class="mt-1 font-black">
                                {{ readiness.missing_count }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex gap-3 rounded-2xl border bg-muted/20 p-4">
                <ShieldAlert class="mt-0.5 size-5 shrink-0" />

                <div class="text-sm">
                    <p class="font-bold">
                        Esta matriz no contiene las mensualidades ni anualidades
                        de las soluciones LAUDAAPI.
                    </p>

                    <p class="mt-1 text-muted-foreground">
                        Social, CRM, POS, ECF y demás soluciones mantienen su
                        propio catálogo comercial. Aquí solo se cotiza el
                        trabajo de implementación del Plan.
                    </p>
                </div>
            </div>

            <div
                v-if="Object.keys(form.errors).length"
                class="rounded-2xl border border-destructive/40 bg-destructive/5 p-4"
            >
                <p class="font-bold">No se pudo guardar la matriz.</p>

                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="(message, key) in form.errors" :key="key">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <Card v-for="option in modality_options" :key="option.key">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <CardTitle>
                                {{ option.label }}
                            </CardTitle>

                            <CardDescription class="mt-1">
                                {{ option.summary }}
                            </CardDescription>
                        </div>

                        <Badge variant="outline">
                            {{ matrix.currency }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-6">
                    <div>
                        <div class="mb-3">
                            <p class="font-black">
                                Iniciativas por nivel de esfuerzo
                            </p>

                            <p class="text-sm text-muted-foreground">
                                Cada iniciativa del Roadmap aporta una unidad
                                según su effort real.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[650px] text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-3 pr-4">Nivel</th>

                                        <th class="px-3 py-3">Precio DOP</th>

                                        <th class="px-3 py-3">Duración</th>

                                        <th class="py-3 pl-3">Unidad</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="effort in effortKeys"
                                        :key="effort"
                                        class="border-b last:border-0"
                                    >
                                        <td class="py-3 pr-4 font-semibold">
                                            {{ effort_labels[effort] }}
                                        </td>

                                        <td class="px-3 py-3">
                                            <div class="relative max-w-[220px]">
                                                <CircleDollarSign
                                                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                />

                                                <input
                                                    v-model="
                                                        form.modalities[
                                                            option.key
                                                        ].initiative_effort[
                                                            effort
                                                        ].price_amount
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Sin configurar"
                                                    class="h-10 w-full rounded-md border bg-background pr-3 pl-9"
                                                />
                                            </div>
                                        </td>

                                        <td class="px-3 py-3">
                                            <div class="relative max-w-[180px]">
                                                <Clock3
                                                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                />

                                                <input
                                                    v-model="
                                                        form.modalities[
                                                            option.key
                                                        ].initiative_effort[
                                                            effort
                                                        ].duration_days
                                                    "
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    placeholder="Días"
                                                    class="h-10 w-full rounded-md border bg-background pr-3 pl-9"
                                                />
                                            </div>
                                        </td>

                                        <td
                                            class="py-3 pl-3 text-muted-foreground"
                                        >
                                            días
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3">
                            <p class="font-black">Servicios profesionales</p>

                            <p class="text-sm text-muted-foreground">
                                Se agregan al cálculo únicamente cuando la
                                capability profesional forma parte del Plan.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[650px] text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-3 pr-4">Capability</th>

                                        <th class="px-3 py-3">Precio DOP</th>

                                        <th class="px-3 py-3">Duración</th>

                                        <th class="py-3 pl-3">Unidad</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="capability in professionalKeys"
                                        :key="capability"
                                        class="border-b last:border-0"
                                    >
                                        <td class="py-3 pr-4 font-semibold">
                                            {{
                                                professional_labels[capability]
                                            }}
                                        </td>

                                        <td class="px-3 py-3">
                                            <input
                                                v-model="
                                                    form.modalities[option.key]
                                                        .professional_capabilities[
                                                        capability
                                                    ].price_amount
                                                "
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Sin configurar"
                                                class="h-10 w-full max-w-[220px] rounded-md border bg-background px-3"
                                            />
                                        </td>

                                        <td class="px-3 py-3">
                                            <input
                                                v-model="
                                                    form.modalities[option.key]
                                                        .professional_capabilities[
                                                        capability
                                                    ].duration_days
                                                "
                                                type="number"
                                                min="1"
                                                step="1"
                                                placeholder="Días"
                                                class="h-10 w-full max-w-[180px] rounded-md border bg-background px-3"
                                            />
                                        </td>

                                        <td
                                            class="py-3 pl-3 text-muted-foreground"
                                        >
                                            días
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent
                    class="flex flex-wrap items-center justify-between gap-4 pt-6"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <CheckCircle2
                                v-if="readiness.ready"
                                class="size-5"
                            />

                            <ShieldAlert v-else class="size-5" />

                            <p class="font-black">
                                {{ matrixStatus }}
                            </p>
                        </div>

                        <p class="mt-1 text-sm text-muted-foreground">
                            El motor no generará estimates comerciales hasta que
                            todos los campos requeridos estén completos.
                        </p>
                    </div>

                    <Button :disabled="form.processing" @click="save">
                        <Save class="mr-2 size-4" />
                        Guardar matriz
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
