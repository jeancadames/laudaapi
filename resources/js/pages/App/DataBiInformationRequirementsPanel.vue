<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import DataBiDeclarativeReviewPanel from '@/components/DataBiDeclarativeReviewPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    availabilities,
    sourceTypes,
    useDataBiTenantDeclaration,
} from '@/composables/useDataBiTenantDeclaration';

const props = defineProps<{ implementationRequestId: number | null }>();
const {
    questionnaire,
    declarations,
    revision,
    storedStatus,
    submittedAt,
    state,
    operation,
    busy,
    dirty,
    initialized,
    notice,
    error,
    conflict,
    remote,
    contextChanged,
    submitted,
    editable,
    canWrite,
    completionIssues,
    visibleIssues,
    completed,
    canRebase,
    load,
    validateSubmission,
    saveDraft,
    submit,
    inspectCurrent,
    useRemote,
    keepLocalOnCurrentRevision,
    addSource,
    addStructure,
} = useDataBiTenantDeclaration(() => props.implementationRequestId);
const display = ref<'focused' | 'list'>('focused');
const selectedKey = ref('');
const confirming = ref(false);
const instanceId = useId();
const controlId = (key: string, field: string) =>
    `${instanceId}-bi-${encodeURIComponent(key)}-${field}`;
const selectedIndex = computed(() =>
    Math.max(
        0,
        questionnaire.value?.requirements.findIndex(
            (r) => r.key === selectedKey.value,
        ) ?? 0,
    ),
);
const visibleRequirements = computed(() =>
    (questionnaire.value?.requirements ?? [])
        .filter((r) => display.value === 'list' || r.key === selectedKey.value)
        .map((r) => ({
            ...r,
            response: declarations.value.find(
                (d) => d.requirement_key === r.key,
            ),
        })),
);
const objectiveLabels = computed(
    () =>
        new Map(
            (questionnaire.value?.objectives ?? []).map((o) => [
                o.key,
                o.label,
            ]),
        ),
);
const saveLabel = computed(() =>
    operation.value === 'save' ? 'Guardando borrador…' : 'Guardar borrador',
);
const statusLabel = computed(() =>
    submitted.value
        ? 'Declaración enviada'
        : dirty.value
          ? 'Cambios sin guardar'
          : storedStatus.value === 'draft'
            ? 'Borrador guardado'
            : 'Sin respuestas guardadas',
);

function move(step: number): void {
    const r = questionnaire.value?.requirements[selectedIndex.value + step];
    if (r) selectedKey.value = r.key;
}
function sentDate(value: string | null): string {
    if (!value || Number.isNaN(new Date(value).getTime()))
        return 'Fecha no disponible';
    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
async function askSubmit(): Promise<void> {
    if (!validateSubmission()) {
        const invalid = declarations.value.find(
            (d) => completionIssues.value[d.requirement_key]?.length,
        );
        if (invalid) {
            selectedKey.value = invalid.requirement_key;
            await nextTick();
            document
                .getElementById(controlId(invalid.requirement_key, 'errors'))
                ?.focus();
        }
        return;
    }
    confirming.value = true;
}
async function confirmSubmit(): Promise<void> {
    const ok = await submit();
    if (ok || !busy.value) confirming.value = false;
}
function replaceWithRemote(): void {
    if (
        window.confirm(
            'Se cargarán las respuestas vigentes y se descartarán las modificaciones locales de esta pantalla. ¿Continuar?',
        )
    )
        useRemote();
}
function rebaseLocal(): void {
    if (
        window.confirm(
            'Conservarás tus respuestas locales. Al guardar reemplazarás el borrador vigente que acabas de consultar. ¿Continuar con esa revisión?',
        )
    )
        keepLocalOnCurrentRevision();
}
function guardUnload(event: BeforeUnloadEvent): void {
    if (dirty.value || busy.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}
let stopNavigation: (() => void) | undefined;
onMounted(() => {
    window.addEventListener('beforeunload', guardUnload);
    stopNavigation = router.on('before', (event) => {
        if (
            (dirty.value || busy.value) &&
            !window.confirm(
                busy.value
                    ? 'Hay una operación en curso. Si sales, deberás consultar su resultado al volver. ¿Salir?'
                    : 'Tienes respuestas sin guardar. Si sales, perderás esos cambios. ¿Salir?',
            )
        )
            event.preventDefault();
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', guardUnload);
    stopNavigation?.();
});
watch(questionnaire, (q) => {
    if (q && !q.requirements.some((r) => r.key === selectedKey.value))
        selectedKey.value = q.requirements[0].key;
});
watch(confirming, async (open) => {
    if (!open) {
        await nextTick();
        (
            document.getElementById(controlId('actions', 'submit')) ??
            document.getElementById(controlId('panel', 'title'))
        )?.focus();
    }
});
</script>

<template>
    <section
        v-if="state === 'ready' && questionnaire"
        class="bi-declaration mb-6 min-w-0 rounded-2xl border border-sky-200 bg-white p-4 sm:p-7 dark:border-sky-900 dark:bg-slate-950"
        aria-label="Cuestionario de información de LAUDA"
        :aria-busy="busy"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl min-w-0 space-y-2">
                <h2
                    :id="controlId('panel', 'title')"
                    tabindex="-1"
                    class="text-xl font-bold text-slate-950 dark:text-white"
                >
                    Requerimientos de información para tu empresa
                </h2>
                <p
                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    LAUDA preparó este cuestionario a partir de las necesidades
                    de tu empresa. Declara qué información tienes y dónde se
                    encuentra. Comparte únicamente nombres y descripciones, sin
                    archivos, credenciales ni registros reales.
                </p>
            </div>
            <div class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <p class="font-semibold">
                    Cuestionario · Versión {{ questionnaire.version }}
                </p>
                <p class="text-xs">
                    Publicado: {{ sentDate(questionnaire.sent_at) }}
                </p>
                <Button
                    v-if="editable"
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="!canWrite"
                    @click="saveDraft"
                    >{{ saveLabel }}</Button
                >
            </div>
        </header>

        <div
            v-if="initialized"
            class="mt-5 space-y-2 border-y border-slate-200 py-4 dark:border-slate-800"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
            >
                <p class="font-semibold text-slate-900 dark:text-slate-100">
                    {{ completed }} de
                    {{ questionnaire.requirements.length }} completados ·
                    {{ questionnaire.requirements.length - completed }}
                    pendientes
                </p>
                <p
                    role="status"
                    aria-live="polite"
                    class="text-slate-600 dark:text-slate-300"
                >
                    {{
                        busy
                            ? operation === 'submit'
                                ? 'Enviando declaración…'
                                : operation === 'inspect'
                                  ? 'Consultando estado vigente…'
                                  : 'Guardando…'
                            : statusLabel
                    }}
                    <span v-if="revision > 0" class="text-xs">
                        · Revisión {{ revision }}</span
                    >
                </p>
            </div>
            <progress
                class="h-2 w-full accent-sky-700 dark:accent-sky-400"
                :value="completed"
                :max="questionnaire.requirements.length"
                aria-label="Requerimientos completados"
            />
            <p v-if="submitted" class="text-sm text-sky-800 dark:text-sky-200">
                Enviada: {{ sentDate(submittedAt) }}. Puedes consultar las
                respuestas; la edición está cerrada.
            </p>
        </div>
        <p
            v-else-if="!questionnaire.can_respond"
            class="mt-5 rounded-lg bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-900 dark:text-slate-200"
            role="status"
        >
            Cuestionario de solo lectura. La respuesta todavía no está
            disponible. Puedes revisar todos los requerimientos.
        </p>
        <p v-else-if="busy" class="mt-4 text-sm" role="status">
            Consultando tus respuestas guardadas…
        </p>

        <p
            v-if="notice"
            class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            role="status"
            aria-live="polite"
        >
            {{ notice }}
        </p>
        <div
            v-if="error || contextChanged"
            class="mt-4 space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            role="alert"
        >
            <p>
                {{
                    error ||
                    'La solicitud cambió. Conservamos tus respuestas locales; consulta el estado vigente para continuar.'
                }}
            </p>
            <Button
                v-if="!initialized && !dirty && !contextChanged"
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="load"
                >Reintentar consulta</Button
            >
            <Button
                v-else
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="inspectCurrent"
                >Consultar estado vigente sin perder mis cambios</Button
            >
        </div>

        <section
            v-if="remote"
            class="mt-5 space-y-3 border-y border-slate-300 py-4 dark:border-slate-700"
            aria-label="Consulta del estado vigente"
        >
            <h3 class="font-semibold">Estado vigente del servidor</h3>
            <p class="text-sm">
                Cuestionario versión {{ remote.questionnaire.version }} ·
                {{
                    remote.current?.declaration?.status === 'submitted'
                        ? 'Enviado'
                        : remote.current?.declaration
                          ? 'Borrador'
                          : 'Sin declaración'
                }}
                · Revisión {{ remote.current?.declaration?.revision ?? 0 }}.
            </p>
            <p class="text-sm">
                Tus respuestas locales siguen en el formulario. Elige
                expresamente cómo continuar.
            </p>
            <details class="text-sm">
                <summary
                    class="cursor-pointer py-2 font-medium focus-visible:outline-2 focus-visible:outline-sky-600"
                >
                    Ver requerimientos y respuestas vigentes
                </summary>
                <ul class="mt-2 space-y-4">
                    <li
                        v-for="r in remote.questionnaire.requirements"
                        :key="r.key"
                        class="break-words"
                    >
                        <p class="font-semibold">{{ r.label }}</p>
                        <p class="whitespace-pre-wrap">{{ r.guidance }}</p>
                        <template
                            v-for="d in remote.current?.declaration?.declarations.filter(
                                (d) => d.requirement_key === r.key,
                            ) ?? []"
                            :key="d.requirement_key"
                        >
                            <p>
                                {{
                                    availabilities.find(
                                        ([key]) => key === d.availability,
                                    )?.[1]
                                }}
                            </p>
                            <p class="whitespace-pre-wrap">{{ d.notes }}</p>
                            <ul class="ml-4 list-disc">
                                <li v-for="(s, i) in d.sources" :key="i">
                                    {{ s.name }} ·
                                    {{
                                        sourceTypes.find(
                                            ([key]) => key === s.type,
                                        )?.[1]
                                    }}
                                    · {{ s.structures.join(', ') }}
                                    <span class="whitespace-pre-wrap">{{
                                        s.notes
                                    }}</span>
                                </li>
                            </ul>
                        </template>
                    </li>
                </ul>
            </details>
            <div class="flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 text-left whitespace-normal"
                    :disabled="busy"
                    @click="replaceWithRemote"
                    >Cargar versión vigente y descartar cambios locales</Button
                >
                <Button
                    v-if="canRebase"
                    type="button"
                    variant="outline"
                    class="min-h-11 text-left whitespace-normal"
                    :disabled="busy"
                    @click="rebaseLocal"
                    >Conservar mis respuestas sobre la revisión vigente</Button
                >
            </div>
        </section>

        <details
            v-if="questionnaire.objectives.length"
            class="mt-5 border-b border-slate-200 pb-4 dark:border-slate-800"
        >
            <summary
                class="cursor-pointer py-2 text-sm font-semibold text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-600 dark:text-slate-200"
            >
                Objetivos relacionados ({{ questionnaire.objectives.length }})
            </summary>
            <ul class="mt-2 flex flex-wrap gap-2">
                <li
                    v-for="objective in questionnaire.objectives"
                    :key="objective.key"
                    class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    {{ objective.label }}
                </li>
            </ul>
        </details>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold text-slate-950 dark:text-white">
                Información solicitada
            </h3>
            <div
                class="flex flex-wrap gap-2"
                role="group"
                aria-label="Presentación de requerimientos"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :class="
                        display === 'focused'
                            ? 'bg-sky-700 text-white hover:bg-sky-800 hover:text-white'
                            : ''
                    "
                    :aria-pressed="display === 'focused'"
                    @click="display = 'focused'"
                    >Uno por uno</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :class="
                        display === 'list'
                            ? 'bg-sky-700 text-white hover:bg-sky-800 hover:text-white'
                            : ''
                    "
                    :aria-pressed="display === 'list'"
                    @click="display = 'list'"
                    >Lista completa</Button
                >
            </div>
        </div>
        <nav
            v-if="display === 'focused'"
            class="mt-4 flex flex-wrap items-end gap-2"
            aria-label="Navegación de requerimientos"
        >
            <label
                class="min-w-0 basis-full text-sm sm:flex-1 sm:basis-auto"
                :for="controlId('nav', 'requirement')"
            >
                Requerimiento {{ selectedIndex + 1 }} de
                {{ questionnaire.requirements.length }}
                <select
                    :id="controlId('nav', 'requirement')"
                    v-model="selectedKey"
                    class="bi-input mt-1"
                >
                    <option
                        v-for="item in questionnaire.requirements"
                        :key="item.key"
                        :value="item.key"
                    >
                        {{ item.label
                        }}{{
                            initialized
                                ? completionIssues[item.key]?.length
                                    ? ' · Pendiente'
                                    : ' · Completo'
                                : ''
                        }}
                    </option>
                </select>
            </label>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="selectedIndex === 0"
                @click="move(-1)"
                >Anterior</Button
            >
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="
                    selectedIndex >= questionnaire.requirements.length - 1
                "
                @click="move(1)"
                >Siguiente</Button
            >
        </nav>

        <form
            class="mt-5"
            aria-label="Declaración de disponibilidad y fuentes de información"
            @submit.prevent
        >
            <article
                v-for="item in visibleRequirements"
                :key="item.key"
                class="min-w-0 border-b border-slate-200 py-6 first:pt-0 last:border-0 dark:border-slate-800"
                :aria-labelledby="controlId(item.key, 'title')"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h4
                        :id="controlId(item.key, 'title')"
                        class="min-w-0 text-lg font-semibold break-words text-slate-950 dark:text-white"
                    >
                        {{ item.label }}
                    </h4>
                    <span
                        v-if="initialized"
                        class="text-sm font-medium"
                        :class="
                            completionIssues[item.key]?.length
                                ? 'text-amber-800 dark:text-amber-200'
                                : 'text-emerald-800 dark:text-emerald-200'
                        "
                        >{{
                            completionIssues[item.key]?.length
                                ? 'Pendiente'
                                : 'Completo'
                        }}</span
                    >
                </div>
                <p
                    class="mt-2 max-w-prose text-sm leading-relaxed whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                >
                    {{ item.guidance }}
                </p>
                <p
                    v-if="item.objective_keys.length"
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                >
                    Relacionado con:
                    {{
                        item.objective_keys
                            .map((key) => objectiveLabels.get(key) ?? key)
                            .join(', ')
                    }}
                </p>

                <fieldset
                    v-if="initialized && item.response"
                    class="mt-5 min-w-0 space-y-5"
                    :disabled="!editable || busy"
                    :aria-describedby="controlId(item.key, 'errors')"
                >
                    <legend class="sr-only">
                        Respuesta a {{ item.label }}
                    </legend>
                    <ul
                        :id="controlId(item.key, 'errors')"
                        tabindex="-1"
                        class="space-y-1 text-sm text-red-700 outline-offset-4 dark:text-red-300"
                        :role="
                            visibleIssues[item.key]?.length
                                ? 'alert'
                                : undefined
                        "
                    >
                        <li
                            v-for="issue in visibleIssues[item.key] ?? []"
                            :key="issue"
                        >
                            {{ issue }}
                        </li>
                    </ul>
                    <div>
                        <label
                            class="bi-label"
                            :for="controlId(item.key, 'availability')"
                            >Disponibilidad</label
                        >
                        <select
                            :id="controlId(item.key, 'availability')"
                            v-model="item.response.availability"
                            class="bi-input mt-1"
                            :aria-invalid="
                                visibleIssues[item.key]?.length
                                    ? true
                                    : undefined
                            "
                        >
                            <option
                                v-for="[value, label] in availabilities"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <h5 class="font-semibold">
                                Fuentes de información
                                <span
                                    class="text-sm font-normal text-slate-600 dark:text-slate-300"
                                    >({{
                                        item.response.sources.length
                                    }}/6)</span
                                >
                            </h5>
                            <Button
                                v-if="editable"
                                type="button"
                                variant="outline"
                                class="min-h-11"
                                :disabled="
                                    busy || item.response.sources.length >= 6
                                "
                                @click="addSource(item.response)"
                                >Agregar fuente</Button
                            >
                        </div>
                        <p
                            v-if="!item.response.sources.length"
                            class="text-sm text-slate-600 dark:text-slate-300"
                        >
                            No hay fuentes declaradas. Si la información está
                            disponible o parcialmente disponible, agrega al
                            menos una para enviar.
                        </p>
                        <fieldset
                            v-for="(source, sourceIndex) in item.response
                                .sources"
                            :key="sourceIndex"
                            class="min-w-0 space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <legend class="px-1 text-sm font-semibold">
                                Fuente {{ sourceIndex + 1 }}
                            </legend>
                            <div class="grid min-w-0 gap-4 md:grid-cols-2">
                                <div class="min-w-0">
                                    <label
                                        class="bi-label"
                                        :for="
                                            controlId(
                                                item.key,
                                                `source-${sourceIndex}-name`,
                                            )
                                        "
                                        >Nombre de la fuente</label
                                    >
                                    <input
                                        :id="
                                            controlId(
                                                item.key,
                                                `source-${sourceIndex}-name`,
                                            )
                                        "
                                        v-model="source.name"
                                        maxlength="191"
                                        class="bi-input mt-1"
                                        type="text"
                                        placeholder="Ej.: sistema de ventas"
                                    />
                                    <p class="bi-help">
                                        Hasta 191 caracteres. Puedes completarlo
                                        después al guardar un borrador.
                                    </p>
                                </div>
                                <div class="min-w-0">
                                    <label
                                        class="bi-label"
                                        :for="
                                            controlId(
                                                item.key,
                                                `source-${sourceIndex}-type`,
                                            )
                                        "
                                        >Tipo de fuente</label
                                    >
                                    <select
                                        :id="
                                            controlId(
                                                item.key,
                                                `source-${sourceIndex}-type`,
                                            )
                                        "
                                        v-model="source.type"
                                        class="bi-input mt-1"
                                    >
                                        <option
                                            v-for="[
                                                value,
                                                label,
                                            ] in sourceTypes"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <h6 class="text-sm font-medium">
                                    Nombres de estructuras conocidas ({{
                                        source.structures.length
                                    }}/12)
                                </h6>
                                <p
                                    :id="
                                        controlId(
                                            item.key,
                                            `source-${sourceIndex}-help`,
                                        )
                                    "
                                    class="bi-help"
                                >
                                    Solo nombres de tablas, hojas, reportes u
                                    otras estructuras. No incluyas filas,
                                    registros ni valores de columnas. Máximo 191
                                    caracteres por nombre.
                                </p>
                                <div
                                    v-for="(
                                        _, structureIndex
                                    ) in source.structures"
                                    :key="structureIndex"
                                    class="flex min-w-0 flex-wrap items-end gap-2 sm:flex-nowrap"
                                >
                                    <label
                                        class="min-w-0 flex-1 text-sm"
                                        :for="
                                            controlId(
                                                item.key,
                                                `source-${sourceIndex}-structure-${structureIndex}`,
                                            )
                                        "
                                    >
                                        Estructura {{ structureIndex + 1 }}
                                        <input
                                            :id="
                                                controlId(
                                                    item.key,
                                                    `source-${sourceIndex}-structure-${structureIndex}`,
                                                )
                                            "
                                            v-model="
                                                source.structures[
                                                    structureIndex
                                                ]
                                            "
                                            maxlength="191"
                                            class="bi-input mt-1"
                                            type="text"
                                            :aria-describedby="
                                                controlId(
                                                    item.key,
                                                    `source-${sourceIndex}-help`,
                                                )
                                            "
                                            placeholder="Ej.: Clientes"
                                        />
                                    </label>
                                    <Button
                                        v-if="editable"
                                        type="button"
                                        variant="outline"
                                        class="min-h-11"
                                        :aria-label="`Quitar estructura ${structureIndex + 1} de la fuente ${sourceIndex + 1}`"
                                        @click="
                                            source.structures.splice(
                                                structureIndex,
                                                1,
                                            )
                                        "
                                        >Quitar</Button
                                    >
                                </div>
                                <Button
                                    v-if="editable"
                                    type="button"
                                    variant="outline"
                                    class="min-h-11"
                                    :disabled="
                                        busy || source.structures.length >= 12
                                    "
                                    @click="addStructure(source)"
                                    >Agregar estructura</Button
                                >
                            </div>
                            <div>
                                <label
                                    class="bi-label"
                                    :for="
                                        controlId(
                                            item.key,
                                            `source-${sourceIndex}-notes`,
                                        )
                                    "
                                    >Observaciones de la fuente</label
                                >
                                <textarea
                                    :id="
                                        controlId(
                                            item.key,
                                            `source-${sourceIndex}-notes`,
                                        )
                                    "
                                    v-model="source.notes"
                                    maxlength="800"
                                    rows="3"
                                    class="bi-input mt-1"
                                />
                                <p class="bi-help">
                                    {{ Array.from(source.notes).length }}/800
                                    caracteres
                                </p>
                            </div>
                            <Button
                                v-if="editable"
                                type="button"
                                variant="outline"
                                class="min-h-11 text-red-700 dark:text-red-300"
                                :aria-label="`Quitar fuente ${sourceIndex + 1} de ${item.label}`"
                                @click="
                                    item.response.sources.splice(sourceIndex, 1)
                                "
                                >Quitar fuente</Button
                            >
                        </fieldset>
                    </div>
                    <div>
                        <label
                            class="bi-label"
                            :for="controlId(item.key, 'notes')"
                            >Observaciones del requerimiento</label
                        >
                        <textarea
                            :id="controlId(item.key, 'notes')"
                            v-model="item.response.notes"
                            maxlength="800"
                            rows="3"
                            class="bi-input mt-1"
                            :aria-describedby="
                                controlId(item.key, 'notes-help')
                            "
                        />
                        <p
                            :id="controlId(item.key, 'notes-help')"
                            class="bi-help"
                        >
                            {{ Array.from(item.response.notes).length }}/800
                            caracteres. Explica el motivo si la información está
                            parcialmente disponible, no está disponible o
                            necesita revisión.
                        </p>
                    </div>
                </fieldset>
            </article>

            <footer
                v-if="initialized && !submitted"
                class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white py-4 dark:border-slate-700 dark:bg-slate-950"
            >
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{
                        conflict || contextChanged
                            ? 'Consulta el estado vigente para resolver el conflicto.'
                            : 'Puedes guardar un borrador aunque falten respuestas.'
                    }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="!canWrite"
                        @click="saveDraft"
                        >{{ saveLabel }}</Button
                    >
                    <Button
                        :id="controlId('actions', 'submit')"
                        type="button"
                        class="min-h-11 bg-sky-700 text-white hover:bg-sky-800"
                        :disabled="!canWrite"
                        @click="askSubmit"
                        >Enviar declaración</Button
                    >
                </div>
            </footer>
        </form>
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            Este cuestionario es declarativo. No solicita archivos ni
            inspecciona datos transaccionales.
        </p>

        <Dialog v-model:open="confirming">
            <DialogContent
                :show-close-button="false"
                @escape-key-down="busy && $event.preventDefault()"
                @pointer-down-outside="busy && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>Enviar declaración definitiva</DialogTitle>
                    <DialogDescription
                        >Se enviarán
                        {{ questionnaire.requirements.length }} respuestas del
                        cuestionario versión {{ questionnaire.version }}.
                        Después del envío podrás consultarlas, pero no editarlas
                        ni reabrir la declaración.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="busy"
                        @click="confirming = false"
                        >Seguir revisando</Button
                    >
                    <Button
                        type="button"
                        class="min-h-11 bg-sky-700 text-white hover:bg-sky-800"
                        :disabled="!canWrite"
                        @click="confirmSubmit"
                        >{{
                            operation === 'submit'
                                ? 'Enviando…'
                                : 'Confirmar envío definitivo'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
    <section
        v-else-if="state === 'loading'"
        class="mb-6 p-4 text-sm"
        role="status"
        aria-live="polite"
    >
        Consultando los requerimientos publicados…
    </section>
    <section
        v-else-if="state === 'error'"
        class="mb-6 space-y-3 rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-800"
        role="alert"
    >
        <p>{{ error || 'No fue posible consultar los requerimientos.' }}</p>
        <Button
            type="button"
            variant="outline"
            class="min-h-11"
            :disabled="busy"
            @click="load"
            >Reintentar consulta</Button
        >
    </section>
    <DataBiDeclarativeReviewPanel
        :implementation-request-id="implementationRequestId"
        :version="questionnaire?.version ?? null"
        audience="tenant"
        :enabled="state === 'ready' && submitted"
        :requirements="questionnaire?.requirements ?? []"
        :declarations="declarations"
        :selected-key="selectedKey"
        :display="display"
        @focus-requirement="
            (key) => {
                selectedKey = key;
                display = 'focused';
            }
        "
    />
</template>

<style scoped>
@reference "tailwindcss";
@custom-variant dark (&:is(.dark *));
.bi-input {
    @apply min-h-11 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-base text-slate-950 outline-offset-2 focus-visible:outline-2 focus-visible:outline-sky-600 disabled:cursor-default dark:border-slate-700 dark:bg-slate-900 dark:text-white;
}
.bi-label {
    @apply block text-sm font-medium text-slate-900 dark:text-slate-100;
}
.bi-help {
    @apply mt-1 text-sm text-slate-600 dark:text-slate-300;
}
.bi-declaration {
    @apply text-slate-900 selection:bg-sky-100 selection:text-sky-950 dark:text-slate-100 dark:selection:bg-sky-900 dark:selection:text-sky-100;
}
</style>
