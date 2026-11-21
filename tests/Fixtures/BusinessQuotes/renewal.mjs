import fs from 'node:fs';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';

const base = process.argv[2];
if (!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base) || process.env.LAUDA_BUSINESS_RENEWAL_TEST !== '1') throw new Error('Synthetic renewal runtime required');
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
const fixture = action => execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), action], { env: process.env, encoding: 'utf8' });
const access = () => JSON.parse(fixture('access')).allowed;
async function capture(page, label) {
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.locator('h1').evaluate(el => getComputedStyle(el).fontWeight), '600');
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => { window.scrollTo(0, 0); return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label + ' overflows ' + name);
        await page.screenshot({ path: `${base}/${label}-${name}.png`, fullPage: true, animations: 'disabled' });
    }
}
async function submit(page, name) {
    const result = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/renewal/'));
    await page.getByRole('button', { name, exact: true }).click();
    const response = await result;
    assert.equal(response.status(), 200, await response.text());
    return (await response.json()).renewal;
}
async function refresh(page) {
    await page.getByRole('button', { name: 'Actualizar estado' }).click();
    await page.waitForLoadState('networkidle');
}
try {
    assert.equal((await customer.goto(`${origin}/app/business/quotes/${ids.quote}/activation`)).status(), 200);
    await customer.getByRole('link', { name: 'Revisar renovación' }).click();
    await customer.getByRole('heading', { name: 'Renovación Business', exact: true }).waitFor();
    assert.equal(await customer.getByRole('checkbox').count(), 0);
    await capture(customer, 'renewal-empty');
    assert.equal((await admin.goto(`${origin}/admin/business-implementations/${ids.quote}/renewal`)).status(), 200);
    assert.equal(await admin.locator('#renewal-base').inputValue(), '');
    assert.equal(await admin.locator('#renewal-tax').inputValue(), '');
    assert.equal(await admin.getByRole('checkbox').isChecked(), false);
    assert.equal(await admin.getByRole('button', { name: 'Publicar propuesta' }).isDisabled(), true);
    await capture(admin, 'renewal-form');
    await admin.locator('#renewal-base').fill('3500');
    await admin.locator('#renewal-discount').fill('500');
    await admin.locator('#renewal-tax').fill('18');
    const validity = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16);
    await admin.locator('#renewal-valid').fill(validity);
    const policies = { renewal_mode: 'explicit_single_period', discount_duration: 'this_period_only', price_changes: 'new_acceptance_required', tax_changes: 'new_acceptance_required', unpaid_coverage: 'no_coverage' };
    for (const [key, value] of Object.entries(policies)) await admin.locator('#' + key).selectOption(value);
    await admin.locator('#renewal-note').fill('Condiciones sintéticas: paquete y adicionales recurrentes, descuento exclusivo de este período. Los impuestos y el precio requieren nueva aceptación para cualquier cambio.');
    await admin.getByRole('checkbox').check();
    let lost = false;
    await admin.route('**/renewal/propose', async route => {
        if (lost) return route.continue();
        lost = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        await route.abort('failed');
    });
    await admin.getByRole('button', { name: 'Publicar propuesta' }).click();
    await admin.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await capture(admin, 'renewal-proposal-retry');
    await submit(admin, 'Reintentar operación');
    await admin.getByRole('heading', { name: 'Pendiente de aceptación', exact: true }).waitFor();
    await admin.reload();
    await capture(admin, 'renewal-proposed');
    results.push('operator explicitly selects commercial conditions with no preselected rates; lost publication response recovers one immutable offer');

    await refresh(customer);
    await customer.getByRole('heading', { name: 'Condiciones de renovación', exact: true }).waitFor();
    assert.equal(await customer.getByRole('checkbox').isChecked(), false);
    assert.equal(await customer.getByRole('button', { name: 'Aceptar renovación' }).isDisabled(), true);
    await capture(customer, 'renewal-customer-review');
    await customer.getByRole('checkbox').check();
    let acceptanceLost = false;
    await customer.route('**/renewal/accept', async route => {
        if (acceptanceLost) return route.continue();
        acceptanceLost = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        await route.abort('failed');
    });
    await customer.getByRole('button', { name: 'Aceptar renovación' }).click();
    await customer.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await submit(customer, 'Reintentar operación');
    await customer.getByRole('heading', { name: 'Condiciones aceptadas', exact: true }).waitFor();
    await customer.reload();
    assert.equal(await customer.getByRole('checkbox').count(), 0);
    results.push('customer reviews exact period/discount/tax and explicitly accepts; lost response and reload preserve one consent');

    await refresh(admin);
    await admin.getByRole('checkbox').check();
    let invoiceLost = false;
    await admin.route('**/renewal/invoice', async route => {
        if (invoiceLost) return route.continue();
        invoiceLost = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        await route.abort('failed');
    });
    await admin.getByRole('button', { name: 'Emitir factura de renovación' }).click();
    await admin.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await submit(admin, 'Reintentar operación');
    await admin.getByRole('heading', { name: 'Factura pendiente de pago o revisión', exact: true }).waitFor();
    await capture(admin, 'renewal-invoiced');
    assert.equal(access(), true);
    fixture('renewal-start');
    assert.equal(access(), false);
    fixture('renewal-pay');
    assert.equal(access(), true);
    await refresh(customer);
    await customer.getByRole('heading', { name: 'Factura cubierta', exact: true }).waitFor();
    await capture(customer, 'renewal-paid');
    results.push('idempotent invoice excludes one-time charges; initial access survives future invoice; new period needs confirmed payment');
    fixture('renewal-reverse');
    assert.equal(access(), false);
    await refresh(customer);
    await customer.getByRole('heading', { name: 'Factura pendiente de pago o revisión', exact: true }).waitFor();
    fixture('renewal-restore');
    assert.equal(access(), true);
    fixture('renewal-end');
    assert.equal(access(), false);
    await customer.getByRole('link', { name: 'Volver a activación' }).click();
    await customer.getByRole('heading', { name: 'Período finalizado', exact: true }).waitFor();
    await customer.evaluate(() => document.documentElement.classList.add('dark'));
    await capture(customer, 'renewal-expired-dark');
    results.push('payment reversal/restoration and exact renewed-period expiration drive the real resolver; activation shows renewed dates');
    assert.deepEqual(errors, []);
    fs.writeFileSync(base + '/browser-results.json', JSON.stringify({ status: 'passed', results, javascript_errors: errors,
        limits: ['synthetic SQLite real kernel/session/CSRF', 'external fonts blocked', 'no operational payments, fiscal emission or deployment'] }, null, 2));
    console.log(JSON.stringify({ status: 'passed', checks: results.length, javascript_errors: errors.length }));
} catch (error) {
    fs.writeFileSync(base + '/browser-error.txt', String(error.stack));
    await customer.screenshot({ path: base + '/browser-error-customer.png', fullPage: true });
    await admin.screenshot({ path: base + '/browser-error-admin.png', fullPage: true });
    throw error;
} finally { await browser.close(); }
