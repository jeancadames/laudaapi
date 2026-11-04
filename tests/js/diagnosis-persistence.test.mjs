import assert from 'node:assert/strict';
import test from 'node:test';
import { effectScope, isReactive, reactive } from 'vue';
import { useRemember } from '@inertiajs/vue3';
import { router } from '@inertiajs/core';
import {
    confirmedVisit,
    draftSnapshot,
    navigateAfterSave,
} from '../../resources/js/lib/diagnosis-persistence.ts';

test('Inertia restores a reactive shared draft, including unsaved answers and notes', () => {
    const restore = router.restore;
    const remember = router.remember;
    const scope = effectScope();
    try {
        router.restore = () => ({ answers: { a: 4 }, notes: { a: 'Evidencia sin guardar' }, step: 2 });
        router.remember = () => {};
        scope.run(() => {
            const state = useRemember(reactive({ answers: {}, notes: {}, step: 1 }), 'diagnosis:test');
            assert.equal(isReactive(state), true);
            assert.equal(state.step, 2);
            assert.equal(state.notes.a, 'Evidencia sin guardar');
        });
    } finally { scope.stop(); router.restore = restore; router.remember = remember; }
});

test('a slow save does not navigate until successful completion and stores the destination', async () => {
    let callbacks;
    let step = 1;
    const promise = navigateAfterSave(
        2,
        () =>
            confirmedVisit(
                (c) => {
                    callbacks = c;
                },
                () => true,
                () => {},
            ),
        (next) => {
            step = next;
        },
    );
    assert.equal(step, 1);
    callbacks.onSuccess({ props: {} });
    await Promise.resolve();
    assert.equal(step, 1);
    callbacks.onFinish();
    assert.equal(await promise, true);
    assert.equal(step, 2);
});

for (const failure of [
    'validation',
    'cancel',
    'unconfirmed',
    'conflict',
    'network',
]) {
    test(`${failure} keeps step, answers and notes intact`, async () => {
        const draft = {
            answers: { 'STR-01': 4 },
            notes: { 'STR-01': 'Evidencia local' },
            step: 3,
        };
        const before = structuredClone(draft);
        const ok = await navigateAfterSave(
            2,
            () =>
                confirmedVisit(
                    (c) => {
                        if (failure === 'network') throw new Error('offline');
                        if (failure === 'validation')
                            c.onError({ notes: 'Revise la nota' });
                        if (failure === 'cancel') c.onCancel();
                        if (failure === 'conflict') c.onSuccess({ props: {} });
                        c.onFinish();
                    },
                    () => false,
                    () => {},
                ),
            (next) => {
                draft.step = next;
            },
        );
        assert.equal(ok, false);
        assert.deepEqual(draft, before);
    });
}

test('retry can succeed after an error without clearing the local draft', async () => {
    let error;
    const saved = await confirmedVisit(
        (c) => {
            c.onError({ expected_revision: 'Conflicto' });
            c.onFinish();
        },
        () => true,
        (e) => {
            error = e;
        },
    );
    assert.equal(saved, false);
    assert.equal(error.expected_revision, 'Conflicto');
    assert.equal(
        await confirmedVisit(
            (c) => {
                c.onSuccess({});
                c.onFinish();
            },
            () => true,
            () => {},
        ),
        true,
    );
});

test('a newer redirect snapshot is not silently accepted as confirmation of our save', async () => {
    assert.equal(
        await confirmedVisit(
            (c) => {
                c.onSuccess({ revision: 'another writer' });
                c.onFinish();
            },
            (page) => page.revision === 'our save',
            () => {},
        ),
        false,
    );
});

test('an invalid success response fails closed without an unhandled confirmation error', async () => {
    let message;
    const ok = await confirmedVisit((c) => { c.onSuccess({}); c.onFinish(); },
        () => { throw new Error('invalid response shape'); },
        (errors) => { message = errors.request; });
    assert.equal(ok, false);
    assert.match(message, /respuestas se conservan/);
});

test('canonical snapshots ignore JSON key order but detect answer, note and destination changes', () => {
    assert.equal(
        draftSnapshot({ answers: { a: 1, b: 2 }, notes: {} }),
        draftSnapshot({ notes: {}, answers: { b: 2, a: 1 } }),
    );
    for (const value of [
        { answers: { a: 2 }, notes: {}, step: 1 },
        { answers: { a: 1 }, notes: { a: 'nota' }, step: 1 },
        { answers: { a: 1 }, notes: {}, step: 2 },
    ]) {
        assert.notEqual(
            draftSnapshot(value),
            draftSnapshot({ answers: { a: 1 }, notes: {}, step: 1 }),
        );
    }
});
