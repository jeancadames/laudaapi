<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock3,
    Database,
    FileText,
    Layers3,
    UserRound,
    UserRoundCheck,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Person = {
    id?: number;
    name: string | null;
    email: string | null;
};

type EventItem = {
    id: number;
    event_type: string;
    event_label: string;
    from_status: string | null;
    from_status_label: string | null;
    to_status: string;
    to_status_label: string;
    actor_type: string;
    actor_type_label: string;
    actor: Person | null;
    notes: string | null;
    occurred_at: string | null;
};

const props = defineProps<{
    implementation_request: {
        id: number;
        status: string;
        status_label: string;
        attempt: number;
        source_type: string;
        tenant_note: string | null;
        internal_notes: string | null;
        requested_at: string | null;
        review_started_at: string | null;
        definition_started_at: string | null;
        tenant_review_requested_at: string | null;
        changes_requested_at: string | null;
        definition_agreed_at: string | null;
        ready_for_commercial_at: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
    };
    company: {
        id: number;
        name: string;
        subscriber_id: number | null;
    };
    assessment: {
        id: number;
        organization_name: string | null;
        status: string | null;
        published_at: string | null;
    };
    plan: {
        id: number;
        version: number | null;
        status: string | null;
        presented_at: string | null;
    };
    phase: {
        id: number | null;
        sequence: number | null;
        name: string | null;
    };
    capability: {
        phase_capability_id: number;
        key: string;
        label: string;
        summary: string | null;
        purpose: string | null;
    };
    requested_by: Person | null;
    assigned_to: Person | null;
    events: EventItem[];
    admin_users: {
        id: number;
        name: string;
        email: string;
    }[];
    definition: {
        id: number;
        version: number;
        status: string;
        capability_key: string;
        created_at: string | null;
        content_prepared: boolean;
        deliverable_count: number;
        dependency_count: number;
    } | null;

    definition_review: {

        implementation_scope: Record<string, any>;

        deliverables: Array<Record<string, any>>;

        dependencies: Array<Record<string, any>>;

        responsibility_model: {

            assignments?: Array<Record<string, any>>;

            party_assignment_status?: string;

        };

        readiness: Record<string, any>;

        reviewed_at: string | null;

        reviewed_by_user_id: number | null;

    } | null;


    ready_for_commercial_context: {
        agreement_event_id: number | null;
        functional_closure_event_id: number | null;
        definition_id: number | null;
        definition_version: number | null;
        definition_status: string | null;
        definition_ready: boolean;
        ready_at: string | null;
        request_status: string;
        ready_for_commercial_at: string | null;
        can_mark_ready_for_commercial: boolean;
    } | null;

    functional_closure_context: {
        agreement_event_id: number;
        definition_id: number;
        definition_version: number;
        definition_status: string;
        definition_ready: boolean;
        ready_at: string | null;
        tenant_agreed_at: string | null;
        can_finalize: boolean;
    } | null;

    definition_revision_context: {
        previous_definition_id: number;
        previous_definition_version: number;
        previous_definition_status: string;
        tenant_change_reason: string | null;
        changes_requested_at: string | null;
        current_definition_version: number | null;
    } | null;

    actions: {
        can_create_definition_revision: boolean;
        definition_revision_endpoint: string | null;
        can_create_definition: boolean;
        definition_create_endpoint: string | null;
        can_generate_definition: boolean;
        definition_generate_endpoint: string | null;
        can_review_definition: boolean;
        definition_review_endpoint: string | null;
        can_submit_definition_for_tenant_review: boolean;
        definition_submit_tenant_review_endpoint: string | null;
        can_mark_ready_for_commercial: boolean;
        ready_for_commercial_endpoint: string | null;
        can_finalize_definition_functionally: boolean;
        definition_functional_finalize_endpoint: string | null;
        can_mutate: boolean;
        assign_endpoint: string;
        transition_endpoint: string;
        allowed_transitions: string[];
    };
}>();

const breadcrumbs = [
    {
        title: 'Transformación 360',
        href: '/admin/transformation-360',
    },
    {
        title: 'Solicitudes de Implementación',
        href: '/admin/transformation-360/implementation-requests',
    },
    {
        title: `Solicitud #${props.implementation_request.id}`,
        href: `/admin/transformation-360/implementation-requests/${props.implementation_request.id}`,
    },
];

const assignedUserId = ref<number | null>(
    props.assigned_to?.id ?? null,
);

const transitionNotes = ref('');
const assigning = ref(false);
const transitioning = ref(false);

function assignResponsible(): void {
    if (
        !props.actions.can_mutate
        || !props.actions.assign_endpoint
        || !assignedUserId.value
        || assigning.value
    ) {
        return;
    }

    router.patch(
        props.actions.assign_endpoint,
        {
            assigned_to_user_id: assignedUserId.value,
        },
        {
            preserveScroll: true,
            onStart: () => {
                assigning.value = true;
            },
            onFinish: () => {
                assigning.value = false;
            },
        },
    );
}

function canTransition(
    status: string,
): boolean {
    return props.actions.allowed_transitions.includes(
        status,
    );
}

function transitionRequest(
    targetStatus: string,
): void {
    if (
        !props.actions.can_mutate
        || !props.actions.transition_endpoint
        || !canTransition(targetStatus)
        || transitioning.value
    ) {
        return;
    }

    router.post(
        props.actions.transition_endpoint,
        {
            target_status: targetStatus,
            notes: transitionNotes.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => {
                transitioning.value = true;
            },
            onSuccess: () => {
                transitionNotes.value = '';
            },
            onFinish: () => {
                transitioning.value = false;
            },
        },
    );
}

function createImplementationDefinitionRevision(): void {
    const endpoint =
        props.actions.definition_revision_endpoint;

    if (
        !props.actions.can_create_definition_revision
        || !endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
        },
    );
}


function createImplementationDefinition(): void {
    const endpoint =
        props.actions.definition_create_endpoint;

    if (
        ! props.actions.can_create_definition
        || ! endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
        },
    );
}


function generateImplementationDefinition(): void {
    const endpoint =
        props.actions.definition_generate_endpoint;

    if (
        !props.actions.can_generate_definition
        || !endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
        },
    );
}


const humanReviewForm = useForm({
    implementation_scope: {} as Record<string, any>,
    deliverables: [] as Array<Record<string, any>>,
    dependencies: [] as Array<Record<string, any>>,
    responsibility_model: {
        assignments: [] as Array<Record<string, any>>,
    },
    readiness: {
        scope_confirmed: false,
        deliverables_confirmed: false,
        dependencies_confirmed: false,
        inputs_validated: false,
        accesses_validated: false,
        responsibilities_confirmed: false,
    },
});

const functionalScopeJson = ref('');
const functionalDeliverablesJson = ref('');
const functionalDependenciesJson = ref('');
const functionalEditorError = ref<string | null>(null);

function prettyFunctionalJson(value: unknown): string {
    return JSON.stringify(
        value ?? null,
        null,
        2,
    );
}

function parseFunctionalEditors(): boolean {
    functionalEditorError.value = null;

    try {
        const scope =
            JSON.parse(
                functionalScopeJson.value,
            );

        const deliverables =
            JSON.parse(
                functionalDeliverablesJson.value,
            );

        const dependencies =
            JSON.parse(
                functionalDependenciesJson.value,
            );

        if (
            !scope
            || typeof scope !== 'object'
            || Array.isArray(scope)
        ) {
            throw new Error(
                'El alcance debe ser un objeto JSON.',
            );
        }

        if (
            !Array.isArray(deliverables)
            || !deliverables.length
        ) {
            throw new Error(
                'Los entregables deben ser una lista JSON con al menos un elemento.',
            );
        }

        if (!Array.isArray(dependencies)) {
            throw new Error(
                'Las dependencias deben ser una lista JSON.',
            );
        }

        humanReviewForm.implementation_scope =
            scope;

        humanReviewForm.deliverables =
            deliverables;

        humanReviewForm.dependencies =
            dependencies;

        return true;
    } catch (error) {
        functionalEditorError.value =
            error instanceof Error
                ? error.message
                : 'Revisa la estructura JSON funcional.';

        return false;
    }
}


function syncHumanReviewForm(): void {
    const source =
        props.definition_review;

    humanReviewForm.implementation_scope =
        source?.implementation_scope
        ?? {};

    humanReviewForm.deliverables =
        source?.deliverables
        ?? [];

    humanReviewForm.dependencies =
        source?.dependencies
        ?? [];

    functionalScopeJson.value =
        prettyFunctionalJson(
            humanReviewForm.implementation_scope,
        );

    functionalDeliverablesJson.value =
        prettyFunctionalJson(
            humanReviewForm.deliverables,
        );

    functionalDependenciesJson.value =
        prettyFunctionalJson(
            humanReviewForm.dependencies,
        );

    functionalEditorError.value = null;

    const assignments =
        source?.responsibility_model
            ?.assignments ?? [];

    humanReviewForm.responsibility_model.assignments =
        assignments.map(
            (assignment) => ({
                ...assignment,
                responsible_party:
                    assignment.responsible_party
                    ?? '',
            }),
        );

    const validation =
        source?.readiness
            ?.human_validation
        ?? {};

    humanReviewForm.readiness.scope_confirmed =
        validation.scope_confirmed
        ?? false;

    humanReviewForm.readiness.deliverables_confirmed =
        validation.deliverables_confirmed
        ?? false;

    humanReviewForm.readiness.dependencies_confirmed =
        validation.dependencies_confirmed
        ?? false;

    humanReviewForm.readiness.inputs_validated =
        validation.inputs_validated
        ?? false;

    humanReviewForm.readiness.accesses_validated =
        validation.accesses_validated
        ?? false;

    humanReviewForm.readiness.responsibilities_confirmed =
        validation.responsibilities_confirmed
        ?? false;
}

watch(
    () => props.definition_review,
    () => {
        syncHumanReviewForm();
    },
    {
        deep: true,
        immediate: true,
    },
);

function saveImplementationDefinitionHumanReview(): void {
    const endpoint =
        props.actions.definition_review_endpoint;

    if (
        !props.actions.can_review_definition
        || !endpoint
    ) {
        return;
    }

    if (!parseFunctionalEditors()) {
        return;
    }

    humanReviewForm.patch(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}


const functionalClosureForm = useForm({});

const readyForCommercialForm = useForm({});

const tenantReviewSubmissionForm = useForm({
    notes: '',
});

function submitDefinitionForTenantReview(): void {
    const endpoint =
        props.actions
            .definition_submit_tenant_review_endpoint;

    if (
        !props.actions
            .can_submit_definition_for_tenant_review
        || !endpoint
    ) {
        return;
    }

    tenantReviewSubmissionForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

function finalizeFunctionalDefinition(): void {
    const endpoint =
        props.actions
            .definition_functional_finalize_endpoint;

    if (
        !props.actions
            .can_finalize_definition_functionally
        || !endpoint
        || functionalClosureForm.processing
    ) {
        return;
    }

    functionalClosureForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

function markRequestReadyForCommercial(): void {
    const endpoint =
        props.actions
            .ready_for_commercial_endpoint;

    if (
        !props.actions
            .can_mark_ready_for_commercial
        || !endpoint
        || readyForCommercialForm.processing
    ) {
        return;
    }

    readyForCommercialForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

</script>

<template>
    <Head
        :title="`Solicitud de Implementación #${implementation_request.id}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <Link
                href="/admin/transformation-360/implementation-requests"
                class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-950 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" />
                Volver a Solicitudes de Implementación
            </Link>

            <section
                class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-[10px] font-black tracking-[0.18em] text-slate-400 uppercase"
                        >
                            Solicitud #{{ implementation_request.id }}
                            · intento {{ implementation_request.attempt }}
                        </p>

                        <h1
                            class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            {{ company.name }}
                        </h1>

                        <div class="mt-3 flex items-center gap-2">
                            <Database class="h-5 w-5 text-blue-600" />

                            <p
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ capability.label }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 dark:border-blue-900 dark:bg-blue-950/30"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-blue-500 uppercase"
                        >
                            Estado actual
                        </p>

                        <p
                            class="mt-1 text-sm font-black text-blue-900 dark:text-blue-200"
                        >
                            {{ implementation_request.status_label }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1fr_0.72fr]">
                <div class="space-y-6">
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <h2
                            class="text-lg font-black text-slate-950 dark:text-white"
                        >
                            Contexto de la solicitud
                        </h2>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Building2 class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Empresa
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    {{ company.name }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <FileText class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Diagnóstico
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    #{{ assessment.id }}
                                    · {{ assessment.status ?? '—' }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Layers3 class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Plan
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    #{{ plan.id }}
                                    <template v-if="plan.version">
                                        · V{{ plan.version }}
                                    </template>
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Database class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Fase
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    <template v-if="phase.sequence">
                                        Fase {{ phase.sequence }} ·
                                    </template>
                                    {{ phase.name ?? 'No disponible' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="implementation_request.tenant_note"
                            class="mt-5 rounded-2xl border border-slate-200 p-5 dark:border-slate-800"
                        >
                            <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Nota de la empresa
                            </p>

                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">
                                {{ implementation_request.tenant_note }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <h2
                            class="text-lg font-black text-slate-950 dark:text-white"
                        >
                            Historial
                        </h2>

                        <div
                            v-if="events.length"
                            class="mt-6 space-y-5"
                        >
                            <div
                                v-for="event in events"
                                :key="event.id"
                                class="flex gap-4"
                            >
                                <div
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                </div>

                                <div class="min-w-0 flex-1 border-b border-slate-100 pb-5 last:border-0 dark:border-slate-800">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm font-black">
                                            {{ event.event_label }}
                                        </p>

                                        <p
                                            v-if="event.occurred_at"
                                            class="text-xs text-slate-400"
                                        >
                                            {{ event.occurred_at }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ event.actor_type_label }}

                                        <template v-if="event.actor?.name">
                                            · {{ event.actor.name }}
                                        </template>
                                    </p>

                                    <p
                                        v-if="event.notes"
                                        class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ event.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                            Solicitud iniciada por
                        </p>

                        <div class="mt-4 flex gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900"
                            >
                                <UserRound class="h-5 w-5" />
                            </div>

                            <div>
                                <p class="text-sm font-black">
                                    {{ requested_by?.name ?? 'Usuario de la empresa' }}
                                </p>

                                <p
                                    v-if="requested_by?.email"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ requested_by.email }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="implementation_request.requested_at"
                            class="mt-5 flex items-start gap-3 border-t border-slate-100 pt-5 dark:border-slate-800"
                        >
                            <Clock3 class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />

                            <div>
                                <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Recibida
                                </p>

                                <p class="mt-1 text-xs font-semibold">
                                    {{ implementation_request.requested_at }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-center gap-2">
                            <UserRoundCheck class="h-5 w-5 text-blue-600" />

                            <h2 class="font-black text-slate-950 dark:text-white">
                                Responsable LAUDA
                            </h2>
                        </div>

                        <select
                            v-model="assignedUserId"
                            class="mt-4 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <option :value="null">
                                Seleccionar responsable
                            </option>

                            <option
                                v-for="admin in admin_users"
                                :key="admin.id"
                                :value="admin.id"
                            >
                                {{ admin.name }} · {{ admin.email }}
                            </option>
                        </select>

                        <button
                            type="button"
                            class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-black transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-800 dark:hover:bg-slate-900"
                            :disabled="!assignedUserId || assigning"
                            @click="assignResponsible"
                        >
                            {{
                                assigning
                                    ? 'Guardando...'
                                    : 'Asignar responsable'
                            }}
                        </button>
                    </section>

                    <section
                        v-if="
                            actions.can_mutate
                            && actions.allowed_transitions.length
                        "
                        class="rounded-[2rem] border border-blue-200 bg-blue-50/60 p-6 dark:border-blue-900 dark:bg-blue-950/20"
                    >
                        <p class="text-[10px] font-black tracking-widest text-blue-600 uppercase dark:text-blue-400">
                            Gestión de solicitud
                        </p>

                        <textarea
                            v-model="transitionNotes"
                            rows="3"
                            placeholder="Nota interna opcional sobre este cambio de estado"
                            class="mt-4 w-full rounded-xl border border-blue-200 bg-white p-3 text-sm dark:border-blue-900 dark:bg-slate-950"
                        />

                        <button
                            v-if="canTransition('under_lauda_review')"
                            type="button"
                            class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                            :disabled="transitioning"
                            @click="
                                transitionRequest(
                                    'under_lauda_review',
                                )
                            "
                        >
                            Recibir e iniciar revisión
                        </button>

                        <button
                            v-if="canTransition('definition_preparation')"
                            type="button"
                            class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                            :disabled="transitioning"
                            @click="
                                transitionRequest(
                                    'definition_preparation',
                                )
                            "
                        >
                            Iniciar preparación de definición
                        </button>

                        <div class="mt-4 rounded-xl border border-blue-200 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-900 dark:bg-slate-950/40 dark:text-slate-400">
                            “Preparación de definición” solo cambia el estado
                            operativo de la solicitud. No crea todavía una
                            Definition. Esa vinculación pertenece a F5.
                        </div>
                    </section>

                    <section
                        v-else
                        class="rounded-[2rem] border border-slate-200/70 bg-slate-50 p-5 text-sm leading-6 text-slate-500 dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        Esta solicitud no tiene una transición administrativa
                        habilitada en F4C.
                    </section>
                </aside>
            </div>
        </div>
    
        <section
            v-if="
                props.actions.can_create_definition_revision
                && props.definition_revision_context
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300"
                        >
                            Cambios solicitados por la empresa
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Preparar nueva versión de la Definition
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            La empresa solicitó ajustes sobre la
                            Definition V{{
                                props.definition_revision_context
                                    .previous_definition_version
                            }}.
                            Esa versión se conservará sin cambios como
                            evidencia histórica.
                        </p>

                        <div
                            v-if="
                                props.definition_revision_context
                                    .tenant_change_reason
                            "
                            class="mt-4 rounded-xl border border-amber-200 bg-white/80 p-4 dark:border-amber-900 dark:bg-slate-950/50"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Ajustes solicitados
                            </p>

                            <p
                                class="mt-2 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    props.definition_revision_context
                                        .tenant_change_reason
                                }}
                            </p>
                        </div>

                        <p
                            class="mt-4 text-xs leading-5 text-muted-foreground"
                        >
                            Esta acción crea la siguiente versión como
                            borrador de trabajo, copia el contenido
                            funcional anterior como punto de partida y
                            reinicia las confirmaciones humanas.
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            La nueva versión no se envía automáticamente
                            a la empresa y no inicia contratación,
                            facturación, activación, suscripción ni
                            ejecución.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            !props.actions.definition_revision_endpoint
                        "
                        @click="createImplementationDefinitionRevision"
                    >
                        Preparar nueva versión
                    </button>
                </div>
            </div>
        </section>


        <section
            v-if="props.implementation_request.status === 'definition_preparation'"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                        >
                            Definition funcional
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Alcance de la capability solicitada
                        </h2>

                        <template v-if="props.definition">
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Ya existe la Definition V{{ props.definition.version }}
                                para
                                <span class="font-semibold">
                                    {{ props.definition.capability_key }}
                                </span>.
                                Estado:
                                <span class="font-semibold">
                                    {{ props.definition.status }}
                                </span>.
                            </p>
                        </template>

                        <template v-else>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                La solicitud está lista para que LAUDA cree
                                explícitamente el borrador funcional de la
                                capability solicitada.
                            </p>
                        </template>

                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                            Crear el borrador y preparar su contenido son acciones
                            separadas. Ninguna envía la Definition al tenant ni
                            inicia activación, ejecución, contratación, facturación,
                            pagos o suscripciones.
                        </p>

                        <div
                            v-if="
                                props.definition &&
                                props.definition.content_prepared
                            "
                            class="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground"
                        >
                            <span class="rounded-full border px-3 py-1">
                                Entregables preparados:
                                {{ props.definition.deliverable_count }}
                            </span>

                            <span class="rounded-full border px-3 py-1">
                                Dependencias preparadas:
                                {{ props.definition.dependency_count }}
                            </span>
                        </div>
                    </div>

                    <button
                        v-if="
                            !props.definition &&
                            props.actions.can_create_definition
                        "
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="!props.actions.definition_create_endpoint"
                        @click="createImplementationDefinition"
                    >
                        Crear borrador funcional de Definition
                    </button>

                    <button
                        v-else-if="
                            props.definition &&
                            !props.definition.content_prepared &&
                            props.actions.can_generate_definition
                        "
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="!props.actions.definition_generate_endpoint"
                        @click="generateImplementationDefinition"
                    >
                        Preparar contenido de la Definition
                    </button>

                    <div
                        v-else-if="
                            props.definition &&
                            props.definition.content_prepared
                        "
                        class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        Contenido preparado para revisión
                    </div>

                    <div
                        v-else-if="props.definition"
                        class="shrink-0 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-800"
                    >
                        Definition V{{ props.definition.version }} creada
                    </div>
                </div>
            </div>
        </section>


        <section
            v-if="
                props.definition &&
                props.definition.content_prepared
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        Revisión humana LAUDA
                    </p>

                    <h2 class="mt-2 text-lg font-bold">
                        Confirmar Definition funcional
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Revisa responsables, alcance, entregables,
                        dependencias, insumos y accesos antes de
                        continuar con el proceso.
                    </p>

                    <p class="mt-2 text-xs leading-5 text-muted-foreground">
                        Guardar esta revisión no marca la Definition
                        como lista y no la envía al tenant. La solicitud
                        permanece en preparación de definición.
                    </p>
                </div>

                <div
                    v-if="
                        props.definition_revision_context
                        && props.definition_revision_context
                            .current_definition_version
                    "
                    class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900 dark:bg-amber-950/20"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300"
                    >
                        Cambios solicitados por la empresa
                    </p>

                    <p class="mt-2 text-sm leading-6">
                        Estás editando la Definition V{{
                            props.definition_revision_context
                                .current_definition_version
                        }}
                        a partir de los ajustes solicitados sobre la V{{
                            props.definition_revision_context
                                .previous_definition_version
                        }}.
                    </p>

                    <p
                        v-if="
                            props.definition_revision_context
                                .tenant_change_reason
                        "
                        class="mt-3 whitespace-pre-line rounded-xl border border-amber-200 bg-white/80 p-4 text-sm leading-6 dark:border-amber-900 dark:bg-slate-950/50"
                    >
                        {{
                            props.definition_revision_context
                                .tenant_change_reason
                        }}
                    </p>

                    <p
                        class="mt-3 text-xs leading-5 text-muted-foreground"
                    >
                        La versión anterior permanece preservada.
                        Solo esta nueva versión puede modificarse.
                    </p>
                </div>

                <div class="mt-6">
                    <div class="max-w-3xl">
                        <p class="text-sm font-bold">
                            Edición funcional de la nueva versión
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            Estos campos muestran exactamente las
                            estructuras funcionales existentes de la
                            Definition. El editor JSON permite conservar
                            cualquier estructura anidada sin introducir
                            un segundo esquema paralelo.
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            scope_mode, capability_key y el bloqueo
                            request-scoped se vuelven a fijar en el
                            servidor al guardar.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-5">
                        <label class="block">
                            <span class="text-sm font-semibold">
                                Alcance funcional
                            </span>

                            <textarea
                                v-model="functionalScopeJson"
                                rows="12"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold">
                                Entregables
                            </span>

                            <textarea
                                v-model="functionalDeliverablesJson"
                                rows="12"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold">
                                Dependencias
                            </span>

                            <textarea
                                v-model="functionalDependenciesJson"
                                rows="10"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>
                    </div>

                    <div
                        v-if="functionalEditorError"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                    >
                        {{ functionalEditorError }}
                    </div>
                </div>


                <div
                    v-if="
                        humanReviewForm
                            .responsibility_model
                            .assignments
                            .length
                    "
                    class="mt-6 space-y-3"
                >
                    <p class="text-sm font-bold">
                        Responsabilidades
                    </p>

                    <div
                        v-for="(
                            assignment,
                            index
                        ) in humanReviewForm.responsibility_model.assignments"
                        :key="
                            assignment.initiative_id
                            ?? index
                        "
                        class="grid gap-3 rounded-xl border p-4 lg:grid-cols-[1fr_260px] dark:border-slate-800"
                    >
                        <div>
                            <p class="text-sm font-semibold">
                                {{
                                    assignment.initiative_title
                                    ?? assignment.initiative_id
                                }}
                            </p>

                            <p
                                v-if="assignment.suggested_owner_role"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Sugerencia:
                                {{
                                    assignment.suggested_owner_role
                                }}
                            </p>
                        </div>

                        <select
                            v-model="
                                humanReviewForm
                                    .responsibility_model
                                    .assignments[index]
                                    .responsible_party
                            "
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            :disabled="
                                humanReviewForm.processing
                                || !props.actions.can_review_definition
                            "
                        >
                            <option value="">
                                Seleccionar responsable
                            </option>

                            <option value="lauda">
                                LAUDA
                            </option>

                            <option value="client">
                                Cliente
                            </option>

                            <option value="shared">
                                Compartido
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-sm font-bold">
                        Confirmaciones humanas
                    </p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.scope_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Alcance confirmado
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.deliverables_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Entregables confirmados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.dependencies_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Dependencias confirmadas
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.inputs_validated"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Insumos validados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.accesses_validated"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Accesos validados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.responsibilities_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Responsabilidades confirmadas
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    v-if="
                        Object.keys(
                            humanReviewForm.errors,
                        ).length
                    "
                    class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                >
                    Revisa los campos indicados antes de guardar.
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            humanReviewForm.processing
                            || !props.actions.can_review_definition
                            || !props.actions.definition_review_endpoint
                        "
                        @click="saveImplementationDefinitionHumanReview"
                    >
                        Guardar revisión humana
                    </button>
                </div>
            </div>
        </section>


        <section
            v-if="props.ready_for_commercial_context"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-2 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm dark:border-blue-950 dark:bg-blue-950/10"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-400"
                >
                    Cierre del ciclo funcional
                </p>

                <h2 class="mt-2 text-lg font-bold">
                    Definition V{{ props.ready_for_commercial_context.definition_version }}
                </h2>

                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    La Definition exacta acordada ya completó su cierre
                    funcional. El siguiente gate únicamente registra que
                    esta solicitud puede pasar, más adelante, a un proceso
                    comercial independiente.
                </p>

                <div
                    v-if="
                        props.actions.can_mark_ready_for_commercial
                        && props.ready_for_commercial_context.can_mark_ready_for_commercial
                    "
                    class="mt-5 rounded-xl border border-blue-200 bg-white/70 p-4 dark:border-blue-950 dark:bg-slate-950/60"
                >
                    <p class="text-sm font-semibold">
                        Listo para etapa comercial
                    </p>

                    <p
                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                    >
                        Este paso no crea propuesta, precio, contrato,
                        factura, pago, suscripción, activación ni ejecución.
                        Tampoco constituye aceptación comercial. Solo cierra
                        formalmente el ciclo funcional de esta solicitud.
                    </p>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                            :disabled="
                                readyForCommercialForm.processing
                                || !props.actions.can_mark_ready_for_commercial
                                || !props.actions.ready_for_commercial_endpoint
                            "
                            @click="markRequestReadyForCommercial"
                        >
                            <CheckCircle2 class="h-4 w-4" />

                            {{
                                readyForCommercialForm.processing
                                    ? 'Registrando cierre...'
                                    : 'Dejar listo para etapa comercial'
                            }}
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="
                        props.ready_for_commercial_context.request_status
                            === 'ready_for_commercial'
                    "
                    class="mt-5 rounded-xl border border-blue-200 bg-white/70 p-4 dark:border-blue-950 dark:bg-slate-950/60"
                >
                    <p class="text-sm font-semibold">
                        Ciclo funcional completado
                    </p>

                    <p
                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                    >
                        La solicitud quedó lista para que un proceso
                        comercial separado pueda iniciarse posteriormente.
                        No existe aceptación comercial, activación,
                        ejecución ni suscripción automática.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="props.functional_closure_context"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-2 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm dark:border-emerald-950 dark:bg-emerald-950/10"
            >
                <div class="flex items-start gap-3">
                    <CheckCircle2
                        class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />

                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400"
                        >
                            Definition acordada por la empresa
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Definition V{{ props.functional_closure_context.definition_version }}
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                        >
                            Esta es la versión exacta fijada por el acuerdo del tenant.
                            El cierre funcional se aplicará a esta Definition, no a una
                            versión posterior que pudiera existir.
                        </p>

                        <div
                            class="mt-4 grid gap-3 text-sm sm:grid-cols-3"
                        >
                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Estado
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{ props.functional_closure_context.definition_status }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Definition ready
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        props.functional_closure_context.definition_ready
                                            ? 'Sí'
                                            : 'No'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Acuerdo del tenant
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        props.functional_closure_context.tenant_agreed_at
                                            ?? 'Registrado'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                props.actions.can_finalize_definition_functionally
                                && props.functional_closure_context.can_finalize
                            "
                            class="mt-5 rounded-xl border border-emerald-200 bg-white/70 p-4 dark:border-emerald-950 dark:bg-slate-950/60"
                        >
                            <p class="text-sm font-semibold">
                                Cierre funcional
                            </p>

                            <p
                                class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                            >
                                Finalizar esta Definition la marcará como funcionalmente
                                lista. No activa el servicio, no inicia ejecución, no crea
                                una suscripción y no mueve la solicitud a etapa comercial.
                            </p>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                    :disabled="
                                        functionalClosureForm.processing
                                        || !props.actions.can_finalize_definition_functionally
                                        || !props.actions.definition_functional_finalize_endpoint
                                    "
                                    @click="finalizeFunctionalDefinition"
                                >
                                    <CheckCircle2 class="h-4 w-4" />

                                    {{
                                        functionalClosureForm.processing
                                            ? 'Finalizando...'
                                            : 'Finalizar Definition funcional'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-else-if="
                                props.functional_closure_context.definition_status === 'ready'
                                && props.functional_closure_context.definition_ready
                            "
                            class="mt-5 rounded-xl border border-emerald-200 bg-white/70 p-4 text-sm dark:border-emerald-950 dark:bg-slate-950/60"
                        >
                            <p class="font-semibold">
                                Definition funcional finalizada
                            </p>

                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                La Definition acordada ya está lista funcionalmente.
                                La solicitud permanece en “Definición acordada” hasta
                                que LAUDA ejecute, por separado, el gate hacia etapa comercial.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="
                props.definition &&
                props.actions.can_submit_definition_for_tenant_review
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        Siguiente paso funcional
                    </p>

                    <h2 class="mt-2 text-lg font-bold">
                        Enviar Definition a la empresa
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        La revisión humana de LAUDA está completa.
                        Puedes enviar esta versión de la Definition
                        a la empresa para que la revise.
                    </p>

                    <p class="mt-2 text-xs leading-5 text-muted-foreground">
                        Este envío no significa que la empresa haya
                        aceptado la Definition, no la marca como ready
                        y no inicia contratación, facturación,
                        activación ni ejecución.
                    </p>
                </div>

                <div class="mt-5 max-w-3xl">
                    <label
                        class="text-sm font-semibold"
                        for="tenant-review-notes"
                    >
                        Nota para la empresa
                        <span class="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </label>

                    <textarea
                        id="tenant-review-notes"
                        v-model="tenantReviewSubmissionForm.notes"
                        rows="4"
                        maxlength="4000"
                        class="mt-2 w-full rounded-xl border bg-background px-3 py-2 text-sm"
                        placeholder="Contexto adicional para la revisión de esta Definition..."
                        :disabled="tenantReviewSubmissionForm.processing"
                    />
                </div>

                <div
                    v-if="
                        tenantReviewSubmissionForm.errors.notes
                    "
                    class="mt-2 text-sm text-red-600 dark:text-red-400"
                >
                    {{
                        tenantReviewSubmissionForm.errors.notes
                    }}
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            tenantReviewSubmissionForm.processing
                            || !props.actions.can_submit_definition_for_tenant_review
                            || !props.actions.definition_submit_tenant_review_endpoint
                        "
                        @click="submitDefinitionForTenantReview"
                    >
                        Enviar a revisión de la empresa
                    </button>
                </div>
            </div>
        </section>

</AppLayout>
</template>
