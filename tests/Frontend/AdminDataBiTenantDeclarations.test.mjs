import strictAssert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test, { after } from 'node:test';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';
import { effectScope, nextTick, ref } from 'vue';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';

let assertions = 0;
const assert = new Proxy(strictAssert, {
    get(target, key) {
        const value = target[key];
        return typeof value === 'function'
            ? (...args) => {
                  assertions++;
                  return value(...args);
              }
            : value;
    },
});
after(() => console.log('FRONTEND_ASSERTIONS=' + assertions));
const root = fileURLToPath(new URL('../../', import.meta.url));
const compiled = await build({
    entryPoints: [
        root + 'resources/js/composables/useAdminDataBiDeclarationReview.ts',
    ],
    bundle: true,
    write: false,
    platform: 'node',
    format: 'esm',
    plugins: [
        {
            name: 'shared-vue',
            setup(b) {
                b.onResolve({ filter: /^vue$/ }, () => ({
                    path: new URL(
                        '../../node_modules/vue/index.mjs',
                        import.meta.url,
                    ).href,
                    external: true,
                }));
            },
        },
    ],
});
const { useAdminDataBiDeclarationReview, isAdminReview } = await import(
    'data:text/javascript;base64,' +
        Buffer.from(compiled.outputFiles[0].text).toString('base64')
);

function fixture(
    version = 2,
    submitted = true,
    requestId = 101,
    currentVersion = version,
) {
    return {
        implementation_request_id: requestId,
        company: { id: 701, name: 'Empresa sintética' },
        current_version: currentVersion,
        declaration_only: true,
        next_before_version: null,
        versions: [
            {
                version,
                published_at: '2026-10-31T10:00:00+00:00',
                response_status: submitted ? 'submitted' : 'pending',
                revision: submitted ? 3 : null,
                submitted_at: submitted ? '2026-10-31T12:00:00+00:00' : null,
            },
        ],
        selected: {
            questionnaire_id: 200 + version,
            version,
            published_at: '2026-10-31T10:00:00+00:00',
            objectives: [{ key: 'clientes', label: 'Gestión de clientes' }],
            requirements: ['clientes', 'ventas'].map((key) => ({
                key,
                label: key,
                guidance: 'Información solicitada.',
                objective_keys: ['clientes'],
            })),
            declaration: submitted
                ? {
                      status: 'submitted',
                      revision: 3,
                      submitted_at: '2026-10-31T12:00:00+00:00',
                      declarations: ['clientes', 'ventas'].map((key, i) => ({
                          requirement_key: key,
                          availability: i ? 'partial' : 'available',
                          notes: 'Motivo declarado.',
                          sources: [
                              {
                                  type: 'software',
                                  name: 'ERP',
                                  structures: [key],
                                  notes: 'Solo descripción.',
                              },
                          ],
                      })),
                  }
                : null,
            coverage: submitted
                ? {
                      requested: 2,
                      available: 1,
                      partial: 1,
                      unavailable: 0,
                      unknown: 0,
                      sources: 2,
                      structures: 2,
                  }
                : null,
        },
    };
}
const response = (data, status = 200) =>
    new Response(JSON.stringify({ data }), { status });
const settle = async (vm) => {
    for (let i = 0; i < 30 && vm.busy.value; i++)
        await new Promise((resolve) => setImmediate(resolve));
    assert.equal(vm.busy.value, false);
};
async function setup(t, data = fixture(), enabled = true) {
    const queue = [response(data)];
    const calls = [];
    const original = globalThis.fetch;
    globalThis.fetch = async (url, init) => {
        assert.equal(init.method, 'GET');
        assert.equal(init.body, undefined);
        calls.push({ url, ...init });
        assert.ok(queue.length, 'Consulta inesperada.');
        const result = queue.shift();
        if (result instanceof Error) throw result;
        return await result;
    };
    const id = ref(data.implementation_request_id);
    const active = ref(enabled);
    const scope = effectScope();
    const vm = scope.run(() =>
        useAdminDataBiDeclarationReview(
            () => id.value,
            () => active.value,
        ),
    );
    t.after(() => {
        scope.stop();
        globalThis.fetch = original;
    });
    await settle(vm);
    return { vm, queue, calls, id, active, scope };
}

test('la pestaña no consulta datos hasta activarse', async (t) => {
    const { vm, active, calls } = await setup(t, fixture(), false);
    assert.equal(calls.length, 0);
    assert.equal(vm.data.value, null);
    active.value = true;
    await nextTick();
    await settle(vm);
    assert.equal(calls.length, 1);
    assert.equal(vm.selected.value.declaration.status, 'submitted');
});

test('solo GET, credenciales de sesión y no-store para la solicitud persistida', async (t) => {
    const { vm, calls } = await setup(t);
    assert.match(
        calls[0].url,
        /\/101\/information-requirements\/tenant-declarations$/,
    );
    assert.equal(calls[0].credentials, 'same-origin');
    assert.equal(calls[0].cache, 'no-store');
    assert.equal(calls[0].headers.Accept, 'application/json');
    assert.equal(vm.selected.value.version, 2);
    assert.equal(vm.selected.value.declaration.revision, 3);
});

test('lista compacta y vista enfocada conservan datos sin hacer nuevas consultas', async (t) => {
    const { vm, calls } = await setup(t);
    const original = JSON.stringify(vm.data.value);
    assert.equal(vm.visibleRows.value.length, 1);
    assert.equal(vm.selectedKey.value, 'clientes');
    vm.display.value = 'list';
    assert.equal(vm.visibleRows.value.length, 2);
    vm.focusRequirement('ventas');
    assert.equal(vm.display.value, 'focused');
    assert.equal(vm.visibleRows.value[0].response.requirement_key, 'ventas');
    assert.equal(vm.index.value, 1);
    vm.move(1);
    assert.equal(vm.index.value, 1);
    vm.move(-1);
    assert.equal(vm.index.value, 0);
    vm.focusRequirement('ajeno');
    assert.equal(vm.selectedKey.value, 'clientes');
    assert.equal(JSON.stringify(vm.data.value), original);
    assert.equal(calls.length, 1);
});

test('pendiente no inventa disponibilidad, revisión ni cobertura del Tenant', async (t) => {
    const { vm } = await setup(t, fixture(2, false));
    assert.equal(vm.selected.value.declaration, null);
    assert.equal(vm.selected.value.coverage, null);
    assert.ok(vm.rows.value.every((r) => r.response === null));
    assert.equal(vm.data.value.versions[0].revision, null);
});

test('sin publicación conserva un estado pendiente inequívoco', async (t) => {
    const data = {
        ...fixture(),
        current_version: null,
        versions: [],
        selected: null,
    };
    const { vm } = await setup(t, data);
    assert.equal(vm.selected.value, null);
    assert.equal(vm.rows.value.length, 0);
});

test('la respuesta de una versión histórica nunca se presenta como vigente', async (t) => {
    const { vm, queue, calls } = await setup(t, fixture(3, false));
    queue.push(response(fixture(1, true, 101, 3)));
    await vm.load(1);
    assert.equal(vm.historical.value, true);
    assert.equal(vm.selected.value.version, 1);
    assert.equal(vm.data.value.current_version, 3);
    assert.equal(vm.selected.value.declaration.status, 'submitted');
    assert.match(calls.at(-1).url, /\?version=1$/);
    queue.push(response(fixture(3, false)));
    await vm.newestVersions();
    assert.equal(vm.historical.value, false);
    assert.equal(vm.selected.value.declaration, null);
});

test('la paginación del historial conserva la versión seleccionada separadamente', async (t) => {
    const data = fixture(22);
    data.next_before_version = 3;
    const { vm, queue, calls } = await setup(t, data);
    const older = fixture(22);
    older.versions = [2, 1].map((version) => ({
        ...data.versions[0],
        version,
    }));
    queue.push(response(older));
    await vm.olderVersions();
    assert.equal(vm.selected.value.version, 22);
    assert.match(calls.at(-1).url, /version=22&before_version=3/);
    queue.push(
        response({ ...fixture(1, true, 101, 22), versions: older.versions }),
    );
    await vm.load(1);
    assert.match(calls.at(-1).url, /version=1&before_version=3/);
    assert.equal(vm.selected.value.version, 1);
});

test('el frontend rechaza un draft incluso si el servidor lo entregara por error', async (t) => {
    const invalid = fixture();
    invalid.selected.declaration.status = 'draft';
    const { vm } = await setup(t, invalid);
    assert.equal(vm.data.value, null);
    assert.ok(vm.error.value);
});

test('no se admiten respuestas de otra solicitud o claves de requerimiento ajenas', () => {
    assert.equal(isAdminReview(fixture(1, true, 202), 101), false);
    const invalid = fixture();
    invalid.selected.declaration.declarations[0].requirement_key = 'ajeno';
    assert.equal(isAdminReview(invalid, 101), false);
    invalid.selected.declaration.declarations[0].requirement_key = 'ventas';
    assert.equal(isAdminReview(invalid, 101), false);
});

for (const status of [401, 403, 404, 409, 422, 503, 500]) {
    test(`HTTP ${status} oculta cualquier respuesta anterior y explica el fallo`, async (t) => {
        const { vm, queue } = await setup(t);
        queue.push(response(null, status));
        await vm.refresh();
        assert.equal(vm.data.value, null);
        assert.ok(vm.error.value);
        assert.equal(vm.busy.value, false);
    });
}

test('el contenido no JSON se comunica sin mostrar errores internos del parser', async (t) => {
    const { vm, queue } = await setup(t);
    queue.push(new Response('<html>PRIVATE</html>'));
    await vm.refresh();
    assert.equal(vm.data.value, null);
    assert.match(vm.error.value, /respuesta no válida/);
    assert.equal(vm.error.value.includes('PRIVATE'), false);
});

test('fallo de red recuperable sin inventar recepción administrativa', async (t) => {
    const { vm, queue } = await setup(t);
    queue.push(new TypeError('offline'));
    await vm.refresh();
    assert.equal(vm.data.value, null);
    assert.match(vm.error.value, /conectar/);
    queue.push(response(fixture()));
    await vm.newestVersions();
    assert.equal(vm.error.value, '');
    assert.ok(vm.data.value);
});

test('cambiar de solicitud cancela la lectura anterior y evita cruces de empresa', async (t) => {
    const { vm, queue, calls, id } = await setup(t);
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const previous = vm.load(1);
    queue.push(response(fixture(1, true, 202)));
    id.value = 202;
    await nextTick();
    await settle(vm);
    assert.equal(calls.at(-2).signal.aborted, true);
    assert.equal(vm.data.value.implementation_request_id, 202);
    finish(response(fixture(1)));
    await previous;
    assert.equal(vm.data.value.implementation_request_id, 202);
});

test('actualizaciones incompatibles no se duplican y el desmontaje cancela GET', async (t) => {
    const { vm, queue, calls, scope } = await setup(t);
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const pending = vm.refresh();
    await vm.refresh();
    assert.equal(calls.length, 2);
    scope.stop();
    assert.equal(calls.at(-1).signal.aborted, true);
    finish(response(fixture()));
    await pending;
});

test('SFC de solo lectura compila e integra la pestaña preservando los formularios anteriores', async () => {
    const filename =
        root +
        'resources/js/pages/Admin/Transformation360/ImplementationRequests/DataBiTenantDeclarationsPanel.vue';
    const source = await readFile(filename, 'utf8');
    const parsed = parse(source, { filename });
    assert.deepEqual(parsed.errors, []);
    const script = compileScript(parsed.descriptor, { id: 't4fa' });
    const template = compileTemplate({
        id: 't4fa',
        filename,
        source: parsed.descriptor.template.content,
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    assert.doesNotMatch(
        source,
        /<form|<textarea|type="file"|@submit|router\.post/,
    );
    assert.match(source, /Pendiente de respuesta del Tenant/);
    assert.match(source, /Lista compacta/);
    assert.match(source, /Por requerimiento/);
    assert.match(source, /aria-live="polite"/);
    assert.match(source, /no comprueba la existencia técnica/);
    const parent = await readFile(
        root +
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue',
        'utf8',
    );
    assert.match(parent, /<DataBiTenantDeclarationsPanel/);
    assert.match(parent, /v-show="d2fWorkspaceMode==='responses'"/);
    assert.match(parent, /<DataBiInformationRequirementsPreview \/>/);
    assert.match(parent, /:active="d2fWorkspaceMode==='responses'"/);
});
