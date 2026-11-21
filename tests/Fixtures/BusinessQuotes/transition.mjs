import fs from 'node:fs';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';

const base = process.argv[2];
if (!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base) || process.env.LAUDA_BUSINESS_TRANSITION_TEST !== '1') throw new Error('Synthetic transition runtime required');
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
const fixture = action => execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), action], { env: process.env, encoding: 'utf8' });
async function capture(page, label) {
    await page.evaluate(() => document.fonts.ready);
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => { window.scrollTo(0, 0); return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label + ' overflows ' + name);
        assert.notEqual(await page.locator('h1').evaluate(el => getComputedStyle(el).fontSize), '16px');
        await page.screenshot({ path: `${base}/${label}-${name}.png`, fullPage: true, animations: 'disabled' });
    }
}
async function submit(page, name, endpoint) {
    const responsePromise = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith(endpoint));
    await page.getByRole('button', { name, exact: true }).click();
    const response = await responsePromise;
    assert.equal(response.status(), 200, await response.text());
    return response.json();
}
try {
    assert.equal((await admin.goto(`${origin}/admin/business-catalog`)).status(), 200);
    await admin.getByRole('button', { name: 'Cotizador', exact: true }).click();
    await admin.getByLabel('Buscar empresa', { exact: true }).fill('Rivera');
    await admin.getByRole('button', { name: 'Buscar', exact: true }).click();
    await admin.getByLabel(/^Empresa/).selectOption('1');
    await admin.getByLabel(/^Implementación/).selectOption('assisted');
    const previewPromise = admin.waitForResponse(r => r.url().endsWith('/preview'));
    await admin.getByRole('button', { name: /Simular|Calcular/ }).click();
    const preview = await previewPromise;
    assert.equal(preview.status(), 200);
    assert.equal((await preview.json()).transition_review.eligible, true);
    await admin.getByRole('heading', { name: 'Transición al vencer lo pagado' }).waitFor();
    assert.match(await admin.locator('#transition-target option:checked').innerText(), /Selecciona/);
    assert.equal(await admin.locator('#transition-confirmed').isChecked(), false);
    assert.equal(await admin.locator('.transition-consent').evaluate(el => getComputedStyle(el).display), 'flex');
    assert.equal(await admin.getByRole('button', { name: 'Emitir cotización con estas condiciones' }).isDisabled(), true);
    await capture(admin, 'transition-proposal');
    await admin.locator('#transition-target').selectOption('1');
    await admin.locator('#transition-confirmed').check();
    let issuedId;
    let lost = false;
    await admin.route('**/issue', async route => {
        if (lost) return route.continue();
        lost = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        issuedId = (await response.json()).public_id;
        await route.abort('failed');
    });
    await admin.getByRole('button', { name: 'Emitir cotización con estas condiciones' }).click();
    await admin.getByRole('alert').waitFor();
    const recovered = await submit(admin, 'Emitir cotización con estas condiciones', '/issue');
    assert.equal(recovered.public_id, issuedId);
    results.push('operator reviews paid evidence, explicitly chooses retained subscription and deferred policy; lost issuance response recovers same quote');

    assert.equal((await customer.goto(`${origin}/app/business/quotes/${issuedId}`)).status(), 200);
    await customer.getByRole('heading', { name: 'Transición al vencer lo pagado' }).waitFor();
    assert.equal(await customer.locator('#accept-terms').isChecked(), false);
    await capture(customer, 'transition-company-review');
    await customer.locator('#accept-terms').check();
    await submit(customer, 'Aceptar cotización', '/accept');
    await customer.getByRole('link', { name: 'Continuar a facturación' }).click();
    await customer.getByRole('heading', { name: 'Transición al vencer lo pagado' }).waitFor();
    await customer.getByRole('checkbox').check();
    await submit(customer, 'Crear factura', '/checkout');
    results.push('company reviews dates, retained subscription and zero credits before accepting immutable proposal and requesting invoice');

    fixture('transition-prepare');
    assert.equal((await admin.goto(`${origin}/admin/business-implementations/${issuedId}/activation`)).status(), 200);
    await admin.getByText(/todavía hay períodos pagados vigentes/).waitFor();
    assert.equal(await admin.getByRole('button', { name: 'Activar contrato Business', exact: true }).count(), 0);
    await capture(admin, 'transition-waiting-cutoff');
    results.push('paid initial Business invoice and completed implementation do not bypass paid legacy cutoff');
    fixture('transition-cut');
    await admin.reload();
    for (const code of ['ecf', 'pos', 'bys', 'cumplimiento']) {
        await admin.locator('#technical-' + code).fill('Vinculación y empresa de destino comprobadas en el entorno sintético.');
    }
    await admin.getByRole('checkbox').check();
    let activationLost = false;
    await admin.route('**/activation', async route => {
        if (route.request().method() !== 'POST' || activationLost) return route.continue();
        activationLost = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200, await response.text());
        await route.abort('failed');
    });
    await admin.getByRole('button', { name: 'Activar contrato Business', exact: true }).click();
    await admin.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await submit(admin, 'Reintentar activación', '/activation');
    await admin.reload();
    await admin.getByRole('heading', { name: 'Transición aplicada', exact: true }).waitFor();
    await capture(admin, 'transition-activated');
    assert.equal(JSON.parse(fixture('access')).allowed, true);
    await customer.goto(`${origin}/app/business/quotes/${issuedId}/activation`);
    await customer.evaluate(() => document.documentElement.classList.add('dark'));
    await capture(customer, 'transition-company-active-dark');
    results.push('activation at exact cutoff consolidates into one active subscription; lost response replay returns same contract; company can access Business');
    assert.deepEqual(errors, []);
    fs.writeFileSync(base + '/browser-results.json', JSON.stringify({ results, errors }, null, 2));
    console.log(JSON.stringify({ results, errors }));
} catch (error) {
    await admin.screenshot({ path: base + '/failure-admin.png', fullPage: true });
    fs.writeFileSync(base + '/failure-admin.txt', await admin.locator('body').innerText());
    await customer.screenshot({ path: base + '/failure-company.png', fullPage: true });
    fs.writeFileSync(base + '/failure-company.txt', await customer.locator('body').innerText());
    throw error;
} finally {
    await browser.close();
}
