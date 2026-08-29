<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Sparkles,
} from 'lucide-vue-next';

import CompanyProfileForm, {
    type CompanyProfilePayload,
} from '@/components/company/CompanyProfileForm.vue';
import AuthBase from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    account: {
        name: string;
        email: string;
    };
    profile: CompanyProfilePayload;
}>();
</script>

<template>
    <AuthBase
        wide
        title="Configura tu empresa"
        description="Una sola identidad empresarial para todo el ecosistema LAUDAAPI."
    >
        <Head title="Configurar empresa" />

        <div class="space-y-5">
            <div
                class="rounded-2xl border border-red-100 bg-red-50/60 p-4 dark:border-red-950 dark:bg-red-950/20"
            >
                <div class="flex items-start gap-3">
                    <Sparkles class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                    <div>
                        <p class="font-black text-slate-950 dark:text-white">
                            Perfil empresarial central
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Completa estos datos una vez. Después podrás actualizarlos desde tu Perfil de Empresa en el App Hub.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                >
                    <Building2 class="mb-2 h-5 w-5 text-red-600" />
                    <p class="text-sm font-bold">1. Empresa</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Identidad, contacto y ubicación.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                >
                    <CheckCircle2 class="mb-2 h-5 w-5 text-red-600" />
                    <p class="text-sm font-bold">2. App Hub</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Entra a tu centro operativo.
                    </p>
                </div>
            </div>

            <CompanyProfileForm
                :initial="props.profile"
                :account="props.account"
                action="/onboarding"
                submit-label="Crear mi empresa"
                onboarding
            />
        </div>
    </AuthBase>
</template>
