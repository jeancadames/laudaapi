<script setup lang="ts">
defineProps<{
    items?: { question_id: string; answer: number | null; note: string }[];
}>();
</script>

<template>
    <details v-if="items?.length" class="mt-3 text-sm">
        <summary class="cursor-pointer font-medium">
            Respuestas y notas declaradas · sin verificación técnica
        </summary>
        <ul class="mt-2 space-y-2">
            <li v-for="item in items" :key="item.question_id">
                <span class="font-medium">{{ item.question_id }}</span>:
                {{ item.answer === null ? 'Sin respuesta' : `${item.answer}/5` }}
                <p v-if="item.note" class="whitespace-pre-wrap break-words text-muted-foreground">{{ item.note }}</p>
            </li>
        </ul>
    </details>
    <p v-else-if="items" class="mt-3 text-sm text-muted-foreground">
        Sustento agregado; no hay respuestas o notas asociadas disponibles.
    </p>
</template>
