<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    identityStorageKey,
    takeBusinessIdentityHandoff,
    type IdentityTransfer,
} from '@/lib/businessIdentityHandoff';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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
} | null>(null);
const selected = computed(() =>
    props.identity.companies.find(
        (c) => String(c.id) === selectedCompany.value,
    ),
);
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
        await json(
            revoke.value.url,
            {
                company_id: revoke.value.company.id,
                whole_company: revoke.value.whole,
            },
            'DELETE',
        );
        revoke.value = null;
        notice.value = 'Acceso desde LAUDA revocado en esta aplicación.';
        router.reload({ only: ['identity'] });
    });
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
    <div class="bi-shell min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-5 sm:px-10"
            >
                <a
                    :href="identity.home_url"
                    class="text-xl font-bold tracking-tight"
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
                <a :href="identity.home_url" class="bi-link text-sm"
                    >Volver al inicio <span aria-hidden="true">↗︎</span></a
                >
            </div>
        </header>
        <main
            class="mx-auto max-w-5xl px-6 py-10 sm:px-10 sm:py-16"
            :aria-busy="busy"
        >
            <div class="mb-10 max-w-2xl">
                <p
                    class="mb-3 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                >
                    Cuentas y empresas
                </p>
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{
                        transfer
                            ? 'Conecta con confianza.'
                            : 'Tus conexiones Business.'
                    }}
                </h1>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    {{
                        transfer
                            ? 'Comprueba la empresa y la cuenta de cada aplicación antes de confirmar.'
                            : 'Vincula tus cuentas para abrir las aplicaciones desde LAUDA con la empresa correcta.'
                    }}
                </p>
            </div>
            <div
                v-if="error"
                role="alert"
                class="mb-6 border-l-4 border-destructive bg-destructive/5 p-4 text-sm leading-relaxed"
            >
                {{ error }}
                <button
                    v-if="transfer && !expired && identity.account?.verified"
                    type="button"
                    class="bi-link ml-2"
                    :disabled="busy"
                    @click="prepare"
                >
                    Reintentar</button
                ><a href="/business/identity" class="bi-link ml-2"
                    >Recargar página</a
                >
            </div>
            <p
                v-if="notice"
                role="status"
                class="mb-6 border-l-4 border-primary bg-muted p-4 text-sm"
            >
                {{ notice }}
            </p>
            <section v-if="!identity.enabled" class="bi-panel">
                <h2 class="text-xl font-semibold">
                    Conexiones aún no disponibles
                </h2>
                <p class="mt-3 text-muted-foreground">
                    La vinculación Business está pendiente de habilitación en
                    esta aplicación. Podrás continuar cuando esté disponible.
                </p>
            </section>
            <section v-else-if="!identity.account" class="bi-panel">
                <h2 class="text-xl font-semibold">
                    Inicia sesión en {{ identity.service_name }}
                </h2>
                <p class="mt-3 mb-6 text-muted-foreground">
                    Utiliza la cuenta que deseas vincular. Tu solicitud se
                    conservará en esta pestaña durante unos minutos.
                </p>
                <a href="/login" class="bi-primary">Iniciar sesión</a>
            </section>
            <section v-else-if="!identity.account.verified" class="bi-panel">
                <h2 class="text-xl font-semibold">
                    Verifica tu correo para continuar
                </h2>
                <p class="mt-3 mb-6 break-words text-muted-foreground">
                    {{ identity.account.email }}
                </p>
                <a href="/email/verify" class="bi-primary">Verificar correo</a>
            </section>
            <template v-else>
                <div
                    class="mb-8 flex flex-wrap items-baseline justify-between gap-3 border-b border-border pb-5 text-sm"
                >
                    <p>
                        Sesión en {{ identity.service_name }}:
                        <strong>{{ identity.account.name }}</strong
                        ><span
                            class="mt-1 block break-all text-muted-foreground sm:ml-2 sm:inline"
                            >{{ identity.account.email }}</span
                        >
                    </p>
                    <p
                        v-if="transfer && !expired"
                        class="text-muted-foreground"
                    >
                        Vence en
                        <span class="font-mono tabular-nums">{{
                            minuteLabel
                        }}</span>
                    </p>
                </div>
                <section v-if="expired" class="bi-panel">
                    <h2 class="text-xl font-semibold">La solicitud venció</h2>
                    <p class="mt-3 mb-6 text-muted-foreground">
                        Por seguridad, cada solicitud dura unos minutos. Inicia
                        una nueva para continuar.
                    </p>
                    <button class="bi-primary" type="button" @click="clear">
                        Descartar solicitud
                    </button>
                </section>
                <section v-else-if="transfer" aria-labelledby="connection-step">
                    <p
                        v-if="recovered"
                        class="mb-5 text-sm text-muted-foreground"
                    >
                        Recuperamos la solicitud pendiente de esta pestaña.
                    </p>
                    <template v-if="summary">
                        <h2
                            id="connection-step"
                            class="mb-6 text-xl font-semibold"
                        >
                            {{
                                identity.side === 'central'
                                    ? 'Confirma esta conexión'
                                    : 'Elige la empresa en ' +
                                      identity.service_name
                            }}
                        </h2>
                        <div
                            class="grid gap-8 border-y border-border py-7 md:grid-cols-2 md:gap-12"
                        >
                            <div>
                                <p
                                    class="mb-4 text-sm font-semibold text-muted-foreground"
                                >
                                    01 / LAUDA
                                </p>
                                <dl class="space-y-4">
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Empresa
                                        </dt>
                                        <dd
                                            class="mt-1 text-lg font-medium break-words"
                                        >
                                            {{ summary.central_company_name }}
                                        </dd>
                                        <dd
                                            class="text-xs text-muted-foreground"
                                        >
                                            Empresa #{{
                                                summary.central_company_id
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Cuenta
                                        </dt>
                                        <dd class="mt-1 font-medium">
                                            {{ summary.central_user_name }}
                                        </dd>
                                        <dd
                                            class="text-sm break-all text-muted-foreground"
                                        >
                                            {{ summary.central_user_email }} ·
                                            #{{ summary.central_user_id }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <p
                                    class="mb-4 text-sm font-semibold text-muted-foreground"
                                >
                                    02 / {{ summary.service_name }}
                                </p>
                                <dl class="space-y-4">
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            <label
                                                v-if="
                                                    identity.side === 'receiver'
                                                "
                                                for="local-company"
                                                >Empresa a vincular</label
                                            ><span v-else>Empresa</span>
                                        </dt>
                                        <dd
                                            v-if="identity.side === 'receiver'"
                                            class="mt-2"
                                        >
                                            <select
                                                id="local-company"
                                                v-model="localCompany"
                                                class="bi-select"
                                                :disabled="
                                                    busy ||
                                                    !summary.companies?.length
                                                "
                                            >
                                                <option value="" disabled>
                                                    Selecciona una empresa
                                                </option>
                                                <option
                                                    v-for="company in summary.companies"
                                                    :key="company.id"
                                                    :value="String(company.id)"
                                                >
                                                    {{ company.name }} · #{{
                                                        company.id
                                                    }}
                                                </option>
                                            </select>
                                            <p
                                                v-if="
                                                    !summary.companies?.length
                                                "
                                                class="mt-3 text-sm text-muted-foreground"
                                            >
                                                Esta cuenta no tiene una empresa
                                                con el permiso necesario. Revisa
                                                tu acceso o vuelve a LAUDA para
                                                iniciar otra solicitud.
                                            </p>
                                        </dd>
                                        <template v-else
                                            ><dd
                                                class="mt-1 text-lg font-medium break-words"
                                            >
                                                {{ summary.local_company_name }}
                                            </dd>
                                            <dd
                                                class="text-xs text-muted-foreground"
                                            >
                                                Empresa #{{
                                                    summary.local_company_id
                                                }}
                                            </dd></template
                                        >
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Cuenta
                                        </dt>
                                        <dd class="mt-1 font-medium">
                                            {{
                                                summary.local_user_name ??
                                                identity.account.name
                                            }}
                                        </dd>
                                        <dd
                                            class="text-sm break-all text-muted-foreground"
                                        >
                                            {{
                                                summary.local_user_email ??
                                                identity.account.email
                                            }}
                                            · #{{
                                                summary.local_user_id ??
                                                identity.account.id
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        <p
                            class="mt-6 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                        >
                            {{
                                identity.side === 'central'
                                    ? 'Al confirmar, estas cuentas y empresas quedarán asociadas. Puedes revocar el acceso después; la asociación de identidad se conserva.'
                                    : 'Continuar enviará esta selección a LAUDA para que la revises y confirmes. Podrás cancelarla antes de confirmar.'
                            }}
                        </p>
                        <div class="mt-7 flex flex-wrap items-center gap-4">
                            <button
                                type="button"
                                class="bi-primary"
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
                                class="bi-link text-sm"
                                :disabled="busy"
                                @click="cancel"
                            >
                                Cancelar solicitud
                            </button>
                        </div>
                    </template>
                    <template v-else
                        ><h2 id="connection-step" class="text-xl font-semibold">
                            {{
                                busy
                                    ? 'Comprobando la conexión…'
                                    : 'Continuar con la conexión'
                            }}
                        </h2>
                        <p class="my-4 text-muted-foreground">
                            {{
                                transfer.stage === 'launch'
                                    ? 'Verificamos tu acceso a la empresa antes de abrir la aplicación.'
                                    : 'Estamos comprobando la confirmación entre las dos aplicaciones.'
                            }}
                        </p>
                        <button
                            v-if="!busy"
                            type="button"
                            class="bi-primary"
                            @click="prepare"
                        >
                            Continuar</button
                        ><button
                            v-if="!busy"
                            type="button"
                            class="bi-link ml-4 text-sm"
                            @click="clear"
                        >
                            Descartar enlace
                        </button></template
                    >
                </section>
                <section
                    v-else-if="identity.side === 'central'"
                    aria-labelledby="connections-title"
                >
                    <div class="mb-8 max-w-md">
                        <label
                            for="central-company"
                            class="mb-2 block text-sm font-medium"
                            >Empresa en LAUDA</label
                        ><select
                            id="central-company"
                            v-model="selectedCompany"
                            class="bi-select"
                            :disabled="busy || !identity.companies.length"
                        >
                            <option v-if="!identity.companies.length" value="">
                                No hay empresas disponibles
                            </option>
                            <option
                                v-for="company in identity.companies"
                                :key="company.id"
                                :value="String(company.id)"
                            >
                                {{ company.name }} · #{{ company.id }}
                            </option>
                        </select>
                        <p
                            v-if="!identity.companies.length"
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            Necesitas una membresía activa en una empresa para
                            crear conexiones.
                        </p>
                    </div>
                    <h2
                        id="connections-title"
                        class="mb-2 text-lg font-semibold"
                    >
                        Aplicaciones
                    </h2>
                    <div
                        v-for="service in selected?.services"
                        :key="service.key"
                        class="flex flex-col gap-4 border-b border-border py-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="font-semibold">{{ service.name }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    !service.configured
                                        ? 'Pendiente de habilitación'
                                        : service.ready
                                          ? 'Conexión confirmada'
                                          : service.pending
                                            ? 'Solicitud pendiente'
                                            : service.member_active
                                              ? 'Confirmación pendiente'
                                              : service.active
                                                ? 'Empresa vinculada · conecta tu cuenta'
                                                : service.linked
                                                  ? 'Acceso revocado'
                                                  : 'Sin conexión'
                                }}<span v-if="service.local_company_id">
                                    · Empresa #{{
                                        service.local_company_id
                                    }}</span
                                >
                            </p>
                            <p
                                v-if="!service.can_link"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                El propietario o administrador debe vincular
                                primero la empresa.
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-4 sm:justify-end"
                        >
                            <a
                                v-if="service.launch_url"
                                :href="service.launch_url"
                                class="bi-primary"
                                >Abrir {{ service.name }}
                                <span aria-hidden="true">↗︎</span></a
                            >
                            <template
                                v-else-if="
                                    service.configured && service.can_link
                                "
                                ><button
                                    type="button"
                                    class="bi-primary"
                                    :disabled="busy"
                                    @click="start(service)"
                                >
                                    {{
                                        service.member_active || service.pending
                                            ? 'Continuar'
                                            : 'Conectar'
                                    }}</button
                                ><button
                                    v-if="service.pending"
                                    type="button"
                                    class="bi-link text-xs"
                                    :disabled="busy"
                                    @click="start(service, true)"
                                >
                                    Reiniciar solicitud
                                </button></template
                            >
                            <button
                                v-if="service.member_active"
                                class="bi-link text-xs"
                                type="button"
                                :disabled="busy"
                                @click="
                                    revoke = {
                                        url: service.revoke_url,
                                        company: selected!,
                                        whole: false,
                                        service: service.name,
                                    }
                                "
                            >
                                Revocar mi acceso
                            </button>
                            <button
                                v-if="selected?.can_manage && service.active"
                                class="bi-link text-xs"
                                type="button"
                                :disabled="busy"
                                @click="
                                    revoke = {
                                        url: service.revoke_url,
                                        company: selected!,
                                        whole: true,
                                        service: service.name,
                                    }
                                "
                            >
                                Revocar empresa
                            </button>
                        </div>
                    </div>
                    <p
                        class="mt-6 text-xs leading-relaxed text-muted-foreground"
                    >
                        Cada aplicación conserva sus permisos y su inicio de
                        sesión. Abrir una aplicación también requiere acceso
                        comercial vigente.
                    </p>
                </section>
                <section v-else aria-labelledby="local-connections">
                    <h2 id="local-connections" class="text-lg font-semibold">
                        Empresas vinculadas
                    </h2>
                    <p class="mt-2 mb-6 text-sm text-muted-foreground">
                        Inicia las nuevas conexiones desde LAUDA. Aquí puedes
                        revocar el acceso a esta aplicación.
                    </p>
                    <a
                        v-if="identity.hub_url"
                        :href="identity.hub_url"
                        class="bi-primary"
                        >Ir a conexiones en LAUDA
                        <span aria-hidden="true">↗︎</span></a
                    >
                    <p
                        v-if="!identity.companies.length"
                        class="mt-8 text-sm text-muted-foreground"
                    >
                        No tienes empresas disponibles con esta cuenta.
                    </p>
                    <div
                        v-for="company in identity.companies"
                        :key="company.id"
                        class="mt-6 flex flex-wrap items-center justify-between gap-4 border-b border-border pb-6"
                    >
                        <div>
                            <h3 class="font-medium">{{ company.name }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    company.member_active
                                        ? 'Tu cuenta está vinculada'
                                        : company.linked
                                          ? 'Empresa vinculada; cuenta sin acceso'
                                          : 'Sin conexión activa'
                                }}<span v-if="company.central_company_id">
                                    · LAUDA #{{
                                        company.central_company_id
                                    }}</span
                                >
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <button
                                v-if="company.member_active"
                                type="button"
                                class="bi-link text-sm"
                                :disabled="busy"
                                @click="
                                    revoke = {
                                        url: String(identity.endpoints.revoke),
                                        company,
                                        whole: false,
                                        service: identity.service_name,
                                    }
                                "
                            >
                                Revocar mi acceso</button
                            ><button
                                v-if="company.can_manage && company.linked"
                                type="button"
                                class="bi-link text-sm"
                                :disabled="busy"
                                @click="
                                    revoke = {
                                        url: String(identity.endpoints.revoke),
                                        company,
                                        whole: true,
                                        service: identity.service_name,
                                    }
                                "
                            >
                                Revocar empresa
                            </button>
                        </div>
                    </div>
                </section>
                <section
                    v-if="revoke"
                    role="region"
                    aria-labelledby="revoke-title"
                    class="bi-panel mt-8"
                >
                    <h2 id="revoke-title" class="text-lg font-semibold">
                        {{
                            revoke.whole
                                ? 'Revocar el acceso de toda la empresa'
                                : 'Revocar tu acceso'
                        }}
                    </h2>
                    <p
                        class="my-4 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ revoke.company.name }} · {{ revoke.service }}.
                        {{
                            revoke.whole
                                ? 'Ningún miembro podrá usar este vínculo para entrar desde LAUDA.'
                                : 'Tu cuenta dejará de usar este vínculo para entrar desde LAUDA.'
                        }}
                        Las sesiones locales y la asociación de identidad se
                        conservan.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <button
                            type="button"
                            class="bi-primary"
                            :disabled="busy"
                            @click="confirmRevoke"
                        >
                            {{
                                busy ? 'Revocando…' : 'Confirmar revocación'
                            }}</button
                        ><button
                            type="button"
                            class="bi-link text-sm"
                            :disabled="busy"
                            @click="revoke = null"
                        >
                            Conservar acceso
                        </button>
                    </div>
                </section>
            </template>
        </main>
    </div>
</template>

<style scoped>
.bi-shell :is(button, a, select):focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 4px;
}
.bi-primary {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 1.1rem;
    border-radius: 0.4rem;
    background: color-mix(in srgb, var(--primary) 85%, black);
    color: var(--primary-foreground);
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}
.bi-primary:hover {
    filter: brightness(0.92);
}
.bi-primary:disabled,
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
    padding: 1.5rem;
    border-radius: 0.5rem;
}
.bi-select {
    width: 100%;
    min-height: 44px;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.35rem;
    background: var(--background);
    color: var(--foreground);
    font-size: 0.875rem;
}
</style>
