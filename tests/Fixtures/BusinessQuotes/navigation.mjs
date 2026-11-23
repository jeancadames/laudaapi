import assert from 'node:assert/strict';
import fs from 'node:fs';
import { navigationByRole } from '../../../resources/js/config/navigationByRole.ts';
import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';

const base = process.argv[2];
const origin = 'https://127.0.0.1:9446';
const browser = await chromium.launch();
try {
    for (const admin of [false, true]) {
        for (const mobile of [false, true]) {
            const context = await browser.newContext({
                ignoreHTTPSErrors: true,
                viewport: mobile ? { width: 390, height: 844 } : { width: 1365, height: 900 },
            });
            const cookies = JSON.parse(fs.readFileSync(base + (admin ? '/auth-admin.json' : '/auth.json'))).cookies;
            await context.addCookies(cookies);
            await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
            const page = await context.newPage();
            const start = admin ? '/admin/business-implementations' : '/app/business/quotes';
            assert.equal((await page.goto(origin + start)).status(), 200);
            if (mobile) await page.locator('[data-sidebar="trigger"]').click();
            const menu = page.locator('[data-sidebar="content"]:visible');
            await menu.waitFor();
            const destination = admin ? 'Catálogo y tarifas' : 'Armar mi paquete';
            const configured = admin ? navigationByRole.admin.main : navigationByRole.subscriber_admin.main;
            const hrefs = await menu.getByRole('link').evaluateAll(links => links.map(link => link.pathname));
            // The synthetic company has no identity integration or active branding capability.
            for (const item of configured) {
                const visible = !['/business/identity', '/app/branding-identidad'].includes(item.href);
                assert.equal(hrefs.includes(item.href), visible, item.title);
            }
            await page.screenshot({animations: 'disabled', path: base + '/menu-' + (admin ? 'admin' : 'cliente') + (mobile ? '-movil' : '-escritorio') + '.png'});
            await menu.getByRole('link', { name: destination, exact: true }).click();
            await page.waitForURL(origin + (admin ? '/admin/business-catalog' : '/app/business/self-service'));
            if (!admin) {
                await page.getByRole('button', { name: 'Fiscal', exact: true }).click();
                assert.equal(await page.locator('input[type="checkbox"]:checked').count(), 2);
                await page.getByRole('button', { name: 'Business', exact: true }).click();
                assert.equal(await page.locator('input[type="checkbox"]:checked').count(), 4);
            } else {
                await page.getByRole('heading', { name: /catálogo/i }).first().waitFor();
                if (mobile) await page.locator('[data-sidebar="trigger"]').click();
                await menu.getByRole('link', { name: 'Pagos', exact: true }).click();
                await page.getByRole('heading', { name: 'Pagos', exact: true }).waitFor();
            }
            await context.close();
        }
    }
    console.log('Navegación Business verificada para ambos roles en escritorio y móvil.');
} finally {
    await browser.close();
}
