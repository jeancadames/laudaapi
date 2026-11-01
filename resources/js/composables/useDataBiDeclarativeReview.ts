import { computed, onScopeDispose, reactive, ref, watch } from 'vue';
import { csrfHeaders } from './useDataBiTenantDeclaration';

export type ReviewAudience = 'admin' | 'tenant';
export type ReviewAction =
    | 'request'
    | 'answer'
    | 'resolve'
    | 'cancel'
    | 'close';
export type Clarification = {
    id: number;
    questionnaire_version: number;
    requirement_key: string;
    status: 'requested' | 'answered' | 'resolved' | 'cancelled';
    revision: number;
    question: string;
    events: {
        action: 'request' | 'answer' | 'resolve' | 'cancel';
        message: string;
        actor_type: 'lauda_admin' | 'tenant';
        occurred_at: string;
    }[];
    allowed_actions: { answer: boolean; resolve: boolean; cancel: boolean };
};
export type DeclarativeReview = {
    questionnaire_version: number;
    is_current: boolean;
    review_status: 'open' | 'closed';
    clarifications: Clarification[];
    closure: null | {
        status: 'closed';
        questionnaire_version: number;
        closed_at: string;
        conclusion: string;
        limitations: string;
        declared_gaps: {
            requirement_key: string;
            availability: string;
            notes: string;
        }[];
    };
    allowed_actions: { request_clarification: boolean; close_review: boolean };
};

const object = (x: unknown): x is Record<string, unknown> =>
    !!x && typeof x === 'object' && !Array.isArray(x);
const keys = (x: Record<string, unknown>, fields: string[]) =>
    Object.keys(x).length === fields.length && fields.every((k) => k in x);
const integer = (x: unknown): x is number =>
    Number.isSafeInteger(x) && Number(x) > 0;
const text = (x: unknown): x is string => typeof x === 'string';
const date = (x: unknown): x is string =>
    text(x) && !Number.isNaN(new Date(x).getTime());
const flags = (x: unknown, fields: string[]) =>
    object(x) &&
    keys(x, fields) &&
    fields.every((k) => typeof x[k] === 'boolean');

/** Public projection B3 only. Never retain arbitrary snapshots from a response. */
export function isDeclarativeReview(
    x: unknown,
    version: number,
    requirements: string[],
): x is DeclarativeReview {
    if (
        !object(x) ||
        !keys(x, [
            'questionnaire_version',
            'is_current',
            'review_status',
            'clarifications',
            'closure',
            'allowed_actions',
        ]) ||
        x.questionnaire_version !== version ||
        typeof x.is_current !== 'boolean' ||
        !['open', 'closed'].includes(String(x.review_status)) ||
        !flags(x.allowed_actions, ['request_clarification', 'close_review']) ||
        !Array.isArray(x.clarifications) ||
        x.clarifications.length > 1000 ||
        new Set(x.clarifications.map((c) => (object(c) ? c.id : null))).size !==
            x.clarifications.length ||
        !x.clarifications.every(
            (c) =>
                object(c) &&
                keys(c, [
                    'id',
                    'questionnaire_version',
                    'requirement_key',
                    'status',
                    'revision',
                    'question',
                    'events',
                    'allowed_actions',
                ]) &&
                integer(c.id) &&
                c.questionnaire_version === version &&
                text(c.requirement_key) &&
                requirements.includes(c.requirement_key) &&
                ['requested', 'answered', 'resolved', 'cancelled'].includes(
                    String(c.status),
                ) &&
                integer(c.revision) &&
                text(c.question) &&
                flags(c.allowed_actions, ['answer', 'resolve', 'cancel']) &&
                Array.isArray(c.events) &&
                c.events.length > 0 &&
                c.events.every(
                    (e) =>
                        object(e) &&
                        keys(e, [
                            'action',
                            'message',
                            'actor_type',
                            'occurred_at',
                        ]) &&
                        ['request', 'answer', 'resolve', 'cancel'].includes(
                            String(e.action),
                        ) &&
                        text(e.message) &&
                        ['lauda_admin', 'tenant'].includes(
                            String(e.actor_type),
                        ) &&
                        date(e.occurred_at),
                ),
        )
    )
        return false;
    const c = x.closure;
    return c === null
        ? x.review_status === 'open'
        : x.review_status === 'closed' &&
              object(c) &&
              keys(c, [
                  'status',
                  'questionnaire_version',
                  'closed_at',
                  'conclusion',
                  'limitations',
                  'declared_gaps',
              ]) &&
              c.status === 'closed' &&
              c.questionnaire_version === version &&
              date(c.closed_at) &&
              text(c.conclusion) &&
              text(c.limitations) &&
              Array.isArray(c.declared_gaps) &&
              c.declared_gaps.every(
                  (g) =>
                      object(g) &&
                      keys(g, ['requirement_key', 'availability', 'notes']) &&
                      text(g.requirement_key) &&
                      requirements.includes(g.requirement_key) &&
                      ['partial', 'unavailable', 'unknown'].includes(
                          String(g.availability),
                      ) &&
                      text(g.notes),
              );
}

type Attempt = {
    action: ReviewAction;
    target: string;
    url: string;
    body: string;
    fields: Record<string, string>;
};
type Session = {
    data: DeclarativeReview | null;
    loaded: boolean;
    drafts: Record<string, string>;
    error: string;
    code: string;
    problem: string;
    notice: string;
    blocked: boolean;
    conflict: boolean;
    inspected: boolean;
    uncertain: boolean;
    attempt: Attempt | null;
};
const messages: Record<string, string> = {
    declaration_not_submitted:
        'Todavía no hay una declaración enviada para esta versión. Los borradores permanecen privados.',
    questionnaire_unavailable: 'Todavía no hay un cuestionario publicado.',
    questionnaire_replaced:
        'El cuestionario fue sustituido. Consulta esta versión como historial y vuelve a la publicación vigente para continuar.',
    revision_conflict:
        'La revisión cambió. Tu texto sigue aquí. Consulta el estado vigente antes de decidir si vuelves a enviarlo.',
    duplicate_clarification:
        'Esta pregunta ya existe. Consulta las aclaraciones vigentes antes de continuar.',
    clarification_state_conflict:
        'El estado de esta aclaración cambió. Consulta su historial antes de continuar.',
    clarifications_open:
        'Hay aclaraciones pendientes. LAUDA debe resolverlas o cancelarlas antes del cierre.',
    review_closed:
        'La revisión declarativa ya está cerrada. Su conclusión se conserva en modo lectura.',
    clarification_limit_reached:
        'Se alcanzó el límite de aclaraciones de esta declaración.',
    declarative_integrity_conflict:
        'No se pudo verificar la revisión. Solicita ayuda a LAUDA; tus textos locales se conservan.',
    validation_failed:
        'Revisa los textos: de 1 a 800 caracteres, solo información declarativa, sin credenciales, archivos ni registros reales.',
    schema_unavailable:
        'La revisión declarativa no está disponible en este momento. Conservamos tus textos; consulta de nuevo más tarde.',
    service_unavailable:
        'El servicio no está disponible. Conservamos tus textos; consulta de nuevo más tarde.',
};
const statusMessages: Record<number, string> = {
    401: 'La sesión terminó. Inicia sesión de nuevo antes de continuar. Tus textos siguen en esta pantalla.',
    403: 'Esta operación requiere una cuenta verificada con los permisos correspondientes.',
    404: 'La solicitud o aclaración no está disponible para tu cuenta.',
    419: 'La sesión o la protección de seguridad caducó. Renueva la sesión antes de continuar; conserva tus textos.',
    422: messages.validation_failed,
    503: messages.service_unavailable,
};
export const clarificationLabels = {
    requested: 'Solicitada · esperando respuesta del Tenant',
    answered: 'Respondida · pendiente de revisión por LAUDA',
    resolved: 'Resuelta · aclaración atendida',
    cancelled: 'Cancelada · solicitud cancelada con motivo',
};
export const reviewTextValid = (value: string) => {
    const length = Array.from(value.replace(/\r\n?/g, '\n').trim()).length;
    return length >= 1 && length <= 800;
};

export function useDataBiDeclarativeReview(
    context: () => {
        requestId: number | null;
        version: number | null;
        audience: ReviewAudience;
        enabled: boolean;
        requirementKeys: string[];
    },
) {
    const sessions = reactive<Record<string, Session>>({});
    const busy = ref(false);
    let controller: AbortController | null = null;
    let disposed = false;
    const session = computed(() => {
        const c = context();
        const key = `${c.audience}:${c.requestId}:${c.version}`;
        return (sessions[key] ??= {
            data: null,
            loaded: false,
            drafts: {},
            error: '',
            code: '',
            problem: '',
            notice: '',
            blocked: false,
            conflict: false,
            inspected: false,
            uncertain: false,
            attempt: null,
        });
    });
    const data = computed(() => session.value.data);
    const dirty = computed(() =>
        Object.values(sessions).some(
            (s) =>
                s.uncertain ||
                Object.values(s.drafts).some((v) => v.trim().length > 0),
        ),
    );
    const writable = computed(
        () =>
            context().enabled &&
            !busy.value &&
            !!data.value?.is_current &&
            data.value.review_status === 'open' &&
            !session.value.blocked &&
            !session.value.conflict &&
            !session.value.uncertain,
    );
    const base = () => {
        const c = context();
        return c.audience === 'admin'
            ? `/admin/transformation-360/implementation-requests/${c.requestId}/declarative-review`
            : `/app/transformacion-360/datos-bi/requerimientos/${c.requestId}/aclaraciones`;
    };
    function offered(action: ReviewAction, id?: number): boolean {
        if (
            !context().enabled ||
            !data.value?.is_current ||
            data.value.review_status !== 'open'
        )
            return false;
        const admin = context().audience === 'admin';
        if (action === 'request')
            return admin && data.value!.allowed_actions.request_clarification;
        if (action === 'close')
            return admin && data.value!.allowed_actions.close_review;
        if (admin === (action === 'answer')) return false;
        return (
            data.value!.clarifications.find((c) => c.id === id)
                ?.allowed_actions[action] === true
        );
    }
    function allowed(action: ReviewAction, id?: number): boolean {
        return writable.value && offered(action, id);
    }
    const target = (action: ReviewAction, key?: string | number) =>
        `${action}:${key ?? ''}`;
    function draft(key: string): string {
        return session.value.drafts[key] ?? '';
    }
    function setDraft(key: string, value: string): void {
        session.value.drafts[key] = value;
    }
    function valid(action: ReviewAction, key?: string | number): boolean {
        return action === 'close'
            ? reviewTextValid(draft('conclusion')) &&
                  reviewTextValid(draft('limitations'))
            : reviewTextValid(draft(target(action, key)));
    }
    async function exchange(url: string, body?: string): Promise<Response> {
        controller = new AbortController();
        const timeout = setTimeout(() => controller?.abort(), 30000);
        try {
            return await fetch(url, {
                method: body === undefined ? 'GET' : 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                // Session rotation can leave Inertia's initial meta stale. Reuse T4E's
                // current XSRF cookie first, with meta only as the established fallback.
                headers:
                    body === undefined
                        ? { Accept: 'application/json' }
                        : csrfHeaders(),
                ...(body === undefined ? {} : { body }),
            });
        } finally {
            clearTimeout(timeout);
        }
    }
    async function accept(
        response: Response,
        s: Session,
        version: number,
        requirements: string[],
    ): Promise<boolean> {
        let body: unknown;
        try {
            body = await response.json();
        } catch {
            body = null;
        }
        if (!response.ok) {
            const code =
                object(body) && object(body.error) && text(body.error.code)
                    ? body.error.code
                    : '';
            s.code = code;
            s.error =
                statusMessages[response.status] ??
                messages[code] ??
                'No se pudo completar la operación. Consulta el estado vigente antes de continuar.';
            // Detailed stable conflict codes take precedence over the generic HTTP status.
            if (response.status === 409 && messages[code])
                s.error = messages[code];
            s.conflict = response.status === 409;
            s.blocked = response.status !== 422;
            return false;
        }
        if (
            !object(body) ||
            !isDeclarativeReview(body.data, version, requirements)
        )
            throw new Error('invalid_projection');
        s.data = body.data;
        s.blocked = false;
        return true;
    }
    async function load(): Promise<void> {
        const c = context();
        if (
            busy.value ||
            !c.enabled ||
            !integer(c.requestId) ||
            !integer(c.version)
        )
            return;
        const s = session.value;
        const url = `${base()}?version=${c.version}`;
        const requirements = [...c.requirementKeys];
        busy.value = true;
        s.loaded = true;
        s.error = '';
        s.notice = '';
        // No actions or stale timeline while a fresh read is unverified.
        s.blocked = true;
        try {
            const response = await exchange(url);
            if (disposed) return;
            if (await accept(response, s, c.version, requirements)) {
                s.inspected = s.conflict || s.uncertain;
                s.code = '';
                s.notice = s.inspected
                    ? 'Estado vigente consultado. Tus textos locales se conservaron; revisa el historial antes de continuar.'
                    : '';
            } else s.data = null;
        } catch {
            s.data = null;
            s.error =
                'No fue posible consultar el estado. Comprueba la conexión y vuelve a intentarlo. Tus textos se conservan.';
        } finally {
            busy.value = false;
        }
    }
    function useInspected(): void {
        const s = session.value;
        if (busy.value || !s.inspected || s.blocked || !s.data) return;
        s.conflict = false;
        s.uncertain = false;
        s.inspected = false;
        s.attempt = null;
        s.error = '';
        s.code = '';
        s.notice =
            'Se usará la revisión consultada. Tus textos locales siguen disponibles.';
    }
    async function execute(attempt: Attempt): Promise<boolean> {
        const c = context();
        if (busy.value || !integer(c.version)) return false;
        const s = session.value;
        const version = c.version;
        const requirements = [...c.requirementKeys];
        busy.value = true;
        s.attempt = attempt;
        s.error = '';
        s.notice = '';
        s.problem = attempt.target;
        s.inspected = false;
        try {
            const response = await exchange(attempt.url, attempt.body);
            if (disposed) return false;
            s.uncertain = false;
            if (!(await accept(response, s, version, requirements)))
                return false;
            for (const [key, value] of Object.entries(attempt.fields))
                if (s.drafts[key] === value) s.drafts[key] = '';
            s.attempt = null;
            s.conflict = false;
            s.code = '';
            s.problem = '';
            s.notice = {
                request: 'Aclaración solicitada.',
                answer: 'Respuesta enviada a LAUDA.',
                resolve: 'Aclaración resuelta.',
                cancel: 'Aclaración cancelada.',
                close: 'Revisión declarativa gratuita completada.',
            }[attempt.action];
            return true;
        } catch {
            s.uncertain = true;
            s.error =
                'No se pudo confirmar el resultado. Tu texto se conserva. Consulta el estado vigente o reintenta exactamente el mismo envío; no se enviará automáticamente.';
            return false;
        } finally {
            busy.value = false;
        }
    }
    async function send(
        action: ReviewAction,
        key?: string | number,
    ): Promise<boolean> {
        const s = session.value;
        if (!allowed(action, typeof key === 'number' ? key : undefined))
            return false;
        if (
            action === 'request' &&
            (typeof key !== 'string' ||
                !context().requirementKeys.includes(key))
        )
            return false;
        if (!valid(action, key)) {
            s.problem = target(action, key);
            s.error = 'Completa cada texto con entre 1 y 800 caracteres.';
            return false;
        }
        const fields =
            action === 'close'
                ? {
                      conclusion: draft('conclusion'),
                      limitations: draft('limitations'),
                  }
                : { [target(action, key)]: draft(target(action, key)) };
        const payload = {
            version: context().version,
            ...(action === 'close'
                ? fields
                : { message: draft(target(action, key)) }),
            ...(action === 'request' ? { requirement_key: key } : {}),
            expected_revision:
                action === 'request' || action === 'close'
                    ? 0
                    : data.value!.clarifications.find((c) => c.id === key)!
                          .revision,
        };
        const suffix =
            action === 'request'
                ? '/clarifications'
                : action === 'close'
                  ? '/close'
                  : context().audience === 'tenant'
                    ? `/${key}/responder`
                    : `/clarifications/${key}/${action}`;
        return execute({
            action,
            target: target(action, key),
            url: base() + suffix,
            body: JSON.stringify(payload),
            fields,
        });
    }
    async function retry(): Promise<boolean> {
        const s = session.value;
        // A lost acknowledgement may be retried with the identical captured revision/body.
        // A known conflict or historical/closed read always requires inspection instead.
        if (
            !s.uncertain ||
            s.conflict ||
            s.blocked ||
            !s.attempt ||
            !context().enabled ||
            !s.data?.is_current ||
            s.data.review_status === 'closed'
        )
            return false;
        return execute(s.attempt);
    }
    watch(
        () => [
            context().requestId,
            context().version,
            context().enabled,
            busy.value,
        ],
        () => {
            if (context().enabled && !busy.value && !session.value.loaded)
                void load();
        },
        { immediate: true },
    );
    onScopeDispose(() => {
        disposed = true;
        controller?.abort();
    });
    return {
        session,
        data,
        busy,
        dirty,
        writable,
        allowed,
        offered,
        draft,
        setDraft,
        target,
        valid,
        load,
        send,
        retry,
        useInspected,
    };
}
