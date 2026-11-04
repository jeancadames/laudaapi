import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { interpretationBand } from '../../resources/js/lib/diagnosis-interpretation.ts';

// Load the exact Laravel narrative ranges without app boot or environment reads.
const bands = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$c = new Illuminate\\Container\\Container;
Illuminate\\Container\\Container::setInstance($c);
$c->instance('config', new Illuminate\\Config\\Repository(['lauda360_diagnosis' => require 'config/lauda360_diagnosis.php']));
echo json_encode((new App\\Services\\Diagnosis\\DiagnosisInterpretation)->bands());
`], { encoding: 'utf8' }));

for (const [score, label] of [[0, 'Capacidad interna limitada'], [39, 'Capacidad interna limitada'], [40, 'Capacidad interna media'], [44, 'Capacidad interna media'], [69, 'Capacidad interna media'], [70, 'Capacidad interna alta'], [100, 'Capacidad interna alta']]) {
    test(`wizard capacity ${score} follows official ranges`, () => assert.equal(interpretationBand(score, bands.capacity)?.label, label));
}
for (const [score, label] of [[0, 'Baja'], [24, 'Baja'], [25, 'Media'], [49, 'Media'], [50, 'Alta'], [74, 'Alta'], [75, 'Crítica'], [100, 'Crítica']]) {
    test(`wizard urgency ${score} follows official ranges`, () => assert.equal(interpretationBand(score, bands.urgency)?.label, label));
}
test('wizard consumes server ranges for three separate indicators', () => {
    const wizard = readFileSync('resources/js/pages/Diagnosis/DigitalDiagnosisWizard.vue', 'utf8');
    const page = readFileSync('resources/js/pages/Diagnosis/Show.vue', 'utf8');
    for (const name of ['maturity', 'capacity', 'urgency']) assert.ok(wizard.includes(`props.bands.${name}`));
    assert.ok(page.includes(':bands="interpretationBands"'));
    assert.ok(!wizard.includes('capacity.value >= 45'));
});

test('declared notes render as text, including untrusted HTML', async () => {
    const { parse, compileScript } = await import('@vue/compiler-sfc');
    const ts = (await import('typescript')).default;
    const { createRequire } = await import('node:module');
    const { createSSRApp } = await import('vue');
    const { renderToString } = await import('@vue/server-renderer');
    const filename = 'resources/js/components/diagnosis/DiagnosisDeclaredEvidence.vue';
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename });
    const compiled = compileScript(descriptor, { id: 'declared-evidence', inlineTemplate: true, templateOptions: { ssr: true } });
    const js = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
    const mod = { exports: {} };
    new Function('require', 'module', 'exports', js)(createRequire(import.meta.url), mod, mod.exports);
    const html = await renderToString(createSSRApp(mod.exports.default, {
        items: [{ question_id: 'STR-01', answer: 1, note: '<script>alert(1)</script>\nDeclaración sin verificar.' }],
    }));
    assert.ok(html.includes('STR-01'));
    assert.ok(html.includes('1/5'));
    assert.ok(html.includes('sin verificación técnica'));
    assert.ok(html.includes('&lt;script&gt;'));
    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('Declaración sin verificar.'));
});
