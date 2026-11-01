import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Declaration, Requirement } from './useDataBiTenantDeclaration';

export type Coverage = {
    requested: number;
    available: number;
    partial: number;
    unavailable: number;
    unknown: number;
    sources: number;
    structures: number;
};
export type ReviewVersion = {
    version: number;
    published_at: string;
    response_status: 'pending' | 'submitted';
    revision: number | null;
    submitted_at: string | null;
};
export type ReviewSelection = {
    questionnaire_id: number;
    version: number;
    published_at: string;
    objectives: { key: string; label: string }[];
    requirements: Requirement[];
    declaration: {
        status: 'submitted';
        revision: number;
        submitted_at: string;
        declarations: Declaration[];
    } | null;
    coverage: Coverage | null;
};
export type AdminReview = {
    implementation_request_id: number;
    company: { id: number; name: string };
    current_version: number | null;
    versions: ReviewVersion[];
    next_before_version: number | null;
    selected: ReviewSelection | null;
    declaration_only: true;
};

const object = (x: unknown): x is Record<string, unknown> =>
    !!x && typeof x === 'object' && !Array.isArray(x);
const integer = (x: unknown, min = 1): x is number =>
    Number.isSafeInteger(x) && Number(x) >= min;
const texts = (x: unknown): x is string[] =>
    Array.isArray(x) && x.every((v) => typeof v === 'string');
const date = (x: unknown): x is string =>
    typeof x === 'string' && !Number.isNaN(new Date(x).getTime());

export function isAdminReview(x: unknown, requestId: number): x is AdminReview {
    if (
        !object(x) ||
        x.implementation_request_id !== requestId ||
        x.declaration_only !== true ||
        !object(x.company) ||
        !integer(x.company.id) ||
        typeof x.company.name !== 'string' ||
        !(x.current_version === null || integer(x.current_version)) ||
        !(x.next_before_version === null || integer(x.next_before_version)) ||
        !Array.isArray(x.versions) ||
        x.versions.length > 20 ||
        !x.versions.every(
            (v) =>
                object(v) &&
                integer(v.version) &&
                date(v.published_at) &&
                (v.response_status === 'pending'
                    ? v.revision === null && v.submitted_at === null
                    : v.response_status === 'submitted' &&
                      integer(v.revision) &&
                      date(v.submitted_at)),
        )
    )
        return false;
    const s = x.selected;
    if (s === null)
        return x.current_version === null && x.versions.length === 0;
    if (
        !object(s) ||
        !integer(s.questionnaire_id) ||
        !integer(s.version) ||
        !integer(x.current_version) ||
        s.version > x.current_version ||
        !date(s.published_at) ||
        !Array.isArray(s.objectives) ||
        !s.objectives.every(
            (o) =>
                object(o) &&
                typeof o.key === 'string' &&
                typeof o.label === 'string',
        ) ||
        !Array.isArray(s.requirements) ||
        !s.requirements.length ||
        s.requirements.length > 100 ||
        new Set(s.requirements.map((r) => (object(r) ? r.key : null))).size !==
            s.requirements.length ||
        !s.requirements.every(
            (r) =>
                object(r) &&
                typeof r.key === 'string' &&
                typeof r.label === 'string' &&
                typeof r.guidance === 'string' &&
                texts(r.objective_keys),
        )
    )
        return false;
    const d = s.declaration;
    if (d === null) return s.coverage === null;
    return (
        object(d) &&
        d.status === 'submitted' &&
        integer(d.revision) &&
        date(d.submitted_at) &&
        Array.isArray(d.declarations) &&
        d.declarations.length === s.requirements.length &&
        s.requirements.every(
            (r) =>
                object(r) &&
                Array.isArray(d.declarations) &&
                d.declarations.filter(
                    (item) => object(item) && item.requirement_key === r.key,
                ).length === 1,
        ) &&
        d.declarations.every(
            (r) =>
                object(r) &&
                typeof r.requirement_key === 'string' &&
                ['available', 'partial', 'unavailable', 'unknown'].includes(
                    String(r.availability),
                ) &&
                typeof r.notes === 'string' &&
                Array.isArray(r.sources) &&
                r.sources.every(
                    (source) =>
                        object(source) &&
                        [
                            'software',
                            'database',
                            'spreadsheet',
                            'flat_file',
                            'api',
                            'other',
                        ].includes(String(source.type)) &&
                        typeof source.name === 'string' &&
                        typeof source.notes === 'string' &&
                        texts(source.structures),
                ),
        ) &&
        object(s.coverage) &&
        [
            'requested',
            'available',
            'partial',
            'unavailable',
            'unknown',
            'sources',
            'structures',
        ].every((key) => object(s.coverage) && integer(s.coverage[key], 0))
    );
}

export function useAdminDataBiDeclarationReview(
    requestId: () => number,
    active: () => boolean = () => true,
) {
    const data = ref<AdminReview | null>(null);
    const busy = ref(false);
    const error = ref('');
    const selectedKey = ref('');
    const display = ref<'focused' | 'list'>('focused');
    const cursor = ref<number | null>(null);
    let controller: AbortController | null = null;
    let generation = 0;
    const selected = computed(() => data.value?.selected ?? null);
    const index = computed(() =>
        Math.max(
            0,
            selected.value?.requirements.findIndex(
                (r) => r.key === selectedKey.value,
            ) ?? 0,
        ),
    );
    const rows = computed(() =>
        (selected.value?.requirements ?? []).map((requirement) => ({
            ...requirement,
            response:
                selected.value?.declaration?.declarations.find(
                    (d) => d.requirement_key === requirement.key,
                ) ?? null,
        })),
    );
    const visibleRows = computed(() =>
        display.value === 'list'
            ? rows.value
            : rows.value.filter((r) => r.key === selectedKey.value),
    );
    const historical = computed(
        () =>
            !!selected.value &&
            selected.value.version !== data.value?.current_version,
    );

    async function load(
        version?: number,
        before: number | null = cursor.value,
    ): Promise<void> {
        if (busy.value || !integer(requestId())) return;
        const id = requestId();
        const turn = ++generation;
        const pending = new AbortController();
        controller = pending;
        busy.value = true;
        error.value = '';
        const timeout = setTimeout(() => pending.abort(), 30000);
        const query = new URLSearchParams();
        if (version !== undefined) query.set('version', String(version));
        if (before !== null) query.set('before_version', String(before));
        try {
            const response = await fetch(
                `/admin/transformation-360/implementation-requests/${id}/information-requirements/tenant-declarations${query.size ? '?' + query : ''}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                    signal: pending.signal,
                },
            );
            if (turn !== generation || pending.signal.aborted) return;
            if (!response.ok) {
                throw new Error(
                    (
                        {
                            401: 'Inicia sesión de nuevo para consultar las declaraciones.',
                            403: 'Esta consulta requiere una cuenta verificada de Admin LAUDA.',
                            404: 'La solicitud o la versión publicada no están disponibles.',
                            409: 'No se muestran respuestas: la declaración o su publicación no superan las comprobaciones de integridad.',
                            422: 'La versión o la consulta no son válidas.',
                            503: 'La consulta de declaraciones no está disponible en este momento.',
                        } as Record<number, string>
                    )[response.status] ??
                        'No fue posible consultar las declaraciones.',
                );
            }
            let body: unknown;
            try {
                body = await response.json();
            } catch {
                throw new Error(
                    'La consulta recibió una respuesta no válida. Vuelve a intentarlo.',
                );
            }
            if (turn !== generation || pending.signal.aborted) return;
            if (
                !object(body) ||
                !isAdminReview(body.data, id) ||
                (version !== undefined &&
                    body.data.selected?.version !== version)
            )
                throw new Error(
                    'La respuesta recibida no corresponde a esta consulta administrativa.',
                );
            data.value = body.data;
            cursor.value = before;
            if (!rows.value.some((r) => r.key === selectedKey.value))
                selectedKey.value = rows.value[0]?.key ?? '';
        } catch (e) {
            if (turn === generation) {
                // Un fallo de integridad/autorización nunca debe dejar otra respuesta visible como vigente.
                data.value = null;
                error.value = pending.signal.aborted
                    ? 'La consulta tardó demasiado. Vuelve a intentarlo.'
                    : e instanceof Error && !(e instanceof TypeError)
                      ? e.message
                      : 'No se pudo conectar. Vuelve a intentar la consulta.';
            }
        } finally {
            clearTimeout(timeout);
            if (turn === generation) busy.value = false;
        }
    }
    function focusRequirement(key: string): void {
        if (rows.value.some((r) => r.key === key)) {
            selectedKey.value = key;
            display.value = 'focused';
        }
    }
    function move(step: number): void {
        const row = rows.value[index.value + step];
        if (row) selectedKey.value = row.key;
    }
    function refresh(): Promise<void> {
        return load(selected.value?.version);
    }
    function olderVersions(): Promise<void> {
        return data.value?.next_before_version
            ? load(selected.value?.version, data.value.next_before_version)
            : Promise.resolve();
    }
    watch(
        [requestId, active],
        ([id, enabled], previous) => {
            if (id !== previous?.[0]) {
                controller?.abort();
                generation++;
                busy.value = false;
                data.value = null;
                cursor.value = null;
                selectedKey.value = '';
            }
            if (enabled && !data.value && !busy.value) void load();
        },
        { immediate: true },
    );
    onScopeDispose(() => {
        generation++;
        controller?.abort();
    });
    return {
        data,
        selected,
        rows,
        visibleRows,
        index,
        selectedKey,
        display,
        historical,
        busy,
        error,
        load,
        refresh,
        olderVersions,
        focusRequirement,
        move,
        newestVersions: () => load(undefined, null),
    };
}
