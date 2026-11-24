<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';
import BusinessPackageConfigurator from '@/components/BusinessPackageConfigurator.vue';
import BusinessPackagePurchase from '@/components/BusinessPackagePurchase.vue';
import PortalPage from '@/components/portal/PortalPage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createSelfServiceClient,
    isAbort,
    isPurchaseId,
    SelfServiceError,
    type FailureCode,
} from '@/lib/businessSelfService';
import type {
    Catalog,
    Configurator,
    Product,
    Purchase,
} from '@/types/businessSelfService';

const props = defineProps<{ configurator: Configurator }>();
const configurator = props.configurator;
const client = createSelfServiceClient(configurator);

// Compra que se muestra en lugar del configurador. Orden: la de la URL
// (?compra=, al volver del pago o recargar), la recién confirmada y la que el
// servidor indica como pendiente, aunque el catálogo ya no esté disponible.
const page = usePage();
const currentUrl = () => new URL(page.url, 'http://lauda.local');
const urlPurchaseId = computed(() => {
    const value = currentUrl().searchParams.get('compra');
    return isPurchaseId(value) ? value : null;
});
// «Agregar a mi paquete» desde el Panel de control: la aplicación llega marcada.
const requestedApps = currentUrl().searchParams.getAll('agregar');
const sessionPurchaseId = ref<string | null>(null);
const dismissedPurchaseId = ref<string | null>(null);
const resumeId =
    configurator.resume_purchase_id ??
    configurator.catalog?.resume_purchase_id ??
    null;
const purchaseId = computed(() => {
    const id =
        urlPurchaseId.value ??
        sessionPurchaseId.value ??
        (isPurchaseId(resumeId) ? resumeId : null);
    return id && id !== dismissedPurchaseId.value ? id : null;
});
const createdPurchase = shallowRef<Purchase | null>(null);

function showPurchase(id: string, created: Purchase | null = null) {
    createdPurchase.value = created;
    sessionPurchaseId.value = id;
    dismissedPurchaseId.value = null;
    // Reemplaza el paso del resumen: Atrás ya no vuelve a un formulario confirmado.
    const url = currentUrl();
    url.searchParams.delete('paso');
    url.searchParams.set('compra', id);
    router.replace({ url: url.pathname + url.search, preserveState: true });
}

/** La compra no existe para esta empresa: volver al configurador. */
function restart() {
    dismissedPurchaseId.value = purchaseId.value;
    sessionPurchaseId.value = null;
    createdPurchase.value = null;
    const url = currentUrl();
    url.searchParams.delete('compra');
    router.replace({ url: url.pathname + url.search, preserveState: true });
}

type State =
    | { status: 'ready'; catalog: Catalog }
    | { status: 'loading' }
    | { status: 'failed'; code: FailureCode; message: string };

// La página llega con el catálogo vigente; `catalog_url` solo se usa para reintentar.
const state = ref<State>(
    configurator.availability === 'available' &&
        configurator.catalog?.products.length
        ? { status: 'ready', catalog: configurator.catalog }
        : {
              status: 'failed',
              code: 'catalog_unavailable',
              message:
                  'Estamos preparando los paquetes para contratar en línea. Vuelve a intentarlo más tarde.',
          },
);

let controller: AbortController | null = null;
async function reload() {
    controller?.abort();
    controller = new AbortController();
    state.value = { status: 'loading' };
    try {
        const catalog = await client.catalog(controller.signal);
        state.value = catalog.products.length
            ? { status: 'ready', catalog }
            : {
                  status: 'failed',
                  code: 'catalog_unavailable',
                  message:
                      'Todavía no hay aplicaciones para contratar en línea.',
              };
    } catch (error) {
        if (isAbort(error)) return;
        state.value =
            error instanceof SelfServiceError
                ? { status: 'failed', code: error.code, message: error.message }
                : {
                      status: 'failed',
                      code: 'unknown',
                      message:
                          'No pudimos cargar las opciones. Vuelve a intentarlo.',
                  };
    }
}
onBeforeUnmount(() => controller?.abort());
const reloadPage = () => window.location.reload();

const productNames = computed(
    () =>
        Object.fromEntries(
            (state.value.status === 'ready'
                ? state.value.catalog.products
                : (configurator.catalog?.products ?? [])
            ).map((product) => [product.code, product.name]),
        ) as Partial<Record<Product, string>>,
);

const failureTitle: Partial<Record<FailureCode, string>> = {
    forbidden: 'No puedes contratar por esta empresa',
    catalog_unavailable: 'Todavía no hay precios publicados',
    session_expired: 'Tu sesión venció',
};
</script>

<template>
    <Head :title="purchaseId ? 'Tu compra' : 'Armar mi paquete'" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: configurator.home_url },
            { title: purchaseId ? 'Tu compra' : 'Armar mi paquete', href: '' },
        ]"
    >
        <PortalPage>
            <BusinessPackagePurchase
                v-if="purchaseId"
                :key="purchaseId"
                :client="client"
                :purchase-id="purchaseId"
                :initial="
                    createdPurchase?.purchase_id === purchaseId
                        ? createdPurchase
                        : null
                "
                :names="productNames"
                :company-name="configurator.company.name"
                :home-url="configurator.home_url"
                :can-restart="state.status === 'ready'"
                @restart="restart"
            />

            <BusinessPackageConfigurator
                v-else-if="state.status === 'ready'"
                :catalog="state.catalog"
                :client="client"
                :storage-scope="configurator.company.id"
                :purchases-enabled="!!configurator.confirm_url"
                :add="requestedApps"
                @purchased="
                    (created) => showPurchase(created.purchase_id, created)
                "
                @resume="(id) => showPurchase(id)"
            />

            <!-- Sin catálogo, sin permiso o sin conexión: orientación y salida -->
            <div
                v-else
                class="max-w-xl space-y-4"
                :aria-busy="state.status === 'loading'"
            >
                <p class="text-sm font-medium break-words">
                    {{ configurator.company.name }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{
                        state.status === 'loading'
                            ? 'Buscando las opciones…'
                            : (failureTitle[state.code] ??
                              'No pudimos cargar las opciones')
                    }}
                </h1>
                <p
                    v-if="state.status === 'failed'"
                    class="leading-7 text-muted-foreground"
                >
                    {{ state.message }}
                    <template v-if="state.code === 'forbidden'">
                        Pide al propietario o a un administrador que arme el
                        paquete desde su cuenta.</template
                    >
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button
                        v-if="
                            state.status === 'failed' &&
                            state.code === 'session_expired'
                        "
                        size="lg"
                        class="h-12"
                        @click="reloadPage"
                        >Recargar la página</Button
                    >
                    <Button
                        v-else-if="
                            state.status === 'loading' ||
                            state.code !== 'forbidden'
                        "
                        size="lg"
                        class="h-12"
                        :disabled="state.status === 'loading'"
                        @click="reload"
                        >Volver a comprobar</Button
                    >
                    <Button variant="outline" size="lg" class="h-12" as-child>
                        <Link :href="configurator.home_url"
                            >Volver a Inicio</Link
                        >
                    </Button>
                </div>
            </div>
        </PortalPage>
    </AppLayout>
</template>
