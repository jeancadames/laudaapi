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
    <Card v-if="progress" class="overflow-hidden">
        <CardHeader class="border-b bg-muted/10 p-5 sm:p-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0">
                    <CardTitle class="text-lg">
                        Seguimiento Transformación 360
                    </CardTitle>

                    <CardDescription class="mt-2 max-w-2xl leading-6">
                        Consulta el avance completo del proceso, la etapa actual
                        y lo que debe ocurrir a continuación.
                    </CardDescription>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
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

        <CardContent class="space-y-6 p-5 sm:p-6">
            <div
                v-if="progress.current_step_label"
                class="rounded-2xl border border-primary/20 bg-primary/5 p-5"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Clock3 class="size-4" />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-xs font-black tracking-wide text-primary uppercase"
                        >
                            Etapa actual
                        </p>

                        <p class="mt-1 text-base font-black">
                            {{ progress.current_step_label }}
                        </p>

                        <p
                            v-if="progress.next_action"
                            class="mt-2 text-sm leading-6 text-muted-foreground"
                        >
                            {{ progress.next_action }}
                        </p>
                    </div>
                </div>
            </div>

            <div :class="admin ? 'grid gap-4' : 'grid gap-4 xl:grid-cols-2'">
                <div
                    v-for="(step, index) in progress.steps"
                    :key="step.code"
                    class="rounded-2xl border bg-background p-5 shadow-sm transition"
                    :class="{
                        'border-emerald-200 bg-emerald-50/30 dark:border-emerald-950 dark:bg-emerald-950/10':
                            step.status === 'completed',
                        'border-primary/40 bg-primary/5 ring-1 ring-primary/20':
                            step.status === 'current',
                        'border-destructive/30 bg-destructive/5':
                            step.status === 'blocked',
                    }"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full border bg-background"
                        >
                            <CheckCircle2
                                v-if="step.status === 'completed'"
                                class="size-4 text-emerald-600"
                            />

                            <Clock3
                                v-else-if="step.status === 'current'"
                                class="size-4 text-primary"
                            />

                            <ShieldAlert
                                v-else-if="step.status === 'blocked'"
                                class="size-4 text-destructive"
                            />

                            <Circle
                                v-else
                                class="size-4 text-muted-foreground"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        Paso {{ index + 1 }}
                                    </p>

                                    <p class="mt-1 leading-6 font-bold">
                                        {{ step.label }}
                                    </p>
                                </div>

                                <Badge
                                    class="w-fit shrink-0"
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
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                {{ step.description }}
                            </p>

                            <p
                                v-if="step.occurred_at"
                                class="mt-3 text-xs font-medium text-muted-foreground"
                            >
                                {{ formatDate(step.occurred_at) }}
                            </p>

                            <div
                                v-if="
                                    admin &&
                                    (step.admin_detail ||
                                        (['current', 'blocked'].includes(
                                            step.status,
                                        ) &&
                                            step.admin_action))
                                "
                                class="mt-4 space-y-2 rounded-xl border bg-muted/30 p-3"
                            >
                                <p
                                    v-if="step.admin_detail"
                                    class="text-xs leading-5 font-semibold"
                                >
                                    {{ step.admin_detail }}
                                </p>

                                <p
                                    v-if="
                                        ['current', 'blocked'].includes(
                                            step.status,
                                        ) && step.admin_action
                                    "
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    <strong>Siguiente acción:</strong>
                                    {{ step.admin_action }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
