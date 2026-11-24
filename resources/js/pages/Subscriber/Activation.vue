<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import { useDateFormatter } from '@/composables/useDateFormatter';
import AppLayout from '@/layouts/AppLayout.vue';

const { formatDate } = useDateFormatter();

const props = defineProps<{
    activation: any | null;
    state: {
        has_activation_request: boolean;
        has_subscriber: boolean;
        has_company: boolean;
        has_subscription: boolean;
    };
    subscriber: any | null;
    company: any | null;
    subscription: any | null;
}>();

const status = computed(() => String(props.activation?.status ?? ''));
const canActivate = computed(() => status.value === 'accepted');

const statusMessage = computed(() => {
    if (!props.activation)
        return 'No encontramos una solicitud de activación para tu cuenta.';
    if (status.value === 'accepted')
        return 'Tu solicitud fue aceptada. Ya puedes empezar tu período de prueba.';
    if (status.value === 'trialing')
        return 'Tu período de prueba ya está en curso. No necesitas hacer nada más.';
    if (status.value === 'converted')
        return 'Tu cuenta ya está activa. No necesitas hacer nada más.';
    return 'Tu solicitud todavía está en revisión. Te avisaremos cuando puedas continuar.';
});

const buttonText = computed(() => {
    if (!props.activation) return 'No disponible';
    if (canActivate.value) return 'Empezar mi prueba';
    if (['trialing', 'converted'].includes(status.value))
        return 'Ya está activa';
    return 'No disponible';
});

function activate() {
    if (!canActivate.value) return;
    router.post(
        '/subscriber/activation/activate',
        {},
        { preserveScroll: true },
    );
}

const done = computed(() => ['trialing', 'converted'].includes(status.value));
const steps = computed(() => [
    { label: 'Solicitud recibida', ok: props.state.has_activation_request },
    {
        label: 'Cuenta y empresa creadas',
        ok: props.state.has_subscriber && props.state.has_company,
    },
    { label: 'Suscripción activa', ok: props.state.has_subscription },
]);
</script>

<template>
    <Head title="Activa tu cuenta" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: '/app' },
            { title: 'Activa tu cuenta', href: '/subscriber/activation' },
        ]"
    >
        <PortalPage
            :eyebrow="props.company?.name ?? props.subscriber?.name ?? null"
            title="Activa tu cuenta"
            :description="statusMessage"
            width="narrow"
        >
            <ol
                class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
            >
                <li
                    v-for="step in steps"
                    :key="step.label"
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <span
                        class="text-sm font-medium text-slate-950 dark:text-white"
                        >{{ step.label }}</span
                    >
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            step.ok
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300'
                        "
                        >{{ step.ok ? 'Listo' : 'Pendiente' }}</span
                    >
                </li>
            </ol>

            <div
                v-if="props.activation?.trial_ends_at_human"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
            >
                Tu período de prueba termina el
                <strong>{{
                    formatDate(props.activation.trial_ends_at_human)
                }}</strong>
                <template v-if="props.activation.trial_days_left">
                    ({{ props.activation.trial_days_left }}
                    {{
                        props.activation.trial_days_left === 1 ? 'día' : 'días'
                    }}).</template
                >
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    v-if="!done"
                    type="button"
                    :disabled="!canActivate"
                    :class="portalButton.primary"
                    @click="activate"
                >
                    {{ buttonText }}
                </button>
                <a v-else href="/app" :class="portalButton.dark"
                    >Ir a mis aplicaciones</a
                >
            </div>
        </PortalPage>
    </AppLayout>
</template>
