<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

type SelectedNeed = {
    key: string;
    label: string;
    group_label: string;
};
type Requirement = {
    key: string;
    label: string;
    guidance: string;
    objective_labels: string[];
    included: boolean;
};
type SavedReview = {
    schema_version: number;
    purpose: string;
    requirements: Array<{
        key: string;
        label: string;
        guidance: string;
        objective_keys: string[];
    }>;
};
type Draft = {
    mode: string;
    selected_needs: SelectedNeed[];
    requirements: Array<{
        key: string;
        label: string;
        guidance: string;
        objective_labels: string[];
    }>;
    unmapped_need_keys: string[];
    data_inspected: boolean;
};

// La ruta identifica únicamente la solicitud mostrada. El servidor autoriza
// acceso y filtra por capacidad: la URL nunca otorga permisos por sí misma.
const requestId = ref<number | null>(null);
const expanded = ref(false);
const loading = ref(false);
const saving = ref(false);
const savedVersion = ref(0);
const canSave = ref(false);
const persistenceAvailable = ref(false);
const saveBlockedReason = ref('');
const saveNotice = ref('');
const error = ref('');
const selectedNeeds = ref<SelectedNeed[]>([]);
const unmapped = ref<string[]>([]);
const requirements = ref<Requirement[]>([]);
const baseline = ref<Requirement[]>([]);
const loaded = ref(false);
const includedCount = computed(() => requirements.value.filter((item) => item.included).length);
const isEdited = computed(() => JSON.stringify(requirements.value) !== JSON.stringify(baseline.value));

function normalizeDraft(draft: Draft, saved: SavedReview | null): void {
    selectedNeeds.value = Array.isArray(draft.selected_needs) ? draft.selected_needs : [];
    unmapped.value = Array.isArray(draft.unmapped_need_keys) ? draft.unmapped_need_keys : [];
    const savedByKey = new Map<string, SavedReview['requirements'][number]>(
        (saved?.purpose === 'information_source_declaration' && Array.isArray(saved.requirements)
            ? saved.requirements : []).map((item): [string, SavedReview['requirements'][number]] => [item.key, item]),
    );
    requirements.value = (Array.isArray(draft.requirements) ? draft.requirements : []).map((item) => {
        const previous = savedByKey.get(item.key);
        return {
            key: item.key,
            label: previous?.label ?? item.label,
            guidance: previous?.guidance ?? item.guidance,
            objective_labels: Array.isArray(item.objective_labels) ? item.objective_labels : [],
            included: saved === null ? true : previous !== undefined,
        };
    });
    baseline.value = requirements.value.map((item) => ({ ...item, objective_labels: [...item.objective_labels] }));
    loaded.value = true;
}

async function loadPreview(): Promise<void> {
    if (requestId.value === null || loading.value) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(
            `/admin/transformation-360/implementation-requests/${requestId.value}/information-requirements/draft`,
            { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' },
        );
        if (!response.ok) throw new Error(`No se pudo cargar la propuesta (HTTP ${response.status}).`);
        const payload = await response.json();
        if (
            Number(payload?.request_id) !== requestId.value
            || payload?.draft?.mode !== 'preview_only'
            || !Array.isArray(payload?.draft?.requirements)
            || payload.draft.data_inspected !== false
        ) {
            throw new Error('La respuesta no cumple el contrato de vista previa declarativa.');
        }
        if (!Number.isInteger(payload.version) || payload.version < 0
            || typeof payload.persistence_available !== 'boolean'
            || typeof payload.can_save !== 'boolean') {
            throw new Error('Respuesta de guardado/versión inválida.');
        }
        normalizeDraft(payload.draft as Draft, payload.saved ?? null);
        savedVersion.value = payload.version;
        persistenceAvailable.value = payload.persistence_available;
        canSave.value = payload.can_save;
        saveBlockedReason.value = typeof payload.can_save_reason === 'string' ? payload.can_save_reason : '';
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'No fue posible consultar la propuesta.';
    } finally {
        loading.value = false;
    }
}

async function saveDraft(): Promise<void> {
    if (requestId.value === null || !canSave.value || saving.value || loading.value || (!isEdited.value && savedVersion.value > 0)) return;
    saving.value = true;
    error.value = '';
    saveNotice.value = '';
    try {
        const url = `/admin/transformation-360/implementation-requests/${requestId.value}/information-requirements/draft`;
        const result = await axios.post(url, {
            expected_version: savedVersion.value,
            requirements: requirements.value.map((item) => ({
                key: item.key,
                included: item.included,
                label: item.label,
                guidance: item.guidance,
            })),
        }, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (result.data?.sent !== false || !Number.isInteger(result.data?.version)) {
            throw new Error('La respuesta de guardado no cumple el contrato.');
        }
        await loadPreview();
        if (!error.value) saveNotice.value = `Borrador guardado como versión ${result.data.version}. No fue enviado al Tenant.`;
    } catch (caught) {
        if (axios.isAxiosError(caught)) {
            const validation = caught.response?.data?.errors;
            const first = validation && typeof validation === 'object'
                ? Object.values(validation).flat().find((v) => typeof v === 'string') : null;
            error.value = typeof first === 'string' ? first
                : (caught.response?.data?.message ?? `Error al guardar (HTTP ${caught.response?.status ?? 'sin respuesta'}).`);
        } else {
            error.value = caught instanceof Error ? caught.message : 'No fue posible guardar el borrador.';
        }
    } finally {
        saving.value = false;
    }
}

function resetLocal(): void {
    requirements.value = baseline.value.map((item) => ({ ...item, objective_labels: [...item.objective_labels] }));
}

async function reload(): Promise<void> {
    if (isEdited.value && !window.confirm('Se perderán los ajustes locales de esta vista previa. ¿Recargar?')) return;
    saveNotice.value = '';
    await loadPreview();
}

onMounted(() => {
    const match = window.location.pathname.match(/^\/admin\/transformation-360\/implementation-requests\/([1-9]\d*)\/?$/);
    if (!match) return;
    requestId.value = Number(match[1]);
    void loadPreview();
});
</script>

<template>
    <section v-if="requestId !== null" class="mb-6 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5" aria-label="Solicitud preliminar de información">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">D2F · Nuevo diagnóstico gratuito</p>
                <h2 class="text-lg font-semibold text-foreground">Requerimientos de información sugeridos</h2>
                <p class="max-w-3xl text-sm text-muted-foreground">LAUDA prepara las preguntas según los objetivos del Tenant. No se han inspeccionado datos ni confirmado fuentes.</p>
            </div>
            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted" :aria-expanded="expanded" @click="expanded = !expanded">
                {{ expanded ? 'Ocultar propuesta' : 'Ver propuesta' }}
            </button>
        </div>

        <div v-if="expanded" class="mt-5 space-y-4">
            <p v-if="!canSave && loaded" class="rounded-lg border border-amber-500/50 p-3 text-sm text-muted-foreground" role="status">{{ saveBlockedReason || (!persistenceAvailable ? 'Migración pendiente de autorización.' : 'El guardado no está habilitado para esta solicitud.') }}</p>
            <p v-if="saveNotice" class="rounded-lg border border-emerald-500/50 p-3 text-sm" role="status">{{ saveNotice }}</p>
            <p v-if="loading" class="text-sm text-muted-foreground" role="status">Cargando requerimientos…</p>
            <div v-if="error" class="rounded-lg border border-destructive/40 p-3 text-sm text-destructive" role="alert">
                {{ error }}
                <button type="button" class="ml-2 underline" :disabled="loading || saving" @click="reload">Reintentar</button>
            </div>
            <template v-if="loaded">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border p-3"><p class="text-2xl font-semibold">{{ selectedNeeds.length }}</p><p class="text-xs text-muted-foreground">Objetivos del Tenant</p></div>
                    <div class="rounded-xl border p-3"><p class="text-2xl font-semibold">{{ requirements.length }}</p><p class="text-xs text-muted-foreground">Requerimientos sugeridos</p></div>
                    <div class="rounded-xl border p-3"><p class="text-2xl font-semibold">{{ includedCount }}</p><p class="text-xs text-muted-foreground">Incluidos en borrador local</p></div>
                </div>
                <div v-if="unmapped.length" class="rounded-lg border border-amber-500/50 p-3 text-sm" role="alert">
                    Hay {{ unmapped.length }} objetivos sin regla automática: {{ unmapped.join(', ') }}. LAUDA deberá revisarlos antes de cualquier envío.
                </div>
                <details class="rounded-xl border p-3">
                    <summary class="cursor-pointer text-sm font-semibold">Objetivos recibidos ({{ selectedNeeds.length }})</summary>
                    <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
                        <li v-for="need in selectedNeeds" :key="need.key">{{ need.group_label }} · {{ need.label }}</li>
                    </ul>
                </details>
                <div v-if="requirements.length === 0" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    La solicitud no contiene objetivos reconocidos; no se inventaron requerimientos.
                </div>
                <div v-for="requirement in requirements" :key="requirement.key" class="space-y-3 rounded-xl border p-4">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="requirement.included" type="checkbox" :disabled="saving" class="h-4 w-4 rounded border-input" />
                        Incluir este requerimiento
                    </label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Información requerida</span>
                            <input v-model="requirement.label" type="text" maxlength="191" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" :disabled="!requirement.included || saving" />
                        </label>
                        <label class="space-y-1 text-sm">
                            <span class="font-medium">Qué deberá identificar el Tenant</span>
                            <textarea v-model="requirement.guidance" rows="2" maxlength="800" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" :disabled="!requirement.included || saving"></textarea>
                        </label>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        <span class="font-medium">Objetivos relacionados:</span>
                        {{ requirement.objective_labels.join(' · ') }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed p-3">
                    <p class="max-w-2xl text-xs text-muted-foreground">
                        {{ isEdited ? 'Hay cambios locales sin guardar.' : (savedVersion === 0 ? 'Borrador inicial aún no guardado.' : `Borrador sincronizado. Versión ${savedVersion}.`) }}
                        Los cambios solo se guardan al pulsar «Guardar borrador». Guardar NO envía la solicitud al Tenant.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border border-primary bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50" :disabled="loading || saving || !canSave || (!isEdited && savedVersion > 0)" @click="saveDraft">{{ saving ? 'Guardando…' : 'Guardar borrador' }}</button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm hover:bg-muted" :disabled="loading || saving || !isEdited" @click="resetLocal">Restaurar borrador</button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm hover:bg-muted" :disabled="loading || saving" @click="reload">Actualizar desde LAUDA</button>
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
