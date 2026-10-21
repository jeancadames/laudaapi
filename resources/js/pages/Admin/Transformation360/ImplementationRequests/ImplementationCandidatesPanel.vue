<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

type Available = { id: number; evaluation_id: number; title: string; details: string; priority: string | null };
type Selected = { id: number; challenge_id: number; title: string; selected_at: string | null; origin_available: boolean };

const requestId = ref<number | null>(null);
const available = ref<Available[]>([]);
const selected = ref<Selected[]>([]);
const loading = ref(false);
const choosing = ref<number | null>(null);
const error = ref('');
const notice = ref('');
const api = computed(() => requestId.value
    ? `/admin/transformation-360/implementation-requests/${requestId.value}/data-bi-candidates`
    : '');

async function refresh(): Promise<void> {
    if (!api.value) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get<{ available: Available[]; selected: Selected[] }>(api.value);
        available.value = response.data.available;
        selected.value = response.data.selected;
    } catch {
        error.value = 'No se pudo consultar la selección. Intenta nuevamente.';
    } finally {
        loading.value = false;
    }
}

async function selectChallenge(item: Available): Promise<void> {
    if (!api.value || choosing.value !== null) return;
    const accepted = window.confirm(
        `¿Seleccionar «${item.title}» para revisión futura?\n\n` +
        'Esto no crea un plan, contrato ni inicia trabajos.'
    );
    if (!accepted) return;
    choosing.value = item.id;
    error.value = '';
    notice.value = '';
    try {
        const response = await axios.post<{ message: string }>(`${api.value}/${item.id}/select`, {});
        notice.value = response.data.message;
        await refresh();
    } catch {
        error.value = 'No fue posible guardar la selección. Comprueba si el reto sigue disponible.';
    } finally {
        choosing.value = null;
    }
}

onMounted(() => {
    // La ruta Admin fija el request; ningún ID de empresa proviene del navegador.
    const found = window.location.pathname.match(/^\/admin\/transformation-360\/implementation-requests\/(\d+)\/?$/);
    if (!found) return;
    requestId.value = Number(found[1]);
    void refresh();
});
</script>

<template>
    <section v-if="requestId" id="seleccion-retos-bi" aria-labelledby="candidate-panel-title" class="mx-auto mt-8 max-w-7xl rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl space-y-1">
                <h2 id="candidate-panel-title" class="text-lg font-semibold text-foreground">Retos para futura implementación</h2>
                <p class="text-sm leading-relaxed text-muted-foreground">Selecciona los retos del diagnóstico publicado que merecen una revisión posterior. Esta acción no contrata servicios ni inicia proyectos.</p>
            </div>
            <button type="button" :disabled="loading || choosing !== null" class="rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-accent disabled:opacity-50" @click="refresh">Actualizar</button>
        </div>

        <p v-if="error" role="alert" class="mt-4 rounded-lg border border-destructive/30 p-3 text-sm text-destructive">{{ error }}</p>
        <p v-if="notice" role="status" class="mt-4 rounded-lg border border-emerald-300 p-3 text-sm text-emerald-700 dark:text-emerald-300">{{ notice }}</p>
        <p v-if="loading" role="status" class="mt-4 text-sm text-muted-foreground">Consultando retos…</p>

        <div v-if="!loading" class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Disponibles para seleccionar ({{ available.length }})</h3>
                <p v-if="!available.length" class="rounded-lg bg-muted/40 p-4 text-sm text-muted-foreground">No hay retos nuevos elegibles para seleccionar en esta solicitud.</p>
                <article v-for="item in available" :key="item.id" class="space-y-2 rounded-xl border border-border p-4">
                    <h4 class="font-medium text-foreground">{{ item.title }}</h4>
                    <p v-if="item.details" class="whitespace-pre-line text-sm text-muted-foreground">{{ item.details }}</p>
                    <p v-if="item.priority" class="text-xs text-muted-foreground">Prioridad profesional: {{ item.priority === 'high' ? 'Alta' : item.priority === 'medium' ? 'Media' : item.priority === 'low' ? 'Baja' : item.priority }}</p>
                    <button type="button" :disabled="choosing !== null" class="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50" @click="selectChallenge(item)">{{ choosing === item.id ? 'Guardando…' : 'Seleccionar para revisión' }}</button>
                </article>
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Retos seleccionados ({{ selected.length }})</h3>
                <p v-if="!selected.length" class="rounded-lg bg-muted/40 p-4 text-sm text-muted-foreground">Todavía no se han seleccionado retos para esta solicitud.</p>
                <article v-for="item in selected" :key="item.id" class="space-y-1 rounded-xl border border-border p-4">
                    <h4 class="font-medium text-foreground">{{ item.title }}</h4>
                    <p class="text-sm text-muted-foreground">Seleccionado: {{ item.selected_at || 'Fecha no disponible' }}</p>
                    <p v-if="!item.origin_available" class="text-xs text-muted-foreground">El documento original ya no está disponible; la selección histórica se conserva.</p>
                    <p class="text-xs text-muted-foreground">Pendiente de revisión de planificación. No implica contratación.</p>
                </article>
            </div>
        </div>
    </section>
</template>
