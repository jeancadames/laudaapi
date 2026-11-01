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
    clarificationLabels,
    useDataBiDeclarativeReview,
    type ReviewAction,
    type ReviewAudience,
} from '@/composables/useDataBiDeclarativeReview';
import {
    availabilities,
    sourceTypes,
    type Declaration,
    type Requirement,
} from '@/composables/useDataBiTenantDeclaration';

const props = defineProps<{
    implementationRequestId: number | null;
    version: number | null;
    audience: ReviewAudience;
    enabled: boolean;
    requirements: Requirement[];
    declarations: Declaration[];
    selectedKey: string;
    display: 'focused' | 'list';
}>();
const emit = defineEmits<{ focusRequirement: [key: string] }>();
const {
    session,
    data,
    busy,
    dirty,
    allowed,
    offered,
    draft,
    setDraft,
    target,
    valid,
    load,
    send,
    retry,
    useInspected,
} = useDataBiDeclarativeReview(() => ({
    requestId: props.implementationRequestId,
    version: props.version,
    audience: props.audience,
    enabled: props.enabled,
    requirementKeys: props.requirements.map((r) => r.key),
}));
const id = useId();
const tab = ref<'clarifications' | 'closure'>('clarifications');
const pending = ref<{ action: ReviewAction; key?: string | number } | null>(
    null,
);
const discardKey = ref<string | null>(null);
const confirming = computed({
    get: () => pending.value !== null || discardKey.value !== null,
    set: (open: boolean) => {
        if (!open && !busy.value) {
            pending.value = null;
            discardKey.value = null;
        }
    },
});
const actionLabels = {
    request: 'Solicitar aclaración',
    answer: 'Enviar respuesta',
    resolve: 'Resolver aclaración',
    cancel: 'Cancelar aclaración',
    close: 'Completar revisión declarativa gratuita',
};
const eventLabels = {
    request: 'Pregunta',
    answer: 'Respuesta',
    resolve: 'Resolución',
    cancel: 'Motivo de cancelación',
};
const requirementLabel = (key: string) =>
    props.requirements.find((r) => r.key === key)?.label ?? key;
const availability = (value: string) =>
    availabilities.find(([key]) => key === value)?.[1] ?? value;
const sourceType = (value: string) =>
    sourceTypes.find(([key]) => key === value)?.[1] ?? value;
const original = (key: string) =>
    props.declarations.find((d) => d.requirement_key === key);
const items = (key: string) =>
    data.value?.clarifications.filter((c) => c.requirement_key === key) ?? [];
const focused = computed(
    () =>
        props.requirements.find((r) => r.key === props.selectedKey) ??
        props.requirements[0],
);
const gaps = computed(
    () =>
        data.value?.closure?.declared_gaps ??
        props.declarations.filter((d) => d.availability !== 'available'),
);
const openCount = computed(
    () =>
        data.value?.clarifications.filter(
            (c) => c.status === 'requested' || c.status === 'answered',
        ).length ?? 0,
);
const localTexts = computed(() =>
    Object.entries(session.value.drafts).filter(([, value]) => value.trim()),
);
function localLabel(key: string): string {
    if (key === 'conclusion') return 'Conclusión local';
    if (key === 'limitations') return 'Limitaciones locales';
    const [action, ...rest] = key.split(':');
    const reference = rest.join(':');
    const requirement =
        action === 'request'
            ? reference
            : data.value?.clarifications.find((c) => c.id === Number(reference))
                  ?.requirement_key;
    return `${actionLabels[action as ReviewAction]}${requirement ? ' · ' + requirementLabel(requirement) : ''}`;
}
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
const input = (event: Event, key: string) =>
    setDraft(key, (event.target as HTMLTextAreaElement).value);
async function inspectRequirement(key: string): Promise<void> {
    emit('focusRequirement', key);
    await nextTick();
    document.getElementById(`${id}-detail`)?.focus();
}
function ask(action: ReviewAction, key?: string | number): void {
    if (
        allowed(action, typeof key === 'number' ? key : undefined) &&
        valid(action, key)
    )
        pending.value = { action, key };
}
async function confirm(): Promise<void> {
    if (busy.value) return;
    if (discardKey.value !== null) {
        setDraft(discardKey.value, '');
        discardKey.value = null;
        return;
    }
    if (!pending.value) return;
    const { action, key } = pending.value;
    await send(action, key);
    pending.value = null;
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
                'Hay textos de aclaración sin confirmar o una operación en curso. Si sales, perderás los textos locales. ¿Salir?',
            )
        )
            event.preventDefault();
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', guardUnload);
    stopNavigation?.();
});
watch(
    () => [props.implementationRequestId, props.version, props.enabled],
    () => {
        pending.value = null;
        discardKey.value = null;
    },
);
</script>

<template>
    <section
        v-show="enabled"
        class="bi-review mt-6 min-w-0 border-t border-border pt-5"
        :aria-labelledby="`${id}-title`"
        :aria-busy="busy"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-2">
                <h3 :id="`${id}-title`" class="text-lg font-semibold">
                    Aclaraciones y revisión declarativa
                </h3>
                <p class="max-w-prose text-sm text-muted-foreground">
                    Versión {{ version }}. Las aclaraciones son mensajes
                    independientes: no modifican la declaración enviada ni sus
                    fuentes y estructuras. Una corrección formal requiere un
                    nuevo cuestionario y una nueva declaración.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy || !enabled"
                @click="load"
                >{{
                    busy
                        ? 'Consultando o enviando…'
                        : 'Consultar estado vigente'
                }}</Button
            >
        </header>
        <p v-if="busy" class="mt-4 text-sm" role="status" aria-live="polite">
            Operación en curso. Espera antes de enviar otro mensaje.
        </p>
        <div
            v-if="session.error"
            :id="`${id}-error`"
            class="mt-4 space-y-3 rounded-lg border border-destructive p-3 text-sm"
            role="alert"
        >
            <p>{{ session.error }}</p>
            <p v-if="session.problem">
                El texto de la operación se conserva en su formulario o en
                «Textos locales conservados».
            </p>
            <Button
                v-if="
                    session.uncertain &&
                    !session.blocked &&
                    data?.is_current &&
                    data.review_status === 'open'
                "
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="retry"
                >Reintentar el mismo envío</Button
            >
        </div>
        <p
            v-if="session.notice"
            class="mt-4 text-sm text-emerald-800 dark:text-emerald-200"
            role="status"
            aria-live="polite"
        >
            {{ session.notice }}
        </p>
        <div
            v-if="session.inspected && !session.blocked"
            class="mt-4 space-y-2 text-sm"
        >
            <p>
                Revisa el historial consultado. Continuar conserva tus textos y
                utiliza la revisión recibida; no envía ningún mensaje.
            </p>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="busy"
                @click="useInspected"
                >Continuar con el estado consultado</Button
            >
        </div>
        <p
            v-if="data && !data.is_current"
            class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100"
            role="status"
        >
            Publicación histórica · Versión {{ data.questionnaire_version }}.
            Solo lectura. Sus aclaraciones y cierre no se trasladan a una
            versión nueva.
        </p>
        <details v-if="localTexts.length" class="mt-4 text-sm">
            <summary
                class="cursor-pointer py-2 font-medium focus-visible:outline-2 focus-visible:outline-ring"
            >
                Textos locales conservados ({{ localTexts.length }})
            </summary>
            <dl class="space-y-3 py-2">
                <div v-for="[key, value] in localTexts" :key="key">
                    <dt class="font-medium">{{ localLabel(key) }}</dt>
                    <dd class="mt-1 break-words whitespace-pre-wrap">
                        {{ value }}
                    </dd>
                    <dd class="mt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :disabled="busy"
                            :aria-label="`Descartar texto local: ${localLabel(key)}`"
                            @click="discardKey = key"
                            >Descartar este texto local</Button
                        >
                    </dd>
                </div>
            </dl>
        </details>
        <template v-if="data && !session.blocked">
            <div
                v-if="audience === 'admin'"
                class="mt-5 flex flex-wrap gap-2"
                role="group"
                aria-label="Sección de revisión declarativa"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :aria-pressed="tab === 'clarifications'"
                    :aria-controls="`${id}-clarifications`"
                    @click="tab = 'clarifications'"
                    >Aclaraciones ({{ data.clarifications.length }})</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :aria-pressed="tab === 'closure'"
                    :aria-controls="`${id}-closure`"
                    @click="tab = 'closure'"
                    >{{
                        data.closure ? 'Consultar cierre' : 'Preparar cierre'
                    }}</Button
                >
            </div>
            <div
                :id="`${id}-clarifications`"
                v-show="tab === 'clarifications' || audience === 'tenant'"
                class="mt-4 space-y-4"
            >
                <p
                    v-if="!data.clarifications.length"
                    class="text-sm text-muted-foreground"
                    role="status"
                >
                    Todavía no hay aclaraciones para esta declaración enviada.
                </p>
                <ul
                    v-if="display === 'list'"
                    class="divide-y divide-border"
                    aria-label="Aclaraciones por requerimiento"
                >
                    <li
                        v-for="requirement in requirements"
                        :key="requirement.key"
                        class="flex min-w-0 flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="min-w-0 flex-1 basis-48">
                            <h4 class="font-medium break-words">
                                {{ requirement.label }}
                            </h4>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ items(requirement.key).length }} aclaraciones
                                ·
                                {{
                                    availability(
                                        original(requirement.key)
                                            ?.availability ?? 'unknown',
                                    )
                                }}
                            </p>
                            <p
                                v-for="item in items(requirement.key)"
                                :key="item.id"
                                class="mt-1 text-sm break-words"
                            >
                                {{ item.question }} ·
                                {{ clarificationLabels[item.status] }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :aria-label="`Ver aclaraciones de ${requirement.label}`"
                            @click="inspectRequirement(requirement.key)"
                            >Ver requerimiento</Button
                        >
                    </li>
                </ul>
                <div v-else-if="focused" class="space-y-4">
                    <h4
                        :id="`${id}-detail`"
                        tabindex="-1"
                        class="font-semibold break-words outline-offset-4"
                    >
                        {{ focused.label }}
                    </h4>
                    <details class="text-sm">
                        <summary
                            class="cursor-pointer py-2 font-medium focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            Declaración original de referencia ·
                            {{
                                availability(
                                    original(focused.key)?.availability ??
                                        'unknown',
                                )
                            }}
                        </summary>
                        <div class="space-y-3 py-2">
                            <p class="break-words whitespace-pre-wrap">
                                {{
                                    original(focused.key)?.notes ||
                                    'Sin observaciones del requerimiento.'
                                }}
                            </p>
                            <ul class="space-y-3">
                                <li
                                    v-for="(source, index) in original(
                                        focused.key,
                                    )?.sources ?? []"
                                    :key="index"
                                    class="break-words"
                                >
                                    <p class="font-medium">
                                        {{ source.name }} ·
                                        {{ sourceType(source.type) }}
                                    </p>
                                    <p>
                                        Estructuras:
                                        {{
                                            source.structures.join(', ') ||
                                            'Sin nombres declarados.'
                                        }}
                                    </p>
                                    <p class="whitespace-pre-wrap">
                                        {{
                                            source.notes ||
                                            'Sin observaciones de la fuente.'
                                        }}
                                    </p>
                                </li>
                            </ul>
                            <p v-if="!original(focused.key)?.sources.length">
                                No hay fuentes declaradas para este
                                requerimiento.
                            </p>
                        </div>
                    </details>
                    <p
                        v-if="!items(focused.key).length"
                        class="text-sm text-muted-foreground"
                    >
                        Este requerimiento no tiene aclaraciones.
                    </p>
                    <article
                        v-for="item in items(focused.key)"
                        :key="item.id"
                        class="min-w-0 space-y-3 border-t border-border pt-4"
                        :aria-labelledby="`${id}-question-${item.id}`"
                    >
                        <h5
                            :id="`${id}-question-${item.id}`"
                            class="font-medium break-words"
                        >
                            {{ item.question }}
                        </h5>
                        <p class="text-sm font-medium">
                            {{ clarificationLabels[item.status] }} · Revisión
                            {{ item.revision }}
                        </p>
                        <ol
                            class="space-y-3"
                            aria-label="Historial cronológico de mensajes"
                        >
                            <li
                                v-for="(event, index) in item.events"
                                :key="index"
                                class="text-sm"
                            >
                                <p class="text-muted-foreground">
                                    {{ eventLabels[event.action] }} ·
                                    {{
                                        event.actor_type === 'lauda_admin'
                                            ? 'Admin LAUDA'
                                            : 'Admin Tenant'
                                    }}
                                    ·
                                    <time :datetime="event.occurred_at">{{
                                        date(event.occurred_at)
                                    }}</time>
                                </p>
                                <p class="mt-1 break-words whitespace-pre-wrap">
                                    {{ event.message }}
                                </p>
                            </li>
                        </ol>
                        <template
                            v-for="action in [
                                'answer',
                                'resolve',
                                'cancel',
                            ] as const"
                            :key="action"
                        >
                            <form
                                v-if="offered(action, item.id)"
                                class="space-y-2"
                                @submit.prevent="ask(action, item.id)"
                            >
                                <label
                                    class="block text-sm font-medium"
                                    :for="`${id}-${action}-${item.id}`"
                                    >{{
                                        action === 'answer'
                                            ? 'Tu respuesta declarativa'
                                            : action === 'resolve'
                                              ? 'Explicación de la resolución'
                                              : 'Motivo obligatorio de cancelación'
                                    }}</label
                                >
                                <textarea
                                    :id="`${id}-${action}-${item.id}`"
                                    :value="draft(target(action, item.id))"
                                    :disabled="!allowed(action, item.id)"
                                    :aria-describedby="`${id}-text-help`"
                                    :aria-errormessage="
                                        session.error
                                            ? `${id}-error`
                                            : undefined
                                    "
                                    :aria-invalid="
                                        session.problem ===
                                            target(action, item.id) &&
                                        !!session.error
                                    "
                                    rows="3"
                                    maxlength="1600"
                                    class="review-input"
                                    @input="
                                        input($event, target(action, item.id))
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        Array.from(
                                            draft(target(action, item.id)),
                                        ).length
                                    }}/800 caracteres
                                </p>
                                <Button
                                    type="submit"
                                    :variant="
                                        action === 'cancel'
                                            ? 'outline'
                                            : 'default'
                                    "
                                    class="min-h-11"
                                    :class="
                                        action === 'cancel'
                                            ? ''
                                            : 'bg-sky-700 text-white hover:bg-sky-800'
                                    "
                                    :disabled="
                                        !valid(action, item.id) ||
                                        !allowed(action, item.id)
                                    "
                                    >{{ actionLabels[action] }}</Button
                                >
                            </form>
                        </template>
                    </article>
                    <form
                        v-if="offered('request')"
                        class="space-y-2 border-t border-border pt-4"
                        @submit.prevent="ask('request', focused.key)"
                    >
                        <label
                            :for="`${id}-request`"
                            class="block text-sm font-medium"
                            >Pregunta para el Tenant sobre
                            {{ focused.label }}</label
                        >
                        <textarea
                            :id="`${id}-request`"
                            :value="draft(target('request', focused.key))"
                            rows="3"
                            maxlength="1600"
                            :aria-describedby="`${id}-text-help`"
                            :aria-invalid="
                                session.problem ===
                                    target('request', focused.key) &&
                                !!session.error
                            "
                            class="review-input"
                            :disabled="!allowed('request')"
                            :aria-errormessage="
                                session.error ? `${id}-error` : undefined
                            "
                            @input="
                                input($event, target('request', focused.key))
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                Array.from(
                                    draft(target('request', focused.key)),
                                ).length
                            }}/800 caracteres
                        </p>
                        <Button
                            type="submit"
                            class="min-h-11 bg-sky-700 text-white hover:bg-sky-800"
                            :disabled="
                                !valid('request', focused.key) ||
                                !allowed('request')
                            "
                            >Solicitar aclaración</Button
                        >
                    </form>
                </div>
            </div>
            <section
                :id="`${id}-closure`"
                v-show="
                    tab === 'closure' || audience === 'tenant' || !!data.closure
                "
                class="mt-5 space-y-4 border-t border-border pt-4"
                aria-label="Cierre de revisión declarativa"
            >
                <h4 class="font-semibold">
                    {{
                        data.closure
                            ? 'Revisión declarativa gratuita completada'
                            : 'Cierre de la revisión declarativa gratuita'
                    }}
                </h4>
                <p class="max-w-prose text-sm text-muted-foreground">
                    El cierre registra que LAUDA terminó de examinar lo
                    declarado. No certifica técnicamente las fuentes ni
                    constituye aprobación comercial. Puede completarse con
                    brechas de información.
                </p>
                <p class="text-sm">
                    {{ data.clarifications.length }} aclaraciones ·
                    {{ openCount }} pendientes
                </p>
                <details class="text-sm" open>
                    <summary
                        class="cursor-pointer py-2 font-medium focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Brechas declaradas en la versión {{ version }} ({{
                            gaps.length
                        }})
                    </summary>
                    <ul class="mt-2 space-y-3">
                        <li v-for="gap in gaps" :key="gap.requirement_key">
                            <p class="font-medium break-words">
                                {{ requirementLabel(gap.requirement_key) }} ·
                                {{ availability(gap.availability) }}
                            </p>
                            <p class="mt-1 break-words whitespace-pre-wrap">
                                {{ gap.notes }}
                            </p>
                        </li>
                    </ul>
                    <p v-if="!gaps.length" class="mt-2 text-muted-foreground">
                        El Tenant no declaró brechas de disponibilidad.
                    </p>
                </details>
                <dl v-if="data.closure" class="space-y-4 text-sm">
                    <div>
                        <dt class="font-medium">
                            Fecha de cierre · Versión
                            {{ data.closure.questionnaire_version }}
                        </dt>
                        <dd class="mt-1">
                            <time :datetime="data.closure.closed_at">{{
                                date(data.closure.closed_at)
                            }}</time>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Conclusión declarativa</dt>
                        <dd class="mt-1 break-words whitespace-pre-wrap">
                            {{ data.closure.conclusion }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Limitaciones identificadas</dt>
                        <dd class="mt-1 break-words whitespace-pre-wrap">
                            {{ data.closure.limitations }}
                        </dd>
                    </div>
                </dl>
                <form
                    v-else-if="offered('close')"
                    class="space-y-4"
                    @submit.prevent="ask('close')"
                >
                    <div
                        v-for="field in ['conclusion', 'limitations'] as const"
                        :key="field"
                        class="space-y-2"
                    >
                        <label
                            :for="`${id}-${field}`"
                            class="block text-sm font-medium"
                            >{{
                                field === 'conclusion'
                                    ? 'Conclusión declarativa'
                                    : 'Limitaciones identificadas'
                            }}</label
                        >
                        <textarea
                            :id="`${id}-${field}`"
                            :value="draft(field)"
                            rows="4"
                            maxlength="1600"
                            :aria-describedby="`${id}-text-help`"
                            :aria-invalid="
                                session.problem === target('close') &&
                                !!session.error
                            "
                            class="review-input"
                            :disabled="!allowed('close')"
                            :aria-errormessage="
                                session.error ? `${id}-error` : undefined
                            "
                            @input="input($event, field)"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ Array.from(draft(field)).length }}/800 caracteres
                        </p>
                    </div>
                    <Button
                        type="submit"
                        class="min-h-11 bg-sky-700 text-white hover:bg-sky-800"
                        :disabled="!valid('close') || !allowed('close')"
                        >Completar revisión declarativa gratuita</Button
                    >
                </form>
                <p
                    v-else-if="
                        audience === 'admin' &&
                        data.is_current &&
                        !session.conflict &&
                        !session.uncertain
                    "
                    class="text-sm text-muted-foreground"
                    role="status"
                >
                    El servidor no permite el cierre en el estado vigente.
                    Resuelve o cancela las aclaraciones pendientes y consulta de
                    nuevo.
                </p>
                <p
                    v-else-if="audience === 'tenant'"
                    class="text-sm text-muted-foreground"
                >
                    El cierre corresponde a Admin LAUDA. Tu declaración enviada
                    permanece inmutable.
                </p>
            </section>
        </template>
        <p :id="`${id}-text-help`" class="mt-4 text-xs text-muted-foreground">
            Textos de 1 a 800 caracteres. Comparte solo descripciones
            declarativas; sin archivos, credenciales ni registros empresariales
            reales.
        </p>
        <Dialog v-model:open="confirming">
            <DialogContent
                :show-close-button="false"
                @escape-key-down="busy && $event.preventDefault()"
                @pointer-down-outside="busy && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        discardKey !== null
                            ? 'Descartar texto local'
                            : pending
                              ? actionLabels[pending.action]
                              : 'Confirmar operación'
                    }}</DialogTitle>
                    <DialogDescription>
                        <template v-if="discardKey !== null"
                            >Se eliminará únicamente este texto sin enviar de la
                            pantalla: {{ localLabel(discardKey) }}. El historial
                            recibido y la declaración formal se
                            conservan.</template
                        >
                        <template v-else>
                            Versión {{ version
                            }}{{
                                typeof pending?.key === 'string'
                                    ? ' · ' + requirementLabel(pending.key)
                                    : ''
                            }}.
                            {{
                                pending?.action === 'close'
                                    ? 'La conclusión y las limitaciones quedarán registradas sin posibilidad de edición o reemplazo. Las brechas declaradas se conservarán.'
                                    : 'Este mensaje quedará en el historial y no podrá editarse silenciosamente. La declaración original no cambia.'
                            }}
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="busy"
                        @click="
                            pending = null;
                            discardKey = null;
                        "
                        >Seguir revisando</Button
                    >
                    <Button
                        type="button"
                        class="min-h-11 bg-sky-700 text-white hover:bg-sky-800"
                        :disabled="
                            busy ||
                            (discardKey === null &&
                                (!pending ||
                                    !allowed(
                                        pending.action,
                                        typeof pending.key === 'number'
                                            ? pending.key
                                            : undefined,
                                    )))
                        "
                        @click="confirm"
                        >{{ busy ? 'Enviando…' : 'Confirmar' }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>

<style scoped>
@reference "tailwindcss";
.review-input {
    @apply min-h-11 w-full min-w-0 rounded-lg border px-3 py-2 text-base outline-offset-2 focus-visible:outline-2 disabled:cursor-default;
    border-color: var(--input);
    background: var(--background);
    color: var(--foreground);
}
.review-input:focus-visible {
    outline-color: var(--ring);
}
.bi-review {
    @apply selection:bg-sky-200 selection:text-slate-950;
    color: var(--foreground);
}
</style>
