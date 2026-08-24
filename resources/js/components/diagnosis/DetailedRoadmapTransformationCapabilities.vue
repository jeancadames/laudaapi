<script setup lang="ts">
import { BookOpenCheck, Palette } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    capabilities: Record<string, any> | null;
}>();
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
            </div>

            <p class="text-xs leading-5 text-muted-foreground lg:col-span-2">
                {{ capabilities.score_note }}
            </p>
        </CardContent>
    </Card>
</template>
