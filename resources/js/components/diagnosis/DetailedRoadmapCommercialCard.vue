<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, ReceiptText } from 'lucide-vue-next';

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
    commercial: Record<string, any> | null;
    preview: Record<string, any> | null;
    roadmap: {
        id: number;
        version: number;
        published_at: string | null;
    } | null;
    requestUrl: string;
    roadmapUrl: string;
}>();

function money(value: any, currency = 'DOP') {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function requestRoadmap() {
    if (!window.confirm('¿Solicitar el Roadmap Detallado LAUDA 360?')) {
        return;
    }

    router.post(props.requestUrl, {}, { preserveScroll: true });
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <CardTitle>Roadmap Detallado LAUDA 360</CardTitle>
                    <CardDescription>
                        Fases, iniciativas, responsables, dependencias e
                        indicadores.
                    </CardDescription>
                </div>

                <Badge v-if="commercial?.paid_access" variant="secondary">
                    Pago confirmado
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <template v-if="!commercial">
                <div v-if="preview" class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border p-3">
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            Precio base
                        </p>
                        <p class="mt-1 font-black">
                            {{ money(preview.base_subtotal, preview.currency) }}
                        </p>
                    </div>

                    <div class="rounded-xl border p-3">
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            Total con ITBIS
                        </p>
                        <p class="mt-1 font-black">
                            {{ money(preview.total, preview.currency) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="preview?.credit_eligible"
                    class="rounded-xl border bg-muted/30 p-4"
                >
                    <p class="font-bold">
                        Crédito del Informe Ampliado aplicable
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Crédito:
                        <strong>
                            {{ money(preview.credit_amount, preview.currency) }}
                        </strong>
                        · Total del Roadmap:
                        <strong>
                            {{ money(preview.total, preview.currency) }}
                        </strong>
                    </p>
                </div>

                <Button @click="requestRoadmap">
                    Solicitar Roadmap Detallado
                </Button>
            </template>

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
                    class="flex gap-3 rounded-xl border p-4"
                >
                    <Clock3 class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">Solicitud recibida</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            LAUDA está preparando la facturación.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="commercial.status === 'invoiced'"
                    class="flex gap-3 rounded-xl border p-4"
                >
                    <ReceiptText class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">Facturación preparada</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                commercial.invoice?.number ?? 'Factura emitida'
                            }}
                            ·
                            {{
                                money(
                                    commercial.invoice?.total,
                                    commercial.currency,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="commercial.paid_access && !roadmap"
                    class="flex gap-3 rounded-xl border p-4"
                >
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">
                            Pago confirmado · Roadmap en preparación
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            El entregable será visible cuando LAUDA termine la
                            revisión y publicación.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="commercial.paid_access && roadmap"
                    class="space-y-3 rounded-xl border p-4"
                >
                    <p class="font-bold">
                        Roadmap disponible · V{{ roadmap.version }}
                    </p>

                    <Button as-child>
                        <Link :href="roadmapUrl"> Ver Roadmap Detallado </Link>
                    </Button>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
