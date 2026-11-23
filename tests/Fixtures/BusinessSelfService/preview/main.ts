/**
 * Vista previa del autoservicio Business sin Laravel.
 *
 * Monta las páginas Inertia reales (con AppLayout) y responde con las muestras
 * del contrato v1 a través del doble de pruebas. Cada respuesta queda en
 * `window.__selfService` para que el recorrido compare lo que ve el cliente con
 * lo que devolvió el servidor.
 *
 *   ?pantalla=inicio|configurador   &escenario=<clave de SCENARIOS>
 */
import '../../../../resources/css/app.css';

import * as samples from '@self-service-contract';
import { createInertiaApp, router } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createMockBackend, SCENARIOS } from '../mock-backend.mjs';

const query = new URLSearchParams(window.location.search);
const scenario = query.get('escenario') ?? 'exito';
if (!(scenario in SCENARIOS))
    throw new Error(`Escenario desconocido: ${scenario}`);
const screen = query.get('pantalla') ?? 'configurador';
const here = window.location.pathname;
const link = (pantalla: string) =>
    `${here}?pantalla=${pantalla}&escenario=${scenario}`;

const backend = createMockBackend({
    samples,
    scenario,
    delay: 350,
    base: '/vista-previa/v1',
});
const log: { path: string; status: number; body: unknown }[] = [];
(window as unknown as { __selfService: typeof log }).__selfService = log;
const realFetch = window.fetch.bind(window);
window.fetch = async (input, init) => {
    const url = String(input);
    if (!url.startsWith(backend.base)) return realFetch(input, init);
    const response = await backend.fetch(url, init);
    log.push({
        path: url,
        status: response.status,
        body: await response.clone().json(),
    });
    return response;
};

// Sin servidor Inertia, los enlaces internos se resuelven con una carga normal.
router.on('invalid', (event) => {
    event.preventDefault();
    window.location.assign(event.detail.response.request.responseURL);
});

const allowed = scenario !== 'sin-permiso';
const tenantAccess = {
    mode: allowed ? 'subscriber.admin' : 'subscriber.user',
    pivot_role: allowed ? 'owner' : 'member',
    tenant_admin: allowed,
    can_browse_store: allowed,
    can_manage_billing: allowed,
    can_launch_apps: true,
    can_manage_company: allowed,
};
const company = {
    id: 77,
    name: samples.catalog.company_name,
    subscriber_id: 12,
};
const shared = {
    name: 'LaudaAPI',
    auth: {
        user: {
            id: 1,
            name: 'Ana Rivera',
            email: 'ana@example.test',
            role: 'subscriber',
            subscriber_id: 12,
            tenant_access: tenantAccess,
        },
    },
    activeCompany: {
        ...company,
        slug: 'empresa-de-prueba',
        ws_subdomain: null,
        tax_id: null,
    },
    sidebarOpen: true,
    flash: {},
    lauda360: null,
    nav: { erp: [] },
    menu: [],
    quote: { message: '', author: '' },
};

const pages: Record<
    string,
    { component: string; props: Record<string, unknown> }
> = {
    configurador: {
        component: 'Business/Package/Build',
        props: {
            configurator: backend.configurator({ homeUrl: link('inicio') }),
        },
    },
    inicio: {
        component: 'App/Home',
        props: {
            company,
            groups: [],
            tenant_access: tenantAccess,
            // Laravel solo la envía a dueños y administradores.
            ...(allowed
                ? {
                      business_self_service: {
                          configurator_url: link('configurador'),
                          pending_purchase: null,
                      },
                  }
                : {}),
        },
    },
};

const target = pages[screen];
if (!target) throw new Error(`Pantalla desconocida: ${screen}`);
const modules = import.meta.glob<DefineComponent>(
    '../../../../resources/js/pages/{App/Home,Business/Package/Build}.vue',
);
void createInertiaApp({
    page: {
        component: target.component,
        props: { ...shared, ...target.props, errors: {} },
        url: window.location.pathname + window.location.search,
        version: 'preview',
        flash: {},
    } as never,
    resolve: async (name) =>
        (await modules[`../../../../resources/js/pages/${name}.vue`]()).default,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
