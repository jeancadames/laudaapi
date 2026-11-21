<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BusinessAmountInput from '@/components/BusinessAmountInput.vue';
import BusinessCapacitySummary from '@/components/BusinessCapacitySummary.vue';
import BusinessTransitionSummary from '@/components/BusinessTransitionSummary.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BusinessCatalog,
    CatalogVersion,
    CapacityDimension,
    CapacityPolicy,
    QuoteResult,
    QuoteSelection,
} from '@/types/businessCatalog';

type Company = { id: number; name: string; active: boolean };
type SavedQuote = {
    id: number;
    public_id: string;
    company_id: number;
    issued_at: string;
    valid_until: string;
};
const props = defineProps<{
    installed: boolean;
    initialCapacityPolicy: CapacityPolicy;
    versions: CatalogVersion[];
    selected: CatalogVersion | null;
    currentId: number | null;
    services: {
        id: number;
        title: string;
        service_key: string;
        active: boolean;
    }[];
    audit: {
        id: number;
        event: string;
        created_at: string;
        user: { name: string } | null;
        data: { before: unknown; after: unknown };
    }[];
    quotes: SavedQuote[];
}>();
const base = '/admin/business-catalog';
const payload = ref<BusinessCatalog | null>(null);
const busy = ref(false);
const errors = ref<string[]>([]);
const notice = ref('');
const section = ref('general');
const effectiveAt = ref('');
const result = ref<QuoteResult | null>(null);
const quoteId = ref<string | null>(null);
const transitionTarget = ref<number | null>(null);
const transitionConfirmed = ref(false);
const transitionReady = computed(
    () =>
        !result.value?.transition_requires_review ||
        (result.value.transition_review?.eligible &&
            transitionTarget.value !== null &&
            transitionConfirmed.value),
);
watch(result, () => {
    transitionTarget.value = null;
    transitionConfirmed.value = false;
});
watch([transitionTarget, transitionConfirmed], () => {
    idempotencyKey.value = '';
});
const companySearch = ref('');
const companies = ref<Company[]>([]);
const companyId = ref<number | null>(null);
const newCode = ref('');
const idempotencyKey = ref('');
const selection = ref<QuoteSelection>({
    offer: 'business',
    level: 'initial',
    cycle: 'monthly',
    implementation: 'guided',
    addons: {},
    discount: null,
});
const dimensions: { code: CapacityDimension; name: string }[] = [
    { code: 'documents', name: 'Documentos / mes' },
    { code: 'branches', name: 'Sucursales' },
    { code: 'registers', name: 'Cajas POS' },
    { code: 'users', name: 'Usuarios' },
];
const sections = {
    general: 'Condiciones',
    products: 'Productos',
    offers: 'Ofertas y paquetes',
    capacities: 'Capacidad',
    addons: 'Adicionales',
    implementations: 'Implementación',
    discounts: 'Descuentos',
    quote: 'Cotizador',
    history: 'Historial',
};
const editable = computed(() => props.selected?.status === 'draft');
const dirty = computed(
    () =>
        JSON.stringify(payload.value) !==
        JSON.stringify(props.selected?.payload ?? null),
);
const statusName = (status: string) =>
    ({ draft: 'Borrador', published: 'Publicado', retired: 'Retirado' })[
        status
    ] ?? status;
const money = (cents: number) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP',
    }).format(cents / 100);
watch(
    () => props.selected,
    (version) => {
        payload.value = version
            ? JSON.parse(JSON.stringify(version.payload))
            : null;
        result.value = null;
        quoteId.value = null;
        idempotencyKey.value = '';
    },
    { immediate: true },
);
watch(
    [selection, companyId],
    () => {
        result.value = null;
        quoteId.value = null;
        idempotencyKey.value = '';
    },
    { deep: true },
);

async function request(
    path: string,
    method = 'POST',
    data?: unknown,
): Promise<unknown | null> {
    busy.value = true;
    errors.value = [];
    notice.value = '';
    try {
        const csrf = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        const cookie = document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        else if (cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie);
        const response = await fetch(base + path, {
            method,
            headers,
            credentials: 'same-origin',
            body: data === undefined ? undefined : JSON.stringify(data),
        });
        if (!response.headers.get('content-type')?.includes('application/json'))
            throw new Error(
                'No se recibió una respuesta válida. Verifica tu sesión y vuelve a intentar.',
            );
        const body = await response.json();
        if (!response.ok) {
            errors.value = body.errors
                ? (Object.values(body.errors).flat() as string[])
                : [body.message ?? 'No se pudo completar la acción.'];
            return null;
        }
        return body;
    } catch (error) {
        errors.value = [
            error instanceof Error
                ? error.message
                : 'Error de comunicación. Tus entradas se conservan.',
        ];
        return null;
    } finally {
        busy.value = false;
    }
}
function initializeCapacityPolicy() {
    if (payload.value && editable.value) {
        payload.value.capacity_policy = JSON.parse(
            JSON.stringify(props.initialCapacityPolicy),
        );
    }
}
async function initialize() {
    if (await request('/initialize')) router.reload();
}
async function action(kind: 'save' | 'revise' | 'publish' | 'retire') {
    if (!props.selected) return;
    const data: Record<string, unknown> = {
        lock_version: props.selected.lock_version,
    };
    if (kind === 'save') data.payload = payload.value;
    if (kind === 'publish')
        data.effective_at = effectiveAt.value
            ? new Date(effectiveAt.value).toISOString().replace('.000Z', 'Z')
            : null;
    const response = (await request(
        `/${props.selected.id}${kind === 'save' ? '' : '/' + kind}`,
        kind === 'save' ? 'PUT' : 'POST',
        data,
    )) as CatalogVersion | null;
    if (response) {
        notice.value = {
            save: 'Borrador guardado.',
            revise: 'Nueva revisión creada.',
            publish: 'Publicación registrada.',
            retire: 'Versión retirada de nuevas cotizaciones.',
        }[kind];
        router.get(
            base,
            { version: response.id },
            { preserveState: true, preserveScroll: true },
        );
    }
}
async function preview() {
    if (!props.selected) return;
    result.value = (await request(`/${props.selected.id}/preview`, 'POST', {
        selection: selection.value,
        company_id: companyId.value,
    })) as QuoteResult | null;
}
async function issue() {
    if (!props.selected || !result.value || !transitionReady.value) return;
    idempotencyKey.value ||= crypto.randomUUID();
    const response = (await request(`/${props.selected.id}/issue`, 'POST', {
        selection: selection.value,
        company_id: companyId.value,
        lock_version: props.selected.lock_version,
        idempotency_key: idempotencyKey.value,
        transition:
            result.value.transition_requires_review &&
            result.value.transition_review?.eligible
                ? {
                      mode: 'after_paid_periods',
                      target_subscription_id: transitionTarget.value,
                      source_hash: result.value.transition_review.source_hash,
                      confirmed: transitionConfirmed.value,
                  }
                : null,
    })) as { public_id: string } | null;
    if (response) {
        quoteId.value = response.public_id;
        notice.value =
            'Cotización emitida. Conserva sus condiciones durante su vigencia.';
    }
}
async function searchCompanies() {
    const response = await request(
        '/companies?search=' + encodeURIComponent(companySearch.value),
        'GET',
    );
    if (response) companies.value = response as Company[];
}
function addEntry() {
    if (!payload.value) return;
    const code = newCode.value.trim();
    if (!/^[a-z][a-z0-9_-]{0,49}$/.test(code)) {
        errors.value = ['Usa un código en minúsculas, sin espacios.'];
        return;
    }
    const catalog = payload.value;
    const group = section.value;
    if (
        ![
            'offers',
            'capacities',
            'addons',
            'implementations',
            'discounts',
        ].includes(group)
    )
        return;
    if (code in (catalog[group as keyof BusinessCatalog] as object)) {
        errors.value = ['Este código ya existe.'];
        return;
    }
    if (group === 'offers')
        catalog.offers[code] = {
            name: 'Nuevo paquete',
            available: false,
            products: [],
            pricing_method: 'own',
            package_discount_bps: 0,
            levels: Object.fromEntries(
                Object.keys(catalog.capacities).map((key) => [
                    key,
                    { enabled: false, monthly_cents: null, annual_cents: null },
                ]),
            ),
        };
    if (group === 'capacities') {
        catalog.capacities[code] = {
            name: 'Nuevo nivel',
            documents: 1,
            branches: 1,
            registers: 1,
            users: 1,
        };
        Object.values(catalog.offers).forEach((offer) => {
            offer.levels[code] = {
                enabled: false,
                monthly_cents: null,
                annual_cents: null,
            };
        });
    }
    if (group === 'addons')
        catalog.addons[code] = {
            name: 'Nuevo adicional',
            unit: 'unidad',
            block_size: 1,
            price_cents: null,
            recurring: false,
            product: null,
            capacity: {},
        };
    if (group === 'implementations')
        catalog.implementations[code] = {
            name: 'Nueva modalidad',
            price_cents: null,
            hours: 0,
            scope: '',
            offers: [],
        };
    if (group === 'discounts')
        catalog.discounts[code] = {
            name: 'Nueva condición',
            percent_bps: 0,
            company_id: null,
            reason: '',
            compatible_annual: false,
            compatible_package: false,
            offers: [],
            starts_at: '',
            ends_at: '',
        };
    newCode.value = '';
}
function switchMethod(code: string) {
    const offer = payload.value?.offers[code];
    if (!offer) return;
    if (offer.pricing_method === 'sum')
        Object.values(offer.levels).forEach((price) => {
            price.monthly_cents = null;
        });
    else offer.package_discount_bps = 0;
}
function nullableInteger(event: Event): number | null {
    const value = (event.target as HTMLInputElement).value;
    return value === '' ? null : Number(value);
}
function features(event: Event): string[] {
    return (event.target as HTMLTextAreaElement).value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}
function setDiscountDate(
    code: string,
    key: 'starts_at' | 'ends_at',
    event: Event,
) {
    const value = (event.target as HTMLInputElement).value;
    if (payload.value)
        payload.value.discounts[code][key] = value
            ? new Date(value).toISOString().replace('.000Z', 'Z')
            : '';
}
function localDate(value: string) {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
}
function changes(
    before: unknown,
    after: unknown,
    path = '',
): { path: string; before: string; after: string }[] {
    if (JSON.stringify(before) === JSON.stringify(after)) return [];
    if (
        before &&
        after &&
        typeof before === 'object' &&
        typeof after === 'object' &&
        !Array.isArray(before) &&
        !Array.isArray(after)
    ) {
        const a = before as Record<string, unknown>,
            b = after as Record<string, unknown>;
        return [...new Set([...Object.keys(a), ...Object.keys(b)])].flatMap(
            (key) => changes(a[key], b[key], path ? `${path}.${key}` : key),
        );
    }
    return [
        {
            path: path || 'Catálogo',
            before: JSON.stringify(before) ?? '—',
            after: JSON.stringify(after) ?? '—',
        },
    ];
}
</script>

<template>
    <Head title="Catálogo y tarifas · Business" />
    <AppLayout :breadcrumbs="[{ title: 'Catálogo y tarifas', href: base }]">
        <main
            class="business-catalog mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8"
        >
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">
                        Admin LAUDA · Business
                    </p>
                    <h1 class="text-2xl font-semibold">Catálogo y tarifas</h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Productos, capacidad e implementación. Importes en DOP
                        antes de impuestos.
                    </p>
                </div>
                <label v-if="versions.length" class="max-w-xs"
                    >Versión<select
                        :value="selected?.id"
                        :disabled="busy || dirty"
                        @change="
                            router.get(base, {
                                version: ($event.target as HTMLSelectElement)
                                    .value,
                            })
                        "
                    >
                        <option
                            v-for="version in versions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.version }} ·
                            {{ statusName(version.status)
                            }}{{ currentId === version.id ? ' · Vigente' : '' }}
                        </option>
                    </select></label
                >
            </header>
            <div
                v-if="errors.length"
                role="alert"
                class="rounded-lg border border-destructive p-4 text-sm text-destructive"
            >
                <p v-for="error in errors" :key="error">{{ error }}</p>
            </div>
            <p
                v-if="notice"
                role="status"
                class="rounded-lg border p-4 text-sm"
            >
                {{ notice }}
            </p>
            <section v-if="!installed" class="space-y-3 rounded-lg border p-6">
                <h2 class="text-lg font-medium">Catálogo en preparación</h2>
                <p>
                    La instalación debe completarse antes de cargar y publicar
                    tarifas. Los servicios actuales conservan sus condiciones.
                </p>
            </section>
            <section
                v-else-if="!selected"
                class="space-y-4 rounded-lg border p-6"
            >
                <h2 class="text-lg font-medium">
                    Prepara el catálogo Business
                </h2>
                <p>
                    La carga inicial crea un borrador con las tarifas acordadas.
                    Revisa productos, impuestos, vigencia y condiciones antes de
                    publicar.
                </p>
                <Button :disabled="busy" @click="initialize"
                    >Crear catálogo inicial</Button
                >
            </section>
            <template v-else-if="payload">
                <div
                    class="flex flex-wrap items-center gap-3 rounded-lg border p-4"
                >
                    <span class="font-medium"
                        >Versión {{ selected.version }} ·
                        {{ statusName(selected.status) }}</span
                    >
                    <span
                        v-if="selected.effective_at"
                        class="text-sm text-muted-foreground"
                        >Vigencia:
                        {{
                            new Date(selected.effective_at).toLocaleString(
                                'es-DO',
                            )
                        }}</span
                    >
                    <span v-if="dirty" class="text-sm text-amber-700"
                        >Cambios sin guardar</span
                    >
                    <div class="ml-auto flex flex-wrap gap-2">
                        <Button
                            v-if="editable"
                            :disabled="busy || !dirty"
                            @click="action('save')"
                            >{{
                                busy ? 'Procesando…' : 'Guardar borrador'
                            }}</Button
                        ><Button
                            variant="outline"
                            :disabled="busy || dirty"
                            @click="action('revise')"
                            >Crear nueva revisión</Button
                        >
                    </div>
                </div>
                <nav
                    aria-label="Secciones del catálogo"
                    class="flex flex-wrap gap-1 border-b pb-3"
                >
                    <button
                        v-for="(name, key) in sections"
                        :key="key"
                        type="button"
                        :aria-current="section === key ? 'page' : undefined"
                        class="rounded-md px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                        :class="
                            section === key
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="section = key"
                    >
                        {{ name }}
                    </button>
                </nav>

                <fieldset
                    v-if="section !== 'quote' && section !== 'history'"
                    :disabled="busy || !editable"
                    class="space-y-6"
                >
                    <template v-if="section === 'general'">
                        <div class="grid gap-4 md:grid-cols-3">
                            <label
                                >Moneda<input
                                    :value="payload.currency"
                                    disabled /></label
                            ><label
                                >Zona horaria<input
                                    v-model="payload.timezone" /></label
                            ><label
                                >Validez de cotización (días)<input
                                    type="number"
                                    min="1"
                                    :value="payload.quote_valid_days"
                                    placeholder="Pendiente"
                                    @input="
                                        payload.quote_valid_days =
                                            nullableInteger($event)
                                    " /></label
                            ><label
                                >Descuento anual (%)<BusinessAmountInput
                                    :model-value="payload.annual_discount_bps"
                                    @update:model-value="
                                        payload.annual_discount_bps =
                                            $event ?? 0
                                    " /></label
                            ><label
                                >Tasa de impuesto (%)<input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    :value="
                                        payload.tax_rate === null
                                            ? null
                                            : payload.tax_rate * 100
                                    "
                                    placeholder="Pendiente"
                                    @input="
                                        payload.tax_rate =
                                            ($event.target as HTMLInputElement)
                                                .value === ''
                                                ? null
                                                : Number(
                                                      (
                                                          $event.target as HTMLInputElement
                                                      ).value,
                                                  ) / 100
                                    "
                            /></label>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            El precio anual explícito sustituye la fórmula del
                            software. Los adicionales recurrentes conservan el
                            descuento anual configurado; los pagos únicos se
                            calculan por separado.
                        </p>
                        <label
                            >Condiciones comerciales<textarea
                                v-model="payload.terms"
                                rows="5"
                                placeholder="Condiciones, alcance y reglas aplicables a la cotización."
                            />
                        </label>
                    </template>
                    <template v-if="section === 'products'">
                        <article
                            v-for="(product, code) in payload.products"
                            :key="code"
                            class="space-y-4 rounded-lg border p-5"
                        >
                            <template v-if="product"
                                ><div class="grid gap-4 md:grid-cols-2">
                                    <label
                                        >Nombre<input
                                            v-model="product.name" /></label
                                    ><label
                                        >Servicio central<select
                                            v-model="product.service_id"
                                        >
                                            <option :value="null">
                                                Pendiente de vincular
                                            </option>
                                            <option
                                                v-for="service in services.filter(
                                                    (item) =>
                                                        item.service_key ===
                                                        code,
                                                )"
                                                :key="service.id"
                                                :value="service.id"
                                            >
                                                {{ service.title
                                                }}{{
                                                    service.active
                                                        ? ''
                                                        : ' (inactivo)'
                                                }}
                                            </option>
                                        </select></label
                                    >
                                </div>
                                <label class="check"
                                    ><input
                                        v-model="product.available"
                                        type="checkbox"
                                    />Disponible para nuevas ofertas</label
                                ><label
                                    >Descripción<textarea
                                        v-model="product.description"
                                        rows="2"
                                    /></label
                                ><label
                                    >Funcionalidades incluidas (una por
                                    línea)<textarea
                                        :value="product.features.join('\n')"
                                        rows="3"
                                        @change="
                                            product.features = features($event)
                                        "
                                    />
                                </label>
                                <p
                                    v-if="code === 'pos'"
                                    class="text-sm text-muted-foreground"
                                >
                                    POS incluye siempre el e-commerce estándar,
                                    sin otra licencia base.
                                </p></template
                            >
                        </article>
                    </template>
                    <template v-if="section === 'offers'">
                        <article
                            v-for="(offer, code) in payload.offers"
                            :key="code"
                            class="space-y-4 rounded-lg border p-5"
                        >
                            <div class="grid gap-4 md:grid-cols-3">
                                <label
                                    >Oferta · {{ code
                                    }}<input v-model="offer.name" /></label
                                ><label
                                    >Método de precio<select
                                        v-model="offer.pricing_method"
                                        @change="switchMethod(code)"
                                    >
                                        <option value="own">
                                            Tarifa propia
                                        </option>
                                        <option value="sum">
                                            Suma de individuales
                                        </option>
                                    </select></label
                                ><label v-if="offer.pricing_method === 'sum'"
                                    >Descuento de paquete
                                    (%)<BusinessAmountInput
                                        :model-value="
                                            offer.package_discount_bps
                                        "
                                        @update:model-value="
                                            offer.package_discount_bps =
                                                $event ?? 0
                                        "
                                /></label>
                            </div>
                            <label class="check"
                                ><input
                                    v-model="offer.available"
                                    type="checkbox"
                                />Disponible para nuevas cotizaciones</label
                            >
                            <div class="flex flex-wrap gap-4">
                                <label
                                    v-for="(
                                        product, productCode
                                    ) in payload.products"
                                    :key="productCode"
                                    class="check"
                                    ><input
                                        v-model="offer.products"
                                        type="checkbox"
                                        :value="productCode"
                                    />{{ product?.name }}</label
                                >
                            </div>
                            <div
                                v-for="(price, level) in offer.levels"
                                :key="level"
                                class="grid items-end gap-4 border-t pt-4 md:grid-cols-3"
                            >
                                <label class="check"
                                    ><input
                                        v-model="price.enabled"
                                        type="checkbox"
                                    />{{
                                        payload.capacities[level]?.name
                                    }}</label
                                ><label v-if="offer.pricing_method === 'own'"
                                    >Mensual (RD$)<BusinessAmountInput
                                        v-model="price.monthly_cents"
                                /></label>
                                <p v-else class="text-sm text-muted-foreground">
                                    Según individuales del mismo nivel.
                                </p>
                                <label
                                    >Anual explícito (RD$)<BusinessAmountInput
                                        v-model="price.annual_cents"
                                /></label>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Anual vacío: usar fórmula. Mensual vacío: precio
                                pendiente. Un precio cero es explícito.
                                Deshabilita las combinaciones pendientes antes
                                de publicar.
                            </p>
                        </article>
                    </template>
                    <template v-if="section === 'capacities'">
                        <article class="space-y-4 rounded-lg border p-5">
                            <h2 class="text-base font-medium">
                                Cómo se cuenta la capacidad
                            </h2>
                            <template v-if="payload.capacity_policy">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label>
                                        Cupos de usuarios
                                        <select
                                            v-model="
                                                payload.capacity_policy.users
                                            "
                                            aria-describedby="users-count-help"
                                        >
                                            <option value="shared_identity">
                                                Un cupo por persona, compartido
                                                entre soluciones
                                            </option>
                                            <option value="per_solution">
                                                Un cupo por persona y solución
                                            </option>
                                        </select>
                                    </label>
                                    <label class="check">
                                        <input
                                            type="checkbox"
                                            v-model="
                                                payload.capacity_policy
                                                    .owner_counts
                                            "
                                        />
                                        Incluir al propietario en el conteo de
                                        usuarios
                                    </label>
                                </div>
                                <p
                                    id="users-count-help"
                                    class="text-sm text-muted-foreground"
                                >
                                    La bolsa es por empresa. Al contar por
                                    solución, una persona con acceso a dos
                                    soluciones ocupa dos cupos de esa misma
                                    bolsa. Solo cuentan accesos activos.
                                </p>
                                <BusinessCapacitySummary
                                    :policy="payload.capacity_policy"
                                />
                            </template>
                            <template v-else>
                                <p class="text-sm text-muted-foreground">
                                    Esta versión todavía no define cómo contar
                                    usuarios, cajas y sucursales.
                                </p>
                                <Button
                                    v-if="editable"
                                    type="button"
                                    variant="outline"
                                    @click="initializeCapacityPolicy"
                                    >Usar propuesta inicial</Button
                                >
                                <p v-else class="text-sm text-muted-foreground">
                                    Crea una revisión para configurar estas
                                    reglas.
                                </p>
                            </template>
                            <p class="text-sm text-muted-foreground">
                                Guarda y publica una nueva versión para futuras
                                cotizaciones. Las cotizaciones y contratos
                                existentes conservan sus reglas.
                            </p>
                        </article>
                        <article
                            v-for="(capacity, code) in payload.capacities"
                            :key="code"
                            class="grid gap-4 rounded-lg border p-5 md:grid-cols-5"
                        >
                            <label>Nivel<input v-model="capacity.name" /></label
                            ><label
                                v-for="dimension in dimensions"
                                :key="dimension.code"
                                >{{ dimension.name
                                }}<input
                                    v-model.number="capacity[dimension.code]"
                                    type="number"
                                    min="1"
                            /></label>
                        </article>
                        <p class="text-sm text-muted-foreground">
                            Los documentos se renuevan mensualmente, también en
                            contratos anuales. Usuarios, cajas y sucursales son
                            límites simultáneos por empresa en las soluciones
                            contratadas.
                        </p></template
                    >
                    <template v-if="section === 'addons'"
                        ><article
                            v-for="(addon, code) in payload.addons"
                            :key="code"
                            class="space-y-4 rounded-lg border p-5"
                        >
                            <div class="grid gap-4 md:grid-cols-3">
                                <label
                                    >Adicional<input
                                        v-model="addon.name" /></label
                                ><label
                                    >Unidad<input v-model="addon.unit" /></label
                                ><label
                                    >Tamaño del bloque<input
                                        v-model.number="addon.block_size"
                                        type="number"
                                        min="1" /></label
                                ><label
                                    >Precio por bloque (RD$)<BusinessAmountInput
                                        v-model="addon.price_cents" /></label
                                ><label
                                    >Requiere producto<select
                                        v-model="addon.product"
                                    >
                                        <option :value="null">
                                            Cualquier oferta
                                        </option>
                                        <option
                                            v-for="(
                                                product, productCode
                                            ) in payload.products"
                                            :key="productCode"
                                            :value="productCode"
                                        >
                                            {{ product?.name }}
                                        </option>
                                    </select></label
                                ><label class="check"
                                    ><input
                                        v-model="addon.recurring"
                                        type="checkbox"
                                        @change="
                                            !addon.recurring &&
                                            (addon.capacity = {})
                                        "
                                    />Cargo recurrente mensual</label
                                >
                            </div>
                            <div
                                v-if="addon.recurring"
                                class="grid gap-4 md:grid-cols-4"
                            >
                                <label
                                    v-for="dimension in dimensions"
                                    :key="dimension.code"
                                    >{{ dimension.name }} por unidad<input
                                        :value="
                                            addon.capacity[dimension.code] ?? 0
                                        "
                                        type="number"
                                        min="0"
                                        @input="
                                            addon.capacity[dimension.code] =
                                                Number(
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                        "
                                /></label>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Pago único. No concede capacidad recurrente.
                            </p>
                        </article></template
                    >
                    <template v-if="section === 'implementations'"
                        ><article
                            v-for="(mode, code) in payload.implementations"
                            :key="code"
                            class="space-y-4 rounded-lg border p-5"
                        >
                            <div class="grid gap-4 md:grid-cols-3">
                                <label
                                    >Modalidad<input
                                        v-model="mode.name" /></label
                                ><label
                                    >Pago único (RD$)<BusinessAmountInput
                                        v-model="mode.price_cents" /></label
                                ><label
                                    >Horas incluidas<input
                                        v-model.number="mode.hours"
                                        type="number"
                                        min="0"
                                /></label>
                            </div>
                            <label
                                >Alcance incluido<textarea
                                    v-model="mode.scope"
                                    rows="3"
                                />
                            </label>
                            <p class="text-sm">
                                Ofertas con esta tarifa de implementación:
                            </p>
                            <div class="flex flex-wrap gap-4">
                                <label
                                    v-for="(offer, offerCode) in payload.offers"
                                    :key="offerCode"
                                    class="check"
                                >
                                    <input
                                        v-model="mode.offers"
                                        type="checkbox"
                                        :value="offerCode"
                                    />{{ offer.name }}
                                </label>
                            </div>
                        </article></template
                    >
                    <template v-if="section === 'discounts'"
                        ><p class="text-sm text-muted-foreground">
                            Una promoción o condición particular por cotización.
                            Su compatibilidad con paquetes y anualidad debe ser
                            explícita.
                        </p>
                        <p v-if="!Object.keys(payload.discounts).length">
                            No hay descuentos adicionales configurados.
                        </p>
                        <article
                            v-for="(discount, code) in payload.discounts"
                            :key="code"
                            class="space-y-4 rounded-lg border p-5"
                        >
                            <div class="grid gap-4 md:grid-cols-3">
                                <label
                                    >Nombre<input
                                        v-model="discount.name" /></label
                                ><label
                                    >Descuento (%)<BusinessAmountInput
                                        :model-value="discount.percent_bps"
                                        @update:model-value="
                                            discount.percent_bps = $event ?? 0
                                        " /></label
                                ><label
                                    >Empresa central (ID; vacío = promoción
                                    general)<input
                                        type="number"
                                        min="1"
                                        :value="discount.company_id"
                                        @input="
                                            discount.company_id =
                                                nullableInteger($event)
                                        " /></label
                                ><label
                                    >Inicio (hora local del navegador)<input
                                        type="datetime-local"
                                        :value="localDate(discount.starts_at)"
                                        @input="
                                            setDiscountDate(
                                                code,
                                                'starts_at',
                                                $event,
                                            )
                                        " /></label
                                ><label
                                    >Fin (hora local del navegador)<input
                                        type="datetime-local"
                                        :value="localDate(discount.ends_at)"
                                        @input="
                                            setDiscountDate(
                                                code,
                                                'ends_at',
                                                $event,
                                            )
                                        "
                                /></label>
                            </div>
                            <label
                                >Motivo y condiciones<textarea
                                    v-model="discount.reason"
                                    rows="2"
                                />
                            </label>
                            <div class="flex flex-wrap gap-4">
                                <label class="check"
                                    ><input
                                        v-model="discount.compatible_annual"
                                        type="checkbox"
                                    />Compatible con anualidad</label
                                ><label class="check"
                                    ><input
                                        v-model="discount.compatible_package"
                                        type="checkbox"
                                    />Compatible con paquetes</label
                                >
                            </div>
                            <p>Ofertas compatibles:</p>
                            <div class="flex flex-wrap gap-4">
                                <label
                                    v-for="(offer, offerCode) in payload.offers"
                                    :key="offerCode"
                                    class="check"
                                    ><input
                                        v-model="discount.offers"
                                        type="checkbox"
                                        :value="offerCode"
                                    />{{ offer.name }}</label
                                >
                            </div>
                            <Button
                                variant="outline"
                                @click="delete payload.discounts[code]"
                                >Quitar del borrador</Button
                            >
                        </article></template
                    >
                    <div
                        v-if="
                            [
                                'offers',
                                'capacities',
                                'addons',
                                'implementations',
                                'discounts',
                            ].includes(section)
                        "
                        class="flex flex-wrap items-end gap-3"
                    >
                        <label
                            >Código del nuevo concepto<input
                                v-model="newCode"
                                placeholder="ejemplo_nuevo" /></label
                        ><Button
                            variant="outline"
                            :disabled="busy || !editable"
                            @click="addEntry"
                            >Añadir</Button
                        >
                    </div>
                </fieldset>

                <section
                    v-if="section === 'general'"
                    class="space-y-4 border-t pt-5"
                >
                    <h2 class="text-lg font-medium">Publicación</h2>
                    <p class="text-sm text-muted-foreground">
                        Publicar habilita esta versión para nuevas cotizaciones
                        desde su vigencia. Las cotizaciones y contratos
                        anteriores conservan sus valores.
                    </p>
                    <template v-if="editable"
                        ><label class="max-w-md"
                            >Vigencia futura (hora local del navegador; vacío =
                            ahora)<input
                                v-model="effectiveAt"
                                type="datetime-local"
                                :disabled="busy" /></label
                        ><Button
                            :disabled="busy || dirty"
                            @click="action('publish')"
                            >{{
                                effectiveAt
                                    ? 'Programar publicación'
                                    : 'Publicar ahora'
                            }}</Button
                        >
                        <p v-if="dirty" class="text-sm">
                            Guarda el borrador antes de publicar.
                        </p></template
                    >
                    <details
                        v-else-if="selected.status === 'published'"
                        class="rounded-lg border p-4"
                    >
                        <summary class="cursor-pointer font-medium">
                            Retirar esta versión
                        </summary>
                        <p class="my-3 text-sm">
                            Impedirá nuevas cotizaciones de esta versión. No
                            reactiva versiones anteriores ni modifica
                            cotizaciones emitidas. Para retirar una sola oferta,
                            crea una revisión, deshabilítala y publica la
                            revisión.
                        </p>
                        <Button
                            variant="destructive"
                            :disabled="busy"
                            @click="action('retire')"
                            >Confirmar retiro de versión
                            {{ selected.version }}</Button
                        >
                    </details>
                </section>

                <section v-if="section === 'quote'" class="space-y-5">
                    <h2 class="text-lg font-medium">Simular cotización</h2>
                    <p class="text-sm text-muted-foreground">
                        Usa los valores guardados de la versión
                        {{ selected.version }}. La simulación no realiza cobros
                        ni activa servicios.
                    </p>
                    <form
                        class="flex flex-wrap items-end gap-3"
                        @submit.prevent="searchCompanies"
                    >
                        <label
                            >Buscar empresa<input
                                v-model="companySearch"
                                placeholder="Nombre de empresa"
                                minlength="2" /></label
                        ><Button
                            variant="outline"
                            :disabled="busy || companySearch.length < 2"
                            >Buscar</Button
                        ><label v-if="companies.length"
                            >Empresa<select v-model="companyId">
                                <option :value="null">
                                    Sin empresa (solo simulación)
                                </option>
                                <option
                                    v-for="company in companies"
                                    :key="company.id"
                                    :value="company.id"
                                >
                                    {{ company.name }} · #{{ company.id
                                    }}{{ company.active ? '' : ' (inactiva)' }}
                                </option>
                            </select></label
                        >
                    </form>
                    <form class="space-y-4" @submit.prevent="preview">
                        <div class="grid gap-4 md:grid-cols-4">
                            <label
                                >Producto o paquete<select
                                    v-model="selection.offer"
                                >
                                    <option
                                        v-for="(offer, code) in payload.offers"
                                        :key="code"
                                        :value="code"
                                        :disabled="!offer.available"
                                    >
                                        {{ offer.name }}
                                    </option>
                                </select></label
                            ><label
                                >Nivel<select v-model="selection.level">
                                    <option
                                        v-for="(
                                            capacity, code
                                        ) in payload.capacities"
                                        :key="code"
                                        :value="code"
                                        :disabled="
                                            !payload.offers[selection.offer]
                                                ?.levels[code]?.enabled
                                        "
                                    >
                                        {{ capacity.name }}
                                    </option>
                                </select></label
                            ><label
                                >Periodicidad<select v-model="selection.cycle">
                                    <option value="monthly">Mensual</option>
                                    <option value="yearly">Anual</option>
                                </select></label
                            ><label
                                >Implementación<select
                                    v-model="selection.implementation"
                                >
                                    <option
                                        v-for="(
                                            mode, code
                                        ) in payload.implementations"
                                        :key="code"
                                        :value="code"
                                        :disabled="
                                            !mode.offers.includes(
                                                selection.offer,
                                            )
                                        "
                                    >
                                        {{ mode.name }}
                                    </option>
                                </select></label
                            >
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <label
                                v-for="(addon, code) in payload.addons"
                                :key="code"
                                >{{ addon.name }} (cantidad de bloques)<input
                                    v-model.number="selection.addons[code]"
                                    type="number"
                                    min="0"
                                    placeholder="0" /></label
                            ><label
                                >Promoción o condición<select
                                    v-model="selection.discount"
                                >
                                    <option :value="null">
                                        Sin descuento adicional
                                    </option>
                                    <option
                                        v-for="(
                                            discount, code
                                        ) in payload.discounts"
                                        :key="code"
                                        :value="code"
                                    >
                                        {{ discount.name }}
                                    </option>
                                </select></label
                            >
                        </div>
                        <Button :disabled="busy || dirty"
                            >Calcular cotización</Button
                        >
                        <p v-if="dirty" class="text-sm">
                            Guarda tus cambios para simularlos.
                        </p>
                    </form>
                    <div
                        v-if="result"
                        aria-live="polite"
                        class="space-y-4 rounded-lg border p-5"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Recurrencia
                                    {{
                                        result.billing_cycle === 'yearly'
                                            ? 'anual'
                                            : 'mensual'
                                    }}
                                    antes de impuestos
                                </p>
                                <p class="text-2xl font-semibold">
                                    {{ money(result.recurring_cents) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Implementación y adicionales únicos antes de
                                    impuestos
                                </p>
                                <p class="text-2xl font-semibold">
                                    {{ money(result.one_time_cents) }}
                                </p>
                            </div>
                        </div>
                        <p class="text-sm">
                            Descuento anual:
                            {{ money(result.annual_discount_cents) }} ·
                            Descuento adicional:
                            {{ money(result.commercial_discount_cents) }}
                        </p>
                        <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div
                                v-for="dimension in dimensions"
                                :key="dimension.code"
                            >
                                <dt class="text-sm text-muted-foreground">
                                    {{ dimension.name }}
                                </dt>
                                <dd class="font-medium">
                                    {{ result.capacity[dimension.code] }}
                                </dd>
                            </div>
                        </dl>
                        <BusinessCapacitySummary
                            :policy="result.capacity_policy"
                            :has-pos="result.pos_includes_standard_ecommerce"
                        />
                        <p v-if="!result.taxes" class="text-sm">
                            Impuestos pendientes de configurar. No se puede
                            emitir hasta publicar condiciones completas.
                        </p>
                        <div v-else class="space-y-1 text-sm">
                            <p>
                                Impuesto recurrente:
                                {{
                                    money(
                                        result.taxes.recurring.tax_amount * 100,
                                    )
                                }}
                                · Total recurrente:
                                {{
                                    money(
                                        result.taxes.recurring.line_total * 100,
                                    )
                                }}
                            </p>
                            <p>
                                Impuesto único:
                                {{
                                    money(
                                        result.taxes.one_time.tax_amount * 100,
                                    )
                                }}
                                · Total único:
                                {{
                                    money(
                                        result.taxes.one_time.line_total * 100,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            v-if="result.recommendation"
                            class="space-y-2 border-t pt-4"
                        >
                            <p>
                                El nivel {{ result.recommendation.name }} cubre
                                esta capacidad y ahorra
                                {{ money(result.recommendation.savings_cents) }}
                                por período.
                            </p>
                            <Button
                                variant="outline"
                                :disabled="busy"
                                @click="
                                    selection = JSON.parse(
                                        JSON.stringify(
                                            result.recommendation.selection,
                                        ),
                                    )
                                "
                                >Revisar esta alternativa</Button
                            >
                        </div>
                        <div
                            v-if="result.transition_requires_review"
                            class="space-y-4"
                        >
                            <template v-if="result.transition_review?.eligible">
                                <BusinessTransitionSummary
                                    :plan="result.transition_review"
                                />
                                <label
                                    class="block space-y-2 text-sm font-medium"
                                    for="transition-target"
                                >
                                    <span>Suscripción que se conservará</span>
                                    <select
                                        id="transition-target"
                                        v-model="transitionTarget"
                                        :disabled="busy || !!quoteId"
                                        class="min-h-11 w-full rounded-md border bg-background px-3"
                                    >
                                        <option :value="null" disabled>
                                            Selecciona una suscripción
                                        </option>
                                        <option
                                            v-for="source in result
                                                .transition_review
                                                .subscriptions"
                                            :key="source.id"
                                            :value="source.id"
                                        >
                                            #{{ source.id }} ·
                                            {{
                                                source.billing_cycle ===
                                                'yearly'
                                                    ? 'Anual'
                                                    : 'Mensual'
                                            }}
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="transition-consent min-h-11 cursor-pointer text-sm leading-6"
                                >
                                    <input
                                        id="transition-confirmed"
                                        v-model="transitionConfirmed"
                                        type="checkbox"
                                        :disabled="busy || !!quoteId"
                                        class="mt-1 size-4 shrink-0 accent-primary"
                                    />
                                    <span
                                        >He revisado los períodos y propongo
                                        consolidar al vencer lo pagado, con
                                        crédito, devolución y prorrateo de cero.
                                        La empresa deberá aceptar estas
                                        condiciones.</span
                                    >
                                </label>
                            </template>
                            <div
                                v-else
                                role="status"
                                class="space-y-2 border-t pt-4 text-sm leading-6"
                            >
                                <p class="font-medium">
                                    La transición requiere revisión
                                </p>
                                <p>
                                    {{
                                        result.transition_review?.reason ??
                                        'Revisa los servicios y períodos pagados antes de emitir.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            El inicio de nuevos cargos se define en contratación
                            y entrada en operación.
                        </p>
                        <Button
                            :disabled="
                                busy ||
                                !companyId ||
                                selected.id !== currentId ||
                                !transitionReady ||
                                !!quoteId
                            "
                            @click="issue"
                            >Emitir cotización con estas condiciones</Button
                        >
                        <p v-if="quoteId" class="text-sm break-all">
                            Cotización: {{ quoteId }}
                        </p>
                    </div>
                </section>

                <section v-if="section === 'history'" class="space-y-6">
                    <h2 class="text-lg font-medium">Cambios de esta versión</h2>
                    <p v-if="!audit.length">No hay cambios registrados.</p>
                    <details
                        v-for="entry in audit"
                        :key="entry.id"
                        class="rounded-lg border p-4"
                    >
                        <summary class="cursor-pointer">
                            {{ entry.user?.name ?? 'Sistema' }} ·
                            {{
                                new Date(entry.created_at).toLocaleString(
                                    'es-DO',
                                )
                            }}
                            · {{ entry.event }}
                        </summary>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th>Campo</th>
                                        <th>Anterior</th>
                                        <th>Nuevo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="change in changes(
                                            entry.data.before,
                                            entry.data.after,
                                        )"
                                        :key="change.path"
                                    >
                                        <td>{{ change.path }}</td>
                                        <td class="max-w-sm break-words">
                                            {{ change.before }}
                                        </td>
                                        <td class="max-w-sm break-words">
                                            {{ change.after }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <h2 class="text-lg font-medium">
                        Cotizaciones emitidas recientemente
                    </h2>
                    <p v-if="!quotes.length">
                        Todavía no se han emitido cotizaciones.
                    </p>
                    <ul class="space-y-2">
                        <li v-for="quote in quotes" :key="quote.id">
                            <a
                                class="text-sm break-all underline"
                                :href="`${base}/quotes/${quote.id}`"
                                target="_blank"
                                rel="noopener"
                                >{{ quote.public_id }}</a
                            ><span class="ml-2 text-sm text-muted-foreground"
                                >Empresa #{{ quote.company_id }} · válida hasta
                                {{
                                    new Date(quote.valid_until).toLocaleString(
                                        'es-DO',
                                    )
                                }}</span
                            >
                        </li>
                    </ul>
                </section>
            </template>
        </main>
    </AppLayout>
</template>

<style scoped>
@reference '../../../../css/app.css';
.business-catalog label {
    @apply block space-y-1.5 text-sm font-medium;
}
.business-catalog input:not([type='checkbox']),
.business-catalog select,
.business-catalog textarea {
    @apply block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60;
}
.business-catalog .check {
    @apply flex items-center gap-2 space-y-0;
}
.business-catalog .transition-consent {
    @apply flex items-start gap-3 space-y-0 font-normal;
}
.business-catalog input[type='checkbox'] {
    @apply h-4 w-4 accent-primary;
}
.business-catalog th,
.business-catalog td {
    @apply border-b p-2 align-top;
}
</style>
