<script setup lang="ts">
import { CheckCircle2, Circle, Clock3, ShieldAlert } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    progress: Record<string, any> | null;
    admin?: boolean;
}>();

function formatDate(value: string | null) {
    if (!value) return null;

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function statusLabel(status: string) {
    return (
        {
            completed: 'Completado',
            current: 'Etapa actual',
            pending: 'Pendiente',
            blocked: 'Bloqueado',
        }[status] ?? status
    );
}
</script>

<template>
    <Card v-if="progress">
        <CardHeader>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle>Seguimiento Transformación 360</CardTitle>
                    <CardDescription class="mt-1">
                        Paso a paso desde la solicitud inicial hasta la
                        transformación.
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {{ progress.completed_count }}/{{ progress.total }}
                        completados
                    </Badge>
                    <Badge variant="secondary">
                        {{ progress.percentage }}%
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5">
            <div
                v-if="progress.current_step_label"
                class="rounded-2xl border bg-muted/30 p-4"
            >
                <p
                    class="text-xs font-black tracking-wide text-muted-foreground uppercase"
                >
                    Etapa actual
                </p>
                <p class="mt-1 font-black">
                    {{ progress.current_step_label }}
                </p>
                <p
                    v-if="progress.next_action"
                    class="mt-2 text-sm leading-6 text-muted-foreground"
                >
                    {{ progress.next_action }}
                </p>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div
                    v-for="(step, index) in progress.steps"
                    :key="step.code"
                    class="rounded-2xl border p-4"
                    :class="{
                        'bg-muted/30': step.status === 'completed',
                        'ring-1 ring-primary/30': step.status === 'current',
                    }"
                >
                    <div class="flex items-start gap-3">
                        <CheckCircle2
                            v-if="step.status === 'completed'"
                            class="mt-0.5 size-5 shrink-0 text-primary"
                        />
                        <Clock3
                            v-else-if="step.status === 'current'"
                            class="mt-0.5 size-5 shrink-0"
                        />
                        <ShieldAlert
                            v-else-if="step.status === 'blocked'"
                            class="mt-0.5 size-5 shrink-0"
                        />
                        <Circle
                            v-else
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                        />

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-bold">
                                    {{ index + 1 }}. {{ step.label }}
                                </p>

                                <Badge
                                    :variant="
                                        step.status === 'completed'
                                            ? 'secondary'
                                            : step.status === 'blocked'
                                              ? 'destructive'
                                              : 'outline'
                                    "
                                >
                                    {{ statusLabel(step.status) }}
                                </Badge>
                            </div>

                            <p
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                {{ step.description }}
                            </p>

                            <p
                                v-if="step.occurred_at"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{ formatDate(step.occurred_at) }}
                            </p>

                            <template v-if="admin">
                                <p
                                    v-if="step.admin_detail"
                                    class="mt-2 text-xs font-semibold"
                                >
                                    {{ step.admin_detail }}
                                </p>

                                <p
                                    v-if="
                                        ['current', 'blocked'].includes(
                                            step.status,
                                        ) && step.admin_action
                                    "
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Siguiente acción:
                                    {{ step.admin_action }}
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
