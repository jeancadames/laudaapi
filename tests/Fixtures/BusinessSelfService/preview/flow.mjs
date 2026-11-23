/**
 * Recorrido de navegador de «Armar mi paquete»: de Inicio al resumen (bloque 2),
 * confirmar, pagar y retomar la compra (bloque 3).
 * Comprueba en 390 px que lo que ve el cliente coincide con la respuesta del
 * servidor y captura cada estado en móvil y escritorio.
 *
 *   SELF_SERVICE_CONTRACT=/ruta/contrato-v1.ts npx vite --config tests/Fixtures/BusinessSelfService/preview/vite.config.mjs
 *   bash <skill lauda-ui-ux>/scripts/audit.sh \
 *        --flow tests/Fixtures/BusinessSelfService/preview/flow.mjs --base-url http://127.0.0.1:5199
 */
import assert from 'node:assert/strict';

const PREVIEW = '/tests/Fixtures/BusinessSelfService/preview/index.html';
const at = (pantalla, escenario = 'exito', extra = '') => `${PREVIEW}?pantalla=${pantalla}&escenario=${escenario}${extra}`;
const CONTRATAR = '&contratar=1';
const TEXT = (text) => text.replace(/\s+/g, ' ');
// Mismo formato que resources/js/lib/businessPackageCopy.ts.
const money = (cents) =>
    new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: cents % 100 === 0 ? 0 : 2, maximumFractionDigits: 2 }).format(cents / 100);

export default async ({ page, navigate, capture }) => {
    const mobile = () => page.setViewportSize({ width: 390, height: 844 });
    const option = (name) => page.getByRole('checkbox', { name: new RegExp(name) });
    const price = page.getByLabel('Precio de tu paquete');
    const review = page.getByRole('button', { name: 'Ver resumen' });
    const ready = () => review.and(page.locator(':enabled')).waitFor();
    const noOverflow = async (label) =>
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, `${label} desborda`);
    // Cada escenario empieza sin la selección guardada por el anterior.
    const fresh = async (url) => {
        if (page.url().startsWith('http')) await page.evaluate(() => window.sessionStorage.clear());
        await navigate(url);
    };
    /** Última respuesta de cálculo que devolvió el servidor. */
    const lastPreview = () => page.evaluate(() => window.__selfService.filter((entry) => entry.path.endsWith('/preview')).at(-1));
    /** La barra muestra exactamente los importes de la última respuesta. */
    const barMatchesServer = async (products) => {
        await ready();
        const { status, body } = await lastPreview();
        assert.equal(status, 200);
        assert.deepEqual(body.selection.products, products);
        const text = TEXT(await price.innerText());
        assert.ok(text.includes(`Pagas hoy ${money(body.due_today.total_cents)}`), `${text} ≠ hoy ${money(body.due_today.total_cents)}`);
        assert.ok(text.includes(`Precio mensual ${money(body.recurring.total_cents)}`), `${text} ≠ mensual`);
        return { body, text };
    };
    const pickFiscal = async () => {
        await option('Emitir facturas').check();
        await option('Gestionar mis obligaciones').check();
        await ready();
    };

    // 1. Inicio: la entrada lleva al configurador.
    await fresh(at('inicio'));
    await mobile();
    await page.getByRole('heading', { name: 'Armar mi paquete' }).waitFor();
    await capture('01-inicio');
    await mobile();
    await page.getByRole('link', { name: 'Armar mi paquete' }).click();
    await page.getByRole('heading', { level: 1, name: 'Armar mi paquete' }).waitFor();

    // 2. Selección vacía: tareas y aplicaciones del catálogo; Fácil ECF visible antes de elegir.
    assert.equal(await page.getByText('¿Qué quieres hacer en tu negocio?').count(), 1);
    assert.match(TEXT(await option('Emitir facturas').evaluate((input) => input.closest('label').innerText)), /Aplicación: e-CF.*Incluye Fácil ECF/);
    assert.match(TEXT(await option('Registrar ventas').evaluate((input) => input.closest('label').innerText)), /Incluye comercio electrónico estándar/);
    assert.equal(await review.isDisabled(), true);
    await capture('02-seleccion-vacia');
    await mobile();

    // 3. e-CF + Cumplimiento al 0 %: RD$2,000 hoy y por mes, sin mencionar impuestos.
    await pickFiscal();
    const fiscal = await barMatchesServer(['ecf', 'cumplimiento']);
    assert.match(fiscal.text, /Pagas hoy RD\$2,000 Precio mensual RD\$2,000/);
    assert.doesNotMatch(fiscal.text, /impuesto|Luego/i);
    await noOverflow('selección');
    await page.getByRole('radio', { name: 'Anual' }).scrollIntoViewIfNeeded();
    const [radio, bar] = await Promise.all([page.getByRole('radio', { name: 'Anual' }).boundingBox(), price.boundingBox()]);
    assert.ok(radio.y + radio.height <= bar.y, 'la barra de precio tapa el ciclo de pago');
    await capture('03-seleccion-fiscal');
    await mobile();

    // 4. Añadir y quitar BYS: RD$3,000 y de vuelta a RD$2,000, siempre lo que dice el servidor.
    await option('Gestionar compras').check();
    const withBys = await barMatchesServer(['ecf', 'cumplimiento', 'bys']);
    assert.match(withBys.text, /Pagas hoy RD\$3,000 Precio mensual RD\$3,000/);
    await capture('04-seleccion-con-bys');
    await mobile();
    await option('Gestionar compras').uncheck();
    assert.match((await barMatchesServer(['ecf', 'cumplimiento'])).text, /Pagas hoy RD\$2,000/);

    // 5. Resumen al 0 %: incluye Fácil ECF, sin fila de impuestos y «Precio mensual».
    await review.click();
    await page.getByRole('heading', { level: 1, name: 'Resumen de tu paquete' }).waitFor();
    const summary = TEXT(await page.locator('#app').innerText());
    for (const expected of [
        /Fácil ECF incluido/,
        /Implementación Guiada/,
        /Tu selección corresponde al paquete Fiscal\. Facturación mensual\./,
        /Pagas hoy Total de hoy RD\$2,000 Corresponde al primer mes\./,
        /Precio mensual Total mensual RD\$2,000 Este paquete no se renueva automáticamente\./,
        /Este precio se mantiene hasta el 15 de enero de 2030/,
    ])
        assert.match(summary, expected);
    assert.doesNotMatch(summary, /Impuesto|Luego|Confirmar/);
    await noOverflow('resumen');
    await capture('05-resumen-fiscal');
    await mobile();

    // 6. Atrás del teléfono conserva la selección.
    await page.goBack();
    await page.getByRole('heading', { level: 1, name: 'Armar mi paquete' }).waitFor();
    assert.equal(await option('Emitir facturas').isChecked(), true);
    assert.equal(await option('Gestionar mis obligaciones').isChecked(), true);
    assert.equal(await option('Gestionar compras').isChecked(), false);

    // 7. Con BYS el resumen muestra cada componente del precio.
    await option('Gestionar compras').check();
    await barMatchesServer(['ecf', 'cumplimiento', 'bys']);
    await review.click();
    await page.getByText('Total mensual').waitFor();
    assert.match(TEXT(await page.locator('#app').innerText()), /Precio mensual Paquete Fiscal \(e-CF \+ Cumplimiento\) RD\$2,000 BYS RD\$1,000 Total mensual RD\$3,000/);
    await capture('06-resumen-con-bys');
    await mobile();
    await page.getByRole('button', { name: 'Cambiar mi selección' }).click();
    await page.getByRole('heading', { level: 1, name: 'Armar mi paquete' }).waitFor();
    assert.equal(await option('Gestionar compras').isChecked(), true);

    // 8. Tasa positiva: el desglose aparece con los importes del servidor.
    await fresh(at('configurador', 'con-impuestos'));
    await mobile();
    await pickFiscal();
    const taxed = await barMatchesServer(['ecf', 'cumplimiento']);
    assert.match(taxed.text, /Pagas hoy RD\$2,360 Precio mensual RD\$2,360, impuestos incluidos/);
    await review.click();
    await page.getByText('Total de hoy').waitFor();
    assert.match(
        TEXT(await page.locator('#app').innerText()),
        /Pagas hoy Base, antes de impuestos RD\$2,000 Impuestos RD\$360 Total de hoy RD\$2,360.*Precio mensual Base, antes de impuestos RD\$2,000 Impuestos RD\$360 Total mensual RD\$2,360/,
    );
    await capture('07-resumen-con-impuestos');

    // 9. Selección inválida: se señala la opción, se conserva y no se avanza.
    await fresh(at('configurador', 'seleccion-invalida'));
    await mobile();
    await option('Emitir facturas').check();
    await option('Gestionar compras').check();
    await price.getByRole('alert').waitFor();
    assert.match(TEXT(await price.innerText()), /BYS no tiene tarifa publicada/);
    assert.equal(await review.isDisabled(), true);
    await capture('08-seleccion-invalida');
    await mobile();
    await option('Gestionar compras').uncheck();
    await ready();

    // 10. Respuesta atrasada: gana la última selección.
    await fresh(at('configurador', 'red-lenta'));
    await mobile();
    await option('Emitir facturas').check();
    await page.waitForTimeout(400);
    await option('Gestionar mis obligaciones').check();
    await page.getByText('RD$2,000').first().waitFor();
    await page.waitForTimeout(1800);
    assert.match(TEXT(await price.innerText()), /Pagas hoy RD\$2,000/);

    // 11. Fallo de red: mensaje y reintento.
    await fresh(at('configurador', 'sin-conexion'));
    await mobile();
    await option('Emitir facturas').check();
    await page.getByRole('button', { name: 'Volver a calcular' }).click();
    await barMatchesServer(['ecf']);

    // 12. Cargo único: «Pagas hoy» lo incluye y el precio mensual no.
    await fresh(at('configurador', 'cargo-unico'));
    await mobile();
    await pickFiscal();
    await review.click();
    await page.getByText('Cargos únicos').waitFor();
    assert.match(TEXT(await page.locator('#app').innerText()), /Primer mes RD\$2,000 Cargos únicos RD\$6,000 Total de hoy RD\$8,000.*Total mensual RD\$2,000/);
    await capture('09-cargo-unico');

    // 13. Sin catálogo publicado y sin permiso.
    await fresh(at('configurador', 'sin-catalogo'));
    await page.getByRole('heading', { name: 'Todavía no hay precios publicados' }).waitFor();
    assert.equal(await page.getByRole('checkbox').count(), 0);
    await page.getByRole('button', { name: 'Volver a comprobar' }).click();
    await page.getByRole('heading', { name: 'Todavía no hay precios publicados' }).waitFor();
    assert.match(await page.locator('#app').innerText(), /El catálogo estará disponible más adelante/);
    await capture('10-sin-catalogo');
    await navigate(at('inicio', 'sin-permiso'));
    await page.getByRole('heading', { name: 'Inicio' }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'Armar mi paquete' }).count(), 0);

    // ─────────────── Bloque 3: confirmar, pagar y retomar ───────────────
    const purchaseHeading = (name, timeout = 8000) => page.getByRole('heading', { level: 1, name }).waitFor({ timeout });
    const lastPurchase = () =>
        page.evaluate(() => window.__selfService.filter((entry) => entry.path.includes('/purchases')).at(-1));
    const appText = async () => TEXT(await page.locator('#app').innerText());

    // 14. Confirmar: aceptar es obligatorio; la compra creada muestra «Continuar al pago» del servidor.
    await fresh(at('configurador', 'exito', CONTRATAR));
    await mobile();
    await pickFiscal();
    await review.click();
    await page.getByRole('heading', { level: 1, name: 'Resumen de tu paquete' }).waitFor();
    const submit = page.getByRole('button', { name: 'Confirmar compra' });
    assert.equal(await submit.isDisabled(), true, 'sin aceptar no se confirma');
    await page.getByRole('checkbox', { name: /acepto contratar este paquete/ }).check();
    await capture('11-confirmar');
    await mobile();
    await submit.click();
    await purchaseHeading('Tu compra está lista para pagar');
    const created = await lastPurchase();
    assert.equal(created.status, 201);
    assert.equal(new URL(page.url()).searchParams.get('compra'), created.body.purchase_id);
    const pay = page.getByRole('link', { name: created.body.payment.action.label });
    assert.equal(await pay.getAttribute('href'), created.body.payment.action.url);
    const paying = await appText();
    assert.match(paying, new RegExp(`Paga ${money(created.body.balance_cents).replace('$', '\\$')}`));
    assert.match(paying, /Total de la compra RD\$2,000 Por pagar: RD\$2,000\./);
    assert.match(paying, /e-CF Pendiente Esperando el pago\. Cumplimiento Pendiente Esperando el pago\./);
    assert.equal(await page.getByRole('link', { name: /^Abrir/ }).count(), 0, 'sin acceso no hay enlaces de apertura');
    await noOverflow('compra por pagar');
    await capture('12-compra-por-pagar');
    await mobile();

    // 15. Recargar (o volver del proveedor) recupera la misma compra por ?compra=.
    await page.reload();
    await purchaseHeading('Tu compra está lista para pagar');
    assert.equal((await lastPurchase()).path.endsWith(`/purchases/${created.body.purchase_id}`), true);

    // 16. Inicio con compra pendiente: retomarla es la acción principal y sigue el estado.
    await fresh(at('inicio', 'pago-en-proceso'));
    await mobile();
    await page.getByRole('heading', { name: 'Tienes una compra en curso' }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'Armar mi paquete' }).count(), 0);
    assert.equal(await page.getByRole('link', { name: 'Ver mi paquete' }).count(), 0, 'la compra en curso tiene prioridad');
    await capture('13-inicio-compra-en-curso');
    await mobile();
    await page.getByRole('link', { name: 'Ver mi compra' }).click();
    await purchaseHeading('Estamos confirmando tu pago');
    assert.match(await appText(), /No necesitas pagar de nuevo ni enviar otro comprobante/);
    assert.equal(await page.getByRole('link', { name: 'Continuar al pago' }).count(), 0);
    await capture('14-confirmando-pago');
    await mobile();
    // Pago pendiente y pago recibido con activación pendiente: mensajes distintos.
    // La página vuelve a consultar sola (cada 5 s el primer minuto).
    await purchaseHeading('Pago recibido', 20000);
    assert.match(await appText(), /Estamos activando tus aplicaciones/);
    assert.match(await appText(), /Pago confirmado\. Falta completar la activación\./);
    await capture('15-pago-recibido-activando');

    // 17. Pago rechazado: mensaje del servidor y reintento sobre la misma compra.
    await fresh(at('configurador', 'pago-fallido'));
    await mobile();
    await purchaseHeading('No pudimos completar el pago');
    const failed = await lastPurchase();
    assert.match(await appText(), new RegExp(failed.body.payment.message));
    assert.equal(await page.getByRole('link', { name: failed.body.payment.action.label }).getAttribute('href'), failed.body.payment.action.url);
    await capture('16-pago-rechazado');

    // 18. Pago recibido con vínculo pendiente: «Vincular mi empresa» una sola vez.
    await fresh(at('configurador', 'pago-recibido'));
    await mobile();
    await purchaseHeading('Pago recibido: falta un paso');
    assert.equal(await page.getByRole('link', { name: 'Vincular mi empresa' }).count(), 1);
    assert.match(await appText(), /Falta vincular tu cuenta y empresa/);
    assert.match(await appText(), /Pagado\./);
    await capture('17-falta-vincular');

    // 19. Acceso disponible: cada aplicación abre con la URL del servidor.
    await fresh(at('configurador', 'acceso-listo'));
    await mobile();
    await purchaseHeading('Tus aplicaciones están listas');
    const opened = await lastPurchase();
    for (const access of opened.body.access)
        assert.equal(await page.getByRole('link', { name: access.action.label }).getAttribute('href'), access.action.url);
    await noOverflow('acceso disponible');
    await capture('18-acceso-listo');

    // 20. Con resume_purchase_id la página abre la compra pendiente en lugar del configurador.
    await fresh(at('configurador', 'compra-en-curso', CONTRATAR));
    await mobile();
    await purchaseHeading('Tu compra está lista para pagar');

    // 21. Paquete ya activado: Inicio ofrece «Ver mi paquete» y vuelve a esa compra.
    await fresh(at('inicio', 'acceso-listo'));
    await mobile();
    await page.getByRole('heading', { name: 'Tu paquete' }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'Armar mi paquete' }).count(), 0);
    await capture('19-inicio-ver-mi-paquete');
    await mobile();
    await page.getByRole('link', { name: 'Ver mi paquete' }).click();
    await purchaseHeading('Tus aplicaciones están listas');
    assert.equal(new URL(page.url()).searchParams.get('compra'), (await lastPurchase()).body.purchase_id);
};
