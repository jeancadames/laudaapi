import fs from 'node:fs';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';

const base = process.argv[2];
if (!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base) || process.env.LAUDA_BUSINESS_IMPLEMENTATION_TEST !== '1') throw new Error('Synthetic implementation runtime required');
const ids = JSON.parse(fs.readFileSync(base + '/quotes.json', 'utf8'));
const origin = 'https://127.0.0.1:9446';
const browser = await chromium.launch({ headless: true });
const proxy = proxyFromEnvironment(process.env);
if (proxy) proxy.bypass = '127.0.0.1,localhost';
const errors = [];
const results = [];
async function session(file) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1365, height: 900 }, ...(proxy ? { proxy } : {}) });
    await context.addCookies(JSON.parse(fs.readFileSync(base + '/' + file, 'utf8')).cookies);
    await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    return page;
}
const customer = await session('auth.json');
const admin = await session('auth-admin.json');
async function capture(page, label) {
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    assert.match(await page.locator('body').evaluate(el => getComputedStyle(el).fontFamily), /sans/i);
    assert.equal(await page.locator('h1').evaluate(el => getComputedStyle(el).fontWeight), '600');
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => { window.scrollTo(0, 0); return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        if (width === 390 && await page.locator('#history-title').count()) {
            const actions = await page.getByRole('complementary', { name: 'Estado y acciones' }).boundingBox();
            const history = await page.locator('#history-title').boundingBox();
            assert.ok(actions.y < history.y, 'mobile actions precede the history');
        }
        await page.screenshot({ path: `${base}/${label}-${name}.png`, fullPage: true, animations: 'disabled' });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label + ' overflows ' + name);
    }
}
async function act(page, action, note) {
    await page.getByLabel('Acción', { exact: true }).selectOption(action);
    await page.locator('#implementation-note').fill(note);
    await page.getByRole('checkbox').check();
    const response = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes(ids.quote));
    await page.locator('button[type="submit"]').click();
    const saved = await response;
    assert.equal(saved.status(), 200);
    const data = await saved.json();
    await page.getByRole('complementary', { name: 'Estado y acciones' }).getByRole('heading', { name: data.implementation.order.status_label, exact: true }).waitFor();
}
function payment(action) {
    execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), action], { env: process.env, stdio: 'pipe' });
}
async function refresh(page) {
    const response = page.waitForResponse(response => response.request().method() === 'GET' && response.url().includes(ids.quote) && response.status() === 200);
    await page.getByRole('button', { name: 'Actualizar estado' }).click();
    await response;
    await page.waitForLoadState('networkidle');
}
try {
    assert.equal((await customer.goto(origin + '/app/business/quotes/' + ids.quote)).status(), 200);
    await customer.getByRole('checkbox').check();
    await customer.getByRole('button', { name: 'Aceptar cotización', exact: true }).click();
    await customer.getByRole('link', { name: 'Continuar a facturación' }).click();
    await customer.getByRole('checkbox').check();
    await customer.getByRole('button', { name: 'Crear factura', exact: true }).click();
    await customer.getByRole('link', { name: 'Ver implementación' }).click();
    await customer.getByRole('heading', { name: 'Implementación Business', exact: true }).waitFor();
    assert.equal(await customer.getByRole('checkbox').isChecked(), false);
    assert.equal(await customer.locator('button[type="submit"]').isDisabled(), true);
    await capture(customer, 'implementation-request');
    results.push('acceptance and checkout lead to immutable scope review and unchecked request confirmation');

    let dropped = false;
    await customer.route('**/app/business/quotes/*/implementation', async route => {
        if (route.request().method() !== 'POST' || dropped) return route.continue();
        dropped = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        await route.abort('failed');
    });
    await customer.locator('#implementation-note').fill('Coordinar configuración y capacitación del equipo.');
    await customer.getByRole('checkbox').check();
    await customer.locator('button[type="submit"]').click();
    await customer.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await capture(customer, 'implementation-retry');
    await customer.getByRole('button', { name: 'Reintentar acción' }).click();
    await customer.getByRole('complementary', { name: 'Estado y acciones' }).getByRole('heading', { name: 'Pendiente de inicio', exact: true }).waitFor();
    await customer.reload();
    results.push('lost request response recovers one order with one history entry after retry and reload');

    assert.equal((await admin.goto(origin + '/admin/business-implementations')).status(), 200);
    await capture(admin, 'implementation-queue');
    await admin.getByRole('link', { name: 'Revisar implementación de Comercial Rivera' }).click();
    assert.equal(await admin.locator('option[value="start"]').count(), 0);
    payment('partial'); payment('paid');
    await refresh(admin);
    await act(admin, 'start', 'Configuración iniciada; responsable asignado.');
    await capture(admin, 'implementation-running');
    await act(admin, 'block', 'Pendiente de recibir datos iniciales de la empresa.');
    await act(admin, 'resume', 'Datos recibidos y validados para continuar.');
    payment('reverse');
    await refresh(admin);
    assert.equal(await admin.locator('option[value="deliver"]').count(), 0);
    await capture(admin, 'implementation-payment-hold');
    results.push('admin queue assigns execution responsibility and records block/resume; payment reversal removes delivery action');
    payment('restore');
    await refresh(admin);
    await act(admin, 'deliver', 'Configuración completada y capacitación impartida; materiales entregados a la empresa.');
    await refresh(customer);
    await capture(customer, 'implementation-delivered');
    await act(customer, 'request_changes', 'Solicitamos repasar el proceso de cierre con el equipo.');
    await refresh(admin);
    await act(admin, 'resume', 'Se coordina la sesión adicional dentro del alcance.');
    await act(admin, 'deliver', 'Proceso de cierre repasado y evidencia revisada con la empresa.');
    await refresh(customer);
    await act(customer, 'complete', 'Confirmamos la entrega de configuración y capacitación acordadas.');
    assert.equal(await customer.locator('form').count(), 0);
    await customer.evaluate(() => document.documentElement.classList.add('dark'));
    await capture(customer, 'implementation-completed-dark');
    results.push('customer requests corrections and confirms delivery; complete history remains without automatic activation');

    if (process.env.LAUDA_BUSINESS_ACTIVATION_TEST === '1') {
        await admin.getByRole('link', { name: 'Ver activación Business' }).click();
        await admin.getByRole('heading', { name: 'Activación Business', exact: true }).waitFor();
        assert.equal(await admin.getByRole('checkbox').isChecked(), false);
        assert.equal(await admin.getByRole('button', { name: 'Activar contrato Business' }).isDisabled(), true);
        await capture(admin, 'activation-review');
        for (const code of ['ecf', 'pos', 'bys', 'cumplimiento']) {
            await admin.locator('#technical-' + code).fill('Empresa y dashboard correctos; operación inicial verificada en QA sintético.');
        }
        await admin.getByRole('checkbox').check();
        let lost = false;
        let savedId;
        await admin.route('**/admin/business-implementations/*/activation', async route => {
            if (route.request().method() !== 'POST' || lost) return route.continue();
            lost = true;
            const response = await route.fetch();
            assert.equal(response.status(), 200);
            savedId = (await response.json()).activation.contract.id;
            await route.abort('failed');
        });
        await admin.getByRole('button', { name: 'Activar contrato Business' }).click();
        await admin.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
        await capture(admin, 'activation-retry');
        const retry = admin.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/activation') && r.status() === 200);
        await admin.getByRole('button', { name: 'Reintentar activación' }).click();
        assert.equal((await (await retry).json()).activation.contract.id, savedId);
        await admin.getByRole('heading', { name: 'Cobertura activa', exact: true }).waitFor();
        await admin.reload();
        await capture(admin, 'activation-active');
        const access = () => JSON.parse(execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), 'access'], { env: process.env, encoding: 'utf8' })).allowed;
        assert.equal(access(), true);
        results.push('activation review requires per-solution evidence and unchecked consent; lost response and reload recover one contract');
        await customer.getByRole('link', { name: 'Ver activación Business' }).click();
        await customer.getByRole('heading', { name: 'Cobertura activa', exact: true }).waitFor();
        assert.equal(await customer.getByRole('checkbox').count(), 0);
        await capture(customer, 'activation-customer');
        payment('reverse');
        assert.equal(access(), false);
        await refresh(customer);
        await customer.getByRole('heading', { name: 'Cobertura en revisión', exact: true }).waitFor();
        await capture(customer, 'activation-payment-review');
        payment('restore');
        assert.equal(access(), true);
        await refresh(customer);
        execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), 'expire'], { env: process.env, stdio: 'pipe' });
        assert.equal(access(), false);
        await refresh(customer);
        await customer.getByRole('heading', { name: 'Período finalizado', exact: true }).waitFor();
        await customer.evaluate(() => document.documentElement.classList.add('dark'));
        await capture(customer, 'activation-expired-dark');
        results.push('customer sees read-only receipt; payment reversal and exact period expiry deny real resolver access without renewal invoices');
    }
    assert.deepEqual(errors, []);
    fs.writeFileSync(base + '/browser-results.json', JSON.stringify({ status: 'passed', results, javascript_errors: errors, limits: ['synthetic SQLite with real HTTP/kernel/session/CSRF', 'external fonts blocked', 'payments only in synthetic runtime', 'no live activation, renewal, fiscal emission or deployment'] }, null, 2));
    console.log(JSON.stringify({ status: 'passed', checks: results.length, javascript_errors: errors.length }));
} catch (error) {
    fs.writeFileSync(base + '/browser-error.txt', String(error.stack));
    await customer.screenshot({ path: base + '/browser-error-customer.png', fullPage: true });
    await admin.screenshot({ path: base + '/browser-error-admin.png', fullPage: true });
    throw error;
} finally { await browser.close(); }
