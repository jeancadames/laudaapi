import { chromium } from '/home/laudaerp/.nvm/versions/node/v22.23.3/lib/node_modules/@playwright/cli/node_modules/playwright-core/index.mjs';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
const [runtime, output] = process.argv.slice(2);
const fixture = JSON.parse(readFileSync(runtime + '/facil-browser.json', 'utf8'));
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const errors = [];
const captures = [];
try {
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  await context.addCookies([fixture.cookie]);
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => { if (response.status() >= 400) errors.push(response.status() + ' ' + response.url()); });
  await page.route('https://fonts.**/*', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
  const capture = async label => {
    for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
      await page.setViewportSize({ width, height });
      await page.screenshot({ path: `${output}/${label}-${name}.png`, fullPage: true });
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error('Overflow: ' + label + name);
      captures.push(label + '-' + name);
    }
  };
  await page.goto(fixture.url + '/facil/borradores/' + fixture.draft);
  await page.getByRole('button', { name: 'Verificar intento', exact: true }).waitFor();
  await capture('failed');
  await page.getByRole('button', { name: 'Verificar intento', exact: true }).click();
  await page.getByRole('button', { name: 'Revisar y reintentar', exact: true }).waitFor();
  await page.getByText('Listo para revisar', { exact: true }).waitFor();
  await capture('retryable');
  await page.getByRole('button', { name: 'Revisar y reintentar', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.waitFor();
  await capture('review');
  await dialog.getByRole('checkbox').check();
  await dialog.getByRole('button', { name: 'Confirmar y emitir', exact: true }).click();
  await page.getByText('E310000000001', { exact: true }).waitFor();
  await capture('emitted');
  await page.reload();
  await page.getByText('E310000000001', { exact: true }).waitFor();
  if (await page.getByRole('button', { name: 'Revisar y reintentar', exact: true }).count()) throw new Error('Retry shown after document');
  if (errors.length) throw new Error(JSON.stringify(errors));
  writeFileSync(output + '/result.json', JSON.stringify({ captures, errors, fonts: 'external font stylesheet substituted; provider not verified' }, null, 2));
  console.log(JSON.stringify({ captures: captures.length, errors }));
} finally { await browser.close(); }
