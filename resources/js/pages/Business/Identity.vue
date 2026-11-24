<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppTile from '@/components/portal/AppTile.vue';
import { portalButton } from '@/components/portal/buttons';
import IdentityRevokeConfirm from '@/components/portal/IdentityRevokeConfirm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    identityStorageKey,
    takeBusinessIdentityHandoff,
    type IdentityTransfer,
} from '@/lib/businessIdentityHandoff';

type Account = { id: number; name: string; email: string; verified: boolean };
type Solution = {
    key: string;
    name: string;
    configured: boolean;
    pending: boolean;
    ready: boolean;
    linked: boolean;
    active: boolean;
    member_active: boolean;
    local_company_id: number | null;
    can_link: boolean;
    link_company: boolean;
    start_url: string;
    revoke_url: string;
    launch_url: string | null;
};
type Company = {
    id: number;
    name: string;
    can_manage: boolean;
    services?: Solution[];
    linked?: boolean;
    member_active?: boolean;
    central_company_id?: number;
};
type Identity = {
    side: 'central' | 'receiver';
    service: string | null;
    service_name: string;
    enabled: boolean;
    account: Account | null;
    companies: Company[];
    endpoints: Record<string, string | Record<string, string>>;
    home_url: string;
    hub_url: string | null;
};
type Transfer = IdentityTransfer;
type Summary = {
    central_company_name: string;
    central_company_id: number;
    central_user_name: string;
    central_user_email: string | null;
    central_user_id: number;
    local_company_name: string | null;
    local_company_id: number | null;
    local_user_name: string | null;
    local_user_email: string | null;
    local_user_id: number | null;
    service_name: string;
    expires_at: number;
    companies?: Company[];
    cancel_url?: string;
};
const props = defineProps<{ identity: Identity }>();
const storageKey = identityStorageKey;
const transfer = ref<Transfer | null>(null);
const summary = ref<Summary | null>(null);
const selectedCompany = ref('');
const localCompany = ref('');
const busy = ref(false);
const error = ref('');
const notice = ref('');
const recovered = ref(false);
const clock = ref(Date.now());
const revoke = ref<{
    url: string;
    company: Company;
    whole: boolean;
    service: string;
    serviceKey: string;
} | null>(null);
const selected = computed(() =>
    props.identity.companies.find(
        (c) => String(c.id) === selectedCompany.value,
    ),
);
// La confirmación pertenece a una empresa y una aplicación concretas: al cambiar
// de empresa se descarta para no actuar sobre la empresa anterior.
watch(selectedCompany, () => {
    revoke.value = null;
});
function revokeTargets(serviceKey: string): boolean {
    return (
        !!revoke.value &&
        !!selected.value &&
        revoke.value.serviceKey === serviceKey &&
        revoke.value.company.id === selected.value.id
    );
}
const chosenLocal = computed(() =>
    summary.value?.companies?.find((c) => String(c.id) === localCompany.value),
);
const remaining = computed(() =>
    Math.max(
        0,
        Math.ceil(
            ((summary.value?.expires_at ?? transfer.value?.deadline ?? 0) *
                1000 -
                clock.value) /
                1000,
        ),
    ),
);
const expired = computed(
    () => transfer.value !== null && remaining.value === 0,
);
const minuteLabel = computed(
    () =>
        `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`,
);
const endpoint = (name: string): string => {
    const routes =
        props.identity.side === 'central'
            ? props.identity.endpoints[transfer.value?.service ?? '']
            : props.identity.endpoints;
    const url = typeof routes === 'object' ? routes[name] : null;
    if (typeof url !== 'string')
        throw new Error(
            'Esta solicitud no corresponde a la aplicación. Vuelve a LAUDA para iniciar otra.',
        );
    return url;
};
function persist(value: Transfer | null) {
    transfer.value = value;
    try {
        if (value) sessionStorage.setItem(storageKey, JSON.stringify(value));
        else sessionStorage.removeItem(storageKey);
    } catch {
        /* The flow still works without tab storage; authentication may require a new request. */
    }
}
function clear() {
    persist(null);
    summary.value = null;
    localCompany.value = '';
    error.value = '';
}
async function json(
    url: string,
    data: Record<string, unknown>,
    method = 'POST',
) {
    const xsrf = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
        },
        body: JSON.stringify(data),
        redirect: 'error',
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 419)
            throw new Error(
                'La sesión venció. Recarga la página e inténtalo de nuevo.',
            );
        if (response.status === 401)
            throw new Error(
                'Inicia sesión y vuelve a esta página para continuar.',
            );
        if (response.status === 429)
            throw new Error(
                'Has realizado varios intentos. Espera un minuto y vuelve a intentarlo.',
            );
        throw new Error(
            response.status < 500 && body.message
                ? body.message
                : 'No se pudo completar la operación. Inténtalo de nuevo.',
        );
    }
    return body;
}
async function run(work: () => Promise<void>) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        await work();
    } catch (e) {
        error.value =
            e instanceof Error && !(e instanceof TypeError)
                ? e.message
                : 'No se recibió la respuesta. Comprueba tu conexión y vuelve a intentarlo.';
    } finally {
        busy.value = false;
    }
}
function continueTo(url: string) {
    const target = new URL(url, window.location.origin);
    if (
        target.protocol !== 'https:' ||
        target.pathname !== '/business/identity'
    )
        throw new Error('El destino de la conexión no está disponible.');
    persist(null);
    window.location.assign(target.href);
}
async function prepare() {
    if (
        !transfer.value ||
        !props.identity.enabled ||
        !props.identity.account?.verified ||
        expired.value
    )
        return;
    await run(async () => {
        const flow = transfer.value!;
        if (
            props.identity.side === 'receiver' &&
            flow.service !== props.identity.service
        )
            throw new Error(
                'Esta solicitud pertenece a otra aplicación. Vuelve a LAUDA.',
            );
        if (
            (props.identity.side === 'receiver' && flow.stage === 'request') ||
            (props.identity.side === 'central' && flow.stage === 'proof')
        ) {
            summary.value = await json(endpoint('preview'), {
                [flow.stage === 'proof' ? 'proof_token' : 'request_token']:
                    flow.token,
            });
            persist({ ...flow, deadline: summary.value!.expires_at });
            if (summary.value!.companies?.length === 1)
                localCompany.value = String(summary.value!.companies[0].id);
        } else if (
            props.identity.side === 'receiver' &&
            flow.stage === 'activation'
        ) {
            const result = await json(endpoint('activate'), {
                activation_token: flow.token,
            });
            continueTo(result.continue_url);
        } else if (
            props.identity.side === 'central' &&
            flow.stage === 'ready'
        ) {
            const result = await json(endpoint('ready'), {
                ready_token: flow.token,
            });
            clear();
            selectedCompany.value = String(result.company_id);
            notice.value =
                'Conexión completada. Ya puedes abrir la aplicación desde LAUDA.';
            router.reload({ only: ['identity'] });
        } else if (
            props.identity.side === 'receiver' &&
            flow.stage === 'launch'
        ) {
            const result = await json(endpoint('launchPost'), {
                token: flow.token,
            });
            clear();
            window.location.replace(result.destination);
        } else
            throw new Error(
                'Esta solicitud no corresponde a esta pantalla. Vuelve a LAUDA para continuar.',
            );
    });
}
async function consent() {
    await run(async () => {
        if (!transfer.value || expired.value)
            throw new Error('La solicitud venció. Inicia otra desde LAUDA.');
        const central = props.identity.side === 'central';
        const result = await json(
            endpoint(central ? 'confirm' : 'prove'),
            central
                ? { proof_token: transfer.value.token }
                : {
                      request_token: transfer.value.token,
                      company_id: Number(localCompany.value),
                  },
        );
        continueTo(result.continue_url);
    });
}
async function cancel() {
    await run(async () => {
        if (transfer.value && !expired.value) {
            if (
                props.identity.side === 'receiver' &&
                transfer.value.stage === 'request'
            )
                await json(
                    endpoint('cancel'),
                    { request_token: transfer.value.token },
                    'DELETE',
                );
            if (props.identity.side === 'central' && summary.value?.cancel_url)
                await json(summary.value.cancel_url, {}, 'DELETE');
        }
        clear();
        notice.value =
            'Solicitud descartada. Puedes iniciar una nueva conexión desde LAUDA.';
    });
}
async function start(service: Solution, restart = false) {
    await run(async () => {
        const result = await json(service.start_url, {
            link_company: service.link_company,
            restart,
        });
        continueTo(result.continue_url);
    });
}
async function confirmRevoke() {
    await run(async () => {
        if (!revoke.value) return;
        if (
            props.identity.side === 'central' &&
            revoke.value.company.id !== selected.value?.id
        ) {
            revoke.value = null;
            return;
        }
        await json(
            revoke.value.url,
            {
                company_id: revoke.value.company.id,
                whole_company: revoke.value.whole,
            },
            'DELETE',
        );
        revoke.value = null;
        notice.value =
            'Acceso retirado. Puedes volver a conectar la aplicación cuando quieras.';
        router.reload({ only: ['identity'] });
    });
}
/**
 * En LAUDA, con sesión iniciada, la pantalla vive dentro del portal (menú
 * lateral incluido). Sin sesión, o en una aplicación receptora, conserva su
 * marco propio.
 */
const page = usePage();
const inPortal = computed(
    () =>
        props.identity.side === 'central' &&
        !!props.identity.account &&
        // Solo clientes: el servidor comparte entonces los datos del menú lateral.
        !!(page.props as { auth?: { user?: { tenant_access?: unknown } } }).auth
            ?.user?.tenant_access,
);
const breadcrumbs = [
    { title: 'Inicio', href: '/app' },
    { title: 'Conexiones Business', href: '/business/identity' },
];
type Tone = 'active' | 'pending' | 'neutral';
/** Estado de cada aplicación en palabras del cliente. */
function serviceStatus(service: Solution): { label: string; tone: Tone } {
    if (!service.configured)
        return { label: 'Aún no disponible', tone: 'neutral' };
    if (service.ready) return { label: 'Conectada', tone: 'active' };
    if (service.pending)
        return { label: 'Solicitud en curso', tone: 'pending' };
    if (service.member_active)
        return { label: 'Falta tu confirmación', tone: 'pending' };
    if (service.active)
        return { label: 'Falta conectar tu cuenta', tone: 'pending' };
    if (service.linked) return { label: 'Acceso retirado', tone: 'neutral' };
    return { label: 'Sin conectar', tone: 'neutral' };
}
function serviceHint(service: Solution): string {
    if (!service.configured)
        return 'Estará disponible para conectar muy pronto.';
    if (!service.can_link)
        return 'Pide al administrador de tu empresa que la conecte primero.';
    if (service.ready) return 'Ábrela desde LAUDA sin volver a iniciar sesión.';
    if (service.pending) return 'Continúa donde la dejaste o empieza de nuevo.';
    if (service.member_active || service.active)
        return 'Termina la conexión para poder abrirla desde LAUDA.';
    return 'Conéctala una vez y ábrela desde LAUDA con tu empresa.';
}
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    const requestedCompany = new URLSearchParams(window.location.search).get(
        'company',
    );
    selectedCompany.value = props.identity.companies.find(
        (c) => String(c.id) === requestedCompany,
    )
        ? requestedCompany!
        : props.identity.companies[0]
          ? String(props.identity.companies[0].id)
          : '';
    const incoming = takeBusinessIdentityHandoff();
    if (incoming) {
        persist(incoming.transfer);
        if (incoming.invalid)
            error.value =
                'El enlace de conexión no es válido. Inicia uno nuevo desde LAUDA.';
    } else {
        try {
            const saved = JSON.parse(
                sessionStorage.getItem(storageKey) ?? 'null',
            );
            if (
                saved &&
                typeof saved.token === 'string' &&
                saved.token.length <= 16384 &&
                typeof saved.deadline === 'number'
            ) {
                persist(saved);
                recovered.value = true;
            }
        } catch {
            clear();
        }
    }
    timer = setInterval(() => {
        clock.value = Date.now();
        if (expired.value) {
            try {
                sessionStorage.removeItem(storageKey);
            } catch {
                /* optional storage */
            }
        }
    }, 1000);
    void prepare();
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Head title="Conexiones Business"
        ><meta name="referrer" content="no-referrer"
    /></Head>
    <component
        :is="inPortal ? AppLayout : 'div'"
        v-bind="inPortal ? { breadcrumbs } : {}"
    >
        <div
            class="bi-shell text-foreground"
            :class="
                inPortal
                    ? 'min-h-full bg-[#F7F7F5] dark:bg-background'
                    : 'min-h-screen bg-background'
            "
        >
            <header v-if="!inPortal" class="border-b border-border">
                <div
                    class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-5 sm:px-10"
                >
                    <a
                        :href="identity.home_url"
                        class="min-w-0 truncate text-xl font-bold tracking-tight"
                        aria-label="Volver al inicio"
                        >LAUDA<span
                            class="ml-2 text-sm font-normal text-muted-foreground"
                            >{{
                                identity.side === 'central'
                                    ? 'Business'
                                    : identity.service_name
                            }}</span
                        ></a
                    >
                    <a
                        :href="identity.home_url"
                        class="bi-link shrink-0 text-sm"
                        >Volver al inicio <span aria-hidden="true">↗︎</span></a
                    >
                </div>
            </header>
            <main
                class="mx-auto w-full"
                :class="
                    inPortal
                        ? 'max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8'
                        : 'max-w-5xl px-4 py-10 sm:px-10 sm:py-16'
                "
                :aria-busy="busy"
            >
                <div class="mb-8 max-w-2xl">
                    <p
                        v-if="!inPortal"
                        class="mb-2 text-sm font-medium text-muted-foreground"
                    >
                        Cuentas y empresas
                    </p>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-950 sm:text-[28px] dark:text-white"
                    >
                        {{
                            transfer
                                ? 'Confirma la conexión'
                                : 'Conexiones Business'
                        }}
                    </h1>
                    <p
                        class="mt-1.5 text-[15px] leading-6 text-slate-600 dark:text-slate-400"
                    >
                        {{
                            transfer
                                ? 'Revisa que la empresa y la cuenta de cada lado sean las correctas antes de confirmar.'
                                : identity.side === 'central'
                                  ? 'Conecta cada aplicación con tu empresa una sola vez y ábrela desde LAUDA sin volver a iniciar sesión.'
                                  : 'Vincula tus cuentas para abrir las aplicaciones desde LAUDA con la empresa correcta.'
                        }}
                    </p>
                </div>
                <div
                    v-if="error"
                    role="alert"
                    class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-relaxed text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100"
                >
                    {{ error }}
                    <span class="mt-2 flex flex-wrap gap-x-4">
                        <button
                            v-if="
                                transfer &&
                                !expired &&
                                identity.account?.verified
                            "
                            type="button"
                            class="bi-link font-semibold"
                            :disabled="busy"
                            @click="prepare"
                        >
                            Reintentar</button
                        ><a href="/business/identity" class="bi-link"
                            >Recargar página</a
                        >
                    </span>
                </div>
                <p
                    v-if="notice"
                    role="status"
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100"
                >
                    {{ notice }}
                </p>
                <section v-if="!identity.enabled" class="bi-panel">
                    <h2 class="text-lg font-semibold">
                        Conexiones aún no disponibles
                    </h2>
                    <p class="mt-2 text-slate-600 dark:text-slate-400">
                        Muy pronto podrás conectar tus aplicaciones desde aquí.
                    </p>
                </section>
                <section v-else-if="!identity.account" class="bi-panel">
                    <h2 class="text-lg font-semibold">
                        Inicia sesión en {{ identity.service_name }}
                    </h2>
                    <p class="mt-2 mb-5 text-slate-600 dark:text-slate-400">
                        Usa la cuenta que quieres conectar. Guardaremos tu
                        solicitud en esta pestaña durante unos minutos.
                    </p>
                    <a href="/login" :class="portalButton.primary"
                        >Iniciar sesión</a
                    >
                </section>
                <section
                    v-else-if="!identity.account.verified"
                    class="bi-panel"
                >
                    <h2 class="text-lg font-semibold">
                        Verifica tu correo para continuar
                    </h2>
                    <p
                        class="mt-2 mb-5 break-words text-slate-600 dark:text-slate-400"
                    >
                        {{ identity.account.email }}
                    </p>
                    <a href="/email/verify" :class="portalButton.primary"
                        >Verificar correo</a
                    >
                </section>
                <template v-else>
                    <div
                        class="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-400"
                    >
                        <p class="min-w-0">
                            Conectado como
                            <strong
                                class="font-semibold text-slate-900 dark:text-white"
                                >{{ identity.account.name }}</strong
                            ><span class="block break-all sm:ml-2 sm:inline">{{
                                identity.account.email
                            }}</span>
                        </p>
                        <p
                            v-if="transfer && !expired"
                            class="rounded-full bg-amber-50 px-3 py-1 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                        >
                            Vence en
                            <span class="font-semibold tabular-nums">{{
                                minuteLabel
                            }}</span>
                        </p>
                    </div>
                    <section v-if="expired" class="bi-panel">
                        <h2 class="text-lg font-semibold">
                            La solicitud venció
                        </h2>
                        <p class="mt-2 mb-5 text-slate-600 dark:text-slate-400">
                            Por seguridad, cada solicitud dura unos minutos.
                            Empieza una nueva para continuar.
                        </p>
                        <button
                            :class="portalButton.dark"
                            type="button"
                            @click="clear"
                        >
                            Descartar solicitud
                        </button>
                    </section>
                    <section
                        v-else-if="transfer"
                        aria-labelledby="connection-step"
                    >
                        <p
                            v-if="recovered"
                            class="mb-5 text-sm text-slate-600 dark:text-slate-400"
                        >
                            Recuperamos la solicitud que tenías abierta en esta
                            pestaña.
                        </p>
                        <template v-if="summary">
                            <h2
                                id="connection-step"
                                class="mb-4 text-lg font-semibold"
                            >
                                {{
                                    identity.side === 'central'
                                        ? `Conectar ${summary.service_name} con tu empresa`
                                        : 'Elige la empresa en ' +
                                          identity.service_name
                                }}
                            </h2>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="bi-panel">
                                    <p
                                        class="mb-4 text-sm font-semibold text-slate-500"
                                    >
                                        En LAUDA
                                    </p>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-xs text-slate-500">
                                                Empresa
                                            </dt>
                                            <dd
                                                class="mt-1 text-lg font-semibold break-words"
                                            >
                                                {{
                                                    summary.central_company_name
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500">
                                                Cuenta
                                            </dt>
                                            <dd class="mt-1 font-medium">
                                                {{ summary.central_user_name }}
                                            </dd>
                                            <dd
                                                class="text-sm break-all text-slate-600 dark:text-slate-400"
                                            >
                                                {{ summary.central_user_email }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="bi-panel">
                                    <p
                                        class="mb-4 text-sm font-semibold text-slate-500"
                                    >
                                        En {{ summary.service_name }}
                                    </p>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-xs text-slate-500">
                                                <label
                                                    v-if="
                                                        identity.side ===
                                                        'receiver'
                                                    "
                                                    for="local-company"
                                                    >Empresa a vincular</label
                                                ><span v-else>Empresa</span>
                                            </dt>
                                            <dd
                                                v-if="
                                                    identity.side === 'receiver'
                                                "
                                                class="mt-2"
                                            >
                                                <select
                                                    id="local-company"
                                                    v-model="localCompany"
                                                    class="bi-select"
                                                    :disabled="
                                                        busy ||
                                                        !summary.companies
                                                            ?.length
                                                    "
                                                >
                                                    <option value="" disabled>
                                                        Selecciona una empresa
                                                    </option>
                                                    <option
                                                        v-for="company in summary.companies"
                                                        :key="company.id"
                                                        :value="
                                                            String(company.id)
                                                        "
                                                    >
                                                        {{ company.name }} · #{{
                                                            company.id
                                                        }}
                                                    </option>
                                                </select>
                                                <p
                                                    v-if="
                                                        !summary.companies
                                                            ?.length
                                                    "
                                                    class="mt-3 text-sm text-slate-600"
                                                >
                                                    Esta cuenta no tiene una
                                                    empresa con el permiso
                                                    necesario. Revisa tu acceso
                                                    o vuelve a LAUDA para
                                                    iniciar otra solicitud.
                                                </p>
                                            </dd>
                                            <dd
                                                v-else
                                                class="mt-1 text-lg font-semibold break-words"
                                            >
                                                {{ summary.local_company_name }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-slate-500">
                                                Cuenta
                                            </dt>
                                            <dd class="mt-1 font-medium">
                                                {{
                                                    summary.local_user_name ??
                                                    identity.account.name
                                                }}
                                            </dd>
                                            <dd
                                                class="text-sm break-all text-slate-600 dark:text-slate-400"
                                            >
                                                {{
                                                    summary.local_user_email ??
                                                    identity.account.email
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <p
                                class="mt-5 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                {{
                                    identity.side === 'central'
                                        ? 'Al confirmar, podrás abrir la aplicación desde LAUDA con esta empresa. Puedes quitar el acceso cuando quieras.'
                                        : 'Continuar enviará esta selección a LAUDA para que la revises y confirmes. Podrás cancelarla antes de confirmar.'
                                }}
                            </p>
                            <div
                                class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center"
                            >
                                <button
                                    type="button"
                                    :class="portalButton.primary"
                                    :disabled="
                                        busy ||
                                        (identity.side === 'receiver' &&
                                            !chosenLocal)
                                    "
                                    @click="consent"
                                >
                                    {{
                                        busy
                                            ? 'Procesando…'
                                            : identity.side === 'central'
                                              ? 'Confirmar conexión'
                                              : 'Revisar en LAUDA'
                                    }}</button
                                ><button
                                    type="button"
                                    :class="portalButton.link"
                                    :disabled="busy"
                                    @click="cancel"
                                >
                                    Cancelar solicitud
                                </button>
                            </div>
                        </template>
                        <div v-else class="bi-panel">
                            <h2
                                id="connection-step"
                                class="text-lg font-semibold"
                            >
                                {{
                                    busy
                                        ? 'Comprobando la conexión…'
                                        : 'Continuar con la conexión'
                                }}
                            </h2>
                            <p
                                class="mt-2 mb-5 text-slate-600 dark:text-slate-400"
                            >
                                {{
                                    transfer.stage === 'launch'
                                        ? 'Comprobamos tu acceso a la empresa antes de abrir la aplicación.'
                                        : 'Estamos comprobando la confirmación entre las dos aplicaciones.'
                                }}
                            </p>
                            <div
                                v-if="!busy"
                                class="flex flex-col gap-3 sm:flex-row sm:items-center"
                            >
                                <button
                                    type="button"
                                    :class="portalButton.primary"
                                    @click="prepare"
                                >
                                    Continuar</button
                                ><button
                                    type="button"
                                    :class="portalButton.link"
                                    @click="clear"
                                >
                                    Descartar enlace
                                </button>
                            </div>
                        </div>
                    </section>
                    <section
                        v-else-if="identity.side === 'central'"
                        aria-labelledby="connections-title"
                    >
                        <div
                            v-if="identity.companies.length !== 1"
                            class="mb-6 max-w-md"
                        >
                            <label
                                for="central-company"
                                class="mb-2 block text-sm font-medium"
                                >Empresa</label
                            ><select
                                id="central-company"
                                v-model="selectedCompany"
                                class="bi-select"
                                :disabled="busy || !identity.companies.length"
                            >
                                <option
                                    v-if="!identity.companies.length"
                                    value=""
                                >
                                    No hay empresas disponibles
                                </option>
                                <option
                                    v-for="company in identity.companies"
                                    :key="company.id"
                                    :value="String(company.id)"
                                >
                                    {{ company.name }}
                                </option>
                            </select>
                            <p
                                v-if="!identity.companies.length"
                                class="mt-3 text-sm text-slate-600"
                            >
                                Necesitas pertenecer a una empresa activa para
                                conectar aplicaciones.
                            </p>
                        </div>
                        <h2
                            id="connections-title"
                            class="mb-4 flex flex-wrap items-baseline gap-x-2 text-lg font-semibold"
                        >
                            Aplicaciones
                            <span
                                v-if="selected"
                                class="text-sm font-normal text-slate-500"
                                >de {{ selected.name }}</span
                            >
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppTile
                                v-for="service in selected?.services"
                                :key="service.key"
                                :title="service.name"
                                :status="serviceStatus(service).label"
                                :tone="serviceStatus(service).tone"
                                :description="serviceHint(service)"
                                :solid="service.ready"
                            >
                                <a
                                    v-if="service.launch_url"
                                    :href="service.launch_url"
                                    class="w-full"
                                    :class="portalButton.dark"
                                    >Abrir {{ service.name }}
                                    <span aria-hidden="true">↗︎</span></a
                                >
                                <template
                                    v-else-if="
                                        service.configured && service.can_link
                                    "
                                    ><button
                                        type="button"
                                        :class="portalButton.primary"
                                        :disabled="busy"
                                        @click="start(service)"
                                    >
                                        {{
                                            service.member_active ||
                                            service.pending
                                                ? 'Continuar'
                                                : 'Conectar'
                                        }}</button
                                    ><button
                                        v-if="service.pending"
                                        type="button"
                                        :class="portalButton.link"
                                        :disabled="busy"
                                        @click="start(service, true)"
                                    >
                                        Empezar de nuevo
                                    </button></template
                                >
                                <button
                                    v-if="service.member_active"
                                    :class="portalButton.link"
                                    type="button"
                                    :disabled="busy"
                                    @click="
                                        revoke = {
                                            url: service.revoke_url,
                                            company: selected!,
                                            whole: false,
                                            service: service.name,
                                            serviceKey: service.key,
                                        }
                                    "
                                >
                                    Quitar mi acceso
                                </button>
                                <button
                                    v-if="
                                        selected?.can_manage && service.active
                                    "
                                    :class="portalButton.link"
                                    type="button"
                                    :disabled="busy"
                                    @click="
                                        revoke = {
                                            url: service.revoke_url,
                                            company: selected!,
                                            whole: true,
                                            service: service.name,
                                            serviceKey: service.key,
                                        }
                                    "
                                >
                                    Desconectar la empresa
                                </button>
                                <IdentityRevokeConfirm
                                    v-if="revoke && revokeTargets(service.key)"
                                    :company="revoke.company.name"
                                    :service="revoke.service"
                                    :whole="revoke.whole"
                                    :busy="busy"
                                    @confirm="confirmRevoke"
                                    @cancel="revoke = null"
                                />
                            </AppTile>
                        </div>
                        <p
                            class="mt-6 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            Cada aplicación mantiene sus propios permisos. Para
                            abrirla, también debe estar incluida en tu paquete
                            vigente.
                        </p>
                    </section>
                    <section v-else aria-labelledby="local-connections">
                        <h2
                            id="local-connections"
                            class="text-lg font-semibold"
                        >
                            Empresas vinculadas
                        </h2>
                        <p class="mt-2 mb-6 text-sm text-slate-600">
                            Inicia las nuevas conexiones desde LAUDA. Aquí
                            puedes quitar el acceso a esta aplicación.
                        </p>
                        <a
                            v-if="identity.hub_url"
                            :href="identity.hub_url"
                            :class="portalButton.primary"
                            >Ir a conexiones en LAUDA
                            <span aria-hidden="true">↗︎</span></a
                        >
                        <p
                            v-if="!identity.companies.length"
                            class="mt-8 text-sm text-slate-600"
                        >
                            No tienes empresas disponibles con esta cuenta.
                        </p>
                        <div
                            v-for="company in identity.companies"
                            :key="company.id"
                            class="mt-6 flex flex-wrap items-center justify-between gap-4 border-b border-border pb-6"
                        >
                            <div class="min-w-0">
                                <h3 class="font-medium break-words">
                                    {{ company.name }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{
                                        company.member_active
                                            ? 'Tu cuenta está vinculada'
                                            : company.linked
                                              ? 'Empresa vinculada; tu cuenta no tiene acceso'
                                              : 'Sin conexión activa'
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-4">
                                <button
                                    v-if="company.member_active"
                                    type="button"
                                    :class="portalButton.link"
                                    :disabled="busy"
                                    @click="
                                        revoke = {
                                            url: String(
                                                identity.endpoints.revoke,
                                            ),
                                            company,
                                            whole: false,
                                            service: identity.service_name,
                                            serviceKey: identity.service_name,
                                        }
                                    "
                                >
                                    Quitar mi acceso</button
                                ><button
                                    v-if="company.can_manage && company.linked"
                                    type="button"
                                    :class="portalButton.link"
                                    :disabled="busy"
                                    @click="
                                        revoke = {
                                            url: String(
                                                identity.endpoints.revoke,
                                            ),
                                            company,
                                            whole: true,
                                            service: identity.service_name,
                                            serviceKey: identity.service_name,
                                        }
                                    "
                                >
                                    Desconectar la empresa
                                </button>
                            </div>
                        </div>
                    </section>
                    <div
                        v-if="revoke && identity.side !== 'central'"
                        class="mt-8"
                    >
                        <IdentityRevokeConfirm
                            :company="revoke.company.name"
                            :service="revoke.service"
                            :whole="revoke.whole"
                            :busy="busy"
                            @confirm="confirmRevoke"
                            @cancel="revoke = null"
                        />
                    </div>
                </template>
            </main>
        </div>
    </component>
</template>

<style scoped>
.bi-shell :is(button, a, select):focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 4px;
}
.bi-link:disabled {
    opacity: 0.5;
    cursor: wait;
}
.bi-link {
    text-decoration: underline;
    text-underline-offset: 4px;
    cursor: pointer;
}
.bi-panel {
    border: 1px solid var(--border);
    padding: 1.25rem;
    border-radius: 1rem;
    background: var(--card, #fff);
}
.bi-select {
    width: 100%;
    min-height: 44px;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--background);
    color: var(--foreground);
    font-size: 0.875rem;
}
</style>
