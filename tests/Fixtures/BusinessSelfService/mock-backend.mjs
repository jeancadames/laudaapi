/**
 * Doble de pruebas del autoservicio Business · contrato v1.
 *
 * Responde con las muestras exportadas por docs/autoservicio/contrato-v1.ts
 * (se inyectan en `samples`). Solo cuando una selección no tiene muestra
 * genera una respuesta sintética con la misma forma, usando las tarifas del
 * catálogo inicial de prueba. No es el motor de precios: la referencia es la
 * respuesta de Laravel.
 */

export const SCENARIOS = {
    exito: 'Catálogo inicial al 0 % (muestras del contrato)',
    'con-impuestos': 'Tasa positiva de prueba (18 %) sobre las mismas muestras',
    'cargo-unico': 'Incluye un cargo único de implementación',
    'renovacion-automatica': 'Propuesta con automatic_renewal: true',
    'sin-catalogo': 'Catálogo sin publicar (503 catalog_unavailable)',
    'sin-permiso': 'Usuario sin permiso (403 forbidden)',
    'seleccion-invalida': 'BYS no tiene tarifa (422 invalid_selection)',
    'precio-actualizado': 'El catálogo cambia antes de confirmar (409 price_changed)',
    'red-lenta': 'La primera respuesta llega después de la segunda',
    'sin-conexion': 'El primer cálculo falla por red',
};

/** Tarifas mensuales del catálogo inicial (BusinessCatalogSeeder), solo para muestras sintéticas. */
const OFFERS = [
    { code: 'ecf', name: 'e-CF', products: ['ecf'], monthly: 150000 },
    { code: 'pos', name: 'POS', products: ['pos'], monthly: 150000 },
    { code: 'cumplimiento', name: 'Cumplimiento', products: ['cumplimiento'], monthly: 100000 },
    { code: 'bys', name: 'BYS', products: ['bys'], monthly: 100000 },
    { code: 'fiscal', name: 'Fiscal', products: ['ecf', 'cumplimiento'], monthly: 200000 },
    { code: 'comercial', name: 'Comercial', products: ['ecf', 'pos'], monthly: 250000 },
    { code: 'business', name: 'Business', products: ['ecf', 'pos', 'cumplimiento', 'bys'], monthly: 350000 },
];

const clone = (value) => JSON.parse(JSON.stringify(value));
const json = (status, body, headers = {}) =>
    new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
const sameSelection = (a, b) =>
    a.cycle === b.cycle && a.level === b.level && a.products.length === b.products.length && a.products.every((code) => b.products.includes(code));

function cheapestCover(products) {
    let best = null;
    for (let mask = 1; mask < 1 << OFFERS.length; mask++) {
        const chosen = OFFERS.filter((_, index) => mask & (1 << index));
        const covered = chosen.flatMap((offer) => offer.products);
        if (covered.length !== products.length || !products.every((code) => covered.includes(code))) continue;
        const cost = chosen.reduce((sum, offer) => sum + offer.monthly, 0);
        if (!best || cost < best.cost || (cost === best.cost && chosen.length < best.chosen.length)) best = { cost, chosen };
    }
    return best;
}

export function createMockBackend({ samples, scenario = 'exito', delay = 0, base = '/app/business/self-service/v1' }) {
    const urls = { catalog_url: `${base}/catalog`, calculate_url: `${base}/preview`, confirm_url: `${base}/purchases` };
    if (!(scenario in SCENARIOS)) throw new Error(`Escenario desconocido: ${scenario}`);
    const state = { previews: 0, confirmations: 0, purchases: new Map(), proposals: new Map(), requests: [], priceChanged: false };
    const order = samples.catalog.products.map((product) => product.code);
    const amount = (net, rateBps) => {
        const tax = Math.round((net * rateBps) / 10000);
        return { net_cents: net, tax_cents: tax, total_cents: net + tax };
    };

    function synthetic(selection) {
        const products = order.filter((code) => selection.products.includes(code));
        const cover = cheapestCover(products);
        const perCycle = (monthly) => (selection.cycle === 'yearly' ? monthly * 12 - Math.round(monthly * 12 * 0.1) : monthly);
        return {
            ...clone(samples.preview),
            proposal_ref: `synthetic_${products.join('_')}_${selection.cycle}`,
            selection: { ...selection, products },
            components: cover.chosen.map((offer) => ({ offer_code: offer.code, name: offer.name, products: offer.products, net_cents: perCycle(offer.monthly) })),
            capacity: { ...samples.preview.capacity, ...(products.includes('pos') ? { branches: 1, registers: 2 } : {}) },
            recurring: amount(perCycle(cover.cost), 0),
        };
    }

    /** Respuesta del servidor para una selección: muestra del contrato si existe. */
    function previewFor(selection) {
        const sample = [samples.preview, samples.customPreview].find((candidate) => sameSelection(candidate.selection, selection));
        const preview = sample ? clone(sample) : synthetic(selection);
        const rate = scenario === 'con-impuestos' ? 1800 : 0;
        if (scenario === 'cargo-unico') preview.one_time = amount(600000, 0);
        preview.recurring = amount(preview.recurring.net_cents, rate);
        preview.one_time = amount(preview.one_time.net_cents, rate);
        // El servidor suma; el navegador nunca.
        preview.due_today = {
            net_cents: preview.recurring.net_cents + preview.one_time.net_cents,
            tax_cents: preview.recurring.tax_cents + preview.one_time.tax_cents,
            total_cents: preview.recurring.total_cents + preview.one_time.total_cents,
        };
        preview.due_today_cents = preview.due_today.total_cents;
        if (scenario === 'renovacion-automatica') preview.automatic_renewal = true;
        // Una propuesta con otras condiciones lleva otra referencia.
        if (rate || ['cargo-unico', 'renovacion-automatica'].includes(scenario)) preview.proposal_ref += `_${scenario}`;
        state.proposals.set(preview.proposal_ref, preview);
        return preview;
    }

    function catalog() {
        if (scenario === 'sin-catalogo') return json(503, samples.catalogUnavailable);
        if (scenario === 'sin-permiso')
            return json(403, { code: 'forbidden', message: `Solo el propietario o un administrador de ${samples.catalog.company_name} puede contratar.` });
        return json(200, clone(samples.catalog));
    }

    async function preview(body) {
        const turn = ++state.previews;
        const wait = scenario === 'red-lenta' ? (turn === 1 ? 1500 : 150) : delay;
        if (wait) await new Promise((resolve) => setTimeout(resolve, wait));
        if (scenario === 'sin-conexion' && turn === 1) throw new TypeError('Failed to fetch');
        if (!Array.isArray(body.products) || body.products.length === 0) return json(422, samples.invalidSelection);
        const retired = scenario === 'seleccion-invalida' ? body.products.indexOf('bys') : -1;
        if (retired >= 0)
            return json(422, {
                code: 'invalid_selection',
                message: samples.invalidSelection.message,
                fields: { [`products.${retired}`]: ['BYS no tiene tarifa publicada para este nivel.'] },
            });
        return json(200, previewFor(body));
    }

    async function confirm(body) {
        state.confirmations++;
        if (delay) await new Promise((resolve) => setTimeout(resolve, delay));
        const existing = state.purchases.get(body.idempotency_key);
        if (existing) return json(200, existing);
        const reviewed = state.proposals.get(body.proposal_ref) ?? (body.proposal_ref === samples.priceChanged.replacement.proposal_ref ? samples.priceChanged.replacement : null);
        if (!reviewed || body.accepted !== true) return json(409, { code: 'proposal_expired', message: 'La propuesta venció. Vuelve a revisar el precio.' });
        if (scenario === 'precio-actualizado' && !state.priceChanged) {
            state.priceChanged = true;
            return json(409, samples.priceChanged);
        }
        const purchase = { ...clone(samples.payable), summary: clone(reviewed), balance_cents: reviewed.due_today_cents };
        state.purchases.set(body.idempotency_key, purchase);
        return json(201, purchase);
    }

    /** Sustituto de fetch para las rutas v1. */
    async function fetch(url, init = {}) {
        const method = init.method ?? 'GET';
        const body = init.body ? JSON.parse(init.body) : null;
        const path = new URL(String(url), 'http://lauda.local').pathname;
        state.requests.push({ method, path, body });
        const abort = new Promise((_, reject) =>
            init.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError'))),
        );
        const route = path.slice(base.length);
        const work =
            method === 'GET' && route === '/catalog'
                ? Promise.resolve(catalog())
                : method === 'POST' && route === '/preview'
                  ? preview(body)
                  : method === 'POST' && route === '/purchases'
                    ? confirm(body)
                    : Promise.resolve(json(404, {}));
        return Promise.race([work, abort]);
    }

    /** Prop `configurator` que Laravel enviaría a Business/Package/Build. */
    function configurator({ homeUrl = '/app', purchases = false } = {}) {
        const unpublished = scenario === 'sin-catalogo';
        return {
            company: { id: 77, name: samples.catalog.company_name },
            availability: unpublished ? 'catalog_unpublished' : 'available',
            catalog: unpublished ? null : clone(samples.catalog),
            ...urls,
            confirm_url: purchases ? urls.confirm_url : null,
            home_url: homeUrl,
        };
    }

    return { fetch, state, base, urls, configurator };
}
