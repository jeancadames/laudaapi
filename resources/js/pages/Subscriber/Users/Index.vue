<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    KeyRound,
    Mail,
    RefreshCw,
    ShieldCheck,
    UserCheck,
    UserPlus,
    Users,
    UserX,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RoleValue = 'owner' | 'admin' | 'member' | 'billing';

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
    { title: 'Control Panel', href: '/app' },
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
    Object.fromEntries(
        props.members.map((member) => [member.id, member.role]),
    ),
);

const query = ref('');

const filteredMembers = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) return props.members;

    return props.members.filter((member) =>
        [member.name, member.email, member.role]
            .some((value) => value.toLowerCase().includes(term)),
    );
});

const roleLabel = (role: RoleValue) =>
    props.role_options.find((option) => option.value === role)?.label ?? role;

const roleDescription = (role: RoleValue) =>
    props.role_options.find((option) => option.value === role)?.description ?? '';

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

const toggleActive = (member: Member) => {
    const action = member.active ? 'desactivar' : 'activar';

    if (!window.confirm(`¿Seguro que deseas ${action} a ${member.name}?`)) {
        return;
    }

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
    <Head title="Usuarios · LAUDAAPI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-[#FAFAF8] dark:bg-background">
            <div class="mx-auto w-full max-w-7xl space-y-7 p-4 sm:p-6 lg:p-8">
                <header class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950">
                    <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/40 dark:text-red-300">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Control Panel
                            </div>

                            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Usuarios
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400">
                                Administra quién puede acceder al ecosistema central de
                                {{ props.subscriber.name }}. Los permisos operativos se
                                configuran dentro de cada solución.
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center dark:bg-slate-900">
                                <p class="text-xl font-black text-slate-950 dark:text-white">
                                    {{ props.summary.total }}
                                </p>
                                <p class="text-[11px] text-slate-500">Total</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center dark:bg-slate-900">
                                <p class="text-xl font-black text-slate-950 dark:text-white">
                                    {{ props.summary.active }}
                                </p>
                                <p class="text-[11px] text-slate-500">Activos</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center dark:bg-slate-900">
                                <p class="text-xl font-black text-slate-950 dark:text-white">
                                    {{ props.summary.admins }}
                                </p>
                                <p class="text-[11px] text-slate-500">Admins</p>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
                    <form
                        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        @submit.prevent="submit"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                <UserPlus class="h-5 w-5 text-slate-700 dark:text-slate-200" />
                            </div>
                            <div>
                                <h2 class="font-bold text-slate-950 dark:text-white">
                                    Agregar usuario
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Alta central segura para este tenant.
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-4">
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Nombre
                                </span>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    autocomplete="name"
                                    class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                    placeholder="Nombre del usuario"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Correo
                                </span>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                    placeholder="usuario@empresa.com"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.email }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Rol central
                                </span>
                                <select
                                    v-model="form.role"
                                    class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <option
                                        v-for="option in props.role_options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                    {{ roleDescription(form.role) }}
                                </p>
                                <p v-if="form.errors.role" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.role }}
                                </p>
                            </label>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                <UserPlus class="h-4 w-4" />
                                {{ form.processing ? 'Agregando…' : 'Agregar usuario' }}
                            </button>
                        </div>

                        <div class="mt-5 rounded-xl bg-slate-50 p-4 text-xs leading-5 text-slate-500 dark:bg-slate-900">
                            Si el correo es nuevo, LAUDAAPI crea la cuenta con una
                            contraseña aleatoria desconocida y envía un enlace de
                            recuperación para que la persona defina su propia contraseña.
                        </div>
                    </form>

                    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                    <Users class="h-5 w-5 text-slate-700 dark:text-slate-200" />
                                </div>
                                <div>
                                    <h2 class="font-bold text-slate-950 dark:text-white">
                                        Miembros del tenant
                                    </h2>
                                    <p class="text-xs text-slate-500">
                                        {{ props.summary.owners }} owner activo
                                        <template v-if="props.summary.owners !== 1">s</template>
                                    </p>
                                </div>
                            </div>

                            <input
                                v-model="query"
                                type="search"
                                placeholder="Buscar usuario"
                                class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-slate-400 sm:w-56 dark:border-slate-800 dark:bg-slate-950"
                            />
                        </div>

                        <div class="mt-5 space-y-3">
                            <article
                                v-for="member in filteredMembers"
                                :key="member.id"
                                class="rounded-2xl border border-slate-100 p-4 dark:border-slate-800"
                            >
                                <div class="flex flex-col gap-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="truncate font-bold text-slate-950 dark:text-white">
                                                    {{ member.name }}
                                                </h3>

                                                <span
                                                    v-if="member.id === props.current_user_id"
                                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                                >
                                                    Tú
                                                </span>

                                                <span
                                                    :class="[
                                                        'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                                        member.active
                                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                            : 'bg-slate-100 text-slate-500 dark:bg-slate-900',
                                                    ]"
                                                >
                                                    {{ member.active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>

                                            <div class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                                <Mail class="h-3.5 w-3.5" />
                                                <span class="truncate">{{ member.email }}</span>
                                            </div>

                                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                                {{ roleLabel(member.role) }} ·
                                                {{ roleDescription(member.role) }}
                                            </p>
                                        </div>

                                        <div
                                            :class="[
                                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                                                member.active
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40'
                                                    : 'bg-slate-100 text-slate-400 dark:bg-slate-900',
                                            ]"
                                        >
                                            <UserCheck v-if="member.active" class="h-4 w-4" />
                                            <UserX v-else class="h-4 w-4" />
                                        </div>
                                    </div>

                                    <div
                                        v-if="member.id !== props.current_user_id"
                                        class="grid gap-2 sm:grid-cols-[1fr_auto_auto]"
                                    >
                                        <div class="flex gap-2">
                                            <select
                                                v-model="roleDrafts[member.id]"
                                                class="h-9 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs outline-none dark:border-slate-800 dark:bg-slate-950"
                                            >
                                                <option
                                                    v-for="option in props.role_options"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>

                                            <button
                                                type="button"
                                                class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-semibold transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                                                @click="updateRole(member)"
                                            >
                                                Guardar rol
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 text-xs font-semibold transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                                            @click="resendAccess(member)"
                                        >
                                            <KeyRound class="h-3.5 w-3.5" />
                                            Reenviar acceso
                                        </button>

                                        <button
                                            type="button"
                                            :class="[
                                                'inline-flex h-9 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-semibold transition',
                                                member.active
                                                    ? 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/30 dark:text-red-300'
                                                    : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300',
                                            ]"
                                            @click="toggleActive(member)"
                                        >
                                            <UserX v-if="member.active" class="h-3.5 w-3.5" />
                                            <RefreshCw v-else class="h-3.5 w-3.5" />
                                            {{ member.active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-900"
                                    >
                                        Tu propia membresía se protege para evitar que
                                        pierdas accidentalmente el acceso administrativo.
                                    </div>
                                </div>
                            </article>

                            <div
                                v-if="!filteredMembers.length"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-800"
                            >
                                No encontramos usuarios con ese filtro.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
