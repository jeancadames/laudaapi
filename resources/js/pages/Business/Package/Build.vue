<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import BusinessPackageConfigurator from '@/components/BusinessPackageConfigurator.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createSelfServiceClient,
    isAbort,
    SelfServiceError,
    type FailureCode,
} from '@/lib/businessSelfService';
import type { Catalog, Configurator } from '@/types/businessSelfService';

const props = defineProps<{ configurator: Configurator }>();
const configurator = props.configurator;
const client = createSelfServiceClient(configurator);

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

const failureTitle: Partial<Record<FailureCode, string>> = {
    forbidden: 'No puedes contratar por esta empresa',
    catalog_unavailable: 'Todavía no hay precios publicados',
    session_expired: 'Tu sesión venció',
};
</script>

<template>
    <Head title="Armar mi paquete" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: configurator.home_url },
            { title: 'Armar mi paquete', href: '' },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-8 sm:py-8">
            <BusinessPackageConfigurator
                v-if="state.status === 'ready'"
                :catalog="state.catalog"
                :client="client"
                :storage-scope="configurator.company.id"
                :purchases-enabled="!!configurator.confirm_url"
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
        </div>
    </AppLayout>
</template>
