<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import { useToast } from '@/components/ui/toast/use-toast';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/portalFormat';
import servicesRoutes from '@/routes/subscriber/services';
import type { BreadcrumbItem } from '@/types';

const { toast } = useToast();

const DEFAULT_CATEGORY = 'api-facturacion-electronica';

const props = defineProps<{
    company: null | {
        id: number;
        name: string;
        currency: string;
        timezone: string;
    };
    activation_request: null | { id: number; status: string };
    subscription_status: string | null;
    can_select_services: boolean;
    can_select_services_reason?: string | null;

    category: {
        id: number;
        title: string;
        slug: string;
        short_description?: string | null;
        description?: string | null;
        icon?: string | null;
    };

    services: Array<{
        id: number;
        title: string;
        slug: string;
        short_description?: string | null;
        description?: string | null;
        badge?: string | null;

        type: string;
        billable: boolean;
        billing_model: string;
        currency: string;
        monthly_price: string | number | null;
        yearly_price: string | number | null;

        requested: boolean;
        request_status?: string | null;
        active: boolean;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Servicios', href: servicesRoutes.category(DEFAULT_CATEGORY).url },
    {
        title: props.category.title,
        href: servicesRoutes.category(props.category.slug).url,
    },
];

const canSelect = computed(() => !!props.can_select_services);

function toggleService(serviceId: number) {
    if (!canSelect.value) {
        toast({
            title: 'No disponible',
            description:
                props.can_select_services_reason ??
                'No puedes solicitar servicios en este momento.',
            variant: 'destructive',
        });
        return;
    }

    if (!props.activation_request) {
        toast({
            title: 'Primero activa tu cuenta',
            description:
                'Cuando tu cuenta esté activa podrás pedir aplicaciones.',
            variant: 'destructive',
        });
        return;
    }

    router.post(
        '/subscriber/services/request',
        { service_id: serviceId },
        { preserveScroll: true },
    );
}

function priceLabel(s: any) {
    if (!s.billable) return 'Incluido';
    const m = s.monthly_price
        ? `${formatMoney(s.monthly_price, s.currency)} al mes`
        : null;
    const y = s.yearly_price
        ? `${formatMoney(s.yearly_price, s.currency)} al año`
        : null;
    return [m, y].filter(Boolean).join(' · ') || 'Precio a consultar';
}

/**
 * ✅ NORMALIZACIÓN para catálogo:
 * - cancelled => se considera "Disponible" (no lo pintes rojo)
 * - active => Activo
 * - pending/pending_payment => solicitado/pendiente
 */
function statusLabel(s: any) {
    if (s.active) return 'Activo';

    const st = String(s.request_status ?? '').toLowerCase();

    // ✅ si está cancelled, para el catálogo es como "no solicitado"
    if (st === 'cancelled') return 'Disponible';

    if (!s.requested) return 'Disponible';
    if (st === 'pending_payment') return 'Pendiente de pago';
    if (st === 'pending') return 'Solicitado';

    return 'Solicitado';
}

const headerDescription = computed(() => {
    return (
        props.category.short_description?.trim() ||
        props.category.description?.trim() ||
        'Servicios disponibles en esta categoría'
    );
});

/** Verde = activa · ámbar = solicitada o por pagar · gris = disponible. */
function statusTone(s: any) {
    const st = statusLabel(s);
    if (st === 'Activo')
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (st === 'Disponible')
        return 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300';
    return 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
}

/**
 * ✅ Regla de UI para el botón:
 * - active => deshabilitado y dice "Activo"
 * - si no puede seleccionar => deshabilitado
 * - si request_status es cancelled => debe permitir "Solicitar" (no "Quitar")
 */
function isCancelledRequest(s: any) {
    return String(s.request_status ?? '').toLowerCase() === 'cancelled';
}

function canToggle(s: any) {
    if (!props.activation_request) return false;
    if (!canSelect.value) return false;
    if (s.active) return false;
    return true;
}

function buttonLabel(s: any) {
    if (s.active) return 'Activo';
    if (!canSelect.value) return 'Requiere suscripción';

    // ✅ cancelled => vuelve a ser "Solicitar"
    if (isCancelledRequest(s)) return 'Solicitar';

    // requested (no cancelled) => Quitar
    if (s.requested) return 'Quitar';

    return 'Solicitar';
}

// búsqueda opcional
const q = ref('');
const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return props.services;
    return props.services.filter((s) => {
        const t = (s.title ?? '').toLowerCase();
        const sd = (s.short_description ?? '').toLowerCase();
        const d = (s.description ?? '').toLowerCase();
        return t.includes(term) || sd.includes(term) || d.includes(term);
    });
});
</script>

<template>
    <Head :title="props.category.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company?.name ?? null"
            :title="props.category.title"
            :description="headerDescription"
        >
            <p
                v-if="!props.activation_request"
                role="status"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
            >
                Para pedir aplicaciones, primero activa tu cuenta.
            </p>
            <p
                v-else-if="!canSelect"
                role="status"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
            >
                {{
                    props.can_select_services_reason ??
                    'Por ahora no puedes pedir aplicaciones desde aquí.'
                }}
            </p>

            <label v-if="props.services.length > 4" class="block max-w-md">
                <span class="sr-only">Buscar aplicación</span>
                <input
                    v-model="q"
                    type="search"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                    placeholder="Buscar aplicación"
                />
            </label>

            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="s in filtered"
                    :key="s.id"
                    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h2
                            class="font-semibold break-words text-slate-950 dark:text-white"
                        >
                            {{ s.title }}
                        </h2>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="statusTone(s)"
                            >{{ statusLabel(s) }}</span
                        >
                    </div>
                    <p
                        v-if="s.short_description || s.description"
                        class="line-clamp-3 text-sm text-slate-600 dark:text-slate-400"
                    >
                        {{ s.short_description || s.description }}
                    </p>
                    <p
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        {{ priceLabel(s) }}
                    </p>
                    <p
                        v-if="s.badge"
                        class="text-xs text-slate-600 dark:text-slate-400"
                    >
                        {{ s.badge }}
                    </p>
                    <button
                        type="button"
                        class="mt-auto w-full"
                        :class="
                            canToggle(s) &&
                            !(s.requested && !isCancelledRequest(s))
                                ? portalButton.primary
                                : portalButton.secondary
                        "
                        :disabled="!canToggle(s)"
                        @click="toggleService(s.id)"
                    >
                        {{ buttonLabel(s) }}
                    </button>
                </li>
            </ul>

            <p
                v-if="filtered.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
            >
                No hay aplicaciones que coincidan con tu búsqueda.
            </p>
        </PortalPage>
    </AppLayout>
</template>
