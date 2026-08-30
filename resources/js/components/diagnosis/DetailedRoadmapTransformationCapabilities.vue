<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { BookOpenCheck, CheckCircle2, LoaderCircle, Palette } from 'lucide-vue-next';
import { ref } from 'vue';

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
    capabilities: Record<string, any> | null;
    brandingActivation: {
        recommended: boolean;
        available: boolean;
        activated: boolean;
        status: string | null;
        activated_at: string | null;
        endpoint: string | null;
    } | null;
}>();

const activatingBranding = ref(false);

function activateBranding(): void {
    const activation = props.brandingActivation;

    if (
        !activation
        || !activation.recommended
        || !activation.available
        || activation.activated
        || !activation.endpoint
        || activatingBranding.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Activar gratis Branding e Identidad Digital para esta empresa? Esta activación no genera compra, pago ni contratación.'
        )
    ) {
        return;
    }

    router.post(
        activation.endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                activatingBranding.value = true;
            },
            onFinish: () => {
                activatingBranding.value = false;
            },
        }
    );
}
</script>

<template>
    <Card v-if="capabilities">
        <CardHeader>
            <CardTitle>
                {{
                    capabilities.title ||
                    'Capacidades de Transformación Detallada'
                }}
            </CardTitle>
            <CardDescription>
                Capacidades complementarias que LAUDA puede estructurar y
                ejecutar después del Roadmap.
            </CardDescription>
        </CardHeader>

        <CardContent class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <BookOpenCheck class="size-5" />

                    <p class="font-black">
                        {{ capabilities.procedures_guide?.title }}
                    </p>

                    <Badge variant="secondary"> Estructural </Badge>
                </div>

                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    {{ capabilities.procedures_guide?.purpose }}
                </p>

                <ul class="mt-4 space-y-2 text-sm">
                    <li
                        v-for="item in capabilities.procedures_guide
                            ?.includes ?? []"
                        :key="item"
                    >
                        • {{ item }}
                    </li>
                </ul>

                <p class="mt-4 text-xs leading-5 text-muted-foreground">
                    {{ capabilities.procedures_guide?.commercial_note }}
                </p>
            </div>

            <div class="rounded-2xl border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Palette class="size-5" />

                    <p class="font-black">
                        {{ capabilities.branding_identity?.title }}
                    </p>

                    <Badge variant="outline"> Opcional </Badge>

                    <Badge
                        v-if="capabilities.branding_identity?.recommended"
                        variant="secondary"
                    >
                        Recomendado para revisión
                    </Badge>

                    <Badge
                        v-if="brandingActivation?.activated"
                        variant="secondary"
                        class="inline-flex items-center gap-1"
                    >
                        <CheckCircle2 class="size-3.5" />
                        Activado
                    </Badge>
                </div>

                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    {{ capabilities.branding_identity?.purpose }}
                </p>

                <ul class="mt-4 space-y-2 text-sm">
                    <li
                        v-for="item in capabilities.branding_identity
                            ?.includes ?? []"
                        :key="item"
                    >
                        • {{ item }}
                    </li>
                </ul>

                <p class="mt-4 text-sm leading-6">
                    {{ capabilities.branding_identity?.recommendation_basis }}
                </p>

                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    {{ capabilities.branding_identity?.commercial_note }}
                </p>

                <div
                    v-if="
                        brandingActivation?.recommended
                        && brandingActivation?.available
                    "
                    class="mt-5 rounded-xl border bg-muted/30 p-4"
                >
                    <p class="text-sm font-bold">
                        Activación gratuita disponible
                    </p>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Activa esta capacidad para incorporarla al recorrido de
                        Transformación 360. No genera compra, pago, suscripción
                        ni aceptación comercial.
                    </p>

                    <Button
                        class="mt-3"
                        type="button"
                        :disabled="activatingBranding"
                        @click="activateBranding"
                    >
                        <LoaderCircle
                            v-if="activatingBranding"
                            class="mr-2 size-4 animate-spin"
                        />
                        <Palette
                            v-else
                            class="mr-2 size-4"
                        />
                        Activar gratis
                    </Button>
                </div>

                <div
                    v-else-if="brandingActivation?.activated"
                    class="mt-5 rounded-xl border bg-muted/30 p-4"
                >
                    <p class="flex items-center gap-2 text-sm font-bold">
                        <CheckCircle2 class="size-4" />
                        Branding e Identidad Digital activado
                    </p>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Esta capacidad ya forma parte de su recorrido de
                        Transformación 360.
                    </p>
                </div>
            </div>

            <p class="text-xs leading-5 text-muted-foreground lg:col-span-2">
                {{ capabilities.score_note }}
            </p>
        </CardContent>
    </Card>
</template>
