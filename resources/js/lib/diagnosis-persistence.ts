export type DiagnosisDraft = {
    answers: Record<string, number>;
    notes: Record<string, string>;
    step: number;
};

/** Stable comparison, independent of JSON object key order returned by MySQL. */
export function draftSnapshot(value: unknown): string {
    return JSON.stringify(value, (_key, item) => {
        if (item && typeof item === 'object' && !Array.isArray(item)) {
            return Object.fromEntries(
                Object.entries(item).sort(([a], [b]) => a.localeCompare(b)),
            );
        }
        return item;
    });
}

export function confirmedVisit<T>(
    send: (callbacks: {
        onSuccess: (page: T) => void;
        onError: (errors: Record<string, string>) => void;
        onCancel: () => void;
        onFinish: () => void;
    }) => void,
    accept: (page: T) => boolean,
    error: (errors: Record<string, string>) => void,
): Promise<boolean> {
    return new Promise((resolve) => {
        let saved = false;
        try {
            send({
                onSuccess: (page) => {
                    try {
                        saved = accept(page);
                    } catch {
                        saved = false;
                        error({ request: 'No se pudo confirmar el guardado. Tus respuestas se conservan.' });
                    }
                },
                onError: (errors) => {
                    saved = false;
                    error(errors);
                },
                onCancel: () => {
                    saved = false;
                },
                onFinish: () => resolve(saved),
            });
        } catch {
            error({
                request:
                    'No se pudo conectar. Tus respuestas se conservan; vuelve a intentar guardar.',
            });
            resolve(false);
        }
    });
}

export async function navigateAfterSave(
    step: number,
    save: () => Promise<boolean>,
    move: (step: number) => void,
): Promise<boolean> {
    if (!(await save())) return false;
    move(step);
    return true;
}
