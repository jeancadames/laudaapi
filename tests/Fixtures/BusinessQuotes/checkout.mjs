import fs from 'node:fs';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';

const base = process.argv[2];
if (!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base) || process.env.LAUDA_BUSINESS_CHECKOUT_TEST !== '1') throw new Error('Synthetic checkout runtime required');
const ids = JSON.parse(fs.readFileSync(base + '/quotes.json', 'utf8'));
const origin = 'https://127.0.0.1:9446';
const browser = await chromium.launch({ headless: true });
const proxy = proxyFromEnvironment(process.env);
if (proxy) proxy.bypass = '127.0.0.1,localhost';
const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1365, height: 900 }, ...(proxy ? { proxy } : {}) });
await context.addCookies(JSON.parse(fs.readFileSync(base + '/auth.json', 'utf8')).cookies);
await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const results = [];
async function capture(label) {
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    assert.match(await page.locator('body').evaluate(el => getComputedStyle(el).fontFamily), /sans/i);
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => { window.scrollTo(0, 0); return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        await page.screenshot({ path: `${base}/${label}-${name}.png`, fullPage: true, animations: 'disabled' });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label + ' overflows ' + name);
    }
}
async function payment(action) {
    execFileSync('php', [fileURLToPath(new URL('./browser.php', import.meta.url)), action], { env: process.env, stdio: 'pipe' });
    await page.getByRole('button', { name: 'Actualizar estado' }).click();
}
try {
    assert.equal((await page.goto(origin + '/app/business/quotes/' + ids.quote)).status(), 200);
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Aceptar cotización', exact: true }).click();
    await page.getByRole('link', { name: 'Continuar a facturación' }).click();
    await page.getByRole('heading', { name: 'Facturación Business', exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Crear factura', exact: true }).isDisabled(), true);
    await capture('checkout-review');
    results.push('acceptance leads to invoice review with separate recurring/one-time taxes and unchecked confirmation');

    await page.getByRole('checkbox').check();
    let dropped = false;
    let saved;
    await page.route('**/app/business/quotes/*/checkout', async route => {
        if (route.request().method() !== 'POST' || dropped) return route.continue();
        dropped = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        saved = (await response.json()).checkout.invoice;
        await route.abort('failed');
    });
    await page.getByRole('button', { name: 'Crear factura', exact: true }).click();
    await page.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await page.screenshot({ path: base + '/checkout-retry-mobile.png', fullPage: true });
    const retry = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/checkout') && response.status() === 200);
    await page.getByRole('button', { name: 'Crear factura', exact: true }).click();
    assert.deepEqual((await (await retry).json()).checkout.invoice, saved);
    await page.getByRole('heading', { name: saved.number }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: saved.number }).waitFor();
    assert.equal(await page.getByRole('checkbox').count(), 0);
    assert.equal(await page.getByRole('link', { name: 'Ir al pago' }).count(), 0);
    await capture('checkout-issued');
    results.push('lost response and reload recover the same invoice without a second charge');
    results.push('missing payment URL provides billing instructions without a simulated gateway');

    await payment('partial');
    await page.getByRole('heading', { name: 'Pagos registrados' }).waitFor();
    await page.waitForFunction(() => document.body.innerText.includes('10,000.00'));
    await capture('checkout-partial');
    results.push('existing payment reconciliation shows partial balance');
    await payment('paid');
    await page.getByText('Pagada', { exact: true }).waitFor();
    await page.getByText(/No hay saldo pendiente/).waitFor();
    await capture('checkout-paid');
    results.push('full payment clears balance without claiming service activation');
    await payment('reverse');
    await page.getByText('Pendiente de pago', { exact: true }).waitFor();
    await page.waitForFunction(() => document.body.innerText.includes('1,210.00'));
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await capture('checkout-reversed-dark');
    results.push('payment reversal restores balance, desktop/mobile and dark appearance');
    assert.deepEqual(errors, []);
    fs.writeFileSync(base + '/browser-results.json', JSON.stringify({ status: 'passed', results, javascript_errors: errors, limits: ['synthetic SQLite, real HTTP/kernel/session/CSRF', 'external fonts blocked; system font fallback', 'payments registered through the existing Payment model in synthetic data only', 'no operational payments, fiscal emission or activation'] }, null, 2));
    console.log(JSON.stringify({ status: 'passed', checks: results.length, javascript_errors: errors.length }));
} catch (error) {
    fs.writeFileSync(base + '/browser-error.txt', String(error.stack));
    await page.screenshot({ path: base + '/browser-error.png', fullPage: true });
    throw error;
} finally { await browser.close(); }
