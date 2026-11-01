import { computed, onScopeDispose, ref, watch } from 'vue';

export const availabilities = [
    ['available', 'Disponible'],
    ['partial', 'Parcialmente disponible'],
    ['unavailable', 'No disponible'],
    ['pending', 'Por confirmar'],
    ['unknown', 'No sabe / requiere revisión'],
] as const;
export const sourceTypes = [
    ['software', 'Software'],
    ['database', 'Base de datos'],
    ['spreadsheet', 'Hoja de cálculo'],
    ['flat_file', 'Archivo de texto o CSV'],
    ['api', 'API'],
    ['other', 'Otra fuente'],
] as const;
export type Availability = (typeof availabilities)[number][0];
export type SourceType = (typeof sourceTypes)[number][0];
export type DeclarationSource = {
    type: SourceType;
    name: string;
    structures: string[];
    notes: string;
};
export type Declaration = {
    requirement_key: string;
    availability: Availability;
    sources: DeclarationSource[];
    notes: string;
};
export type Requirement = {
    key: string;
    label: string;
    guidance: string;
    objective_keys: string[];
};
export type Questionnaire = {
    implementation_request_id: number;
    version: number;
    sent_at: string | null;
    objectives: { key: string; label: string }[];
    requirements: Requirement[];
    declaration_only: true;
    can_respond: boolean;
    attachments_required: false;
};
type StoredDeclaration = {
    id: number;
    status: 'draft' | 'submitted';
    revision: number;
    version: number;
    submitted_at: string | null;
    declarations: Declaration[];
};
type Current = {
    implementation_request_id: number;
    version: number;
    declaration: StoredDeclaration | null;
};
type Remote = { questionnaire: Questionnaire; current: Current | null };

const record = (x: unknown): x is Record<string, unknown> =>
    !!x && typeof x === 'object' && !Array.isArray(x);
const integer = (x: unknown, min = 1): x is number =>
    Number.isSafeInteger(x) && Number(x) >= min;
const strings = (x: unknown): x is string[] =>
    Array.isArray(x) && x.every((v) => typeof v === 'string');
const textLength = (text: string): number => Array.from(text.trim()).length;

export function isQuestionnaire(x: unknown, id: number): x is Questionnaire {
    return (
        record(x) &&
        x.implementation_request_id === id &&
        integer(x.version) &&
        x.version <= 9999 &&
        (x.sent_at === null || typeof x.sent_at === 'string') &&
        x.declaration_only === true &&
        typeof x.can_respond === 'boolean' &&
        x.attachments_required === false &&
        Array.isArray(x.objectives) &&
        x.objectives.every(
            (o) =>
                record(o) &&
                typeof o.key === 'string' &&
                typeof o.label === 'string',
        ) &&
        Array.isArray(x.requirements) &&
        x.requirements.length > 0 &&
        x.requirements.length <= 100 &&
        new Set(x.requirements.map((r) => (record(r) ? r.key : null))).size ===
            x.requirements.length &&
        x.requirements.every(
            (r) =>
                record(r) &&
                typeof r.key === 'string' &&
                typeof r.label === 'string' &&
                typeof r.guidance === 'string' &&
                strings(r.objective_keys),
        )
    );
}

function isDeclaration(x: unknown): x is Declaration {
    return (
        record(x) &&
        typeof x.requirement_key === 'string' &&
        availabilities.some(([key]) => key === x.availability) &&
        typeof x.notes === 'string' &&
        Array.isArray(x.sources) &&
        x.sources.length <= 6 &&
        x.sources.every(
            (s) =>
                record(s) &&
                sourceTypes.some(([key]) => key === s.type) &&
                typeof s.name === 'string' &&
                typeof s.notes === 'string' &&
                strings(s.structures) &&
                s.structures.length <= 12,
        )
    );
}

function isCurrent(x: unknown, q: Questionnaire): x is Current {
    if (
        !record(x) ||
        x.implementation_request_id !== q.implementation_request_id ||
        x.version !== q.version
    )
        return false;
    const d = x.declaration;
    return (
        d === null ||
        (record(d) &&
            integer(d.id) &&
            integer(d.revision) &&
            d.revision <= 4294967295 &&
            d.version === q.version &&
            (d.status === 'draft' || d.status === 'submitted') &&
            (d.status === 'draft'
                ? d.submitted_at === null
                : typeof d.submitted_at === 'string') &&
            Array.isArray(d.declarations) &&
            d.declarations.length === q.requirements.length &&
            d.declarations.every(isDeclaration) &&
            q.requirements.every(
                (r) =>
                    d.declarations instanceof Array &&
                    d.declarations.filter(
                        (item) =>
                            isDeclaration(item) &&
                            item.requirement_key === r.key,
                    ).length === 1,
            ))
    );
}

// Proyección explícita de los únicos datos declarativos que admite T4B.
export function declarationPayload(
    version: number,
    declarations: Declaration[],
    revision: number,
) {
    return {
        version,
        declarations: declarations.map((d) => ({
            requirement_key: d.requirement_key,
            availability: d.availability,
            notes: d.notes,
            sources: d.sources.map((s) => ({
                type: s.type,
                name: s.name,
                notes: s.notes,
                // Una fila vacía del editor es un campo aún sin completar, no una estructura conocida.
                structures: s.structures.filter((value) => value.trim() !== ''),
            })),
        })),
        expected_revision: revision,
    };
}

export function declarationIssues(d: Declaration, submit: boolean): string[] {
    const issues: string[] = [];
    if (submit && d.availability === 'pending')
        issues.push('Confirma la disponibilidad antes de enviar.');
    if (
        submit &&
        ['partial', 'unavailable', 'unknown'].includes(d.availability) &&
        !d.notes.trim()
    ) {
        issues.push(
            'Explica esta disponibilidad en las observaciones del requerimiento.',
        );
    }
    if (
        ['unavailable', 'unknown'].includes(d.availability) &&
        d.sources.length
    ) {
        issues.push(
            'Esta disponibilidad no admite fuentes. Quita las fuentes o cambia la disponibilidad.',
        );
    }
    if (
        submit &&
        ['available', 'partial'].includes(d.availability) &&
        !d.sources.length
    )
        issues.push('Agrega al menos una fuente de información.');
    if (textLength(d.notes) > 800)
        issues.push(
            'Las observaciones del requerimiento admiten hasta 800 caracteres.',
        );
    if (d.sources.length > 6)
        issues.push('Puedes declarar hasta seis fuentes por requerimiento.');
    const seen = new Set<string>();
    for (const [i, s] of d.sources.entries()) {
        const prefix = `Fuente ${i + 1}: `;
        const names = s.structures.filter((v) => v.trim() !== '');
        if (submit && !s.name.trim())
            issues.push(prefix + 'escribe el nombre de la fuente.');
        if (submit && !names.length)
            issues.push(prefix + 'indica al menos una estructura conocida.');
        if (textLength(s.name) > 191 || /[\r\n]/.test(s.name))
            issues.push(
                prefix + 'el nombre admite hasta 191 caracteres, en una línea.',
            );
        if (textLength(s.notes) > 800)
            issues.push(
                prefix + 'las observaciones admiten hasta 800 caracteres.',
            );
        if (names.length > 12)
            issues.push(prefix + 'puedes indicar hasta doce estructuras.');
        if (names.some((n) => textLength(n) > 191 || /[\r\n]/.test(n)))
            issues.push(
                prefix +
                    'cada estructura admite hasta 191 caracteres, en una línea.',
            );
        if (
            new Set(names.map((n) => n.trim().toLowerCase())).size !==
            names.length
        )
            issues.push(prefix + 'hay nombres de estructuras repetidos.');
        const key = s.type + ':' + s.name.trim().toLowerCase();
        if (seen.has(key))
            issues.push(
                prefix +
                    'hay otra fuente del mismo tipo y nombre; identifica cada fuente de forma distinta.',
            );
        seen.add(key);
    }
    return issues;
}

class HttpFailure extends Error {
    constructor(
        public status: number,
        public code: string,
        public fields: Record<string, unknown>,
    ) {
        super(code);
    }
}

function message(error: unknown): string {
    if (!(error instanceof HttpFailure))
        return 'No se pudo confirmar la operación. Tus respuestas siguen aquí; revisa la conexión y vuelve a intentarlo.';
    return (
        (
            {
                401: 'Tu sesión terminó. Inicia sesión de nuevo; tus cambios locales siguen en esta pantalla.',
                403: 'Verifica el correo de tu cuenta antes de responder.',
                404: 'La solicitud o el cuestionario ya no están disponibles para tu cuenta.',
                409:
                    error.code === 'questionnaire_replaced'
                        ? 'LAUDA publicó otra versión del cuestionario. Consulta la versión vigente antes de continuar.'
                        : error.code === 'declaration_submitted'
                          ? 'La declaración ya fue enviada desde otra sesión. Consulta las respuestas vigentes.'
                          : 'La declaración cambió en otra sesión. Consulta la revisión vigente antes de volver a guardar.',
                419: 'La sesión o el token de seguridad vencieron. Abre una sesión vigente antes de reintentar; tus respuestas siguen aquí.',
                422: 'El servidor rechazó parte de la respuesta. Revisa los requerimientos y sus observaciones.',
                503: 'La respuesta no está disponible temporalmente. Puedes seguir consultando el cuestionario.',
            } as Record<number, string>
        )[error.status] ??
        'No fue posible completar la operación. Conservamos tus respuestas para volver a intentarlo.'
    );
}

export function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };
    if (typeof document === 'undefined') return headers;
    const cookie = document.cookie
        .split(';')
        .map((c) => c.trim())
        .find((c) => c.startsWith('XSRF-TOKEN='));
    if (cookie) {
        try {
            headers['X-XSRF-TOKEN'] = decodeURIComponent(
                cookie.slice('XSRF-TOKEN='.length),
            );
        } catch {
            /* Usar el token meta si la cookie no es válida. */
        }
    }
    if (!headers['X-XSRF-TOKEN']) {
        const token = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        if (token) headers['X-CSRF-TOKEN'] = token;
    }
    return headers;
}

export function useDataBiTenantDeclaration(requestId: () => number | null) {
    const questionnaire = ref<Questionnaire | null>(null);
    const declarations = ref<Declaration[]>([]);
    const revision = ref(0);
    const storedStatus = ref<'draft' | 'submitted' | null>(null);
    const submittedAt = ref<string | null>(null);
    const state = ref<'loading' | 'ready' | 'absent' | 'error'>('loading');
    const operation = ref<'load' | 'save' | 'submit' | 'inspect' | null>(null);
    const loadedId = ref<number | null>(null);
    const initialized = ref(false);
    const saved = ref('');
    const notice = ref('');
    const error = ref('');
    const conflict = ref(false);
    const remote = ref<Remote | null>(null);
    const showErrors = ref(false);
    const busy = computed(() => operation.value !== null);
    const fingerprint = computed(() => JSON.stringify(declarations.value));
    const dirty = computed(
        () => initialized.value && fingerprint.value !== saved.value,
    );
    const contextChanged = computed(
        () => loadedId.value !== null && requestId() !== loadedId.value,
    );
    const submitted = computed(() => storedStatus.value === 'submitted');
    const editable = computed(
        () =>
            !!questionnaire.value?.can_respond &&
            initialized.value &&
            !submitted.value &&
            !contextChanged.value &&
            remote.value?.current?.declaration?.status !== 'submitted',
    );
    const canWrite = computed(
        () => editable.value && !busy.value && !conflict.value,
    );
    const completionIssues = computed(() =>
        Object.fromEntries(
            declarations.value.map((d) => [
                d.requirement_key,
                declarationIssues(d, true),
            ]),
        ),
    );
    const visibleIssues = computed(() =>
        Object.fromEntries(
            declarations.value.map((d) => [
                d.requirement_key,
                declarationIssues(d, showErrors.value),
            ]),
        ),
    );
    const completed = computed(
        () =>
            declarations.value.filter(
                (d) => completionIssues.value[d.requirement_key]?.length === 0,
            ).length,
    );
    const canRebase = computed(
        () =>
            initialized.value &&
            !!remote.value?.questionnaire.can_respond &&
            remote.value.questionnaire.version ===
                questionnaire.value?.version &&
            remote.value.current?.declaration?.status !== 'submitted' &&
            !contextChanged.value,
    );
    let controller: AbortController | null = null;
    let disposed = false;

    async function api(
        url: string,
        method: 'GET' | 'POST',
        body?: unknown,
    ): Promise<unknown> {
        const active = controller;
        const timeout = setTimeout(() => active?.abort(), 30000);
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: csrfHeaders(),
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
                signal: active?.signal,
            });
            let result: unknown;
            try {
                result = await response.json();
            } catch {
                if (!response.ok)
                    throw new HttpFailure(response.status, '', {});
                throw new Error('Respuesta JSON no válida.');
            }
            if (!response.ok) {
                const detail =
                    record(result) && record(result.error) ? result.error : {};
                throw new HttpFailure(
                    response.status,
                    typeof detail.code === 'string' ? detail.code : '',
                    record(detail.fields) ? detail.fields : {},
                );
            }
            if (disposed || active?.signal.aborted)
                throw new Error('Solicitud cancelada.');
            return result;
        } finally {
            clearTimeout(timeout);
        }
    }

    const baseUrl = (id: number) =>
        `/app/transformacion-360/datos-bi/requerimientos/${id}`;
    function begin(kind: NonNullable<typeof operation.value>): void {
        controller = new AbortController();
        operation.value = kind;
        error.value = '';
        notice.value = '';
    }
    function failure(e: unknown): void {
        if (disposed) return;
        error.value = message(e);
        if (e instanceof HttpFailure && e.status === 409) conflict.value = true;
        if (e instanceof HttpFailure && e.status === 422) {
            showErrors.value = operation.value === 'submit';
            const fields = Object.values(e.fields)
                .flat()
                .filter((v) => typeof v === 'string');
            if (fields.length) error.value += ' ' + fields.join(' ');
        }
    }
    async function current(q: Questionnaire): Promise<Current> {
        const result = await api(
            baseUrl(q.implementation_request_id) + '/declaracion',
            'GET',
        );
        if (!record(result) || !isCurrent(result.data, q))
            throw new Error(
                'La declaración recibida no corresponde al cuestionario.',
            );
        return result.data;
    }
    function applyCurrent(q: Questionnaire, data: Current | null): void {
        questionnaire.value = q;
        loadedId.value = q.implementation_request_id;
        declarations.value = q.requirements.map((r) => {
            const d = data?.declaration?.declarations.find(
                (d) => d.requirement_key === r.key,
            );
            return d
                ? declarationPayload(q.version, [d], 0).declarations[0]
                : {
                      requirement_key: r.key,
                      availability: 'pending',
                      sources: [],
                      notes: '',
                  };
        });
        revision.value = data?.declaration?.revision ?? 0;
        storedStatus.value = data?.declaration?.status ?? null;
        submittedAt.value = data?.declaration?.submitted_at ?? null;
        initialized.value = q.can_respond && data !== null;
        saved.value = fingerprint.value;
        conflict.value = false;
        remote.value = null;
        showErrors.value = false;
    }

    async function load(): Promise<void> {
        // Nunca sustituir respuestas locales de forma implícita.
        if (busy.value || dirty.value || disposed) return;
        const id = requestId();
        if (!integer(id)) {
            state.value = 'absent';
            return;
        }
        begin('load');
        state.value = questionnaire.value ? 'ready' : 'loading';
        try {
            const q = await api(baseUrl(id), 'GET');
            if (!isQuestionnaire(q, id))
                throw new Error('Cuestionario no válido.');
            // Mostrar siempre la publicación, incluso si la consulta de declaraciones falla.
            applyCurrent(q, null);
            state.value = 'ready';
            if (q.can_respond) applyCurrent(q, await current(q));
        } catch (e) {
            failure(e);
            if (!questionnaire.value)
                state.value =
                    e instanceof HttpFailure && e.status === 404
                        ? 'absent'
                        : 'error';
        } finally {
            if (!disposed) operation.value = null;
        }
    }

    function validateSubmission(): boolean {
        showErrors.value = true;
        if (completed.value !== declarations.value.length) {
            error.value =
                'Completa los requerimientos indicados antes de enviar. Puedes guardar un borrador en cualquier momento.';
            return false;
        }
        return canWrite.value;
    }

    async function write(submit: boolean): Promise<boolean> {
        if (!canWrite.value || !questionnaire.value) return false;
        if (submit && !validateSubmission()) return false;
        if (
            declarations.value.some((d) => declarationIssues(d, false).length)
        ) {
            showErrors.value = submit;
            error.value =
                'Revisa los datos indicados. El borrador puede estar incompleto, pero debe respetar los límites de cada campo.';
            return false;
        }
        const q = questionnaire.value;
        const payload = declarationPayload(
            q.version,
            declarations.value,
            revision.value,
        );
        begin(submit ? 'submit' : 'save');
        try {
            const result = await api(
                baseUrl(q.implementation_request_id) +
                    (submit ? '/declaracion/enviar' : '/declaracion/borrador'),
                'POST',
                payload,
            );
            if (
                !record(result) ||
                !isCurrent(result.data, q) ||
                result.data.declaration?.status !==
                    (submit ? 'submitted' : 'draft')
            )
                throw new Error('Estado de respuesta no válido.');
            applyCurrent(q, result.data);
            notice.value = submit
                ? 'Declaración enviada. Puedes consultar tus respuestas; la edición quedó cerrada.'
                : 'Borrador guardado correctamente.';
            return true;
        } catch (e) {
            failure(e);
            return false;
        } finally {
            if (!disposed) operation.value = null;
        }
    }

    async function inspectCurrent(): Promise<void> {
        if (busy.value || disposed) return;
        const id = requestId();
        if (!integer(id)) return;
        begin('inspect');
        try {
            const q = await api(baseUrl(id), 'GET');
            if (!isQuestionnaire(q, id))
                throw new Error('Cuestionario no válido.');
            remote.value = {
                questionnaire: q,
                current: q.can_respond ? await current(q) : null,
            };
            notice.value =
                'Consulta vigente cargada. Tus respuestas locales se conservaron.';
        } catch (e) {
            failure(e);
        } finally {
            if (!disposed) operation.value = null;
        }
    }

    function useRemote(): void {
        if (!remote.value || busy.value) return;
        applyCurrent(remote.value.questionnaire, remote.value.current);
        error.value = '';
        notice.value = 'Se cargaron el cuestionario y las respuestas vigentes.';
    }
    function keepLocalOnCurrentRevision(): void {
        if (!canRebase.value || !remote.value || busy.value) return;
        revision.value = remote.value.current?.declaration?.revision ?? 0;
        storedStatus.value = remote.value.current?.declaration?.status ?? null;
        saved.value = JSON.stringify(
            declarationPayload(
                questionnaire.value!.version,
                remote.value.current?.declaration?.declarations ?? [],
                revision.value,
            ).declarations,
        );
        conflict.value = false;
        remote.value = null;
        error.value = '';
        notice.value =
            'Conservamos tus respuestas. El próximo guardado reemplazará el borrador vigente.';
    }
    function addSource(d: Declaration): void {
        if (editable.value && !busy.value && d.sources.length < 6)
            d.sources.push({
                type: 'software',
                name: '',
                structures: [],
                notes: '',
            });
    }
    function addStructure(s: DeclarationSource): void {
        if (editable.value && !busy.value && s.structures.length < 12)
            s.structures.push('');
    }

    watch(
        requestId,
        () => {
            if (dirty.value || busy.value) {
                if (contextChanged.value)
                    error.value =
                        'La solicitud cambió. Conservamos tus respuestas locales; consulta la versión vigente para elegir cómo continuar.';
                return;
            }
            questionnaire.value = null;
            initialized.value = false;
            void load();
        },
        { immediate: true },
    );
    watch(fingerprint, () => {
        if (dirty.value) notice.value = '';
    });
    onScopeDispose(() => {
        disposed = true;
        controller?.abort();
    });

    return {
        questionnaire,
        declarations,
        revision,
        storedStatus,
        submittedAt,
        state,
        operation,
        busy,
        dirty,
        initialized,
        notice,
        error,
        conflict,
        remote,
        contextChanged,
        submitted,
        editable,
        canWrite,
        completionIssues,
        visibleIssues,
        completed,
        showErrors,
        canRebase,
        load,
        validateSubmission,
        saveDraft: () => write(false),
        submit: () => write(true),
        inspectCurrent,
        useRemote,
        keepLocalOnCurrentRevision,
        addSource,
        addStructure,
    };
}
