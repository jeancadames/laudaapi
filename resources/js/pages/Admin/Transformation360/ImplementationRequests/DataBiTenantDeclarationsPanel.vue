<script setup lang="ts">
import { computed, nextTick, useId } from 'vue';
import DataBiDeclarativeReviewPanel from '@/components/DataBiDeclarativeReviewPanel.vue';
import { Button } from '@/components/ui/button';
import { useAdminDataBiDeclarationReview } from '@/composables/useAdminDataBiDeclarationReview';
import {
    availabilities,
    sourceTypes,
} from '@/composables/useDataBiTenantDeclaration';

const props = withDefaults(
    defineProps<{ implementationRequestId: number; active?: boolean }>(),
    { active: true },
);
const {
    data,
    selected,
    rows,
    visibleRows,
    index,
    selectedKey,
    display,
    historical,
    busy,
    error,
    load,
    refresh,
    olderVersions,
    newestVersions,
    focusRequirement,
    move,
} = useAdminDataBiDeclarationReview(
    () => props.implementationRequestId,
    () => props.active,
);
const id = useId();
const objectives = computed(
    () =>
        new Map(
            (selected.value?.objectives ?? []).map((o) => [o.key, o.label]),
        ),
);
const versionOptions = computed(() => {
    const list = data.value?.versions ?? [];
    if (
        !selected.value ||
        list.some((v) => v.version === selected.value?.version)
    )
        return list;
    return [
        {
            version: selected.value.version,
            response_status: selected.value.declaration
                ? 'submitted'
                : 'pending',
        },
        ...list,
    ];
});
const coverageLabels = [
    ['requested', 'Solicitados'],
    ['available', 'Disponibles'],
    ['partial', 'Parciales'],
    ['unavailable', 'No disponibles'],
    ['unknown', 'Por aclarar'],
    ['sources', 'Fuentes declaradas'],
    ['structures', 'Estructuras identificadas'],
] as const;
function date(value: string | null | undefined): string {
    return value && !Number.isNaN(new Date(value).getTime())
        ? new Intl.DateTimeFormat('es-DO', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'No disponible';
}
const availabilityLabel = (value: string | undefined) =>
    availabilities.find(([key]) => key === value)?.[1] ??
    'Pendiente de respuesta';
const sourceLabel = (value: string) =>
    sourceTypes.find(([key]) => key === value)?.[1] ?? value;
const structureCount = (sources: { structures: string[] }[]) =>
    sources.reduce((total, source) => total + source.structures.length, 0);
function changeVersion(event: Event): void {
    void load(Number((event.target as HTMLSelectElement).value));
}
async function inspect(key: string): Promise<void> {
    focusRequirement(key);
    await nextTick();
    document.getElementById(`${id}-detail`)?.focus();
}
</script>

<template>
    <section
        class="mb-4 min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6"
        aria-label="Revisión administrativa de declaraciones del Tenant"
        :aria-busy="busy"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-2">
                <h2 class="text-lg font-semibold">Respuestas del Tenant</h2>
                <p
                    class="max-w-prose text-sm leading-relaxed text-muted-foreground"
                >
                    Consulta de entregas declarativas. Esta síntesis refleja lo
                    informado por el Tenant; no comprueba la existencia técnica,
                    el contenido ni la calidad de las fuentes.
                </p>
                <p v-if="data" class="text-sm font-medium break-words">
                    {{ data.company.name }} · Solicitud BI #{{
                        data.implementation_request_id
                    }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="refresh"
                >{{ busy ? 'Consultando…' : 'Actualizar consulta' }}</Button
            >
        </header>

        <p
            v-if="busy"
            class="mt-4 text-sm text-muted-foreground"
            role="status"
            aria-live="polite"
        >
            Consultando declaraciones enviadas…
        </p>
        <div
            v-if="error"
            class="mt-4 space-y-3 rounded-lg border border-destructive p-4 text-sm"
            role="alert"
        >
            <p>{{ error }}</p>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="newestVersions"
                >Reintentar consulta</Button
            >
        </div>
        <template v-if="data && !error">
            <div
                class="mt-5 flex flex-wrap items-end gap-3 border-y border-border py-4"
            >
                <label
                    v-if="selected"
                    :for="`${id}-version`"
                    class="min-w-0 flex-1 text-sm font-medium"
                >
                    Versión del cuestionario publicado
                    <select
                        :id="`${id}-version`"
                        class="mt-1 min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base text-foreground"
                        :value="selected.version"
                        :disabled="busy"
                        @change="changeVersion"
                    >
                        <option
                            v-for="v in versionOptions"
                            :key="v.version"
                            :value="v.version"
                        >
                            Versión {{ v.version }} ·
                            {{
                                v.response_status === 'submitted'
                                    ? 'Respuesta recibida'
                                    : 'Pendiente de respuesta'
                            }}
                        </option>
                    </select>
                    <span
                        v-if="data.versions.length"
                        class="mt-1 block text-xs font-normal text-muted-foreground"
                        >Historial cargado: versiones
                        {{ data.versions[data.versions.length - 1].version }} a
                        {{ data.versions[0].version }}.</span
                    >
                </label>
                <Button
                    v-if="data.next_before_version"
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="busy"
                    @click="olderVersions"
                    >Ver versiones anteriores</Button
                >
                <Button
                    v-if="
                        data.versions.length &&
                        data.versions[0]?.version !== data.current_version
                    "
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="busy"
                    @click="newestVersions"
                    >Volver a versiones recientes</Button
                >
            </div>
            <p
                v-if="historical"
                class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100"
                role="status"
            >
                Versión histórica {{ selected?.version }}. La versión vigente es
                {{ data.current_version }}; sus respuestas se consultan por
                separado.
            </p>
            <div class="mt-4 space-y-2" role="status" aria-live="polite">
                <p
                    class="font-semibold"
                    :class="
                        selected?.declaration
                            ? 'text-emerald-800 dark:text-emerald-200'
                            : 'text-muted-foreground'
                    "
                >
                    {{
                        selected?.declaration
                            ? 'Respuesta formal recibida del Tenant'
                            : 'Pendiente de respuesta del Tenant'
                    }}
                </p>
                <p v-if="!selected" class="text-sm text-muted-foreground">
                    Todavía no hay un cuestionario publicado para esta
                    solicitud.
                </p>
                <p
                    v-else-if="!selected.declaration"
                    class="text-sm text-muted-foreground"
                >
                    No hay una declaración enviada para esta versión. Los
                    borradores del Tenant permanecen privados.
                </p>
            </div>
            <template v-if="selected">
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Publicado</dt>
                        <dd class="mt-1 font-medium">
                            {{ date(selected.published_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Envío definitivo</dt>
                        <dd class="mt-1 font-medium">
                            {{
                                selected.declaration
                                    ? date(selected.declaration.submitted_at)
                                    : 'Pendiente'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Revisión de la declaración
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{
                                selected.declaration?.revision ??
                                'Sin entrega formal'
                            }}
                        </dd>
                    </div>
                </dl>
                <section
                    v-if="selected.coverage"
                    class="mt-5 border-y border-border py-4"
                    aria-label="Cobertura declarativa de esta versión"
                >
                    <h3 class="font-semibold">Cobertura declarativa</h3>
                    <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-4">
                        <div v-for="[key, label] in coverageLabels" :key="key">
                            <dt class="text-sm text-muted-foreground">
                                {{ label }}
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">
                                {{ selected.coverage[key] }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 max-w-prose text-xs text-muted-foreground">
                        Se cuentan menciones por requerimiento. Una fuente o
                        estructura puede aparecer en varios requerimientos. «Por
                        aclarar» corresponde a lo declarado como desconocido o
                        que requiere revisión; no implica una solicitud formal
                        de aclaraciones.
                    </p>
                </section>
                <details
                    v-if="selected.objectives.length"
                    class="mt-4 border-b border-border pb-3"
                >
                    <summary
                        class="cursor-pointer py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Objetivos empresariales relacionados ({{
                            selected.objectives.length
                        }})
                    </summary>
                    <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                        <li
                            v-for="objective in selected.objectives"
                            :key="objective.key"
                            class="break-words"
                        >
                            {{ objective.label }}
                        </li>
                    </ul>
                </details>
                <div
                    class="mt-5 flex flex-wrap items-center justify-between gap-3"
                >
                    <h3 class="font-semibold">
                        Requerimientos de la versión {{ selected.version }}
                    </h3>
                    <div
                        class="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Presentación de respuestas"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :aria-pressed="display === 'focused'"
                            :class="
                                display === 'focused'
                                    ? 'border-sky-700 bg-sky-700 text-white hover:bg-sky-800 hover:text-white dark:bg-sky-700 dark:text-white dark:hover:bg-sky-800'
                                    : ''
                            "
                            @click="display = 'focused'"
                            >Por requerimiento</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :aria-pressed="display === 'list'"
                            :class="
                                display === 'list'
                                    ? 'border-sky-700 bg-sky-700 text-white hover:bg-sky-800 hover:text-white dark:bg-sky-700 dark:text-white dark:hover:bg-sky-800'
                                    : ''
                            "
                            @click="display = 'list'"
                            >Lista compacta</Button
                        >
                    </div>
                </div>
                <ul
                    v-if="display === 'list'"
                    class="mt-4 divide-y divide-border"
                    aria-label="Resumen por requerimiento"
                >
                    <li
                        v-for="row in rows"
                        :key="row.key"
                        class="flex min-w-0 flex-wrap items-center gap-3 py-3"
                    >
                        <div class="min-w-0 flex-1 basis-48">
                            <p class="font-medium break-words">
                                {{ row.label }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    availabilityLabel(
                                        row.response?.availability,
                                    )
                                }}
                            </p>
                        </div>
                        <p
                            v-if="row.response"
                            class="text-sm text-muted-foreground tabular-nums"
                        >
                            {{ row.response.sources.length }}
                            {{
                                row.response.sources.length === 1
                                    ? 'fuente'
                                    : 'fuentes'
                            }}
                            ·
                            {{ structureCount(row.response.sources) }}
                            {{
                                structureCount(row.response.sources) === 1
                                    ? 'estructura'
                                    : 'estructuras'
                            }}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :aria-label="`Revisar ${row.label}`"
                            @click="inspect(row.key)"
                            >Revisar</Button
                        >
                    </li>
                </ul>
                <template v-else>
                    <nav
                        class="mt-4 flex flex-wrap items-end gap-2"
                        aria-label="Navegación de requerimientos declarados"
                    >
                        <label
                            :for="`${id}-requirement`"
                            class="min-w-0 basis-full text-sm sm:flex-1 sm:basis-auto"
                            >Requerimiento {{ index + 1 }} de {{ rows.length }}
                            <select
                                :id="`${id}-requirement`"
                                v-model="selectedKey"
                                class="mt-1 min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base text-foreground"
                            >
                                <option
                                    v-for="row in rows"
                                    :key="row.key"
                                    :value="row.key"
                                >
                                    {{ row.label }}
                                </option>
                            </select>
                        </label>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :disabled="index === 0"
                            @click="move(-1)"
                            >Anterior</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :disabled="index >= rows.length - 1"
                            @click="move(1)"
                            >Siguiente</Button
                        >
                    </nav>
                    <article
                        v-for="row in visibleRows"
                        :key="row.key"
                        class="mt-5 space-y-4"
                        :aria-labelledby="`${id}-detail`"
                    >
                        <h4
                            :id="`${id}-detail`"
                            tabindex="-1"
                            class="font-semibold break-words outline-offset-4"
                        >
                            {{ row.label }}
                        </h4>
                        <p
                            class="max-w-prose text-sm leading-relaxed break-words whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ row.guidance }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Objetivos:
                            {{
                                row.objective_keys
                                    .map((key) => objectives.get(key) ?? key)
                                    .join(', ')
                            }}
                        </p>
                        <template v-if="row.response">
                            <p class="font-medium">
                                Disponibilidad declarada:
                                {{
                                    availabilityLabel(row.response.availability)
                                }}
                            </p>
                            <div>
                                <h5 class="text-sm font-medium">
                                    Observaciones del requerimiento
                                </h5>
                                <p
                                    class="mt-1 text-sm break-words whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{
                                        row.response.notes ||
                                        'Sin observaciones.'
                                    }}
                                </p>
                            </div>
                            <ul
                                class="divide-y divide-border"
                                aria-label="Fuentes declaradas para este requerimiento"
                            >
                                <li
                                    v-for="(source, i) in row.response.sources"
                                    :key="i"
                                    class="space-y-3 py-4 first:pt-0"
                                >
                                    <p class="font-medium break-words">
                                        {{ source.name }}
                                        <span
                                            class="text-sm font-normal text-muted-foreground"
                                            >·
                                            {{ sourceLabel(source.type) }}</span
                                        >
                                    </p>
                                    <div>
                                        <h6 class="text-sm font-medium">
                                            Estructuras identificadas
                                        </h6>
                                        <ul
                                            class="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground"
                                        >
                                            <li
                                                v-for="structure in source.structures"
                                                :key="structure"
                                                class="break-words"
                                            >
                                                {{ structure }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h6 class="text-sm font-medium">
                                            Observaciones de la fuente
                                        </h6>
                                        <p
                                            class="mt-1 text-sm break-words whitespace-pre-wrap text-muted-foreground"
                                        >
                                            {{
                                                source.notes ||
                                                'Sin observaciones.'
                                            }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-if="!row.response.sources.length"
                                class="text-sm text-muted-foreground"
                            >
                                El Tenant no declaró fuentes para este
                                requerimiento.
                            </p>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">
                            Pendiente de respuesta del Tenant para este
                            requerimiento.
                        </p>
                    </article>
                </template>
            </template>
        </template>
        <DataBiDeclarativeReviewPanel
            :implementation-request-id="implementationRequestId"
            :version="selected?.version ?? null"
            audience="admin"
            :enabled="active && !!selected?.declaration && !error"
            :requirements="selected?.requirements ?? []"
            :declarations="selected?.declaration?.declarations ?? []"
            :selected-key="selectedKey"
            :display="display"
            @focus-requirement="focusRequirement"
        />
    </section>
</template>
