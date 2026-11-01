import strictAssert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import test, { after } from 'node:test';
import { build } from 'esbuild';
import { effectScope, nextTick, ref } from 'vue';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';

let assertionCount = 0;
const assert = new Proxy(strictAssert, {
    get(target, key) {
        const value = target[key];
        return typeof value === 'function'
            ? (...args) => {
                  assertionCount++;
                  return value(...args);
              }
            : value;
    },
});
after(() => console.log('FRONTEND_ASSERTIONS=' + assertionCount));

// Compilar TypeScript en memoria, sin Vite, dotenv, servidor ni dependencias nuevas.
const root = fileURLToPath(new URL('../../', import.meta.url));
const compiled = await build({
    entryPoints: [
        root + 'resources/js/composables/useDataBiTenantDeclaration.ts',
    ],
    bundle: true,
    write: false,
    format: 'esm',
    platform: 'node',
    plugins: [
        {
            name: 'same-vue-runtime',
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
const {
    useDataBiTenantDeclaration,
    declarationIssues,
    declarationPayload,
    availabilities,
    sourceTypes,
} = await import(
    'data:text/javascript;base64,' +
        Buffer.from(compiled.outputFiles[0].text).toString('base64')
);

const clone = (x) => JSON.parse(JSON.stringify(x));
const q = (version = 1, can_respond = true, id = 101) => ({
    implementation_request_id: id,
    version,
    sent_at: '2026-10-31T00:00:00Z',
    objectives: [{ key: 'clientes', label: 'Clientes' }],
    requirements: ['clientes', 'ventas'].map((key) => ({
        key,
        label: key,
        guidance: 'Describe la fuente.',
        objective_keys: ['clientes'],
    })),
    declaration_only: true,
    can_respond,
    attachments_required: false,
});
const empty = (key) => ({
    requirement_key: key,
    availability: 'pending',
    sources: [],
    notes: '',
});
const complete = (key) => ({
    requirement_key: key,
    availability: 'available',
    notes: '',
    sources: [
        { type: 'software', name: 'ERP', structures: ['Clientes'], notes: '' },
    ],
});
const current = (questionnaire, declaration = null) => ({
    data: {
        implementation_request_id: questionnaire.implementation_request_id,
        version: questionnaire.version,
        declaration,
    },
});
const stored = (responses, revision = 1, status = 'draft', version = 1) => ({
    id: 303,
    status,
    revision,
    version,
    submitted_at: status === 'submitted' ? '2026-10-31T12:00:00+00:00' : null,
    declarations: clone(responses),
});
const response = (body, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
const settle = async (vm) => {
    for (let i = 0; i < 30 && vm.busy.value; i++)
        await new Promise((resolve) => setImmediate(resolve));
    assert.equal(vm.busy.value, false);
    await nextTick();
};

async function setup(t, options = {}) {
    const questionnaire = options.questionnaire ?? q();
    const queue = [
        response(questionnaire),
        ...(questionnaire.can_respond
            ? [response(current(questionnaire, options.declaration ?? null))]
            : []),
    ];
    const calls = [];
    const original = globalThis.fetch;
    globalThis.fetch = async (url, init) => {
        calls.push({
            url,
            ...init,
            body: init.body ? JSON.parse(init.body) : undefined,
        });
        assert.ok(queue.length, 'Petición inesperada: ' + url);
        const next = queue.shift();
        if (next instanceof Error) throw next;
        return typeof next === 'function' ? next() : await next;
    };
    const scope = effectScope();
    const id = ref(questionnaire.implementation_request_id);
    const vm = scope.run(() => useDataBiTenantDeclaration(() => id.value));
    t.after(() => {
        scope.stop();
        globalThis.fetch = original;
    });
    await settle(vm);
    return { vm, queue, calls, scope, id, questionnaire };
}

test('una publicación de solo lectura no consulta ni habilita declaraciones', async (t) => {
    const { vm, calls } = await setup(t, { questionnaire: q(1, false) });
    assert.equal(vm.state.value, 'ready');
    assert.equal(vm.editable.value, false);
    assert.equal(calls.length, 1);
    assert.equal(await vm.saveDraft(), false);
});

test('inicio nuevo usa revisión 0 y un único estado para todos los requerimientos', async (t) => {
    const { vm } = await setup(t);
    assert.equal(vm.revision.value, 0);
    assert.equal(vm.dirty.value, false);
    vm.declarations.value[0].notes = 'Respuesta local';
    assert.equal(vm.dirty.value, true);
    assert.equal(vm.declarations.value[1].notes, '');
    assert.equal(vm.declarations.value[0].notes, 'Respuesta local');
});

test('guardar incompleto sincroniza revisión y proyecta exclusivamente T4D', async (t) => {
    const { vm, queue, calls, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = 'Trabajo incompleto';
    queue.push(response(current(questionnaire, stored(vm.declarations.value))));
    assert.equal(await vm.saveDraft(), true);
    const call = calls.at(-1);
    assert.deepEqual(Object.keys(call.body).sort(), [
        'declarations',
        'expected_revision',
        'version',
    ]);
    assert.equal(call.body.expected_revision, 0);
    assert.equal(call.method, 'POST');
    assert.match(call.url, /\/declaracion\/borrador$/);
    assert.equal(call.credentials, 'same-origin');
    assert.equal(call.cache, 'no-store');
    assert.equal(call.headers.Accept, 'application/json');
    assert.equal(vm.revision.value, 1);
    assert.equal(vm.dirty.value, false);
    assert.match(vm.notice.value, /guardado/);
    vm.declarations.value[0].notes = 'Edición';
    queue.push(
        response(current(questionnaire, stored(vm.declarations.value, 2))),
    );
    await vm.saveDraft();
    assert.equal(calls.at(-1).body.expected_revision, 1);
    assert.equal(vm.revision.value, 2);
});

test('recargar restaura el borrador persistido y su revisión', async (t) => {
    const saved = [empty('clientes'), complete('ventas')];
    saved[0].notes = 'Guardado antes de recargar';
    const { vm } = await setup(t, { declaration: stored(saved, 7) });
    assert.deepEqual(
        vm.declarations.value,
        declarationPayload(1, saved, 7).declarations,
    );
    assert.equal(vm.revision.value, 7);
    assert.equal(vm.storedStatus.value, 'draft');
    assert.equal(vm.dirty.value, false);
});

test('límites de fuentes y estructuras se cumplen sin cortar los datos existentes', async (t) => {
    const { vm } = await setup(t);
    const d = vm.declarations.value[0];
    for (let i = 0; i < 9; i++) vm.addSource(d);
    assert.equal(d.sources.length, 6);
    for (let i = 0; i < 15; i++) vm.addStructure(d.sources[0]);
    assert.equal(d.sources[0].structures.length, 12);
    assert.deepEqual(
        declarationPayload(1, [d], 0).declarations[0].sources[0].structures,
        [],
    );
});

test('completitud respeta disponibilidades, justificaciones y fuentes T4B', () => {
    assert.deepEqual(
        availabilities.map(([key]) => key),
        ['available', 'partial', 'unavailable', 'pending', 'unknown'],
    );
    assert.deepEqual(
        sourceTypes.map(([key]) => key),
        ['software', 'database', 'spreadsheet', 'flat_file', 'api', 'other'],
    );
    assert.equal(declarationIssues(empty('a'), false).length, 0);
    assert.ok(declarationIssues(empty('a'), true).length);
    assert.equal(declarationIssues(complete('a'), true).length, 0);
    for (const availability of ['partial', 'unavailable', 'unknown']) {
        const d = { ...empty('a'), availability };
        assert.ok(declarationIssues(d, true).length);
        d.notes = 'Explicación';
        if (availability === 'partial') d.sources = complete('a').sources;
        assert.equal(declarationIssues(d, true).length, 0);
    }
    assert.ok(
        declarationIssues(
            { ...complete('a'), availability: 'unavailable', notes: 'Motivo' },
            false,
        ).length,
    );
});

test('límites, duplicados y tipos se conservan sin normalización de negocio en el navegador', () => {
    const d = complete('a');
    d.notes = 'x'.repeat(801);
    assert.ok(declarationIssues(d, false).length);
    d.notes = '';
    d.sources[0].name = 'x'.repeat(192);
    assert.ok(declarationIssues(d, false).length);
    d.sources[0].name = 'ERP';
    d.sources[0].structures = ['Clientes', ' clientes '];
    assert.ok(declarationIssues(d, false).length);
    d.sources[0].structures = ['Clientes'];
    d.sources.push(clone(d.sources[0]));
    assert.ok(declarationIssues(d, false).length);
    const payload = declarationPayload(
        1,
        [
            {
                ...empty('a'),
                company_id: 99,
                sources: [{ ...complete('a').sources[0], user_id: 88 }],
            },
        ],
        0,
    );
    assert.equal(JSON.stringify(payload).includes('company_id'), false);
    assert.equal(JSON.stringify(payload).includes('user_id'), false);
});

test('el envío incompleto no hace POST y el envío confirmado bloquea la edición por respuesta real', async (t) => {
    const { vm, queue, calls, questionnaire } = await setup(t);
    assert.equal(vm.validateSubmission(), false);
    assert.equal(await vm.submit(), false);
    assert.equal(calls.length, 2);
    vm.declarations.value = ['clientes', 'ventas'].map(complete);
    assert.equal(vm.completed.value, 2);
    assert.equal(vm.validateSubmission(), true);
    queue.push(
        response(
            current(
                questionnaire,
                stored(vm.declarations.value, 1, 'submitted'),
            ),
        ),
    );
    assert.equal(await vm.submit(), true);
    assert.match(calls.at(-1).url, /\/declaracion\/enviar$/);
    assert.equal(vm.submitted.value, true);
    assert.equal(vm.editable.value, false);
    assert.equal(vm.submittedAt.value, '2026-10-31T12:00:00+00:00');
    assert.equal(await vm.saveDraft(), false);
    assert.equal(await vm.submit(), false);
});

test('una declaración submitted cargada no se puede reabrir', async (t) => {
    const { vm, calls } = await setup(t, {
        declaration: stored(
            ['clientes', 'ventas'].map(complete),
            5,
            'submitted',
        ),
    });
    assert.equal(vm.submitted.value, true);
    assert.equal(await vm.saveDraft(), false);
    assert.equal(calls.length, 2);
});

test('las solicitudes incompatibles no se ejecutan simultáneamente', async (t) => {
    const { vm, queue, calls, questionnaire } = await setup(t);
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const saving = vm.saveDraft();
    assert.equal(vm.busy.value, true);
    assert.equal(vm.canWrite.value, false);
    assert.equal(await vm.saveDraft(), false);
    await vm.inspectCurrent();
    assert.equal(calls.length, 3);
    finish(response(current(questionnaire, stored(vm.declarations.value))));
    await saving;
    assert.equal(vm.busy.value, false);
});

test('un fallo de red conserva respuestas y revisión para un reintento idéntico', async (t) => {
    const { vm, queue, calls, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = 'Conservar';
    queue.push(new TypeError('offline'));
    assert.equal(await vm.saveDraft(), false);
    assert.equal(vm.declarations.value[0].notes, 'Conservar');
    assert.equal(vm.revision.value, 0);
    const first = calls.at(-1).body;
    queue.push(response(current(questionnaire, stored(vm.declarations.value))));
    assert.equal(await vm.saveDraft(), true);
    assert.deepEqual(calls.at(-1).body, first);
});

for (const status of [401, 403, 404, 409, 419, 422, 503]) {
    test(`HTTP ${status} conserva cambios locales y no inventa éxito`, async (t) => {
        const { vm, queue } = await setup(t);
        vm.declarations.value[0].notes = 'Local';
        queue.push(
            response(
                {
                    error: {
                        code:
                            status === 409
                                ? 'revision_conflict'
                                : 'validation_failed',
                        fields: { declaration: ['Revisa el contenido.'] },
                    },
                },
                status,
            ),
        );
        assert.equal(await vm.saveDraft(), false);
        assert.equal(vm.revision.value, 0);
        assert.equal(vm.declarations.value[0].notes, 'Local');
        assert.equal(vm.dirty.value, true);
        assert.ok(vm.error.value);
        assert.equal(vm.notice.value, '');
        assert.equal(vm.conflict.value, status === 409);
    });
}

test('409 permite consultar sin descartar y usar la nueva revisión solo de forma explícita', async (t) => {
    const { vm, queue, calls, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = 'Local';
    queue.push(response({ error: { code: 'revision_conflict' } }, 409));
    await vm.saveDraft();
    const server = ['clientes', 'ventas'].map(empty);
    server[0].notes = 'Servidor';
    queue.push(
        response(questionnaire),
        response(current(questionnaire, stored(server, 5))),
    );
    await vm.inspectCurrent();
    assert.equal(vm.declarations.value[0].notes, 'Local');
    assert.equal(vm.revision.value, 0);
    assert.equal(
        vm.remote.value.current.declaration.declarations[0].notes,
        'Servidor',
    );
    assert.equal(vm.canWrite.value, false);
    vm.keepLocalOnCurrentRevision();
    assert.equal(vm.revision.value, 5);
    assert.equal(vm.declarations.value[0].notes, 'Local');
    queue.push(
        response(current(questionnaire, stored(vm.declarations.value, 6))),
    );
    await vm.saveDraft();
    assert.equal(calls.at(-1).body.expected_revision, 5);
});

test('cuestionario sustituido conserva respuestas y exige cargar explícitamente la nueva versión', async (t) => {
    const { vm, queue } = await setup(t);
    vm.declarations.value[0].notes = 'Versión anterior';
    queue.push(response({ error: { code: 'questionnaire_replaced' } }, 409));
    await vm.saveDraft();
    queue.push(response(q(2)), response(current(q(2))));
    await vm.inspectCurrent();
    assert.equal(vm.questionnaire.value.version, 1);
    assert.equal(vm.canRebase.value, false);
    assert.equal(vm.declarations.value[0].notes, 'Versión anterior');
    vm.useRemote();
    assert.equal(vm.questionnaire.value.version, 2);
    assert.equal(vm.revision.value, 0);
});

test('una consulta que descubre un envío remoto impide guardar o reabrir', async (t) => {
    const { vm, queue, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = 'Local';
    queue.push(
        response(questionnaire),
        response(
            current(
                questionnaire,
                stored(['clientes', 'ventas'].map(complete), 4, 'submitted'),
            ),
        ),
    );
    await vm.inspectCurrent();
    assert.equal(vm.canRebase.value, false);
    assert.equal(vm.editable.value, false);
    assert.equal(await vm.saveDraft(), false);
    assert.equal(vm.declarations.value[0].notes, 'Local');
    vm.useRemote();
    assert.equal(vm.submitted.value, true);
});

test('cambiar el ámbito con respuestas sin guardar no cruza identidades ni sobrescribe datos', async (t) => {
    const { vm, id, queue, calls } = await setup(t);
    vm.declarations.value[0].notes = 'Empresa anterior';
    id.value = 202;
    await nextTick();
    assert.equal(vm.contextChanged.value, true);
    assert.equal(vm.declarations.value[0].notes, 'Empresa anterior');
    assert.equal(await vm.saveDraft(), false);
    assert.equal(calls.length, 2);
    queue.push(response(q(1, true, 202)), response(current(q(1, true, 202))));
    await vm.inspectCurrent();
    assert.equal(vm.canRebase.value, false);
    vm.useRemote();
    assert.equal(vm.questionnaire.value.implementation_request_id, 202);
});

test('esquema de declaraciones ausente conserva la publicación en lectura', async (t) => {
    const { vm, queue, questionnaire } = await setup(t);
    queue.push(
        response(questionnaire),
        response({ error: { code: 'schema_unavailable' } }, 503),
    );
    await vm.load();
    assert.equal(vm.state.value, 'ready');
    assert.ok(vm.questionnaire.value);
    assert.equal(vm.initialized.value, false);
    assert.equal(vm.editable.value, false);
});

test('csrf prefiere cookie vigente y recurre al meta token', async (t) => {
    const original = globalThis.document;
    t.after(() => {
        if (original === undefined) delete globalThis.document;
        else globalThis.document = original;
    });
    globalThis.document = {
        cookie: 'XSRF-TOKEN=actual%3D',
        querySelector: () => ({ content: 'fallback' }),
    };
    const { vm, queue, calls, questionnaire } = await setup(t);
    queue.push(response(current(questionnaire, stored(vm.declarations.value))));
    await vm.saveDraft();
    assert.equal(calls.at(-1).headers['X-XSRF-TOKEN'], 'actual=');
    globalThis.document.cookie = '';
    queue.push(
        response(current(questionnaire, stored(vm.declarations.value, 1))),
    );
    await vm.saveDraft();
    assert.equal(calls.at(-1).headers['X-CSRF-TOKEN'], 'fallback');
});

test('la respuesta debe corresponder al cuestionario antes de sincronizar estado', async (t) => {
    const { vm, queue } = await setup(t);
    vm.declarations.value[0].notes = 'Local';
    queue.push(
        response(current(q(9), stored(vm.declarations.value, 99, 'draft', 9))),
    );
    assert.equal(await vm.saveDraft(), false);
    assert.equal(vm.revision.value, 0);
    assert.equal(vm.declarations.value[0].notes, 'Local');
});

test('consultar de nuevo nunca sobrescribe respuestas locales sin guardar', async (t) => {
    const { vm, calls } = await setup(t);
    vm.declarations.value[0].notes = 'No descartar';
    await vm.load();
    assert.equal(calls.length, 2);
    assert.equal(vm.declarations.value[0].notes, 'No descartar');
});

test('la sincronización usa las respuestas normalizadas que devuelve el servidor', async (t) => {
    const { vm, queue, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = '   Descripción   ';
    const normalized = clone(vm.declarations.value);
    normalized[0].notes = 'Descripción';
    queue.push(response(current(questionnaire, stored(normalized, 1))));
    assert.equal(await vm.saveDraft(), true);
    assert.equal(vm.declarations.value[0].notes, 'Descripción');
    assert.equal(vm.dirty.value, false);
});

test('desmontar cancela la solicitud e impide aplicar una respuesta tardía', async (t) => {
    const { vm, queue, calls, scope, questionnaire } = await setup(t);
    vm.declarations.value[0].notes = 'Local';
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const saving = vm.saveDraft();
    scope.stop();
    assert.equal(calls.at(-1).signal.aborted, true);
    finish(response(current(questionnaire, stored(vm.declarations.value))));
    assert.equal(await saving, false);
    assert.equal(vm.revision.value, 0);
    assert.equal(vm.declarations.value[0].notes, 'Local');
});

test('can_respond debe ser un booleano real antes de habilitar la respuesta', async (t) => {
    const { vm, calls } = await setup(t, { questionnaire: q(1, 'true') });
    assert.equal(vm.state.value, 'error');
    assert.equal(vm.editable.value, false);
    assert.equal(calls.length, 1);
});

test('el componente Vue compila con un solo formulario, dos vistas y confirmación expresa', async () => {
    const filename =
        root + 'resources/js/pages/App/DataBiInformationRequirementsPanel.vue';
    const source = await readFile(filename, 'utf8');
    const { descriptor, errors } = parse(source, { filename });
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, { id: 't4e' });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 't4e',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    assert.equal((source.match(/<form\b/g) ?? []).length, 1);
    assert.match(source, /display = 'focused'/);
    assert.match(source, /display = 'list'/);
    assert.match(source, /v-model="item.response.availability"/);
    assert.match(source, /Confirmar envío definitivo/);
    assert.match(source, /aria-live="polite"/);
    assert.match(source, /beforeunload/);
    assert.doesNotMatch(source, /type="file"|localStorage|expected_version/);
});
