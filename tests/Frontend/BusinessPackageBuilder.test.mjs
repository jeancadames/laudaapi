/**
 * «Armar mi paquete» contra las muestras del contrato v1.
 *
 *   SELF_SERVICE_CONTRACT=/ruta/docs/autoservicio/contrato-v1.ts \
 *     node --test tests/Frontend/BusinessPackageBuilder.test.mjs
 */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { build } from 'esbuild';
import { contractPath, repositoryRoot as root } from '../Fixtures/BusinessSelfService/contract.mjs';
import { createMockBackend } from '../Fixtures/BusinessSelfService/mock-backend.mjs';

const CONTRACT = contractPath();
const vue = JSON.stringify(pathToFileURL(path.join(root, 'node_modules/vue/index.mjs')).href);

// Compilar TypeScript en memoria, sin Vite, servidor ni dependencias nuevas.
async function load(contents) {
    const compiled = await build({
        stdin: { contents, resolveDir: root, loader: 'ts' },
        bundle: true,
        write: false,
        format: 'esm',
        platform: 'node',
        alias: { '@': path.join(root, 'resources/js') },
        external: ['vue'],
    });
    const code = compiled.outputFiles[0].text.replaceAll(/from\s*"vue"/g, `from ${vue}`);
    return import('data:text/javascript;base64,' + Buffer.from(code).toString('base64'));
}

const samples = await load(`export * from ${JSON.stringify(CONTRACT)};`);
const {
    useBusinessPackageBuilder, useBusinessPurchase, createSelfServiceClient, SelfServiceError, purchaseUrl, isPurchaseId, purchaseView,
    formatMoney, inclusionsOf, showsTax, cycleCopy,
} = await load(`
    export { useBusinessPackageBuilder } from '@/composables/useBusinessPackageBuilder';
    export { useBusinessPurchase } from '@/composables/useBusinessPurchase';
    export { createSelfServiceClient, SelfServiceError, purchaseUrl, isPurchaseId } from '@/lib/businessSelfService';
    export { purchaseView } from '@/lib/businessPurchaseStatus';
    export { cycleCopy, formatMoney, inclusionsOf, showsTax } from '@/lib/businessPackageCopy';
`);

const memory = (seed = {}) => {
    const data = new Map(Object.entries(seed));
    return { data, getItem: (key) => data.get(key) ?? null, setItem: (key, value) => data.set(key, value), removeItem: (key) => data.delete(key) };
};
const STORAGE_KEY = 'lauda.business.package.v1.77';

function mount(scenario = 'exito', { storage = memory(), backend = createMockBackend({ samples, scenario }) } = {}) {
    let keys = 0;
    const builder = useBusinessPackageBuilder({
        catalog: samples.catalog,
        client: createSelfServiceClient(backend.urls, backend.fetch),
        storage,
        storageScope: 77,
        debounceMs: 0,
        newKey: () => `key-${++keys}`,
    });
    return { builder, backend, storage };
}

async function settled(builder, timeout = 4000) {
    const started = Date.now();
    while (builder.calculating.value || builder.confirming.value) {
        if (Date.now() - started > timeout) throw new Error('La operación no terminó');
        await new Promise((resolve) => setTimeout(resolve, 5));
    }
}
const money = (cents) => formatMoney(cents, 'DOP');
const previews = (backend) => backend.state.requests.filter((request) => request.path.endsWith('/preview'));
const confirmations = (backend) => backend.state.requests.filter((request) => request.path.endsWith('/purchases'));

test('los tipos del frontend coinciden con el contrato v1', () => {
    const dir = mkdtempSync(path.join(os.tmpdir(), 'lauda-contract-'));
    try {
        const ours = path.join(root, 'resources/js/types/businessSelfService.ts');
        const names = ['Product', 'Cycle', 'Level', 'Selection', 'Catalog', 'Configurator', 'Preview', 'Amount', 'Confirm', 'Payment', 'Access', 'Purchase', 'Problem'];
        writeFileSync(
            path.join(dir, 'check.ts'),
            [
                `import type * as C from ${JSON.stringify(CONTRACT.replace(/\.ts$/, ''))};`,
                `import type * as F from ${JSON.stringify(ours.replace(/\.ts$/, ''))};`,
                'type Equal<A, B> = (<T>() => T extends A ? 1 : 2) extends (<T>() => T extends B ? 1 : 2) ? true : false;',
                ...names.map((name) => `export const ${name.toLowerCase()}: Equal<C.${name}, F.${name}> = true;`),
            ].join('\n'),
        );
        writeFileSync(
            path.join(dir, 'tsconfig.json'),
            JSON.stringify({ compilerOptions: { strict: true, noEmit: true, skipLibCheck: true, target: 'es2022', module: 'esnext', moduleResolution: 'bundler' }, files: ['check.ts'] }),
        );
        execFileSync(path.join(root, 'node_modules/.bin/tsc'), ['-p', dir], { stdio: 'pipe' });
    } catch (error) {
        assert.fail(`Los tipos difieren del contrato:\n${error.stdout ?? error.message}`);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('catálogo: tareas, aplicación e inclusión de Fácil ECF vienen del servidor', async () => {
    const backend = createMockBackend({ samples });
    const catalog = await createSelfServiceClient(backend.urls, backend.fetch).catalog();
    assert.deepEqual(catalog, samples.catalog);
    assert.deepEqual(backend.state.requests[0], { method: 'GET', path: backend.urls.catalog_url, body: null });
    const [facil] = inclusionsOf(catalog.products.find((product) => product.code === 'ecf'));
    assert.deepEqual(facil, { offer: 'Incluye Fácil ECF', granted: 'Fácil ECF incluido', help: 'Prepara tus facturas con un formulario, sin programar.' });
    assert.deepEqual(inclusionsOf(catalog.products.find((product) => product.code === 'pos')).map((item) => item.offer), ['Incluye comercio electrónico estándar']);
    assert.deepEqual(inclusionsOf(catalog.products.find((product) => product.code === 'cumplimiento')), []);
});

test('las muestras del contrato son coherentes: due_today.total_cents = due_today_cents', () => {
    for (const preview of [samples.preview, samples.customPreview, samples.priceChanged.replacement])
        assert.equal(preview.due_today.total_cents, preview.due_today_cents, preview.proposal_ref);
});

test('e-CF + Cumplimiento al 0 %: «Pagas hoy RD$2,000», Fiscal y sin fila de impuestos', async () => {
    const { builder, backend } = mount();
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);

    assert.deepEqual(previews(backend).at(-1).body, { products: ['ecf', 'cumplimiento'], level: 'initial', cycle: 'monthly' });
    const proposal = builder.proposal.value;
    assert.deepEqual(proposal, samples.preview, 'la interfaz conserva la respuesta tal como llega');
    assert.equal(builder.proposalIsCurrent.value, true);
    assert.equal(money(proposal.due_today.total_cents), 'RD$2,000');
    assert.equal(money(proposal.recurring.total_cents), 'RD$2,000');
    assert.equal(showsTax(proposal.recurring, proposal.one_time, proposal.due_today), false);
    assert.equal(cycleCopy[proposal.selection.cycle].price, 'Precio mensual');
    assert.equal(proposal.automatic_renewal, false);
});

test('añadir BYS da RD$3,000 y quitarlo vuelve a RD$2,000', async () => {
    const { builder, backend } = mount();
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.toggle('bys');
    await settled(builder);
    assert.deepEqual(builder.proposal.value, samples.customPreview);
    assert.equal(money(builder.proposal.value.due_today.total_cents), 'RD$3,000');
    assert.deepEqual(builder.proposal.value.components.map((component) => [component.name, component.net_cents]), [['Fiscal', 200000], ['BYS', 100000]]);
    assert.deepEqual(builder.proposal.value.selection.products, ['ecf', 'cumplimiento', 'bys']);

    builder.toggle('bys');
    await settled(builder);
    assert.equal(money(builder.proposal.value.due_today.total_cents), 'RD$2,000');
    assert.deepEqual(previews(backend).at(-1).body.products, ['ecf', 'cumplimiento']);
});

test('con tasa positiva, el desglose de impuestos aparece con los importes del servidor', async () => {
    const { builder } = mount('con-impuestos');
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    const proposal = builder.proposal.value;
    assert.deepEqual(proposal.recurring, { net_cents: 200000, tax_cents: 36000, total_cents: 236000 });
    assert.deepEqual(proposal.due_today, { net_cents: 200000, tax_cents: 36000, total_cents: 236000 });
    assert.equal(showsTax(proposal.recurring, proposal.one_time, proposal.due_today), true);
    assert.equal(money(proposal.due_today.total_cents), 'RD$2,360');
});

test('el ciclo anual pide un precio nuevo y conserva el nivel', async () => {
    const { builder, backend } = mount();
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.setCycle('yearly');
    assert.equal(builder.proposalIsCurrent.value, false, 'no se avanza con el precio mensual');
    await settled(builder);
    assert.deepEqual(previews(backend).at(-1).body, { products: ['ecf', 'cumplimiento'], level: 'initial', cycle: 'yearly' });
    assert.equal(builder.proposal.value.selection.cycle, 'yearly');
    assert.equal(cycleCopy.yearly.price, 'Precio anual');
});

test('descarta respuestas atrasadas: gana la última selección', async () => {
    const { builder, backend } = mount('red-lenta');
    builder.toggle('ecf');
    await new Promise((resolve) => setTimeout(resolve, 20));
    builder.toggle('cumplimiento');
    await settled(builder);
    assert.deepEqual(builder.proposal.value.selection.products, ['ecf', 'cumplimiento']);
    await new Promise((resolve) => setTimeout(resolve, 1700));
    assert.equal(builder.proposal.value.due_today.total_cents, 200000);
    assert.equal(builder.proposalIsCurrent.value, true);
    assert.equal(previews(backend).length, 2);
});

test('sin opciones marcadas no se pide precio', async () => {
    const { builder, backend } = mount();
    builder.toggle('ecf');
    await settled(builder);
    builder.toggle('ecf');
    assert.equal(builder.proposal.value, null);
    assert.equal(builder.calculating.value, false);
    assert.equal(previews(backend).length, 1);
});

test('conserva selección, nivel y ciclo al volver o recargar', async () => {
    const first = mount();
    first.builder.toggle('ecf');
    first.builder.toggle('cumplimiento');
    first.builder.setCycle('yearly');
    await settled(first.builder);
    assert.deepEqual(JSON.parse(first.storage.data.get(STORAGE_KEY)), { products: ['ecf', 'cumplimiento'], level: 'initial', cycle: 'yearly', confirm: null });

    const again = mount('exito', { storage: first.storage });
    assert.deepEqual(again.builder.selection.value, { products: ['ecf', 'cumplimiento'], level: 'initial', cycle: 'yearly' });
    await settled(again.builder);
    assert.equal(again.builder.proposalIsCurrent.value, true, 'recalcula sola al volver');
});

test('ignora almacenamiento dañado y opciones que el catálogo ya no ofrece', () => {
    for (const [value, products] of [
        ['{no json', []],
        [JSON.stringify({ products: ['ecf', 'nomina'], level: 'enterprise', cycle: 'weekly' }), ['ecf']],
    ]) {
        const { builder } = mount('exito', { storage: memory({ [STORAGE_KEY]: value }) });
        assert.deepEqual(builder.selection.value, { products, level: 'initial', cycle: 'monthly' });
        builder.dispose();
    }
});

test('invalid_selection: señala el producto por `products.N` y muestra el motivo', async () => {
    const { builder } = mount('seleccion-invalida');
    builder.toggle('ecf');
    builder.toggle('bys');
    await settled(builder);
    const error = builder.calculationError.value;
    assert.equal(error.code, 'invalid_selection');
    assert.equal(error.message, samples.invalidSelection.message);
    assert.deepEqual(error.invalidProducts, ['bys']);
    assert.deepEqual(error.details, ['BYS no tiene tarifa publicada para este nivel.']);
    assert.deepEqual(builder.selected.value, ['ecf', 'bys'], 'conserva la selección');
    builder.toggle('bys');
    await settled(builder);
    assert.equal(builder.calculationError.value, null);
    assert.equal(builder.proposalIsCurrent.value, true);
});

test('fallo de red al calcular: mensaje claro y reintento', async () => {
    const { builder } = mount('sin-conexion');
    builder.toggle('ecf');
    await settled(builder);
    assert.equal(builder.calculationError.value.code, 'network');
    builder.retry();
    await settled(builder);
    assert.equal(builder.calculationError.value, null);
    assert.equal(builder.proposal.value.due_today.total_cents, 150000);
});

test('si el servidor ajusta la selección, la interfaz la adopta y lo avisa', async () => {
    const backend = createMockBackend({ samples });
    const adjusting = { ...backend, fetch: async (url, init) => {
        const response = await backend.fetch(url, init);
        if (!String(url).endsWith('/preview')) return response;
        return new Response(JSON.stringify(samples.preview), { status: 200 });
    } };
    const { builder } = mount('exito', { backend: adjusting });
    builder.toggle('ecf');
    await settled(builder);
    assert.equal(builder.adjusted.value, true);
    assert.deepEqual(builder.selected.value, ['ecf', 'cumplimiento']);
});

test('confirmar envía exactamente el cuerpo Confirm del contrato', async () => {
    const { builder, backend } = mount();
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    await builder.confirm();
    assert.equal(confirmations(backend).length, 0, 'sin aceptación no se confirma');

    builder.accepted.value = true;
    builder.toggle('bys'); // cambiar la selección retira la aceptación
    assert.equal(builder.accepted.value, false);
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();
    assert.deepEqual(confirmations(backend)[0].body, { proposal_ref: samples.customPreview.proposal_ref, accepted: true, idempotency_key: 'key-1' });
    assert.deepEqual(builder.purchase.value.payment, samples.payable.payment);
});

test('doble clic: una sola compra', async () => {
    const { builder, backend, storage } = mount();
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.accepted.value = true;
    await Promise.all([builder.confirm(), builder.confirm()]);
    assert.equal(confirmations(backend).length, 1);
    assert.equal(backend.state.purchases.size, 1);
    assert.equal(storage.data.has(STORAGE_KEY), false, 'la compra ya vive en el servidor');
});

test('respuesta perdida: el reintento reutiliza la misma clave', async () => {
    const backend = createMockBackend({ samples });
    let lose = true;
    const lossy = { ...backend, fetch: async (url, init) => {
        const response = await backend.fetch(url, init);
        if (String(url).endsWith('/purchases') && lose) {
            lose = false;
            throw new TypeError('Failed to fetch');
        }
        return response;
    } };
    const { builder } = mount('exito', { backend: lossy });
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(builder.confirmError.value.code, 'network');
    assert.equal(builder.accepted.value, true, 'no hay que volver a aceptar para reintentar');
    await builder.confirm();
    assert.equal(builder.confirmError.value, null);
    assert.equal(backend.state.purchases.size, 1, 'una compra pese a dos envíos');
    assert.deepEqual(confirmations(backend).map((request) => request.body.idempotency_key), ['key-1', 'key-1']);
});

test('price_changed: muestra `replacement`, conserva la selección y pide aceptar de nuevo', async () => {
    const { builder, backend } = mount('precio-actualizado');
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();

    assert.equal(builder.purchase.value, null);
    assert.equal(backend.state.purchases.size, 0, 'no se cobra con el precio anterior');
    assert.equal(builder.accepted.value, false);
    assert.equal(builder.priceChange.value.previous.due_today.total_cents, 200000);
    assert.deepEqual(builder.proposal.value, samples.priceChanged.replacement);
    assert.equal(money(builder.proposal.value.due_today.total_cents), 'RD$2,100');
    assert.deepEqual(builder.selected.value, ['ecf', 'cumplimiento']);

    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(builder.purchase.value.purchase_id, samples.payable.purchase_id);
    assert.deepEqual(confirmations(backend).map((request) => [request.body.proposal_ref, request.body.idempotency_key]), [
        [samples.preview.proposal_ref, 'key-1'],
        [samples.priceChanged.replacement.proposal_ref, 'key-2'],
    ]);
});

test('el adaptador traduce los errores HTTP y de dominio', async () => {
    const respond = (status, body, headers) => async () => new Response(body === undefined ? '' : JSON.stringify(body), { status, headers });
    const cases = [
        [503, samples.catalogUnavailable, undefined, 'catalog_unavailable', samples.catalogUnavailable.message],
        [403, undefined, undefined, 'forbidden', /propietario o un administrador/],
        [419, undefined, undefined, 'session_expired', /sesión venció/],
        [429, undefined, { 'Retry-After': '30' }, 'throttled', /Espera 30 segundos/],
        [503, undefined, undefined, 'temporarily_unavailable', /no está disponible/],
        [500, { message: 'SQLSTATE[HY000]' }, undefined, 'unknown', /No pudimos completar/],
    ];
    for (const [status, body, headers, code, message] of cases) {
        const client = createSelfServiceClient({ catalog_url: '/v1/catalog', calculate_url: '/v1/preview', confirm_url: null }, respond(status, body, headers));
        const error = await client.catalog().then(() => null, (failure) => failure);
        assert.ok(error instanceof SelfServiceError, `${status}`);
        assert.equal(error.code, code, `${status}`);
        if (message instanceof RegExp) assert.match(error.message, message);
        else assert.equal(error.message, message);
    }
});

test('sin confirm_url (bloque 2) confirmar no envía nada', async () => {
    const backend = createMockBackend({ samples });
    const urls = { ...backend.urls, confirm_url: null };
    const { builder } = mount('exito', { backend: { ...backend, urls } });
    builder.toggle('ecf');
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(confirmations(backend).length, 0);
    assert.equal(builder.purchase.value, null);
    assert.match(builder.confirmError.value.message, /todavía no está disponible/);
});

test('la prop configurator del doble coincide con la forma del contrato', () => {
    const page = createMockBackend({ samples }).configurator();
    assert.deepEqual(Object.keys(page).sort(), ['availability', 'calculate_url', 'catalog', 'catalog_url', 'company', 'confirm_url', 'home_url', 'resume_purchase_id']);
    assert.equal(page.confirm_url, null);
    assert.equal(page.resume_purchase_id, null);
    assert.equal(createMockBackend({ samples, scenario: 'sin-catalogo' }).configurator().catalog, null);
});

// ───────────────────────── Bloque 3: confirmar, pagar y retomar ─────────────────────────

const purchaseReads = (backend) => backend.state.requests.filter((request) => request.method === 'GET' && request.path.includes('/purchases/'));

function track(scenario, purchaseId, { initial = null, pollDelaysMs = [0, 0, 0, 0] } = {}) {
    const backend = createMockBackend({ samples, scenario });
    const tracker = useBusinessPurchase({
        client: createSelfServiceClient(backend.urls, backend.fetch),
        purchaseId,
        initial,
        names: Object.fromEntries(samples.catalog.products.map((product) => [product.code, product.name])),
        pollDelaysMs,
    });
    return { tracker, backend };
}

async function until(predicate, timeout = 3000) {
    const started = Date.now();
    while (!predicate()) {
        if (Date.now() - started > timeout) throw new Error('La condición no se cumplió a tiempo');
        await new Promise((resolve) => setTimeout(resolve, 5));
    }
}

test('la URL de consulta sigue la ruta del contrato y solo acepta UUID', () => {
    const id = samples.payable.purchase_id;
    assert.equal(purchaseUrl({ catalog_url: '/app/business/self-service/v1/catalog', confirm_url: '/app/business/self-service/v1/purchases' }, id),
        `/app/business/self-service/v1/purchases/${id}`);
    assert.equal(purchaseUrl({ catalog_url: '/app/business/self-service/v1/catalog', confirm_url: null }, id),
        `/app/business/self-service/v1/purchases/${id}`);
    assert.equal(isPurchaseId(id), true);
    for (const value of ['', '../catalog', 'a8a13d0a', null, 42]) assert.equal(isPurchaseId(value), false, String(value));
});

test('cada estado de pago tiene su propio mensaje y solo las acciones del servidor', () => {
    const payable = purchaseView(samples.payable);
    assert.equal(payable.title, 'Tu compra está lista para pagar');
    assert.match(payable.message, /Paga RD\$2,000/);
    assert.deepEqual(payable.primary, samples.payable.payment.action);
    assert.equal(payable.waiting, false);

    const waiting = purchaseView(samples.waitingForPayment);
    assert.equal(waiting.title, 'Estamos confirmando tu pago');
    assert.equal(waiting.primary, null, 'mientras se confirma no se ofrece pagar otra vez');
    assert.equal(waiting.waiting, true);

    const failed = purchaseView(samples.failedPayment);
    assert.equal(failed.tone, 'problem');
    assert.equal(failed.message, samples.failedPayment.payment.message);
    assert.deepEqual(failed.primary, samples.failedPayment.payment.action, 'reintentar la compra existente');

    const failedWithoutRetry = purchaseView({ ...samples.failedPayment, payment: { ...samples.failedPayment.payment, action: null } });
    assert.equal(failedWithoutRetry.primary, null);

    const preparing = purchaseView({ ...samples.payable, payment: { status: 'preparing', action: null } });
    assert.equal(preparing.title, 'Estamos preparando tu pago');
    assert.equal(preparing.primary, null);
    assert.equal(preparing.waiting, true);

    const reversed = purchaseView({ ...samples.paidNeedsLink, balance_cents: 200000, payment: { status: 'reversed', action: null } });
    assert.equal(reversed.tone, 'problem');
    assert.match(reversed.message, /quedan RD\$2,000 por pagar/);
});

test('abono parcial: «payable» con el saldo restante del servidor, distinto de un pago en revisión', () => {
    const partial = purchaseView({ ...samples.payable, balance_cents: 50000 });
    assert.equal(partial.title, 'Recibimos parte del pago');
    assert.match(partial.message, /Quedan RD\$500 por pagar/);
    assert.deepEqual(partial.primary, samples.payable.payment.action);
    const review = purchaseView(samples.waitingForPayment);
    assert.equal(review.title, 'Estamos confirmando tu pago');
    assert.match(review.message, /ni enviar otro comprobante/);
});

test('pago pendiente y pago recibido con activación pendiente tienen mensajes distintos', () => {
    const pendingPayment = purchaseView(samples.payable);
    const paidPending = purchaseView({
        ...samples.payable, balance_cents: 0, payment: { status: 'paid', action: null },
        access: samples.payable.access.map((item) => ({ ...item, message: 'Pago confirmado. Falta completar la activación.' })),
    });
    assert.notEqual(pendingPayment.title, paidPending.title);
    assert.equal(paidPending.title, 'Pago recibido');
    assert.match(paidPending.message, /Estamos activando/);
    assert.equal(paidPending.primary, null);
    assert.equal(paidPending.waiting, true);
    assert.deepEqual(paidPending.access.map((row) => [row.name, row.badge, row.message]), [
        ['e-CF', 'Pendiente', 'Pago confirmado. Falta completar la activación.'],
        ['Cumplimiento', 'Pendiente', 'Pago confirmado. Falta completar la activación.'],
    ]);
});

test('pago recibido con vínculo pendiente: «Vincular mi empresa» es la acción principal', () => {
    const view = purchaseView(samples.paidNeedsLink, { ecf: 'e-CF', cumplimiento: 'Cumplimiento' });
    assert.equal(view.title, 'Pago recibido: falta un paso');
    assert.match(view.message, /No se te cobrará de nuevo/);
    assert.deepEqual(view.primary, { label: 'Vincular mi empresa', url: '/business/identity' });
    assert.deepEqual(view.access.map((row) => row.badge), ['Falta vincular tu cuenta y empresa', 'Falta vincular tu cuenta y empresa']);
});

test('acceso disponible: cada aplicación abre con la etiqueta y URL del servidor', () => {
    const view = purchaseView(samples.active);
    assert.equal(view.title, 'Tus aplicaciones están listas');
    assert.equal(view.primary, null, 'con dos aplicaciones listas ninguna va primero');
    assert.deepEqual(view.access.map((row) => row.action), samples.active.access.map((item) => item.action));
    assert.deepEqual(view.access.map((row) => row.action.label), ['Abrir e-CF', 'Abrir Cumplimiento']);

    const single = purchaseView({ ...samples.active, access: [samples.active.access[0]] });
    assert.equal(single.title, 'Tu aplicación está lista');
    assert.deepEqual(single.primary, samples.active.access[0].action);

    const mixed = purchaseView({ ...samples.active, access: [samples.active.access[0], samples.paidNeedsLink.access[1]] });
    assert.deepEqual(mixed.primary, samples.paidNeedsLink.access[1].action, 'lo que falta por hacer va antes de abrir');
});

test('una acción que el servidor no envía no aparece', () => {
    const view = purchaseView({
        ...samples.paidNeedsLink,
        access: [{ product: 'ecf', status: 'unavailable', message: 'e-CF no está disponible por ahora.', action: null }],
    });
    assert.equal(view.primary, null);
    assert.equal(view.access[0].action, null);
    assert.equal(view.access[0].message, 'e-CF no está disponible por ahora.');
});

test('tras confirmar se muestra la compra devuelta sin volver a pedirla', async () => {
    const { tracker, backend } = track('exito', samples.payable.purchase_id, { initial: samples.payable });
    assert.equal(tracker.view.value.title, 'Tu compra está lista para pagar');
    await new Promise((resolve) => setTimeout(resolve, 20));
    assert.equal(purchaseReads(backend).length, 0, 'payable no cambia solo: no se consulta');
    tracker.dispose();
});

test('retomar: consulta GET /purchases/{id} y sigue el estado hasta el pago recibido', async () => {
    const { tracker, backend } = track('pago-en-proceso', samples.payable.purchase_id, { pollDelaysMs: [0, 0] });
    await until(() => tracker.stalled.value);
    // processing, processing, pago recibido con activación pendiente (que también se espera).
    assert.equal(tracker.view.value.title, 'Pago recibido');
    assert.deepEqual(purchaseReads(backend).map((request) => request.path), Array(3).fill(`${backend.base}/purchases/${samples.payable.purchase_id}`));
    tracker.dispose();
});

test('las consultas automáticas se detienen y queda «Actualizar estado»', async () => {
    const { tracker, backend } = track('pago-en-proceso', samples.payable.purchase_id, { pollDelaysMs: [0] });
    await until(() => tracker.stalled.value);
    assert.equal(purchaseReads(backend).length, 2);
    assert.equal(tracker.view.value.title, 'Estamos confirmando tu pago');
    tracker.refresh();
    await until(() => tracker.view.value.title === 'Pago recibido');
    tracker.dispose();
});

test('el enlace de pago aparece cuando el servidor termina de prepararlo', async () => {
    const { tracker } = track('pago-preparandose', samples.payable.purchase_id);
    await until(() => tracker.view.value?.primary);
    assert.deepEqual(tracker.view.value.primary, samples.payable.payment.action);
    tracker.dispose();
});

test('compra inexistente o de otra empresa: 404 sin estado anterior', async () => {
    const { tracker } = track('exito', 'b6f0a3f2-9a4c-4d55-9d35-6f2a1c0e7d11');
    await until(() => tracker.error.value);
    assert.equal(tracker.error.value.code, 'purchase_not_found');
    assert.equal(tracker.purchase.value, null);
    tracker.dispose();
});

test('si falla una actualización se conserva el último estado conocido', async () => {
    const backend = createMockBackend({ samples, scenario: 'pago-en-proceso' });
    let reads = 0;
    const flaky = async (url, init) => {
        if (String(url).includes('/purchases/') && ++reads === 2) throw new TypeError('Failed to fetch');
        return backend.fetch(url, init);
    };
    const tracker = useBusinessPurchase({
        client: createSelfServiceClient(backend.urls, flaky), purchaseId: samples.payable.purchase_id, pollDelaysMs: [0],
    });
    await until(() => tracker.staleError.value);
    assert.equal(tracker.staleError.value.code, 'network');
    assert.equal(tracker.view.value.title, 'Estamos confirmando tu pago');
    assert.equal(tracker.error.value, null);
    tracker.dispose();
});

test('existing_purchase al confirmar: se ofrece retomar la compra devuelta', async () => {
    const { builder, backend } = mount('compra-en-curso');
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(builder.purchase.value, null);
    assert.equal(builder.existingPurchaseId.value, samples.payable.purchase_id);
    assert.equal(builder.confirmError.value.code, 'existing_purchase');
    assert.equal(backend.state.purchases.size, 0);
});

test('key_conflict: el siguiente intento usa una clave nueva y exige aceptar otra vez', async () => {
    const backend = createMockBackend({ samples });
    let conflict = true;
    const conflicting = { ...backend, fetch: async (url, init) => {
        if (String(url).endsWith('/purchases') && conflict) {
            conflict = false;
            backend.state.requests.push({ method: 'POST', path: new URL(String(url), 'http://lauda.local').pathname, body: JSON.parse(init.body) });
            return new Response(JSON.stringify({ code: 'key_conflict', message: 'La clave pertenece a otra operación.' }), { status: 409 });
        }
        return backend.fetch(url, init);
    } };
    const { builder } = mount('exito', { backend: conflicting });
    builder.toggle('ecf');
    builder.toggle('cumplimiento');
    await settled(builder);
    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(builder.confirmError.value.code, 'key_conflict');
    assert.equal(builder.accepted.value, false);
    builder.accepted.value = true;
    await builder.confirm();
    assert.equal(builder.purchase.value.purchase_id, samples.payable.purchase_id);
    assert.deepEqual(confirmations(backend).map((request) => request.body.idempotency_key), ['key-1', 'key-2']);
});

test('la página con compra pendiente lleva resume_purchase_id, también en el catálogo', async () => {
    const backend = createMockBackend({ samples, scenario: 'pago-recibido' });
    const page = backend.configurator();
    assert.equal(page.resume_purchase_id, samples.payable.purchase_id);
    assert.equal(page.catalog.resume_purchase_id, samples.payable.purchase_id);
    const catalog = await createSelfServiceClient(backend.urls, backend.fetch).catalog();
    assert.equal(catalog.resume_purchase_id, samples.payable.purchase_id);
});
