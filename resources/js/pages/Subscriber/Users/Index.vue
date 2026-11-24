<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Mail, RefreshCw, UserPlus, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RoleValue = 'owner' | 'admin' | 'member';

type Member = {
    id: number;
    name: string;
    email: string;
    global_role: string;
    role: RoleValue;
    active: boolean;
    email_verified: boolean;
    created_at: string | null;
};

type RoleOption = {
    value: RoleValue;
    label: string;
    description: string;
};

const props = defineProps<{
    subscriber: {
        id: number;
        name: string;
    };
    members: Member[];
    role_options: RoleOption[];
    summary: {
        total: number;
        active: number;
        admins: number;
        owners: number;
    };
    current_user_id: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Usuarios', href: '/subscriber/users' },
];

const form = useForm<{
    name: string;
    email: string;
    role: RoleValue;
}>({
    name: '',
    email: '',
    role: 'member',
});

const roleDrafts = ref<Record<number, RoleValue>>(
    Object.fromEntries(props.members.map((member) => [member.id, member.role])),
);

const query = ref('');

const filteredMembers = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) return props.members;

    return props.members.filter((member) =>
        [member.name, member.email, member.role].some((value) =>
            value.toLowerCase().includes(term),
        ),
    );
});

const roleLabel = (role: RoleValue) =>
    props.role_options.find((option) => option.value === role)?.label ?? role;

const roleDescription = (role: RoleValue) =>
    props.role_options.find((option) => option.value === role)?.description ??
    '';

const submit = () => {
    form.post('/subscriber/users', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.role = 'member';
        },
    });
};

const updateRole = (member: Member) => {
    const role = roleDrafts.value[member.id] ?? member.role;

    router.patch(
        `/subscriber/users/${member.id}/role`,
        { role },
        { preserveScroll: true },
    );
};

/** Persona cuya desactivación se está confirmando (la confirmación aparece en su fila). */
const confirmingId = ref<number | null>(null);

const toggleActive = (member: Member) => {
    if (member.active && confirmingId.value !== member.id) {
        confirmingId.value = member.id;
        return;
    }

    confirmingId.value = null;
    router.patch(
        `/subscriber/users/${member.id}/active`,
        {},
        { preserveScroll: true },
    );
};

const resendAccess = (member: Member) => {
    router.post(
        `/subscriber/users/${member.id}/resend-access`,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Usuarios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.subscriber.name"
            title="Usuarios"
            description="Decide quién de tu equipo entra a LAUDA. Lo que cada persona puede hacer dentro de una aplicación se ajusta en esa aplicación."
        >
            <template #actions>
                <dl
                    class="flex gap-2 text-center"
                    aria-label="Resumen del equipo"
                >
                    <div
                        v-for="item in [
                            { label: 'En total', value: props.summary.total },
                            { label: 'Activos', value: props.summary.active },
                            {
                                label: 'Administran',
                                value: props.summary.admins,
                            },
                        ]"
                        :key="item.label"
                        class="min-w-20 rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <dd
                            class="text-lg font-semibold text-slate-950 tabular-nums dark:text-white"
                        >
                            {{ item.value }}
                        </dd>
                        <dt class="text-xs text-slate-600 dark:text-slate-400">
                            {{ item.label }}
                        </dt>
                    </div>
                </dl>
            </template>

            <PortalSection
                heading-id="add-user-title"
                title="Agregar a alguien"
                description="Si la persona aún no tiene cuenta, la creamos y le enviamos un correo para que elija su contraseña."
            >
                <form
                    class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:grid-cols-[1fr_1fr_12rem_auto] md:items-start dark:border-slate-800 dark:bg-slate-950"
                    @submit.prevent="submit"
                >
                    <label class="block">
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Nombre
                        </span>
                        <input
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                            placeholder="Nombre y apellido"
                        />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.name }}
                        </p>
                    </label>

                    <label class="block">
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Correo
                        </span>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                            placeholder="persona@empresa.com"
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </label>

                    <label class="block">
                        <span
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Rol
                        </span>
                        <select
                            v-model="form.role"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <option
                                v-for="option in props.role_options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.role"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.role }}
                        </p>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="md:mt-7"
                        :class="portalButton.dark"
                    >
                        <UserPlus class="size-4" aria-hidden="true" />
                        {{ form.processing ? 'Agregando…' : 'Agregar' }}
                    </button>

                    <p
                        class="text-sm leading-6 text-slate-600 md:col-span-4 dark:text-slate-400"
                    >
                        <span
                            class="font-medium text-slate-800 dark:text-slate-200"
                            >{{ roleLabel(form.role) }}:</span
                        >
                        {{ roleDescription(form.role) }}
                    </p>
                </form>
            </PortalSection>

            <PortalSection
                heading-id="team-title"
                title="Tu equipo"
                :count="props.members.length"
                :description="`${props.summary.owners} ${props.summary.owners === 1 ? 'propietario activo' : 'propietarios activos'}.`"
            >
                <template v-if="props.members.length > 4" #aside>
                    <label class="w-full sm:w-64">
                        <span class="sr-only">Buscar en tu equipo</span>
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Buscar por nombre o correo"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                        />
                    </label>
                </template>

                <ul
                    class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
                >
                    <li
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="p-4 sm:p-5"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        class="font-semibold break-words text-slate-950 dark:text-white"
                                    >
                                        {{ member.name }}
                                    </h3>
                                    <span
                                        v-if="
                                            member.id === props.current_user_id
                                        "
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        Tú
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            member.active
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300'
                                        "
                                    >
                                        {{
                                            member.active
                                                ? 'Activo'
                                                : 'Inactivo'
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 flex min-w-0 items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400"
                                >
                                    <Mail
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="break-all">{{
                                        member.email
                                    }}</span>
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                                >
                                    <span
                                        class="font-medium text-slate-800 dark:text-slate-200"
                                        >{{ roleLabel(member.role) }}</span
                                    >
                                    · {{ roleDescription(member.role) }}
                                </p>
                            </div>

                            <div
                                v-if="member.id !== props.current_user_id"
                                class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:shrink-0 lg:justify-end"
                            >
                                <div class="flex gap-2">
                                    <label
                                        class="min-w-0 flex-1 sm:w-40 sm:flex-none"
                                    >
                                        <span class="sr-only"
                                            >Rol de {{ member.name }}</span
                                        >
                                        <select
                                            v-model="roleDrafts[member.id]"
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none dark:border-slate-800 dark:bg-slate-950"
                                        >
                                            <option
                                                v-for="option in props.role_options"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        v-if="
                                            roleDrafts[member.id] !==
                                            member.role
                                        "
                                        type="button"
                                        :class="portalButton.dark"
                                        @click="updateRole(member)"
                                    >
                                        Guardar
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    :class="portalButton.secondary"
                                    @click="resendAccess(member)"
                                >
                                    <KeyRound
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Reenviar acceso
                                </button>
                                <button
                                    v-if="confirmingId !== member.id"
                                    type="button"
                                    :class="portalButton.secondary"
                                    @click="toggleActive(member)"
                                >
                                    <UserX
                                        v-if="member.active"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <RefreshCw
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{
                                        member.active ? 'Desactivar' : 'Activar'
                                    }}
                                </button>
                            </div>

                            <p
                                v-else
                                class="text-sm text-slate-600 lg:max-w-xs lg:text-right dark:text-slate-400"
                            >
                                No puedes cambiar tu propio rol ni desactivarte.
                            </p>
                        </div>

                        <div
                            v-if="confirmingId === member.id"
                            role="alert"
                            class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/60 dark:bg-amber-950/30"
                        >
                            <p
                                class="text-sm leading-6 text-slate-800 dark:text-slate-200"
                            >
                                {{ member.name }} ya no podrá entrar a LAUDA con
                                esta empresa. Puedes volver a activarlo cuando
                                quieras.
                            </p>
                            <div
                                class="flex flex-col gap-2 sm:shrink-0 sm:flex-row"
                            >
                                <button
                                    type="button"
                                    :class="portalButton.dark"
                                    @click="toggleActive(member)"
                                >
                                    Sí, desactivar
                                </button>
                                <button
                                    type="button"
                                    :class="portalButton.link"
                                    @click="confirmingId = null"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </li>

                    <li
                        v-if="!filteredMembers.length"
                        class="px-4 py-10 text-center text-sm text-slate-600"
                    >
                        No encontramos a nadie con esa búsqueda.
                    </li>
                </ul>
            </PortalSection>
        </PortalPage>
    </AppLayout>
</template>
