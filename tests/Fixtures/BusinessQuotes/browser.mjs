import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { proxyFromEnvironment } from '/home/laudaerp/.agents/skills/lauda-ui-ux/scripts/browser-audit.mjs';

const base = process.argv[2];
if (!/^\/tmp\/lauda-business-[a-f0-9]{16}$/.test(base)) throw new Error('Synthetic runtime required');
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
const resourceFailures = [];
page.on('requestfailed', request => resourceFailures.push({ url: request.url(), error: request.failure()?.errorText }));
const consoleErrors = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
const results = [];
async function capture(label) {
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    assert.match(await page.locator('body').evaluate(el => getComputedStyle(el).fontFamily), /sans/i, 'Application stylesheet must be applied');
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => { window.scrollTo(0, 0); return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        await page.screenshot({ path: `${base}/${label}-${name}.png`, fullPage: true, animations: 'disabled' });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label + ' overflows ' + name);
    }
    await page.setViewportSize({ width: 1365, height: 900 });
}
try {
    assert.equal((await page.goto(origin + '/app/business/quotes')).status(), 200);
    assert.equal(await page.getByRole('link', { name: 'Revisar Business de Comercial Rivera' }).count(), 2);
    assert.equal(await page.locator(`a[href$="${ids.foreign}"]`).count(), 0);
    await capture('quotes-list');
    results.push('tenant-scoped list and desktop/mobile layout');

    await page.locator(`a[href$="${ids.expired}"]`).click();
    await page.getByText('No se puede aceptar esta cotización', { exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Aceptar cotización', exact: true }).count(), 0);
    await capture('quote-expired');
    results.push('expired quote is readable and cannot be accepted');

    await page.getByRole('link', { name: 'Volver a cotizaciones' }).click();
    await page.locator(`a[href$="${ids.quote}"]`).click();
    const accept = page.getByRole('button', { name: 'Aceptar cotización', exact: true });
    await accept.waitFor();
    assert.equal(await accept.isDisabled(), true);
    assert.match(await page.getByLabel('Importes y aceptación').innerText(), /18\s?%/);
    await capture('quote-review');
    results.push('original prices, implementation and explicit unchecked consent');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('checkbox').check();
    let dropped = false;
    let savedReceipt;
    await page.route('**/app/business/quotes/*/accept', async route => {
        if (dropped) return route.continue();
        dropped = true;
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        savedReceipt = (await response.json()).acceptance;
        await route.abort('failed');
    });
    await accept.click();
    await page.getByRole('alert').getByText(/No pudimos confirmar la respuesta/).waitFor();
    await page.screenshot({ path: base + '/quote-retry-mobile.png', fullPage: true });
    assert.equal(await accept.isEnabled(), true);
    const response = page.waitForResponse(r => r.url().endsWith('/accept') && r.status() === 200);
    await accept.click();
    assert.deepEqual((await (await response).json()).acceptance, savedReceipt);
    await page.getByRole('heading', { name: 'Cotización aceptada', exact: true }).waitFor();
    results.push('lost server response is recovered with the same acceptance on mobile');
    await page.reload();
    await page.getByRole('heading', { name: 'Cotización aceptada', exact: true }).waitFor();
    assert.equal(await page.getByRole('checkbox').count(), 0);
    await capture('quote-accepted');
    results.push('persisted acceptance survives reload without a second action');
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await capture('quote-accepted-dark');
    results.push('desktop/mobile receipt in dark appearance');
    assert.deepEqual(errors, []);
    fs.writeFileSync(base + '/browser-results.json', JSON.stringify({ status: 'passed', results, javascript_errors: errors, limits: ['synthetic SQLite, real kernel and CSRF', 'external fonts blocked; system font fallback', 'no DEV acceptance or MySQL concurrency certification'] }, null, 2));
    console.log(JSON.stringify({ status: 'passed', checks: results.length, javascript_errors: errors.length }));
} catch (error) {
    fs.writeFileSync(base + '/browser-error.txt', String(error.stack));
    fs.writeFileSync(base + '/browser-resources.json', JSON.stringify({ resourceFailures, consoleErrors, styles: await page.locator('link[rel="stylesheet"]').evaluateAll(links => links.map(link => link.outerHTML)) }, null, 2));
    await page.screenshot({ path: base + '/browser-error.png', fullPage: true });
    throw error;
} finally {
    await browser.close();
}
