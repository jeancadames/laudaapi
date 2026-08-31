<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    CheckCircle2,
    LoaderCircle,
    Palette,
} from 'lucide-vue-next';
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
        decision?: 'pending' | 'accepted' | 'declined' | null;
        available: boolean;
        activated: boolean;
        status: string | null;
        activated_at: string | null;
        endpoint: string | null;
        decline_endpoint?: string | null;
    } | null;
}>();

const activatingBranding = ref(false);
const decliningBranding = ref(false);

function activateBranding(): void {
    const activation = props.brandingActivation;

    if (
        !activation
        || !activation.available
        || activation.activated
        || !activation.endpoint
        || activatingBranding.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Iniciar la evaluación de Branding e Identidad Digital para esta empresa? La evaluación está incluida. Los trabajos posteriores de diseño, desarrollo o implementación se definirán y cotizarán por separado.',
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
        },
    );
}

function declineBranding(): void {
    const activation = props.brandingActivation;

    if (
        !activation
        || !activation.recommended
        || activation.activated
        || activation.decision === 'declined'
        || !activation.decline_endpoint
        || decliningBranding.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Marcar Branding e Identidad Digital como “Ahora no”? Podrás activarlo manualmente más adelante.',
        )
    ) {
        return;
    }

    router.post(
        activation.decline_endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                decliningBranding.value = true;
            },
            onFinish: () => {
                decliningBranding.value = false;
            },
        },
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
                        v-if="brandingActivation?.recommended"
                        variant="secondary"
                    >
                        Recomendado por tu Diagnóstico 360
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
                        brandingActivation?.available
                        && !brandingActivation?.activated
                    "
                    class="mt-5 rounded-xl border bg-muted/30 p-4"
                >
                    <p class="text-sm font-bold">
                        {{
                            brandingActivation?.recommended
                                ? 'Recomendado por tu Diagnóstico 360'
                                : 'Evaluación incluida'
                        }}
                    </p>

                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        <template v-if="brandingActivation?.recommended">
                            La evaluación recomienda esta capacidad, pero la
                            decisión sigue siendo tuya.
                        </template>
                        <template v-else>
                            Branding es opcional y puedes activarlo aunque la
                            evaluación no lo haya recomendado.
                        </template>
                        No genera compra, pago, suscripción ni aceptación
                        comercial.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
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
                            Iniciar evaluación
                        </Button>

                        <Button
                            v-if="
                                brandingActivation?.recommended
                                && brandingActivation?.decision !== 'declined'
                                && brandingActivation?.decline_endpoint
                            "
                            variant="outline"
                            type="button"
                            :disabled="decliningBranding"
                            @click="declineBranding"
                        >
                            <LoaderCircle
                                v-if="decliningBranding"
                                class="mr-2 size-4 animate-spin"
                            />
                            Ahora no
                        </Button>
                    </div>

                    <p
                        v-if="brandingActivation?.decision === 'declined'"
                        class="mt-3 text-xs font-semibold text-muted-foreground"
                    >
                        Marcaste “Ahora no”. La recomendación queda registrada,
                        pero puedes activar Branding cuando decidas.
                    </p>
                </div>

                <div
                    v-else-if="brandingActivation?.activated"
                    class="mt-5 rounded-xl border bg-muted/30 p-4"
                >
                    <p class="flex items-center gap-2 text-sm font-bold">
                        <CheckCircle2 class="size-4" />
                        Evaluación de Branding e Identidad Digital iniciada
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
