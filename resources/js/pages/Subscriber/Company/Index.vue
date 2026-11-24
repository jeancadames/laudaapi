<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

import CompanyProfileForm, {
    type CompanyProfilePayload,
} from '@/components/company/CompanyProfileForm.vue';
import PortalPage from '@/components/portal/PortalPage.vue';
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
    { title: 'Datos de la empresa', href: '/subscriber/company' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Datos de la empresa" />

        <PortalPage
            :eyebrow="props.profile.company_name || null"
            title="Datos de la empresa"
            description="Los datos que usamos en tus facturas, cotizaciones y aplicaciones."
        >
            <template #actions>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                >
                    <ShieldCheck
                        class="size-4 text-emerald-600"
                        aria-hidden="true"
                    />
                    Tu rol:
                    {{
                        props.viewer.role === 'owner'
                            ? 'Propietario'
                            : 'Administrador'
                    }}
                </span>
            </template>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="min-w-0">
                    <CompanyProfileForm
                        :initial="props.profile"
                        action="/subscriber/company"
                        submit-label="Guardar los datos"
                    />
                </div>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <h2
                            class="text-sm font-semibold text-slate-950 dark:text-white"
                        >
                            Tu cuenta
                        </h2>
                        <p
                            class="mt-1 text-sm font-medium break-words text-slate-800 dark:text-slate-200"
                        >
                            {{ props.account.name }}
                        </p>
                        <p
                            class="text-sm break-all text-slate-600 dark:text-slate-400"
                        >
                            {{ props.account.email }}
                        </p>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            Con este correo entras a LAUDA. Es distinto del
                            correo de facturación de la empresa.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                    >
                        <h2
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Tus datos no cambian solos
                        </h2>
                        <p class="mt-1">
                            Lo que guardes aquí se mantiene. Si la DGII o tus
                            aplicaciones tienen otros datos, te lo mostramos
                            aparte y no se reemplaza nada sin tu permiso.
                        </p>
                    </div>
                </aside>
            </div>
        </PortalPage>
    </AppLayout>
</template>
