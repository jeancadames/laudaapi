<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { computed } from 'vue';

type ScopeCapability = {
    capability_key: string;
    capability_label: string;
    purpose?: string | null;
    scope_items?: string[];
};

type ScopeInitiative = {
    id: string;
    title: string;
    objective?: string | null;
    actions?: string[];
    dependencies?: string[];
    suggested_owner_role?: string | null;
};

type ScopePhase = {
    id: number;
    sequence: number;
    name: string;
    objective?: string | null;
    horizon?: string | null;
    initiatives?: ScopeInitiative[];
    capabilities?: ScopeCapability[];
    dependencies?: string[];
    deliverables?: string[];
};

type ResponsibilityAssignment = {
    phase_id?: number;
    phase_sequence?: number;
    phase_name?: string;
    initiative_id: string;
    initiative_title?: string;
    suggested_owner_role?: string | null;
    responsible_party?: '' | 'lauda' | 'client' | 'shared';
    confirmation_status?: string;
};

type Definition = {
    id: number;
    version: number;
    status: string;
    implementation_scope: {
        source?: string;
        phases?: ScopePhase[];
    };
    deliverables: Array<Record<string, unknown>>;
    dependencies: Array<Record<string, unknown>>;
    responsibility_model: {
        assignments?: ResponsibilityAssignment[];
        unresolved?: ResponsibilityAssignment[];
        party_assignment_status?: string;
    };
    readiness: Record<string, any>;
    reviewed_at: string | null;
    ready_at: string | null;
    editable: boolean;
    is_ready: boolean;
};

const props = defineProps<{
    contact: {
        id: number;
        company: string | null;
        name: string | null;
    };
    assessment: {
        id: number;
        organization_name: string;
    };
    plan: {
        id: number;
        version: number;
        status: string;
        presented_at: string | null;
    };
    definition: Definition | null;
    endpoints: {
        back: string;
        create: string;
        regenerate: string | null;
        review: string | null;
        ready: string | null;
    };
}>();

const page = usePage();

const errors = computed(
    () =>
        (page.props.errors ?? {}) as Record<string, string>,
);

const phases = computed(
    () =>
        props.definition?.implementation_scope
            ?.phases ?? [],
);

const editable = computed(
    () =>
        props.definition?.editable === true,
);

const underReview = computed(
    () =>
        props.definition?.status
            === 'under_review',
);

const responsibilityAssignments =
    (
        props.definition
            ?.responsibility_model
            ?.assignments ?? []
    ).map((item) => ({
        ...item,
        responsible_party:
            item.responsible_party ?? '',
    }));

const humanValidation =
    props.definition?.readiness
        ?.human_validation ?? {};

const reviewForm = useForm({
    responsibility_model: {
        assignments:
            responsibilityAssignments,
    },

    readiness: {
        scope_confirmed:
            humanValidation
                .scope_confirmed
            ?? false,

        deliverables_confirmed:
            humanValidation
                .deliverables_confirmed
            ?? false,

        dependencies_confirmed:
            humanValidation
                .dependencies_confirmed
            ?? false,

        inputs_validated:
            humanValidation
                .inputs_validated
            ?? false,

        accesses_validated:
            humanValidation
                .accesses_validated
            ?? false,

        responsibilities_confirmed:
            humanValidation
                .responsibilities_confirmed
            ?? false,
    },
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inicio',
        href: '/app',
    },
    {
        title: 'Diagnóstico 360',
        href:
            `/admin/diagnosis-requests/${props.contact.id}`,
    },
    {
        title: 'Plan de Implementación',
        href: props.endpoints.back,
    },
    {
        title: 'Definición de Implementación',
        href:
            `/admin/diagnosis-requests/${props.contact.id}`
            + `/implementation-plan/${props.plan.id}/definition`,
    },
];

function statusLabel(
    status: string,
): string {
    return ({
        draft: 'Borrador',
        under_review: 'En revisión',
        ready: 'Lista',
    } as Record<string, string>)[status]
        ?? status;
}

function createDefinition(): void {
    router.post(
        props.endpoints.create,
        {},
        {
            preserveScroll: true,
        },
    );
}

function regenerate(): void {
    if (
        !props.endpoints.regenerate
        || !window.confirm(
            '¿Regenerar la Definición desde el Plan presentado? Se reemplazará la preparación autogenerada editable.',
        )
    ) {
        return;
    }

    router.post(
        props.endpoints.regenerate,
        {},
        {
            preserveScroll: true,
        },
    );
}

function saveReview(): void {
    if (!props.endpoints.review) {
        return;
    }

    reviewForm.patch(
        props.endpoints.review,
        {
            preserveScroll: true,
        },
    );
}

function markReady(): void {
    if (
        !props.endpoints.ready
        || !window.confirm(
            '¿Marcar esta Definición como lista? Después quedará bloqueada para edición. Esto NO inicia ejecución.',
        )
    ) {
        return;
    }

    router.post(
        props.endpoints.ready,
        {},
        {
            preserveScroll: true,
        },
    );
}

function responsiblePartyLabel(
    value: string | undefined,
): string {
    return ({
        lauda: 'LAUDA',
        client: 'Cliente',
        shared: 'Compartido',
    } as Record<string, string>)[value ?? '']
        ?? 'Pendiente';
}

function deliverableText(
    item: Record<string, unknown>,
): string {
    return String(
        item.deliverable
        ?? item.title
        ?? 'Entregable',
    );
}

function dependencyText(
    item: Record<string, unknown>,
): string {
    return String(
        item.dependency
        ?? item.title
        ?? 'Dependencia',
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Definición de Implementación" />

        <div
            class="mx-auto w-full max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-4"
            >
                <div>
                    <div
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Badge variant="outline">
                            LAUDA 360
                        </Badge>

                        <Badge variant="secondary">
                            Plan V{{ plan.version }}
                        </Badge>

                        <Badge
                            v-if="definition"
                            variant="outline"
                        >
                            Definición V{{ definition.version }}
                            ·
                            {{ statusLabel(definition.status) }}
                        </Badge>
                    </div>

                    <h1
                        class="mt-3 text-2xl font-black"
                    >
                        Definición de Implementación
                    </h1>

                    <p
                        class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        {{
                            contact.company
                            || assessment.organization_name
                        }}
                        · Preparación funcional y técnica
                        derivada del Plan de Implementación.
                    </p>

                    <p
                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                    >
                        Esta etapa confirma alcance,
                        entregables, dependencias,
                        responsabilidades, insumos y accesos.
                        No inicia ejecución y no contiene
                        condiciones comerciales.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        as-child
                        variant="outline"
                    >
                        <Link :href="endpoints.back">
                            Volver al Plan
                        </Link>
                    </Button>

                    <Button
                        v-if="!definition"
                        @click="createDefinition"
                    >
                        Preparar Definición
                    </Button>

                    <Button
                        v-if="definition && editable"
                        variant="outline"
                        @click="regenerate"
                    >
                        Regenerar desde Plan
                    </Button>
                </div>
            </div>

            <Card
                v-if="Object.keys(errors).length"
                class="border-destructive/30 bg-destructive/5"
            >
                <CardContent
                    class="space-y-1 p-5 text-sm text-destructive"
                >
                    <p
                        v-for="(message, key) in errors"
                        :key="key"
                    >
                        {{ message }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="!definition">
                <CardHeader>
                    <CardTitle>
                        Preparar Definición
                    </CardTitle>

                    <CardDescription>
                        El Plan presentado se convertirá
                        en una preparación operativa para
                        revisión humana.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Button
                        @click="createDefinition"
                    >
                        Crear y autogenerar
                    </Button>
                </CardContent>
            </Card>

            <template v-else>
                <Card
                    v-if="definition.is_ready"
                    class="border-emerald-200 bg-emerald-50"
                >
                    <CardContent
                        class="p-5 text-sm text-emerald-900"
                    >
                        <p class="font-black">
                            Etapa de definición completada
                        </p>

                        <p class="mt-1 leading-6">
                            El alcance funcional y técnico quedó
                            revisado y confirmado. Esta etapa termina
                            aquí: no inicia ejecución y no activa
                            ninguna condición comercial.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            1. Alcance de implementación
                        </CardTitle>

                        <CardDescription>
                            Estructura derivada del Plan y
                            enriquecida con las capabilities
                            profesionales.
                        </CardDescription>
                    </CardHeader>

                    <CardContent
                        class="space-y-5"
                    >
                        <article
                            v-for="phase in phases"
                            :key="phase.id"
                            class="rounded-xl border p-5"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p class="font-black">
                                        {{ phase.name }}
                                    </p>

                                    <p
                                        v-if="phase.objective"
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ phase.objective }}
                                    </p>
                                </div>

                                <Badge
                                    v-if="phase.horizon"
                                    variant="outline"
                                >
                                    {{ phase.horizon }}
                                </Badge>
                            </div>

                            <div
                                v-if="phase.initiatives?.length"
                                class="mt-5 space-y-3"
                            >
                                <p
                                    class="text-xs font-black tracking-widest uppercase"
                                >
                                    Iniciativas
                                </p>

                                <div
                                    v-for="initiative in phase.initiatives"
                                    :key="initiative.id"
                                    class="rounded-xl bg-muted/30 p-4"
                                >
                                    <p class="font-bold">
                                        {{ initiative.title }}
                                    </p>

                                    <p
                                        v-if="initiative.objective"
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ initiative.objective }}
                                    </p>

                                    <ul
                                        v-if="initiative.actions?.length"
                                        class="mt-3 list-disc space-y-1 pl-5 text-sm"
                                    >
                                        <li
                                            v-for="action in initiative.actions"
                                            :key="action"
                                        >
                                            {{ action }}
                                        </li>
                                    </ul>

                                    <p
                                        v-if="initiative.suggested_owner_role"
                                        class="mt-3 text-xs text-muted-foreground"
                                    >
                                        Responsable sugerido:
                                        <strong>
                                            {{
                                                initiative.suggested_owner_role
                                            }}
                                        </strong>
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="phase.capabilities?.length"
                                class="mt-5 space-y-3"
                            >
                                <p
                                    class="text-xs font-black tracking-widest uppercase"
                                >
                                    Capacidades profesionales
                                </p>

                                <div
                                    v-for="capability in phase.capabilities"
                                    :key="capability.capability_key"
                                    class="rounded-xl border p-4"
                                >
                                    <p class="font-bold">
                                        {{
                                            capability.capability_label
                                        }}
                                    </p>

                                    <p
                                        v-if="capability.purpose"
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ capability.purpose }}
                                    </p>

                                    <ul
                                        v-if="capability.scope_items?.length"
                                        class="mt-3 list-disc space-y-1 pl-5 text-sm"
                                    >
                                        <li
                                            v-for="item in capability.scope_items"
                                            :key="item"
                                        >
                                            {{ item }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </CardContent>
                </Card>

                <div
                    class="grid gap-6 xl:grid-cols-2"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                2. Entregables
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <ul
                                v-if="definition.deliverables.length"
                                class="list-disc space-y-2 pl-5 text-sm"
                            >
                                <li
                                    v-for="(item, index) in definition.deliverables"
                                    :key="index"
                                >
                                    {{ deliverableText(item) }}
                                </li>
                            </ul>

                            <p
                                v-else
                                class="text-sm text-muted-foreground"
                            >
                                No hay entregables preparados.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                3. Dependencias
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <ul
                                v-if="definition.dependencies.length"
                                class="list-disc space-y-2 pl-5 text-sm"
                            >
                                <li
                                    v-for="(item, index) in definition.dependencies"
                                    :key="index"
                                >
                                    {{ dependencyText(item) }}
                                </li>
                            </ul>

                            <p
                                v-else
                                class="text-sm text-muted-foreground"
                            >
                                Sin dependencias adicionales.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            4. Responsabilidades
                        </CardTitle>

                        <CardDescription>
                            El responsable sugerido del Roadmap
                            no se convierte automáticamente en
                            responsable confirmado.
                        </CardDescription>
                    </CardHeader>

                    <CardContent
                        class="space-y-4"
                    >
                        <div
                            v-for="(assignment, index) in reviewForm.responsibility_model.assignments"
                            :key="assignment.initiative_id"
                            class="grid gap-3 rounded-xl border p-4 lg:grid-cols-[1fr_260px]"
                        >
                            <div>
                                <p class="font-bold">
                                    {{
                                        assignment.initiative_title
                                        || assignment.initiative_id
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Sugerido:
                                    {{
                                        assignment.suggested_owner_role
                                        || 'No definido'
                                    }}
                                </p>
                            </div>

                            <div>
                                <select
                                    v-if="editable"
                                    v-model="reviewForm.responsibility_model.assignments[index].responsible_party"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
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

                                <Badge
                                    v-else
                                    variant="outline"
                                >
                                    {{
                                        responsiblePartyLabel(
                                            assignment.responsible_party,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            5. Validación de readiness
                        </CardTitle>

                        <CardDescription>
                            Estas confirmaciones son humanas.
                            Ninguna se infiere automáticamente.
                        </CardDescription>
                    </CardHeader>

                    <CardContent
                        class="space-y-3"
                    >
                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.scope_confirmed"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Alcance confirmado
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.deliverables_confirmed"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Entregables confirmados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.dependencies_confirmed"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Dependencias confirmadas
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.inputs_validated"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Insumos validados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.accesses_validated"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Accesos validados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4"
                        >
                            <input
                                v-model="reviewForm.readiness.responsibilities_confirmed"
                                type="checkbox"
                                :disabled="!editable"
                            />

                            <span class="text-sm">
                                Responsabilidades confirmadas
                            </span>
                        </label>
                    </CardContent>
                </Card>

                <div
                    v-if="editable"
                    class="flex flex-wrap justify-end gap-3"
                >
                    <Button
                        variant="outline"
                        :disabled="reviewForm.processing"
                        @click="saveReview"
                    >
                        Guardar revisión
                    </Button>

                    <Button
                        v-if="underReview"
                        :disabled="reviewForm.processing"
                        @click="markReady"
                    >
                        Marcar como lista
                    </Button>
                </div>

                <Card
                    v-if="definition.status === 'under_review'"
                    class="border-amber-200 bg-amber-50"
                >
                    <CardContent
                        class="p-5 text-sm text-amber-900"
                    >
                        <p class="font-black">
                            Revisión en curso
                        </p>

                        <p class="mt-1 leading-6">
                            Guarda las confirmaciones y
                            responsabilidades antes de marcar
                            la Definición como lista.
                        </p>
                    </CardContent>
                </Card>

                <Card
                    v-if="definition.is_ready"
                >
                    <CardContent
                        class="p-5 text-sm"
                    >
                        <p class="font-black">
                            Cierre de esta etapa
                        </p>

                        <p
                            class="mt-1 leading-6 text-muted-foreground"
                        >
                            La Definición de Implementación queda
                            como referencia aprobada para la siguiente
                            etapa del proceso. No existe acción para
                            iniciar ejecución desde aquí.
                        </p>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
