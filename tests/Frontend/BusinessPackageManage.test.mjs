/**
 * «Administrar mi paquete»: revisión, confirmación y recuperación.
 *
 *   SELF_SERVICE_CONTRACT=/ruta/docs/autoservicio/contrato-v1.ts \
 *     node --test tests/Frontend/BusinessPackageManage.test.mjs
 */
import assert from 'node:assert/strict';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import test from 'node:test';
import { build } from 'esbuild';
import { contractPath, repositoryRoot as root } from '../Fixtures/BusinessSelfService/contract.mjs';

const vue = JSON.stringify(pathToFileURL(path.join(root, 'node_modules/vue/index.mjs')).href);
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

const samples = await load(`export * from ${JSON.stringify(contractPath())};`);
const { useBusinessPackageChange, createManageClient, appRows, changeProgress, purchaseIdFrom, replacementStart, purchaseView, periodFacts, orderedNames } = await load(`
    export { useBusinessPackageChange } from '@/composables/useBusinessPackageChange';
    export { appRows, changeProgress, createManageClient, orderedNames, periodFacts, purchaseIdFrom, replacementStart } from '@/lib/businessPackageManage';
    export { purchaseView } from '@/lib/businessPurchaseStatus';
`);

const EFFECTIVE = '2026-12-23T16:00:00+00:00';
const plan = (change = null) => ({
    current: { products: ['ecf', 'cumplimiento', 'bys'], cycle: 'monthly' },
    available: ['ecf', 'cumplimiento', 'pos', 'bys'],
    names: { ecf: 'e-CF', cumplimiento: 'Cumplimiento', bys: 'BYS', pos: 'POS con e-commerce' },
    company: 'Empresa de prueba',
    timezone: 'America/Santo_Domingo',
    change,
    preview_url: '/self-service/p1/manage/preview',
    confirm_url: '/self-service/p1/manage/confirm',
    back_url: '/self-service?compra=p1',
});

/**
 * Laravel simulado: precio por aplicación, hash = productos + precio, un solo
 * cambio por contrato, confirmación idéntica idempotente.
 */
function backend({ prices = { ecf: 100000, cumplimiento: 100000, pos: 150000, bys: 100000 } } = {}) {
    const state = { prices, saved: null, requests: [], invoices: 1, dropNextResponse: false, delay: 0 };
    const review = (products) => {
        const sorted = [...products].sort();
        const total = sorted.reduce((sum, code) => sum + state.prices[code], 0);
        return { products: sorted, effective_at: EFFECTIVE, total_cents: total, terms_hash: `${sorted.join('+')}:${total}`.padEnd(64, '0') };
    };
    const json = (status, body) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const fetch = async (url, init) => {
        const body = JSON.parse(init.body);
        state.requests.push({ url, body });
        if (state.delay) await new Promise((resolve) => setTimeout(resolve, state.delay));
        if (url.endsWith('/preview')) {
            if (state.saved) return json(409, { message: 'Ya se registró un cambio para este paquete.' });
            return json(200, review(body.products));
        }
        const sorted = [...body.products].sort();
        if (state.saved) {
            const same = state.saved.terms_hash === body.terms_hash && state.saved.products.join() === sorted.join();
            return same ? json(200, state.saved) : json(409, { message: 'Ya se registró un cambio para este paquete.' });
        }
        const current = review(sorted);
        if (current.terms_hash !== body.terms_hash) return json(409, { message: 'Cambió la fecha o el precio. Revisa el resumen otra vez.' });
        if (sorted.length) state.invoices++;
        state.saved = { ...current, purchase_url: sorted.length ? '/self-service?compra=0b9a5a3e-9c0e-4bb5-9d9e-1d4d1d0c0b11' : null };
        if (state.dropNextResponse) {
            state.dropNextResponse = false;
            throw new TypeError('Failed to fetch');
        }
        return json(200, state.saved);
    };
    return { state, fetch };
}
function mount(server = backend(), page = plan(), add = undefined) {
    const change = useBusinessPackageChange({
        plan: page,
        client: createManageClient(page, server.fetch),
        reloadChange: async () => server.state.saved,
        add,
    });
    return { change, server };
}
const fates = (rows) => Object.fromEntries(rows.map((row) => [row.code, row.fate]));

test('cada aplicación dice si se conserva, se agrega o se da de baja', () => {
    assert.deepEqual(fates(appRows(plan(), ['ecf', 'cumplimiento', 'pos'])), { ecf: 'kept', cumplimiento: 'kept', pos: 'added', bys: 'removed' });
    assert.deepEqual(appRows(plan(), []).map((row) => row.code), ['ecf', 'cumplimiento', 'pos', 'bys'], 'orden del catálogo');
    const missing = appRows({ ...plan(), available: ['ecf'] }, ['ecf']);
    assert.equal(missing.find((row) => row.code === 'pos').locked, true, 'no se puede agregar lo que no está publicado');
    assert.equal(missing.find((row) => row.code === 'bys').locked, false, 'lo contratado se puede quitar');
});

test('«Agregar a mi paquete»: conserva lo contratado y marca la aplicación pedida', () => {
    const { change } = mount(backend(), plan(), ['pos', 'ecf', 'nomina']);
    assert.deepEqual(fates(change.rows.value), { ecf: 'kept', cumplimiento: 'kept', pos: 'added', bys: 'kept' });
    assert.equal(change.changed.value, true);

    const locked = mount(backend(), { ...plan(), available: ['ecf'] }, ['pos']).change;
    assert.equal(locked.changed.value, false, 'no marca lo que no está publicado');

    const saved = { products: ['ecf'], effective_at: EFFECTIVE, total_cents: 100000, terms_hash: 'x'.repeat(64), purchase_url: null };
    assert.equal(mount(backend(), plan(saved), ['pos']).change.changed.value, false, 'con un cambio registrado no se marca nada');
});

test('quitar BYS: la revisión usa precio y fecha del servidor; nada se registra hasta confirmar', async () => {
    const { change, server } = mount();
    change.toggle('bys');
    assert.equal(change.changed.value, true);
    await change.reviewSelection();
    assert.deepEqual(server.state.requests.at(-1).body, { products: ['ecf', 'cumplimiento'] });
    assert.equal(change.review.value.total_cents, 200000);
    assert.equal(change.review.value.effective_at, EFFECTIVE);
    assert.deepEqual(fates(change.reviewRows.value), { ecf: 'kept', cumplimiento: 'kept', pos: 'excluded', bys: 'removed' });
    assert.equal(server.state.saved, null);
    await change.confirm();
    assert.equal(server.state.saved, null, 'sin aceptar no se confirma');
    change.accepted.value = true;
    await change.confirm();
    assert.deepEqual(server.state.requests.at(-1).body, { products: ['cumplimiento', 'ecf'], terms_hash: change.saved.value.terms_hash, accepted: true });
    assert.equal(change.saved.value.purchase_url.includes('compra='), true);
    assert.equal(change.review.value, null);
});

test('doble clic: una sola confirmación en vuelo', async () => {
    const { change, server } = mount();
    change.toggle('bys');
    await change.reviewSelection();
    change.accepted.value = true;
    server.state.delay = 20;
    await Promise.all([change.confirm(), change.confirm(), change.confirm()]);
    assert.equal(server.state.requests.filter((r) => r.url.endsWith('/confirm')).length, 1);
    assert.equal(server.state.invoices, 2);
});

test('cambia el precio antes de confirmar: 409 → nueva revisión con aviso y aceptar de nuevo', async () => {
    const { change, server } = mount();
    change.toggle('bys');
    await change.reviewSelection();
    change.accepted.value = true;
    server.state.prices.ecf = 150000;
    await change.confirm();
    assert.equal(change.notice.value, 'price_changed');
    assert.equal(change.review.value.total_cents, 250000, 'el importe nuevo es el del servidor');
    assert.equal(change.accepted.value, false);
    assert.equal(change.error.value, null);
    assert.equal(server.state.saved, null);
    change.accepted.value = true;
    await change.confirm();
    assert.equal(change.saved.value.total_cents, 250000);
});

test('sin respuesta al confirmar: «no sabemos», y reintentar recupera el mismo cambio sin otra factura', async () => {
    const { change, server } = mount();
    change.toggle('bys');
    await change.reviewSelection();
    change.accepted.value = true;
    server.state.dropNextResponse = true;
    await change.confirm();
    assert.equal(change.uncertain.value, true);
    assert.equal(change.error.value.code, 'network');
    assert.equal(change.saved.value, null);
    assert.equal(server.state.invoices, 2, 'el servidor sí lo registró');
    await change.confirm();
    assert.equal(change.uncertain.value, false);
    assert.equal(change.saved.value.terms_hash, server.state.saved.terms_hash);
    assert.equal(server.state.invoices, 2, 'el reintento no factura otra vez');
});

test('ya había un cambio (otra pestaña): se muestra el registrado', async () => {
    const server = backend();
    const first = mount(server).change;
    const second = mount(server).change;
    first.toggle('bys');
    second.toggle('bys');
    await first.reviewSelection();
    await second.reviewSelection();
    first.accepted.value = true;
    await first.confirm();
    second.accepted.value = true;
    second.toggle('pos'); // bloqueado durante la revisión
    await second.confirm();
    assert.equal(second.saved.value.terms_hash, first.saved.value.terms_hash);
    // Otra pestaña que revisa algo distinto con la página vieja.
    const third = mount(server).change;
    third.toggle('pos');
    await third.reviewSelection();
    assert.equal(third.notice.value, 'already_registered');
    assert.deepEqual(third.saved.value.products, ['cumplimiento', 'ecf']);
});

test('baja total: lista vacía, importe cero y sin factura', async () => {
    const { change, server } = mount();
    await change.reviewCancellation();
    assert.deepEqual(server.state.requests.at(-1).body, { products: [] });
    assert.equal(change.review.value.total_cents, 0);
    assert.deepEqual(fates(change.reviewRows.value), { ecf: 'removed', cumplimiento: 'removed', pos: 'excluded', bys: 'removed' });
    change.accepted.value = true;
    await change.confirm();
    assert.equal(change.saved.value.purchase_url, null);
    assert.equal(server.state.invoices, 1);
});

test('al recargar con un cambio registrado se muestra ese cambio', () => {
    const saved = { products: ['cumplimiento', 'ecf'], effective_at: EFFECTIVE, total_cents: 200000, terms_hash: 'x'.repeat(64), purchase_url: null };
    const { change } = mount(backend(), plan(saved));
    assert.equal(change.saved.value, saved);
    change.toggle('pos');
    assert.deepEqual(change.selection.value, ['ecf', 'cumplimiento', 'bys'], 'no se edita un cambio registrado');
});

test('«Editar» vuelve a la selección sin perderla', async () => {
    const { change } = mount();
    change.toggle('bys');
    change.toggle('pos');
    await change.reviewSelection();
    change.edit();
    assert.equal(change.review.value, null);
    assert.deepEqual([...change.selection.value].sort(), ['cumplimiento', 'ecf', 'pos']);
});

test('pasos después de confirmar: pago confirmado ≠ ya puedes activar', () => {
    const money = (cents) => `RD$${cents / 100}`;
    const base = { ...samples.preview, effective_at: EFFECTIVE };
    const purchase = (payment, access, balance = 200000) => ({
        purchase_id: 'n', invoice_number: 'F-2', currency: 'DOP', summary: base, balance_cents: balance, payment, access,
        previous_package_url: '/self-service/p1/manage', manage_url: null,
    });
    const pending = [{ product: 'ecf', status: 'pending', message: 'Podrás activar el nuevo paquete al terminar el período que ya pagaste.', action: null }];
    const pay = { label: 'Enviar comprobante', url: '/factura' };
    let steps = changeProgress(purchase({ status: 'payable', action: pay }, pending), '23 de diciembre de 2026', money);
    assert.deepEqual([steps.payment.state, steps.payment.action], ['todo', pay]);
    assert.match(steps.activation.text, /cuando LAUDA confirme el pago/);
    steps = changeProgress(purchase({ status: 'processing', action: null }, pending), 'x', money);
    assert.equal(steps.payment.state, 'waiting');
    steps = changeProgress(purchase({ status: 'paid', action: null }, pending, 0), '23 de diciembre de 2026', money);
    assert.deepEqual([steps.payment.state, steps.payment.text], ['done', 'LAUDA confirmó el pago.']);
    assert.deepEqual([steps.activation.state, steps.activation.action], ['waiting', null]);
    const consent = { label: 'Revisar la implementación', url: '/impl' };
    steps = changeProgress(purchase({ status: 'paid', action: null }, [{ product: 'ecf', status: 'consent_required', action: consent }], 0), 'x', money);
    assert.deepEqual([steps.activation.state, steps.activation.text, steps.activation.action], ['todo', 'Ya puedes activar tu nuevo paquete.', consent]);

    // La pantalla de la compra nueva dice lo mismo.
    assert.equal(replacementStart(purchase({ status: 'paid', action: null }, pending, 0)), EFFECTIVE);
    assert.equal(purchaseView(purchase({ status: 'payable', action: pay }, pending)).title, 'Paga la factura de tu nuevo paquete');
    const paid = purchaseView(purchase({ status: 'paid', action: null }, pending, 0));
    assert.equal(paid.title, 'LAUDA confirmó el pago');
    assert.match(paid.message, /a partir del 23 de diciembre de 2026/);
    assert.equal(paid.waiting, false);
    assert.equal(purchaseView(purchase({ status: 'paid', action: null }, [{ product: 'ecf', status: 'consent_required', action: consent }], 0)).title, 'Ya puedes activar tu nuevo paquete');
    // Una compra normal no cambia de texto.
    assert.equal(purchaseView({ ...purchase({ status: 'paid', action: null }, pending, 0), previous_package_url: null }).title, 'Pago recibido');
});

test('reemplazo pagado: espera, período terminado y conexión en revisión dicen lo mismo en ambas pantallas', () => {
    const money = (cents) => `RD$${cents / 100}`;
    const day = '23 de diciembre de 2026';
    const purchase = (access, effective = EFFECTIVE) => ({
        purchase_id: 'n', invoice_number: 'F-2', currency: 'DOP', summary: { ...samples.preview, effective_at: effective },
        balance_cents: 0, payment: { status: 'paid', action: null }, access, previous_package_url: '/self-service/p1/manage', manage_url: null,
    });
    const HOLD = 'Podrás activar el nuevo paquete al terminar el período que ya pagaste.';
    const ENDED = 'El período contratado para este paquete terminó.';
    const REVIEW = 'La conexión con esta aplicación requiere revisión de LAUDA.';
    const both = (access, effective) => {
        const p = purchase(access, effective);
        return { view: purchaseView(p), step: changeProgress(p, day, money).activation };
    };

    // 1. Antes de la fecha: espera con fecha.
    let { view, step } = both([
        { product: 'ecf', status: 'pending', message: HOLD, action: null },
        { product: 'cumplimiento', status: 'pending', message: HOLD, action: null },
    ]);
    assert.equal(view.title, 'LAUDA confirmó el pago');
    assert.match(view.message, /a partir del/);
    assert.equal(step.state, 'waiting');
    assert.match(step.text, /a partir del 23 de diciembre de 2026/);

    // 2. Período terminado (reproducción de la revisión de Codex): sin promesa de activación.
    ({ view, step } = both([
        { product: 'ecf', status: 'unavailable', message: ENDED, action: null },
        { product: 'cumplimiento', status: 'unavailable', message: ENDED, action: null },
    ], '2025-01-01T04:00:00+00:00'));
    assert.equal(view.title, 'Tus aplicaciones no están disponibles');
    assert.equal(view.message, ENDED);
    assert.equal(view.tone, 'notice');
    assert.equal(view.primary, null);
    assert.equal(view.waiting, false);
    assert.doesNotMatch(view.message + view.title, /Podrás activar|confirmó el pago/);
    assert.deepEqual([step.state, step.text, step.action], ['problem', ENDED, null], 'misma explicación en «Administrar»');

    // 3. Conexión en revisión mientras el resto espera la fecha: se conservan ambos motivos.
    ({ view, step } = both([
        { product: 'ecf', status: 'pending', message: HOLD, action: null },
        { product: 'pos', status: 'unavailable', message: REVIEW, action: null },
    ]));
    assert.equal(view.title, 'LAUDA confirmó el pago');
    assert.ok(view.message.endsWith(`POS: ${REVIEW}`), view.message);
    assert.ok(step.text.endsWith(REVIEW), step.text);

    // 3b. Conexión en revisión sin pendientes: solo el motivo, sin promesa.
    ({ view, step } = both([
        { product: 'ecf', status: 'ready', action: { label: 'Abrir e-CF', url: '/ecf' } },
        { product: 'pos', status: 'unavailable', message: REVIEW, action: null },
    ]));
    assert.equal(view.title, 'POS no está disponible');
    assert.equal(view.message, REVIEW);
    assert.deepEqual([step.state, step.text], ['problem', REVIEW]);

    // 4. Acción de conformidad del servidor: «Ya puedes activar».
    const consent = { label: 'Revisar la implementación', url: '/impl' };
    ({ view, step } = both([{ product: 'ecf', status: 'consent_required', action: consent }]));
    assert.equal(view.title, 'Ya puedes activar tu nuevo paquete');
    assert.deepEqual(view.primary, consent);
    assert.deepEqual([step.text, step.action], ['Ya puedes activar tu nuevo paquete.', consent]);

    // 5. La compra anterior (sin reemplazo) cuando su período terminó.
    const old = purchaseView({ ...purchase([
        { product: 'ecf', status: 'unavailable', message: ENDED, action: null },
        { product: 'bys', status: 'unavailable', message: ENDED, action: null },
    ]), previous_package_url: null });
    assert.deepEqual([old.title, old.message, old.tone], ['Tus aplicaciones no están disponibles', ENDED, 'notice']);

    // Motivos distintos: se remite al detalle de cada aplicación.
    const mixed = purchaseView({ ...purchase([
        { product: 'ecf', status: 'unavailable', message: ENDED, action: null },
        { product: 'bys', status: 'unavailable', message: REVIEW, action: null },
    ]), previous_package_url: null });
    assert.equal(mixed.message, 'Revisa el motivo indicado en cada aplicación.');
});

test('precio del período en curso y vigencia pagada: mismas etiquetas, renovación y fin', () => {
    const money = (cents) => `RD$${(cents / 100).toLocaleString('en-US')}`;
    const now = Date.parse('2026-12-01T12:00:00Z');
    const facts = (period, cycle = 'monthly') => periodFacts(period, cycle, money, 'America/Santo_Domingo', now)
        .map((fact) => `${fact.label}: ${fact.value}`);
    // Respuesta anterior sin current_period: cada pantalla conserva su texto.
    assert.deepEqual(periodFacts(undefined, 'monthly', money, 'America/Santo_Domingo', now), []);
    assert.deepEqual(periodFacts({}, 'monthly', money, 'America/Santo_Domingo', now), []);
    // Período en curso a RD$3,000 con la renovación prepagada: la fecha se extiende, el precio no.
    assert.deepEqual(facts({ total_cents: 300000, access_until: '2027-01-23T17:28:00Z' }),
        ['Precio del período en curso: RD$3,000 al mes', 'Acceso pagado hasta: 23 de enero de 2027']);
    // Empezó el período renovado: el servidor manda el precio nuevo.
    assert.deepEqual(facts({ total_cents: 240000, access_until: '2027-01-23T17:28:00Z' })[0], 'Precio del período en curso: RD$2,400 al mes');
    assert.deepEqual(facts({ total_cents: 2880000, access_until: '2027-01-23T17:28:00Z' }, 'yearly')[0], 'Precio del período en curso: RD$28,800 al año');
    // Terminó sin otro período: sin precio en curso y la fecha en pasado.
    assert.deepEqual(facts({ total_cents: null, access_until: '2026-11-23T17:28:00Z' }),
        ['Precio del período en curso: Sin período en curso', 'Acceso pagado: Terminó el 23 de noviembre de 2026']);
    // Pago revertido.
    assert.deepEqual(facts({ total_cents: 300000, access_until: null })[1], 'Acceso pagado: Sin pago confirmado');
});

test('cambio programado: nombres en el orden del catálogo', () => {
    assert.deepEqual(orderedNames(['cumplimiento', 'ecf', 'pos'], { ecf: 'e-CF', cumplimiento: 'Cumplimiento', pos: 'POS con e-commerce' }),
        ['e-CF', 'Cumplimiento', 'POS con e-commerce']);
});

test('identificador de la compra nueva desde purchase_url', () => {
    assert.equal(purchaseIdFrom('/app/business/self-service?compra=0b9a5a3e-9c0e-4bb5-9d9e-1d4d1d0c0b11'), '0b9a5a3e-9c0e-4bb5-9d9e-1d4d1d0c0b11');
    assert.equal(purchaseIdFrom(null), null);
    assert.equal(purchaseIdFrom('/x?compra=../otra'), null);
});
