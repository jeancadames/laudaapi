import strictAssert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test, { after } from 'node:test';
import { fileURLToPath } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';
import { build } from 'esbuild';
import { createRenderer, effectScope, h, nextTick, reactive } from 'vue';

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
const sharedVue = new URL('../../node_modules/vue/index.mjs', import.meta.url)
    .href;
async function compile(entry, component = false) {
    const result = await build({
        entryPoints: [root + entry],
        bundle: true,
        write: false,
        platform: 'node',
        format: 'esm',
        plugins: [
            {
                name: 'sfc-shared-runtime',
                setup(b) {
                    b.onResolve({ filter: /^vue$/ }, () => ({
                        path: sharedVue,
                        external: true,
                    }));
                    if (component) {
                        b.onResolve(
                            {
                                filter: /^(@inertiajs\/vue3|@\/components\/ui\/(button|dialog))$/,
                            },
                            (args) => ({ path: args.path, namespace: 'ui' }),
                        );
                        // Exercise real SFC handlers/state. Only UI library primitives and Inertia navigation are substituted.
                        b.onLoad({ filter: /.*/, namespace: 'ui' }, () => ({
                            contents: `
                    import { h } from 'vue';
                    export const router = { on: () => () => {} };
                    export const Button = (props, {slots, attrs}) => h('button', {...attrs, ...props}, slots.default?.());
                    export const Dialog = (props, {slots}) => props.open ? h('div', {role:'dialog'}, slots.default?.()) : null;
                    export const DialogContent = (props, {slots}) => h('div', slots.default?.());
                    export const DialogDescription = DialogContent, DialogFooter = DialogContent, DialogHeader = DialogContent, DialogTitle = DialogContent;
                `,
                        }));
                        b.onResolve({ filter: /^@\// }, (args) => ({
                            path: path.join(
                                root,
                                'resources/js',
                                args.path.slice(2) +
                                    (path.extname(args.path) ? '' : '.ts'),
                            ),
                        }));
                        b.onLoad({ filter: /\.vue$/ }, async (args) => {
                            const source = await readFile(args.path, 'utf8');
                            const { descriptor, errors } = parse(source, {
                                filename: args.path,
                            });
                            assert.deepEqual(errors, []);
                            return {
                                contents: compileScript(descriptor, {
                                    id: 'b4-test',
                                    inlineTemplate: true,
                                }).content,
                                loader: 'ts',
                            };
                        });
                    }
                },
            },
        ],
    });
    return import(
        'data:text/javascript;base64,' +
            Buffer.from(result.outputFiles[0].text).toString('base64')
    );
}
const { useDataBiDeclarativeReview, isDeclarativeReview, reviewTextValid } =
    await compile('resources/js/composables/useDataBiDeclarativeReview.ts');
const { default: Panel } = await compile(
    'resources/js/components/DataBiDeclarativeReviewPanel.vue',
    true,
);

const clone = (x) => structuredClone(x);
const requirements = ['ventas', 'clientes'].map((key) => ({
    key,
    label: key === 'ventas' ? 'Ventas históricas' : 'Clientes activos',
    guidance: 'Solo descripciones.',
    objective_keys: [],
}));
const declarations = requirements.map((r) => ({
    requirement_key: r.key,
    availability: r.key === 'ventas' ? 'unavailable' : 'partial',
    notes: 'Brecha legítima declarada.',
    sources: [],
}));
function clarification(
    status = 'requested',
    audience = 'admin',
    id = 41,
    key = 'ventas',
    version = 2,
) {
    const actions = [
        'request',
        ...(status === 'requested'
            ? []
            : status === 'answered'
              ? ['answer']
              : status === 'resolved'
                ? ['answer', 'resolve']
                : ['cancel']),
    ];
    return {
        id,
        questionnaire_version: version,
        requirement_key: key,
        status,
        revision: actions.length,
        question: '¿Qué período falta?',
        events: actions.map((action, i) => ({
            action,
            message: i
                ? 'Mensaje declarativo ' + action
                : '¿Qué período falta?',
            actor_type: action === 'answer' ? 'tenant' : 'lauda_admin',
            occurred_at: `2026-10-31T12:0${i}:00.000000Z`,
        })),
        allowed_actions: {
            answer: audience === 'tenant' && status === 'requested',
            resolve: audience === 'admin' && status === 'answered',
            cancel:
                audience === 'admin' &&
                ['requested', 'answered'].includes(status),
        },
    };
}
function fixture({
    audience = 'admin',
    status = null,
    historical = false,
    closed = false,
    version = 2,
} = {}) {
    const items = status
        ? [clarification(status, audience, 41, 'ventas', version)]
        : [];
    if (historical || closed)
        items.forEach((c) => {
            c.allowed_actions = {
                answer: false,
                resolve: false,
                cancel: false,
            };
        });
    return {
        questionnaire_version: version,
        is_current: !historical,
        review_status: closed ? 'closed' : 'open',
        clarifications: items,
        closure: closed
            ? {
                  status: 'closed',
                  questionnaire_version: version,
                  closed_at: '2026-10-31T13:00:00.000000Z',
                  conclusion: 'Se examinó lo declarado.',
                  limitations: 'No se inspeccionaron fuentes.',
                  declared_gaps: declarations.map(
                      ({ requirement_key, availability, notes }) => ({
                          requirement_key,
                          availability,
                          notes,
                      }),
                  ),
              }
            : null,
        allowed_actions: {
            request_clarification:
                audience === 'admin' && !historical && !closed,
            close_review:
                audience === 'admin' &&
                !historical &&
                !closed &&
                !['requested', 'answered'].includes(status),
        },
    };
}
const response = (data) =>
    new Response(JSON.stringify({ data }), { status: 200 });
const failure = (status, code) =>
    new Response(
        JSON.stringify({
            error: { code, message: 'Mensaje de B3', fields: {} },
        }),
        { status },
    );
const tick = async () => {
    await new Promise((resolve) => setImmediate(resolve));
    await nextTick();
};
async function settle(vm) {
    for (let i = 0; i < 30 && vm.busy.value; i++) await tick();
    assert.equal(vm.busy.value, false);
}
function transport(t, initial) {
    const queue = [response(initial)],
        calls = [];
    const cleanup = [];
    const previous = {
        fetch: globalThis.fetch,
        document: globalThis.document,
        window: globalThis.window,
    };
    globalThis.document = {
        cookie: '',
        querySelector: () => ({ content: 'synthetic-csrf-token' }),
        getElementById: () => ({ focus() {} }),
    };
    globalThis.window = {
        addEventListener() {},
        removeEventListener() {},
        confirm: () => true,
    };
    globalThis.fetch = async (url, init) => {
        calls.push({ url, ...init });
        assert.ok(queue.length, 'Petición inesperada: ' + url);
        const result = queue.shift();
        if (result instanceof Error) throw result;
        return await result;
    };
    t.after(() => {
        cleanup.forEach((fn) => fn());
        Object.assign(globalThis, previous);
    });
    return { queue, calls, onCleanup: (fn) => cleanup.push(fn) };
}
async function setup(t, initial = fixture(), audience = 'admin') {
    const http = transport(t, initial);
    const context = reactive({
        requestId: 101,
        version: initial.questionnaire_version,
        audience,
        enabled: true,
        requirementKeys: ['ventas', 'clientes'],
    });
    const scope = effectScope();
    const vm = scope.run(() => useDataBiDeclarativeReview(() => context));
    t.after(() => scope.stop());
    await settle(vm);
    return { ...http, context, vm };
}

test('GET B3 público, versión explícita, sesión same-origin y no-store; consulta sin escrituras', async (t) => {
    const { vm, calls } = await setup(t, fixture({ status: 'requested' }));
    assert.equal(
        calls[0].url,
        '/admin/transformation-360/implementation-requests/101/declarative-review?version=2',
    );
    assert.equal(calls[0].method, 'GET');
    assert.equal(calls[0].body, undefined);
    assert.equal(calls[0].credentials, 'same-origin');
    assert.equal(calls[0].cache, 'no-store');
    assert.equal(calls[0].headers.Accept, 'application/json');
    assert.equal(vm.data.value.clarifications[0].requirement_key, 'ventas');
});
test('solicitud Admin: payload exacto, revisión inicial cero, CSRF y sincronización', async (t) => {
    const { vm, queue, calls } = await setup(t);
    vm.setDraft('request:ventas', '¿Qué período falta?');
    queue.push(response(fixture({ status: 'requested' })));
    assert.equal(await vm.send('request', 'ventas'), true);
    assert.equal(
        calls[1].url,
        '/admin/transformation-360/implementation-requests/101/declarative-review/clarifications',
    );
    assert.deepEqual(JSON.parse(calls[1].body), {
        version: 2,
        message: '¿Qué período falta?',
        requirement_key: 'ventas',
        expected_revision: 0,
    });
    assert.equal(calls[1].headers['X-CSRF-TOKEN'], 'synthetic-csrf-token');
    assert.equal(calls[1].headers['Content-Type'], 'application/json');
    assert.equal(vm.draft('request:ventas'), '');
    assert.equal(vm.data.value.clarifications[0].revision, 1);
});
test('respuesta Tenant usa revisión vigente y no puede solicitar, resolver, cancelar ni cerrar', async (t) => {
    const { vm, queue, calls } = await setup(
        t,
        fixture({ audience: 'tenant', status: 'requested' }),
        'tenant',
    );
    for (const action of ['request', 'resolve', 'cancel', 'close'])
        assert.equal(vm.allowed(action, 41), false);
    vm.setDraft('answer:41', 'Falta el período inicial.');
    queue.push(response(fixture({ audience: 'tenant', status: 'answered' })));
    assert.equal(await vm.send('answer', 41), true);
    assert.match(calls[1].url, /\/aclaraciones\/41\/responder$/);
    assert.deepEqual(JSON.parse(calls[1].body), {
        version: 2,
        message: 'Falta el período inicial.',
        expected_revision: 1,
    });
    assert.equal(vm.data.value.clarifications[0].revision, 2);
    assert.equal(vm.allowed('answer', 41), false);
});
for (const action of ['resolve', 'cancel'])
    test(`${action} Admin utiliza revisión y conserva el historial retornado`, async (t) => {
        const { vm, queue, calls } = await setup(
            t,
            fixture({ status: 'answered' }),
        );
        assert.equal(vm.allowed('answer', 41), false);
        vm.setDraft(`${action}:41`, 'Motivo declarativo.');
        const result = fixture({
            status: action === 'resolve' ? 'resolved' : 'cancelled',
        });
        queue.push(response(result));
        assert.equal(await vm.send(action, 41), true);
        assert.match(calls[1].url, new RegExp(`/clarifications/41/${action}$`));
        assert.equal(JSON.parse(calls[1].body).expected_revision, 2);
        assert.deepEqual(
            vm.data.value.clarifications[0].events,
            result.clarifications[0].events,
        );
        assert.equal(vm.allowed('resolve', 41), false);
        assert.equal(vm.allowed('cancel', 41), false);
    });
test('cierre con brechas conserva fuentes no disponibles y no ofrece acciones posteriores', async (t) => {
    const { vm, queue, calls } = await setup(
        t,
        fixture({ status: 'resolved' }),
    );
    vm.setDraft('conclusion', 'Examen declarativo completado con brechas.');
    vm.setDraft('limitations', 'Sin validación técnica de fuentes.');
    queue.push(response(fixture({ status: 'resolved', closed: true })));
    assert.equal(await vm.send('close'), true);
    assert.deepEqual(JSON.parse(calls[1].body), {
        version: 2,
        conclusion: 'Examen declarativo completado con brechas.',
        limitations: 'Sin validación técnica de fuentes.',
        expected_revision: 0,
    });
    assert.match(calls[1].url, /declarative-review\/close$/);
    assert.equal(
        vm.data.value.closure.declared_gaps[0].availability,
        'unavailable',
    );
    assert.equal(vm.allowed('close'), false);
    assert.equal(vm.allowed('request'), false);
});
test('cierre bloqueado por flags del servidor aunque haya textos; no se emite POST', async (t) => {
    const { vm, calls } = await setup(t, fixture({ status: 'requested' }));
    vm.setDraft('conclusion', 'Conclusión');
    vm.setDraft('limitations', 'Limitaciones');
    assert.equal(await vm.send('close'), false);
    assert.equal(calls.length, 1);
});
test('permiso denegado por servidor prevalece sobre estados aparentemente elegibles', async (t) => {
    const f = fixture();
    f.allowed_actions.request_clarification = false;
    f.allowed_actions.close_review = false;
    const { vm, calls } = await setup(t, f);
    vm.setDraft('request:ventas', 'Pregunta');
    assert.equal(await vm.send('request', 'ventas'), false);
    assert.equal(calls.length, 1);
});
test('409 conserva texto y exige consulta + decisión explícita antes de usar la nueva revisión', async (t) => {
    const { vm, queue, calls } = await setup(
        t,
        fixture({ status: 'answered' }),
    );
    vm.setDraft('resolve:41', 'Resolución local');
    queue.push(failure(409, 'revision_conflict'));
    assert.equal(await vm.send('resolve', 41), false);
    assert.equal(vm.draft('resolve:41'), 'Resolución local');
    assert.equal(vm.allowed('resolve', 41), false);
    const fresh = fixture({ status: 'answered' });
    fresh.clarifications[0].revision = 3;
    queue.push(response(fresh));
    await vm.load();
    assert.equal(vm.session.value.inspected, true);
    assert.equal(vm.allowed('resolve', 41), false);
    vm.useInspected();
    assert.equal(vm.allowed('resolve', 41), true);
    queue.push(response(fixture({ status: 'resolved' })));
    await vm.send('resolve', 41);
    assert.equal(JSON.parse(calls.at(-1).body).expected_revision, 3);
});
test('respuesta perdida: reintento idéntico sin cambiar payload ni revisión y sin reintento automático', async (t) => {
    const { vm, queue, calls } = await setup(
        t,
        fixture({ audience: 'tenant', status: 'requested' }),
        'tenant',
    );
    vm.setDraft('answer:41', 'Respuesta original');
    queue.push(new TypeError('offline'));
    assert.equal(await vm.send('answer', 41), false);
    assert.equal(calls.length, 2);
    assert.equal(vm.draft('answer:41'), 'Respuesta original');
    vm.setDraft('answer:41', 'Cambio local posterior');
    queue.push(response(fixture({ audience: 'tenant', status: 'answered' })));
    assert.equal(await vm.retry(), true);
    assert.equal(calls[1].body, calls[2].body);
    assert.equal(vm.draft('answer:41'), 'Cambio local posterior');
});
test('doble envío y consulta incompatibles bloqueados mientras POST está pendiente', async (t) => {
    const { vm, queue, calls } = await setup(t);
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    vm.setDraft('request:ventas', 'Pregunta');
    const pending = vm.send('request', 'ventas');
    assert.equal(await vm.send('request', 'ventas'), false);
    await vm.load();
    assert.equal(calls.length, 2);
    finish(response(fixture({ status: 'requested' })));
    assert.equal(await pending, true);
});
test('navegar requisitos, secciones y versiones conserva textos locales sin cruzarlos', async (t) => {
    const { vm, context, queue, calls } = await setup(t);
    vm.setDraft('request:ventas', 'Pregunta ventas');
    vm.setDraft('request:clientes', 'Pregunta clientes');
    vm.setDraft('conclusion', 'Conclusión local');
    context.enabled = false;
    await nextTick();
    context.enabled = true;
    await nextTick();
    assert.equal(calls.length, 1);
    assert.equal(vm.draft('request:ventas'), 'Pregunta ventas');
    queue.push(response(fixture({ version: 1, historical: true })));
    context.version = 1;
    await nextTick();
    await settle(vm);
    assert.equal(vm.draft('request:ventas'), '');
    assert.equal(vm.allowed('request'), false);
    context.version = 2;
    await nextTick();
    assert.equal(vm.draft('request:clientes'), 'Pregunta clientes');
    assert.equal(vm.draft('conclusion'), 'Conclusión local');
});
for (const [status, code, pattern] of [
    [401, 'unauthenticated', /sesión/],
    [403, 'email_unverified', /verificada/],
    [404, 'not_found', /no está disponible/],
    [419, 'csrf_token_mismatch', /caducó/],
    [422, 'validation_failed', /800/],
    [503, 'schema_unavailable', /disponible/],
    [409, 'questionnaire_replaced', /sustituido/],
    [409, 'clarifications_open', /pendientes/],
])
    test(`error ${status}/${code}: sin pérdida local ni reenvío automático`, async (t) => {
        const { vm, queue, calls } = await setup(t);
        vm.setDraft('request:ventas', 'Texto conservado');
        queue.push(failure(status, code));
        assert.equal(await vm.send('request', 'ventas'), false);
        assert.match(vm.session.value.error, pattern);
        assert.equal(vm.draft('request:ventas'), 'Texto conservado');
        assert.equal(calls.length, 2);
    });
test('sin declaración enviada no inventa recepción, acciones ni cierre', async (t) => {
    const { vm, queue } = await setup(t);
    queue.push(failure(409, 'declaration_not_submitted'));
    await vm.load();
    assert.equal(vm.data.value, null);
    assert.match(vm.session.value.error, /borradores permanecen privados/);
    assert.equal(vm.allowed('request'), false);
});
test('proyección estricta rechaza versión, referencias ajenas e información interna', () => {
    const f = fixture({ status: 'requested' });
    assert.equal(isDeclarativeReview(f, 2, ['ventas', 'clientes']), true);
    assert.equal(isDeclarativeReview(f, 1, ['ventas', 'clientes']), false);
    assert.equal(isDeclarativeReview(f, 2, ['clientes']), false);
    const internal = clone(f);
    internal.clarifications[0].content_sha256 = 'secret';
    assert.equal(
        isDeclarativeReview(internal, 2, ['ventas', 'clientes']),
        false,
    );
    const extra = clone(f);
    extra.clarifications[0].events[0].actor_id = 123;
    assert.equal(isDeclarativeReview(extra, 2, ['ventas', 'clientes']), false);
    assert.equal(reviewTextValid('😀'.repeat(800)), true);
    assert.equal(reviewTextValid('a'.repeat(801)), false);
    assert.equal(reviewTextValid('   '), false);
});

// Vue's renderer exercises actual SFC events/navigation without browser, DB or a DEV session.
const node = (tag, text = '') => ({
    tag,
    text,
    props: {},
    children: [],
    parent: null,
    style: {},
});
const renderer = createRenderer({
    createElement: (tag) => node(tag),
    createText: (text) => node('#text', text),
    createComment: (text) => node('#comment', text),
    setText: (n, text) => {
        n.text = text;
    },
    setElementText: (n, text) => {
        n.text = text;
        n.children = [];
    },
    patchProp: (n, key, _old, value) => {
        n.props[key] = value;
    },
    insert(n, parent, anchor = null) {
        if (n.parent) this.remove(n);
        n.parent = parent;
        const at = parent.children.indexOf(anchor);
        parent.children.splice(at < 0 ? parent.children.length : at, 0, n);
    },
    remove(n) {
        const at = n.parent?.children.indexOf(n) ?? -1;
        if (at >= 0) n.parent.children.splice(at, 1);
        n.parent = null;
    },
    parentNode: (n) => n.parent,
    nextSibling: (n) =>
        n.parent?.children[n.parent.children.indexOf(n) + 1] ?? null,
});
const all = (n) => [n, ...n.children.flatMap(all)];
const content = (n) =>
    (n.tag === '#comment' ? '' : n.text) + n.children.map(content).join(' ');
const button = (host, label) =>
    all(host).find((n) => n.tag === 'button' && content(n).trim() === label);
async function render(
    t,
    initial = fixture({ status: 'requested' }),
    audience = 'admin',
    display = 'focused',
) {
    const http = transport(t, initial);
    const props = reactive({
        implementationRequestId: 101,
        version: 2,
        audience,
        enabled: true,
        requirements,
        declarations: clone(declarations),
        selectedKey: 'ventas',
        display,
        onFocusRequirement(key) {
            props.selectedKey = key;
            props.display = 'focused';
        },
    });
    const host = node('root');
    const app = renderer.createApp({ render: () => h(Panel, props) });
    app.mount(host);
    http.onCleanup(() => app.unmount());
    for (let i = 0; i < 5; i++) await tick();
    return { ...http, host, props };
}
test('render enfocado y lista compacta: pregunta asociada al requerimiento y actor/fechas de API', async (t) => {
    const { host, props } = await render(t);
    assert.match(content(host), /Ventas históricas/);
    assert.match(content(host), /¿Qué período falta\?/);
    assert.match(content(host), /Admin LAUDA/);
    assert.match(content(host), /Solicitada/);
    assert.equal(all(host).filter((n) => n.tag === 'time').length, 1);
    const field = all(host).find(
        (n) => n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
    );
    field.props.onInput({ target: { value: 'Texto antes de navegar' } });
    await nextTick();
    props.display = 'list';
    await nextTick();
    assert.match(content(host), /Clientes activos/);
    const inspect = all(host).find(
        (n) =>
            n.tag === 'button' &&
            n.props['aria-label'] === 'Ver aclaraciones de Clientes activos',
    );
    inspect.props.onClick();
    await nextTick();
    assert.equal(props.selectedKey, 'clientes');
    props.selectedKey = 'ventas';
    await nextTick();
    assert.equal(
        all(host).find(
            (n) =>
                n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
        ).props.value,
        'Texto antes de navegar',
    );
});
test('formulario Admin real: confirmación previa y solicitud HTTP por el requerimiento seleccionado', async (t) => {
    const { host, queue, calls } = await render(t, fixture());
    const field = all(host).find((n) => n.tag === 'textarea');
    field.props.onInput({ target: { value: 'Pregunta desde interfaz' } });
    await nextTick();
    all(host)
        .find((n) => n.tag === 'form')
        .props.onSubmit({ preventDefault() {} });
    await nextTick();
    assert.equal(calls.length, 1);
    assert.ok(button(host, 'Confirmar'));
    queue.push(response(fixture({ status: 'requested' })));
    await button(host, 'Confirmar').props.onClick();
    await tick();
    assert.equal(JSON.parse(calls[1].body).requirement_key, 'ventas');
    assert.match(content(host), /Aclaración solicitada/);
});
test('render Tenant conserva respuesta después del envío y no ofrece acciones administrativas', async (t) => {
    const { host, queue, calls } = await render(
        t,
        fixture({ status: 'requested', audience: 'tenant' }),
        'tenant',
    );
    assert.equal(button(host, 'Solicitar aclaración'), undefined);
    assert.equal(button(host, 'Cancelar aclaración'), undefined);
    const field = all(host).find((n) => n.tag === 'textarea');
    field.props.onInput({ target: { value: 'Respuesta del Tenant' } });
    await nextTick();
    all(host)
        .find((n) => n.tag === 'form')
        .props.onSubmit({ preventDefault() {} });
    await nextTick();
    const answered = fixture({ status: 'answered', audience: 'tenant' });
    answered.clarifications[0].events[1].message = 'Respuesta del Tenant';
    queue.push(response(answered));
    await button(host, 'Confirmar').props.onClick();
    await tick();
    assert.match(calls[1].url, /responder$/);
    assert.match(content(host), /Respuesta del Tenant/);
    assert.equal(all(host).filter((n) => n.tag === 'textarea').length, 0);
});
test('render cierre: brechas visibles, conclusión inmutable y ausencia de formularios tras cierre', async (t) => {
    const { host } = await render(
        t,
        fixture({ status: 'resolved', closed: true }),
    );
    assert.match(content(host), /Revisión declarativa gratuita completada/);
    assert.match(content(host), /No disponible/);
    assert.match(content(host), /No se inspeccionaron fuentes/);
    assert.match(content(host), /No certifica técnicamente/);
    assert.equal(all(host).filter((n) => n.tag === 'form').length, 0);
});
test('render histórico sin acciones, no mezcla el cierre con otra versión', async (t) => {
    const { host } = await render(
        t,
        fixture({ status: 'answered', historical: true }),
    );
    assert.match(content(host), /Publicación histórica/);
    assert.match(content(host), /Solo lectura/);
    assert.equal(all(host).filter((n) => n.tag === 'form').length, 0);
});
test('integración mantiene paneles montados y conserva declaración submitted como lectura', async () => {
    const admin = await readFile(
        root +
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue',
        'utf8',
    );
    assert.match(
        admin,
        /<DataBiTenantDeclarationsPanel[\s\S]*?v-show="d2fWorkspaceMode==='responses'"/,
    );
    const source = await readFile(
        root + 'resources/js/components/DataBiDeclarativeReviewPanel.vue',
        'utf8',
    );
    assert.doesNotMatch(
        source,
        /v-html|localStorage|workspace_company_id|actor_id|company_id|expected_version/,
    );
    assert.match(source, /v-show="\s*tab === 'closure'/);
    assert.match(source, /DialogDescription/);
    for (const file of [
        'resources/js/pages/Admin/Transformation360/ImplementationRequests/DataBiTenantDeclarationsPanel.vue',
        'resources/js/pages/App/DataBiInformationRequirementsPanel.vue',
    ]) {
        const parent = await readFile(root + file, 'utf8');
        assert.match(parent, /<DataBiDeclarativeReviewPanel/);
        const parsed = parse(parent, { filename: file });
        assert.deepEqual(parsed.errors, []);
        assert.ok(
            compileScript(parsed.descriptor, {
                id: file,
                inlineTemplate: true,
            }),
        );
    }
});

for (const action of ['resolve', 'cancel'])
    test(`interfaz Admin ${action}: explicación obligatoria y confirmación antes del POST`, async (t) => {
        const { host, queue, calls } = await render(
            t,
            fixture({ status: 'answered' }),
        );
        const field = all(host).find(
            (n) =>
                n.tag === 'textarea' &&
                String(n.props.id).includes(`-${action}-41`),
        );
        const form = field.parent;
        assert.ok(
            form.children.some((n) => n.tag === 'button' && n.props.disabled),
        );
        field.props.onInput({
            target: { value: 'Explicación administrativa' },
        });
        await nextTick();
        form.props.onSubmit({ preventDefault() {} });
        await nextTick();
        assert.equal(calls.length, 1);
        assert.match(content(host), /Este mensaje quedará en el historial/);
        queue.push(
            response(
                fixture({
                    status: action === 'resolve' ? 'resolved' : 'cancelled',
                }),
            ),
        );
        await button(host, 'Confirmar').props.onClick();
        await tick();
        assert.match(calls.at(-1).url, new RegExp(`/${action}$`));
        assert.equal(
            JSON.parse(calls.at(-1).body).message,
            'Explicación administrativa',
        );
    });

test('cierre desde interfaz: navegar subsecciones conserva ambos formularios y exige confirmación', async (t) => {
    const { host, queue, calls } = await render(
        t,
        fixture({ status: 'resolved' }),
    );
    const request = all(host).find(
        (n) => n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
    );
    request.props.onInput({ target: { value: 'Pregunta local sin enviar' } });
    await nextTick();
    button(host, 'Preparar cierre').props.onClick();
    await nextTick();
    const conclusion = all(host).find(
        (n) =>
            n.tag === 'textarea' && String(n.props.id).endsWith('-conclusion'),
    );
    const limitations = all(host).find(
        (n) =>
            n.tag === 'textarea' && String(n.props.id).endsWith('-limitations'),
    );
    conclusion.props.onInput({
        target: { value: 'Revisión declarativa con brechas' },
    });
    limitations.props.onInput({
        target: { value: 'Sin comprobación técnica' },
    });
    await nextTick();
    button(host, 'Aclaraciones (1)').props.onClick();
    await nextTick();
    assert.equal(request.props.value, 'Pregunta local sin enviar');
    button(host, 'Preparar cierre').props.onClick();
    await nextTick();
    assert.equal(conclusion.props.value, 'Revisión declarativa con brechas');
    assert.match(content(host), /No disponible/);
    conclusion.parent.parent.props.onSubmit({ preventDefault() {} });
    await nextTick();
    assert.equal(calls.length, 1);
    assert.match(content(host), /sin posibilidad de edición o reemplazo/);
    queue.push(response(fixture({ status: 'resolved', closed: true })));
    await button(host, 'Confirmar').props.onClick();
    await tick();
    assert.match(calls.at(-1).url, /\/close$/);
    assert.match(content(host), /Revisión declarativa gratuita completada/);
    assert.equal(all(host).filter((n) => n.tag === 'form').length, 0);
    assert.match(content(host), /Pregunta local sin enviar/);
});

test('interfaz bloquea cierre pendiente sin suponer que las brechas sean impedimento', async (t) => {
    const { host } = await render(t);
    button(host, 'Preparar cierre').props.onClick();
    await nextTick();
    assert.match(content(host), /1 pendientes/);
    assert.match(content(host), /El servidor no permite el cierre/);
    assert.equal(
        button(host, 'Completar revisión declarativa gratuita'),
        undefined,
    );
});

test('409 seguido de aclaración terminal conserva texto accesible; descartarlo exige confirmación', async (t) => {
    const { host, queue, calls } = await render(
        t,
        fixture({ status: 'answered' }),
    );
    const resolve = all(host).find(
        (n) =>
            n.tag === 'textarea' && String(n.props.id).includes('-resolve-41'),
    );
    resolve.props.onInput({ target: { value: 'Resolución local conservada' } });
    await nextTick();
    resolve.parent.props.onSubmit({ preventDefault() {} });
    await nextTick();
    queue.push(failure(409, 'revision_conflict'));
    await button(host, 'Confirmar').props.onClick();
    await tick();
    assert.match(content(host), /Resolución local conservada/);
    queue.push(response(fixture({ status: 'resolved' })));
    await button(host, 'Consultar estado vigente').props.onClick();
    await tick();
    button(host, 'Continuar con el estado consultado').props.onClick();
    await tick();
    assert.match(content(host), /Resolución local conservada/);
    assert.equal(
        all(host).some(
            (n) =>
                n.tag === 'textarea' &&
                String(n.props.id).includes('-resolve-41'),
        ),
        false,
    );
    const discard = all(host).find(
        (n) =>
            n.tag === 'button' &&
            String(n.props['aria-label']).startsWith(
                'Descartar texto local: Resolver',
            ),
    );
    assert.ok(discard);
    discard.props.onClick();
    await nextTick();
    assert.match(
        content(host),
        /Se eliminará únicamente este texto sin enviar/,
    );
    assert.match(content(host), /Resolución local conservada/);
    const before = calls.length;
    await button(host, 'Confirmar').props.onClick();
    await nextTick();
    assert.doesNotMatch(content(host), /Resolución local conservada/);
    assert.equal(calls.length, before);
});

test('inactivar sección y volver conserva la pregunta local sin nuevas consultas ni persistencia', async (t) => {
    const { host, props, calls } = await render(t, fixture());
    const field = all(host).find(
        (n) => n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
    );
    field.props.onInput({ target: { value: 'Pregunta en proceso' } });
    await nextTick();
    props.enabled = false;
    await nextTick();
    props.enabled = true;
    await tick();
    assert.equal(
        all(host).find(
            (n) =>
                n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
        ).props.value,
        'Pregunta en proceso',
    );
    assert.equal(calls.length, 1);
});

test('respuesta lenta de otra solicitud no cruza aclaraciones ni borradores', async (t) => {
    const { vm, queue, context } = await setup(t);
    vm.setDraft('request:ventas', 'Empresa original');
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const previous = vm.load();
    context.requestId = 202;
    await nextTick();
    queue.push(response(fixture({ status: 'requested' })));
    finish(response(fixture()));
    await previous;
    await tick();
    await settle(vm);
    assert.equal(vm.data.value.clarifications.length, 1);
    assert.equal(vm.draft('request:ventas'), '');
    context.requestId = 101;
    await nextTick();
    assert.equal(vm.data.value.clarifications.length, 0);
    assert.equal(vm.draft('request:ventas'), 'Empresa original');
});

test('proyección de éxito no verificable conserva texto y permite únicamente recuperación explícita', async (t) => {
    const { vm, queue, calls } = await setup(t);
    vm.setDraft('request:ventas', 'Pregunta sin confirmación');
    const altered = fixture();
    altered.questionnaire_version = 99;
    queue.push(response(altered));
    assert.equal(await vm.send('request', 'ventas'), false);
    assert.equal(vm.session.value.uncertain, true);
    assert.equal(vm.draft('request:ventas'), 'Pregunta sin confirmación');
    assert.equal(vm.allowed('request'), false);
    assert.equal(calls.length, 2);
});

test('histórico y cierre impiden reintentar después de consultar el resultado de un envío incierto', async (t) => {
    const { vm, queue, calls } = await setup(t);
    vm.setDraft('request:ventas', 'Pregunta');
    queue.push(new TypeError('offline'));
    await vm.send('request', 'ventas');
    queue.push(response(fixture({ historical: true, closed: true })));
    await vm.load();
    const count = calls.length;
    assert.equal(await vm.retry(), false);
    assert.equal(calls.length, count);
    assert.equal(vm.draft('request:ventas'), 'Pregunta');
});

test('doble confirmación de interfaz conserva el bloqueo hasta recibir el único POST', async (t) => {
    const { host, queue, calls } = await render(t, fixture());
    const field = all(host).find(
        (n) => n.tag === 'textarea' && String(n.props.id).endsWith('-request'),
    );
    field.props.onInput({ target: { value: 'Pregunta confirmada una vez' } });
    await nextTick();
    field.parent.props.onSubmit({ preventDefault() {} });
    await nextTick();
    let finish;
    queue.push(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const confirm = button(host, 'Confirmar');
    const pending = confirm.props.onClick();
    await nextTick();
    await confirm.props.onClick();
    assert.equal(calls.length, 2);
    assert.ok(button(host, 'Enviando…'));
    finish(response(fixture({ status: 'requested' })));
    await pending;
    await tick();
    assert.equal(calls.length, 2);
    assert.equal(button(host, 'Enviando…'), undefined);
});

test('CSRF tras login Inertia: cookie actual prevalece sobre meta anterior, sin mandar ambos headers', async (t) => {
    const { vm, queue, calls } = await setup(t);
    globalThis.document.cookie =
        'appearance=dark; XSRF-TOKEN=current%2Bencrypted%3Dtoken; sidebar_state=true';
    globalThis.document.querySelector = () => ({
        content: 'old-meta-before-login',
    });
    vm.setDraft('request:ventas', 'Pregunta tras iniciar sesión');
    queue.push(response(fixture({ status: 'requested' })));
    assert.equal(await vm.send('request', 'ventas'), true);
    assert.equal(calls[1].headers['X-XSRF-TOKEN'], 'current+encrypted=token');
    assert.equal(calls[1].headers['X-CSRF-TOKEN'], undefined);
    assert.equal(calls[1].credentials, 'same-origin');
});

test('CSRF se lee de nuevo para cada POST, incluyendo el reintento idéntico después de rotación', async (t) => {
    const { vm, queue, calls } = await setup(t);
    globalThis.document.cookie = 'XSRF-TOKEN=first-cookie';
    vm.setDraft('request:ventas', 'Pregunta');
    queue.push(new TypeError('offline'));
    await vm.send('request', 'ventas');
    globalThis.document.cookie = 'XSRF-TOKEN=rotated-cookie';
    queue.push(response(fixture({ status: 'requested' })));
    assert.equal(await vm.retry(), true);
    assert.equal(calls[1].body, calls[2].body);
    assert.equal(calls[1].headers['X-XSRF-TOKEN'], 'first-cookie');
    assert.equal(calls[2].headers['X-XSRF-TOKEN'], 'rotated-cookie');
});

test('CSRF con cookie mal codificada conserva el fallback meta aprobado de T4E', async (t) => {
    const { vm, queue, calls } = await setup(t);
    globalThis.document.cookie = 'XSRF-TOKEN=%not-an-encoded-cookie';
    vm.setDraft('request:ventas', 'Pregunta');
    queue.push(response(fixture({ status: 'requested' })));
    assert.equal(await vm.send('request', 'ventas'), true);
    assert.equal(calls[1].headers['X-CSRF-TOKEN'], 'synthetic-csrf-token');
    assert.equal(calls[1].headers['X-XSRF-TOKEN'], undefined);
});
