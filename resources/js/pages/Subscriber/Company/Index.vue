<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    ShieldCheck,
} from 'lucide-vue-next';

import CompanyProfileForm, {
    type CompanyProfilePayload,
} from '@/components/company/CompanyProfileForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    profile: CompanyProfilePayload;
    viewer: {
        role: string | null;
        is_admin: boolean;
    };
    account: {
        name: string;
        email: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Control Panel', href: '/app/control' },
    { title: 'Perfil de Empresa', href: '/subscriber/company' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Perfil de Empresa" />

        <div class="min-h-full bg-slate-50/60 py-6 dark:bg-slate-950/40">
            <div
                class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 sm:px-6 lg:px-8"
            >
                <header
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <div
                                class="mb-3 inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300"
                            >
                                <Building2 class="h-3.5 w-3.5" />
                                Identidad central
                            </div>

                            <h1
                                class="text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                Perfil de Empresa
                            </h1>

                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-slate-500"
                            >
                                La información declarada aquí es la referencia empresarial del App Hub.
                            </p>
                        </div>

                        <div
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300"
                        >
                            <ShieldCheck class="h-4 w-4 text-emerald-600" />
                            {{ props.viewer.role === 'owner' ? 'Owner' : 'Administrador' }}
                        </div>
                    </div>
                </header>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                >
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                        Cuenta
                    </p>
                    <p class="mt-1 font-bold text-slate-950 dark:text-white">
                        {{ props.account.name }}
                    </p>
                    <p class="text-sm text-slate-500">
                        {{ props.account.email }}
                    </p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        El correo de acceso de tu usuario es independiente del correo empresarial.
                    </p>
                </div>

                <CompanyProfileForm
                    :initial="props.profile"
                    action="/subscriber/company"
                    submit-label="Guardar Perfil de Empresa"
                />

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                >
                    <strong class="text-slate-950 dark:text-white">
                        Cumplimiento y DGII se mantienen separados.
                    </strong>
                    El perfil empresarial conserva lo declarado por la empresa. Regímenes, obligaciones, estados DGII y datos verificados externamente se administran o comparan desde su flujo correspondiente y no reemplazan estos valores automáticamente.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
