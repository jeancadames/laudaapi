<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock3,
    Database,
    FileText,
    Layers3,
    UserRound,
    UserRoundCheck,
} from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

type Person = {
    id?: number;
    name: string | null;
    email: string | null;
};

type EventItem = {
    id: number;
    event_type: string;
    event_label: string;
    from_status: string | null;
    from_status_label: string | null;
    to_status: string;
    to_status_label: string;
    actor_type: string;
    actor_type_label: string;
    actor: Person | null;
    notes: string | null;
    occurred_at: string | null;
};

const props = defineProps<{
    implementation_request: {
        id: number;
        status: string;
        status_label: string;
        attempt: number;
        source_type: string;
        tenant_note: string | null;
        internal_notes: string | null;
        requested_at: string | null;
        review_started_at: string | null;
        definition_started_at: string | null;
        tenant_review_requested_at: string | null;
        changes_requested_at: string | null;
        definition_agreed_at: string | null;
        ready_for_commercial_at: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
    };
    company: {
        id: number;
        name: string;
        subscriber_id: number | null;
    };
    assessment: {
        id: number;
        organization_name: string | null;
        status: string | null;
        published_at: string | null;
    };
    plan: {
        id: number;
        version: number | null;
        status: string | null;
        presented_at: string | null;
    };
    phase: {
        id: number | null;
        sequence: number | null;
        name: string | null;
    };
    capability: {
        phase_capability_id: number;
        key: string;
        label: string;
        summary: string | null;
        purpose: string | null;
    };
    requested_by: Person | null;
    assigned_to: Person | null;
    events: EventItem[];
    admin_users: {
        id: number;
        name: string;
        email: string;
    }[];
    definition: {
        id: number;
        version: number;
        status: string;
        capability_key: string;
        created_at: string | null;
        content_prepared: boolean;
        deliverable_count: number;
        dependency_count: number;
    } | null;

    definition_review: {

        implementation_scope: Record<string, any>;

        deliverables: Array<Record<string, any>>;

        dependencies: Array<Record<string, any>>;

        responsibility_model: {

            assignments?: Array<Record<string, any>>;

            party_assignment_status?: string;

        };

        readiness: Record<string, any>;

        reviewed_at: string | null;

        reviewed_by_user_id: number | null;

    } | null;


    ready_for_commercial_context: {
        agreement_event_id: number | null;
        functional_closure_event_id: number | null;
        definition_id: number | null;
        definition_version: number | null;
        definition_status: string | null;
        definition_ready: boolean;
        ready_at: string | null;
        request_status: string;
        ready_for_commercial_at: string | null;
        can_mark_ready_for_commercial: boolean;
    } | null;

    functional_closure_context: {
        agreement_event_id: number;
        definition_id: number;
        definition_version: number;
        definition_status: string;
        definition_ready: boolean;
        ready_at: string | null;
        tenant_agreed_at: string | null;
        can_finalize: boolean;
    } | null;

    definition_revision_context: {
        previous_definition_id: number;
        previous_definition_version: number;
        previous_definition_status: string;
        tenant_change_reason: string | null;
        changes_requested_at: string | null;
        current_definition_version: number | null;
    } | null;

    actions: {
        can_create_definition_revision: boolean;
        definition_revision_endpoint: string | null;
        can_create_definition: boolean;
        definition_create_endpoint: string | null;
        can_generate_definition: boolean;
        definition_generate_endpoint: string | null;
        can_review_definition: boolean;
        definition_review_endpoint: string | null;
        can_submit_definition_for_tenant_review: boolean;
        definition_submit_tenant_review_endpoint: string | null;
        can_mark_ready_for_commercial: boolean;
        ready_for_commercial_endpoint: string | null;
        can_finalize_definition_functionally: boolean;
        definition_functional_finalize_endpoint: string | null;
        can_mutate: boolean;
        assign_endpoint: string;
        transition_endpoint: string;
        allowed_transitions: string[];
    };
}>();

const breadcrumbs = [
    {
        title: 'Transformación 360',
        href: '/admin/transformation-360',
    },
    {
        title: 'Solicitudes de Implementación',
        href: '/admin/transformation-360/implementation-requests',
    },
    {
        title: `Solicitud #${props.implementation_request.id}`,
        href: `/admin/transformation-360/implementation-requests/${props.implementation_request.id}`,
    },
];

const assignedUserId = ref<number | null>(
    props.assigned_to?.id ?? null,
);

const transitionNotes = ref('');
const assigning = ref(false);
const transitioning = ref(false);

const STANDARD_INTAKE_MAX_BYTES = 2 * 1024 * 1024;

type StandardIntakeDomainReport = {
    valid?: boolean;
    row_count?: number;
    errors?: unknown[];
    duplicate_keys?: unknown[];
    relation_errors?: unknown[];
};

type StandardIntakeStructuralDomainReport = {
    warnings?: unknown[];
};

type StandardIntakeValidationResult = {
    valid: boolean;
    schema_version?: number;
    format?: string;
    errors?: unknown[];
    warnings?: unknown[];
    structural?: {
        domains?: Record<string, StandardIntakeStructuralDomainReport>;
    };
    content?: {
        executed?: boolean;
        domains?: Record<string, StandardIntakeDomainReport>;
    };
};

type StandardIntakeHttpResponse = {
    ok: boolean;
    message?: string;
    errors?: Record<string, string[]>;
    file?: {
        name: string;
        extension: string;
        size_bytes: number;
        mime_type?: string;
    };
    validation?: StandardIntakeValidationResult;
};

type StandardIntakeIngestionDomainReport = {
    source_row_count: number;
    staged_row_count: number;
    rejected_row_count: number;
};

type StandardIntakeIngestionResult = {
    reused: boolean;
    batch_id: number;
    status: string;
    schema_version: number;
    format: string;
    original_filename: string;
    domain_count: number;
    source_row_count: number;
    staged_row_count: number;
    rejected_row_count: number;
    domains: Record<string, StandardIntakeIngestionDomainReport>;
};

type StandardIntakeIngestionHttpResponse = {
    ok: boolean;
    message?: string;
    errors?: Record<string, string[]>;
    ingestion?: StandardIntakeIngestionResult;
};

const standardIntakeFile = ref<File | null>(null);
const standardIntakeValidating = ref(false);
const standardIntakeHttpError = ref<string | null>(null);
const standardIntakeReport = ref<StandardIntakeHttpResponse | null>(null);

const standardIntakeIngesting = ref(false);
const standardIntakeIngestionError = ref<string | null>(null);
const standardIntakeIngestionReport =
    ref<StandardIntakeIngestionHttpResponse | null>(null);

const standardIntakeValidationUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/validate`;

const standardIntakeIngestionUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/ingest`;

function standardIntakeIssueText(
    issue: unknown,
): string {
    if (typeof issue === 'string') {
        return issue;
    }

    if (
        issue === null
        || issue === undefined
    ) {
        return '';
    }

    try {
        return JSON.stringify(issue);
    } catch {
        return String(issue);
    }
}

function standardIntakeFileSizeLabel(
    bytes: number,
): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}

function standardIntakeFormatLabel(
    format: string | undefined,
): string {
    if (format === 'xlsx') {
        return 'Excel XLSX';
    }

    if (format === 'csv_zip') {
        return 'Paquete CSV de LAUDA';
    }

    return format || 'Formato no identificado';
}

function standardIntakeDomainLabel(
    domain: string,
): string {
    const labels: Record<string, string> = {
        customers: 'Clientes',
        products: 'Productos',
        inventory: 'Inventario',
        sales: 'Ventas',
        accounts_receivable: 'Cuentas por cobrar',
        suppliers: 'Suplidores',
        accounts_payable: 'Cuentas por pagar',
    };

    return labels[domain] ?? domain;
}

function standardIntakeDomainEntries(): Array<
    [string, StandardIntakeDomainReport]
> {
    return Object.entries(
        standardIntakeReport.value
            ?.validation
            ?.content
            ?.domains
        ?? {},
    );
}

function standardIntakeIngestionDomainEntries(): Array<
    [string, StandardIntakeIngestionDomainReport]
> {
    return Object.entries(
        standardIntakeIngestionReport.value
            ?.ingestion
            ?.domains
        ?? {},
    );
}

function standardIntakeDomainWarnings(
    domain: string,
): unknown[] {
    const warnings =
        standardIntakeReport.value
            ?.validation
            ?.structural
            ?.domains
            ?.[domain]
            ?.warnings;

    return Array.isArray(warnings)
        ? warnings
        : [];
}

function standardIntakeHttpErrors(
    payload:
        | StandardIntakeHttpResponse
        | StandardIntakeIngestionHttpResponse
        | null,
): string[] {
    if (!payload?.errors) {
        return [];
    }

    return Object.values(
        payload.errors,
    ).flatMap(
        (messages) =>
            Array.isArray(messages)
                ? messages
                : [],
    );
}

function standardIntakeCsrfHeaders(): Record<string, string> {
    /*
     * Preferimos la cookie XSRF-TOKEN porque refleja
     * el token actual de la sesión del navegador.
     *
     * El meta csrf-token queda como fallback para
     * contextos donde Laravel no haya emitido la cookie.
     */
    const xsrfCookie =
        document.cookie
            .split('; ')
            .find(
                (item) =>
                    item.startsWith(
                        'XSRF-TOKEN=',
                    ),
            );

    if (xsrfCookie) {
        const encodedToken =
            xsrfCookie
                .slice(
                    'XSRF-TOKEN='.length,
                );

        try {
            return {
                'X-XSRF-TOKEN':
                    decodeURIComponent(
                        encodedToken,
                    ),
            };
        } catch {
            return {
                'X-XSRF-TOKEN':
                    encodedToken,
            };
        }
    }

    const metaToken =
        document
            .querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )
            ?.getAttribute('content');

    if (metaToken) {
        return {
            'X-CSRF-TOKEN': metaToken,
        };
    }

    return {};
}

function selectStandardIntakeFile(
    event: Event,
): void {
    standardIntakeReport.value = null;
    standardIntakeHttpError.value = null;
    standardIntakeIngestionReport.value = null;
    standardIntakeIngestionError.value = null;

    const input =
        event.target as HTMLInputElement;

    const file =
        input.files?.[0]
        ?? null;

    standardIntakeFile.value =
        null;

    if (!file) {
        return;
    }

    const extension =
        file.name
            .split('.')
            .pop()
            ?.toLowerCase()
        ?? '';

    if (
        ![
            'xlsx',
            'zip',
        ].includes(
            extension,
        )
    ) {
        standardIntakeHttpError.value =
            'Selecciona un archivo Excel (.xlsx) '
            + 'o el paquete CSV de LAUDA (.zip).';

        input.value = '';

        return;
    }

    if (
        file.size
        > STANDARD_INTAKE_MAX_BYTES
    ) {
        standardIntakeHttpError.value =
            'El archivo supera el límite actual de 2 MB.';

        input.value = '';

        return;
    }

    standardIntakeFile.value =
        file;
}

async function validateStandardIntakeFile(): Promise<void> {
    const file =
        standardIntakeFile.value;

    if (
        !file
        || standardIntakeValidating.value
        || standardIntakeIngesting.value
    ) {
        return;
    }

    standardIntakeValidating.value =
        true;

    standardIntakeHttpError.value =
        null;

    standardIntakeReport.value =
        null;

    standardIntakeIngestionReport.value =
        null;

    standardIntakeIngestionError.value =
        null;

    const formData =
        new FormData();

    formData.append(
        'file',
        file,
        file.name,
    );

    try {
        const response =
            await fetch(
                standardIntakeValidationUrl,
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                        ...standardIntakeCsrfHeaders(),
                    },
                },
            );

        let payload:
            | StandardIntakeHttpResponse
            | null = null;

        try {
            const parsedPayload =
                await response.json();

            payload =
                parsedPayload as StandardIntakeHttpResponse;
        } catch {
            payload = null;
        }

        if (
            !response.ok
            || !payload?.ok
        ) {
            const details =
                standardIntakeHttpErrors(
                    payload,
                );

            standardIntakeHttpError.value =
                [
                    payload?.message
                    ?? 'No se pudo validar el archivo.',
                    ...details,
                ]
                    .filter(Boolean)
                    .join(' ');

            return;
        }

        standardIntakeReport.value =
            payload;
    } catch {
        standardIntakeHttpError.value =
            'No se pudo completar la validación del archivo.';
    } finally {
        standardIntakeValidating.value =
            false;
    }
}

async function ingestStandardIntakeFile(): Promise<void> {
    const file =
        standardIntakeFile.value;

    const validation =
        standardIntakeReport.value
            ?.validation;

    if (
        !file
        || validation?.valid !== true
        || standardIntakeValidating.value
        || standardIntakeIngesting.value
    ) {
        return;
    }

    standardIntakeIngesting.value =
        true;

    standardIntakeIngestionError.value =
        null;

    standardIntakeIngestionReport.value =
        null;

    const formData =
        new FormData();

    formData.append(
        'file',
        file,
        file.name,
    );

    try {
        const response =
            await fetch(
                standardIntakeIngestionUrl,
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                        ...standardIntakeCsrfHeaders(),
                    },
                },
            );

        let payload:
            | StandardIntakeIngestionHttpResponse
            | null = null;

        try {
            const parsedPayload =
                await response.json();

            payload =
                parsedPayload as StandardIntakeIngestionHttpResponse;
        } catch {
            payload = null;
        }

        if (
            !response.ok
            || !payload?.ok
            || !payload.ingestion
        ) {
            const details =
                standardIntakeHttpErrors(
                    payload,
                );

            standardIntakeIngestionError.value =
                [
                    payload?.message
                    ?? 'No se pudo ingresar el archivo al staging.',
                    ...details,
                ]
                    .filter(Boolean)
                    .join(' ');

            return;
        }

        standardIntakeIngestionReport.value =
            payload;
    } catch {
        standardIntakeIngestionError.value =
            'No se pudo completar el ingreso del archivo al staging.';
    } finally {
        standardIntakeIngesting.value =
            false;
    }
}

function assessmentStatusLabel(
    status: string | null | undefined,
): string {
    if (!status) {
        return '—';
    }

    const labels: Record<string, string> = {
        draft: 'Borrador',
        in_progress: 'En progreso',
        submitted: 'Enviado',
        reviewed: 'Revisado',
        inactive: 'Inactivo',
    };

    return labels[status] ?? status;
}

function definitionStatusLabel(
    status: string | null | undefined,
): string {
    if (!status) {
        return '—';
    }

    const labels: Record<string, string> = {
        draft: 'Borrador',
        prepared_for_review: 'Preparada para revisión',
        under_review: 'En revisión',
        ready: 'Lista',
    };

    return labels[status] ?? status;
}

function phaseDisplayLabel(
    sequence: number | string | null | undefined,
    name: string | null | undefined,
): string {
    const normalizedName = name?.trim() ?? '';

    if (
        sequence === null
        || sequence === undefined
        || sequence === ''
    ) {
        return normalizedName || 'No disponible';
    }

    const prefix = `Fase ${sequence}`;

    if (!normalizedName) {
        return prefix;
    }

    if (
        normalizedName
            .toLocaleLowerCase()
            .startsWith(prefix.toLocaleLowerCase())
    ) {
        return normalizedName;
    }

    return `${prefix} · ${normalizedName}`;
}

function assignResponsible(): void {
    if (
        !props.actions.can_mutate
        || !props.actions.assign_endpoint
        || !assignedUserId.value
        || assigning.value
    ) {
        return;
    }

    router.patch(
        props.actions.assign_endpoint,
        {
            assigned_to_user_id: assignedUserId.value,
        },
        {
            preserveScroll: true,
            onStart: () => {
                assigning.value = true;
            },
            onFinish: () => {
                assigning.value = false;
            },
        },
    );
}

function canTransition(
    status: string,
): boolean {
    return props.actions.allowed_transitions.includes(
        status,
    );
}

function transitionRequest(
    targetStatus: string,
): void {
    if (
        !props.actions.can_mutate
        || !props.actions.transition_endpoint
        || !canTransition(targetStatus)
        || transitioning.value
    ) {
        return;
    }

    router.post(
        props.actions.transition_endpoint,
        {
            target_status: targetStatus,
            notes: transitionNotes.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => {
                transitioning.value = true;
            },
            onSuccess: () => {
                transitionNotes.value = '';
            },
            onFinish: () => {
                transitioning.value = false;
            },
        },
    );
}

function createImplementationDefinitionRevision(): void {
    const endpoint =
        props.actions.definition_revision_endpoint;

    if (
        !props.actions.can_create_definition_revision
        || !endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
        },
    );
}


function createImplementationDefinition(): void {
    const endpoint =
        props.actions.definition_create_endpoint;

    if (
        ! props.actions.can_create_definition
        || ! endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
        },
    );
}


function generateImplementationDefinition(reprepare = false): void {
    const endpoint =
        props.actions.definition_generate_endpoint;

    if (
        (!reprepare && !props.actions.can_generate_definition)
        || !endpoint
    ) {
        return;
    }

    router.post(
        endpoint,
        reprepare
            ? { reprepare: true }
            : {},
        {
            preserveScroll: true,
        },
    );
}


const humanReviewForm = useForm({
    implementation_scope: {} as Record<string, any>,
    deliverables: [] as Array<Record<string, any>>,
    dependencies: [] as Array<Record<string, any>>,
    responsibility_model: {
        assignments: [] as Array<Record<string, any>>,
    },
    readiness: {
        scope_confirmed: false,
        deliverables_confirmed: false,
        dependencies_confirmed: false,
        inputs_validated: false,
        accesses_validated: false,
        validation_evidence: {
            inputs: [] as Array<Record<string, any>>,
            accesses: [] as Array<Record<string, any>>,
        },
        responsibilities_confirmed: false,
    },
});

const functionalScopeJson = ref('');
const functionalDeliverablesJson = ref('');
const functionalDependenciesJson = ref('');
const functionalEditorError = ref<string | null>(null);

function prettyFunctionalJson(value: unknown): string {
    return JSON.stringify(
        value ?? null,
        null,
        2,
    );
}

function parseFunctionalEditors(): boolean {
    functionalEditorError.value = null;

    try {
        const scope =
            JSON.parse(
                functionalScopeJson.value,
            );

        const deliverables =
            JSON.parse(
                functionalDeliverablesJson.value,
            );

        const dependencies =
            JSON.parse(
                functionalDependenciesJson.value,
            );

        if (
            !scope
            || typeof scope !== 'object'
            || Array.isArray(scope)
        ) {
            throw new Error(
                'El alcance debe ser un objeto JSON.',
            );
        }

        if (
            !Array.isArray(deliverables)
            || !deliverables.length
        ) {
            throw new Error(
                'Los entregables deben ser una lista JSON con al menos un elemento.',
            );
        }

        if (!Array.isArray(dependencies)) {
            throw new Error(
                'Las dependencias deben ser una lista JSON.',
            );
        }

        humanReviewForm.implementation_scope =
            scope;

        humanReviewForm.deliverables =
            deliverables;

        humanReviewForm.dependencies =
            dependencies;

        return true;
    } catch (error) {
        functionalEditorError.value =
            error instanceof Error
                ? error.message
                : 'Revisa la estructura JSON funcional.';

        return false;
    }
}


function syncHumanReviewForm(): void {
    const source =
        props.definition_review;

    humanReviewForm.implementation_scope =
        source?.implementation_scope
        ?? {};

    humanReviewForm.deliverables =
        source?.deliverables
        ?? [];

    humanReviewForm.dependencies =
        source?.dependencies
        ?? [];

    functionalScopeJson.value =
        prettyFunctionalJson(
            humanReviewForm.implementation_scope,
        );

    functionalDeliverablesJson.value =
        prettyFunctionalJson(
            humanReviewForm.deliverables,
        );

    functionalDependenciesJson.value =
        prettyFunctionalJson(
            humanReviewForm.dependencies,
        );

    functionalEditorError.value = null;

    const assignments =
        source?.responsibility_model
            ?.assignments ?? [];

    humanReviewForm.responsibility_model.assignments =
        assignments.map(
            (assignment) => ({
                ...assignment,
                responsible_party:
                    assignment.responsible_party
                    ?? '',
            }),
        );

    const validation =
        source?.readiness
            ?.human_validation
        ?? {};

    humanReviewForm.readiness.scope_confirmed =
        validation.scope_confirmed
        ?? false;

    humanReviewForm.readiness.deliverables_confirmed =
        validation.deliverables_confirmed
        ?? false;

    humanReviewForm.readiness.dependencies_confirmed =
        validation.dependencies_confirmed
        ?? false;

    humanReviewForm.readiness.inputs_validated =
        validation.inputs_validated
        ?? false;

    humanReviewForm.readiness.accesses_validated =
        validation.accesses_validated
        ?? false;

    const validationEvidence =
        source?.readiness
            ?.validation_evidence
        ?? {};

    humanReviewForm.readiness.validation_evidence.inputs =
        Array.isArray(validationEvidence.inputs)
            ? validationEvidence.inputs.map(
                (item) => ({
                    ...item,
                    source_role:
                        typeof item.source_role === 'string'
                            ? item.source_role
                            : '',
                    delivery_format:
                        typeof item.delivery_format === 'string'
                            ? item.delivery_format
                            : '',
                    extraction_assistance_required:
                        item.extraction_assistance_required === true
                            ? true
                            : item.extraction_assistance_required === false
                              ? false
                              : null,
                    data_domains:
                        Array.isArray(item.data_domains)
                            ? [...item.data_domains]
                            : [],
                }),
            )
            : [];

    humanReviewForm.readiness.validation_evidence.accesses =
        Array.isArray(validationEvidence.accesses)
            ? validationEvidence.accesses.map(
                (item) => ({
                    ...item,
                    authorized:
                        item.authorized === true,
                    verified:
                        item.verified === true,
                }),
            )
            : [];

    humanReviewForm.readiness.responsibilities_confirmed =
        validation.responsibilities_confirmed
        ?? false;
}

watch(
    () => props.definition_review,
    () => {
        syncHumanReviewForm();
    },
    {
        deep: true,
        immediate: true,
    },
);

function addInputValidationEvidence(): void {
    const inputs =
        humanReviewForm.readiness.validation_evidence.inputs;

    const newIndex =
        inputs.length;

    humanReviewForm.readiness.validation_evidence.inputs = [
        ...inputs,
        {
            source_name: '',
            source_role: '',
            delivery_format: '',
            extraction_assistance_required: null,
            data_domains: [],
            owner: '',
            historical_coverage: '',
            granularity: '',
            status: 'pending',
            notes: '',
        },
    ];

    nextTick(() => {
        document
            .getElementById(
                `input-evidence-${newIndex}`,
            )
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
    });
}

function removeInputValidationEvidence(
    index: number,
): void {
    humanReviewForm.readiness.validation_evidence.inputs.splice(
        index,
        1,
    );
}

function setInputValidationEvidenceDomains(
    index: number,
    event: Event,
): void {
    const target =
        event.target as HTMLTextAreaElement | null;

    if (!target) {
        return;
    }

    humanReviewForm.readiness.validation_evidence.inputs[
        index
    ].data_domains =
        target.value
            .split(',')
            .map((value) => value.trim())
            .filter((value) => value.length > 0);
}

function addAccessValidationEvidence(): void {
    humanReviewForm.readiness.validation_evidence.accesses.push({
        source_name: '',
        access_method: '',
        authorized: false,
        verified: false,
        status: 'pending',
        notes: '',
    });
}

function removeAccessValidationEvidence(
    index: number,
): void {
    humanReviewForm.readiness.validation_evidence.accesses.splice(
        index,
        1,
    );
}

function saveImplementationDefinitionHumanReview(): void {
    const endpoint =
        props.actions.definition_review_endpoint;

    if (
        !props.actions.can_review_definition
        || !endpoint
    ) {
        return;
    }

    if (!parseFunctionalEditors()) {
        return;
    }

    humanReviewForm.patch(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}


const functionalClosureForm = useForm({});

const readyForCommercialForm = useForm({});

const tenantReviewSubmissionForm = useForm({
    notes: '',
});

function submitDefinitionForTenantReview(): void {
    const endpoint =
        props.actions
            .definition_submit_tenant_review_endpoint;

    if (
        !props.actions
            .can_submit_definition_for_tenant_review
        || !endpoint
    ) {
        return;
    }

    tenantReviewSubmissionForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

function finalizeFunctionalDefinition(): void {
    const endpoint =
        props.actions
            .definition_functional_finalize_endpoint;

    if (
        !props.actions
            .can_finalize_definition_functionally
        || !endpoint
        || functionalClosureForm.processing
    ) {
        return;
    }

    functionalClosureForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

function markRequestReadyForCommercial(): void {
    const endpoint =
        props.actions
            .ready_for_commercial_endpoint;

    if (
        !props.actions
            .can_mark_ready_for_commercial
        || !endpoint
        || readyForCommercialForm.processing
    ) {
        return;
    }

    readyForCommercialForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}


type StandardIntakeProcessingResult = {
    run_id: number;
    batch_id?: number;
    status: string;
    source_row_count?: number;
    profiled_row_count?: number;
    normalized_row_count?: number;
    issue_count?: number;
    blocking_issue_count?: number;
    warning_issue_count?: number;
    domain_profile_count?: number;
    field_profile_count?: number;
    normalization_change_count?: number;
    reused?: boolean;
};

type StandardIntakeProcessingHttpResponse = {
    ok: boolean;
    message?: string;
    processing?: StandardIntakeProcessingResult;
    errors?: Record<string, string[]>;
};

const standardIntakeProfiling = ref(false);
const standardIntakeNormalizing = ref(false);

const standardIntakeProcessingError =
    ref<string | null>(null);

const standardIntakeProfileReport =
    ref<StandardIntakeProcessingHttpResponse | null>(null);

const standardIntakeNormalizationReport =
    ref<StandardIntakeProcessingHttpResponse | null>(null);

const standardIntakeProcessingBatchId =
    ref<number | null>(null);

const standardIntakeProfilingUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/profile`;

const standardIntakeNormalizationUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/normalize`;

function currentStandardIntakeBatchId(): number | null {
    const value =
        standardIntakeIngestionReport.value
            ?.ingestion
            ?.batch_id;

    return typeof value === 'number'
        && Number.isInteger(value)
        && value > 0
        ? value
        : null;
}

function standardIntakeProfileIsForCurrentBatch(): boolean {
    const batchId =
        currentStandardIntakeBatchId();

    return (
        batchId !== null
        && standardIntakeProcessingBatchId.value === batchId
        && standardIntakeProfileReport.value?.processing !== undefined
    );
}

function canNormalizeStandardIntake(): boolean {
    if (
        standardIntakeProfiling.value
        || standardIntakeNormalizing.value
        || !standardIntakeProfileIsForCurrentBatch()
    ) {
        return false;
    }

    const processing =
        standardIntakeProfileReport.value
            ?.processing;

    if (!processing) {
        return false;
    }

    return (
        processing.run_id > 0
        && (processing.blocking_issue_count ?? 0) === 0
        && standardIntakeNormalizationReport.value
            ?.processing
            ?.status !== 'completed'
    );
}

async function parseStandardIntakeProcessingResponse(
    response: Response,
): Promise<StandardIntakeProcessingHttpResponse> {
    try {
        return (
            await response.json()
        ) as StandardIntakeProcessingHttpResponse;
    } catch {
        return {
            ok: false,
            message:
                'El servidor devolvió una respuesta no válida.',
        };
    }
}

async function profileStandardIntakeBatch(): Promise<void> {
    const batchId =
        currentStandardIntakeBatchId();

    if (
        batchId === null
        || standardIntakeProfiling.value
        || standardIntakeNormalizing.value
    ) {
        return;
    }

    standardIntakeProfiling.value =
        true;

    standardIntakeProcessingError.value =
        null;

    standardIntakeProfileReport.value =
        null;

    standardIntakeNormalizationReport.value =
        null;

    standardIntakeProcessingBatchId.value =
        batchId;

    try {
        const response =
            await fetch(
                standardIntakeProfilingUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...standardIntakeCsrfHeaders(),
                    },
                    body: JSON.stringify({
                        batch_id: batchId,
                    }),
                },
            );

        const payload =
            await parseStandardIntakeProcessingResponse(
                response,
            );

        if (
            !response.ok
            || !payload.ok
            || !payload.processing
        ) {
            standardIntakeProcessingError.value =
                payload.message
                ?? 'No se pudo completar el análisis de calidad.';

            return;
        }

        standardIntakeProfileReport.value =
            payload;
    } catch {
        standardIntakeProcessingError.value =
            'No se pudo completar el análisis de calidad.';
    } finally {
        standardIntakeProfiling.value =
            false;
    }
}

async function normalizeStandardIntakeBatch(): Promise<void> {
    if (!canNormalizeStandardIntake()) {
        return;
    }

    const processing =
        standardIntakeProfileReport.value
            ?.processing;

    if (!processing) {
        return;
    }

    standardIntakeNormalizing.value =
        true;

    standardIntakeProcessingError.value =
        null;

    standardIntakeNormalizationReport.value =
        null;

    try {
        const response =
            await fetch(
                standardIntakeNormalizationUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...standardIntakeCsrfHeaders(),
                    },
                    body: JSON.stringify({
                        processing_run_id:
                            processing.run_id,
                    }),
                },
            );

        const payload =
            await parseStandardIntakeProcessingResponse(
                response,
            );

        if (
            !response.ok
            || !payload.ok
            || !payload.processing
        ) {
            standardIntakeProcessingError.value =
                payload.message
                ?? 'No se pudo completar la normalización.';

            return;
        }

        standardIntakeNormalizationReport.value =
            payload;
    } catch {
        standardIntakeProcessingError.value =
            'No se pudo completar la normalización.';
    } finally {
        standardIntakeNormalizing.value =
            false;
    }
}


</script>

<template>
    <Head
        :title="`Solicitud de Implementación #${implementation_request.id}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <Link
                href="/admin/transformation-360/implementation-requests"
                class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-950 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" />
                Volver a Solicitudes de Implementación
            </Link>

            <section
                class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-[10px] font-black tracking-[0.18em] text-slate-400 uppercase"
                        >
                            Solicitud #{{ implementation_request.id }}
                            · intento {{ implementation_request.attempt }}
                        </p>

                        <h1
                            class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            {{ company.name }}
                        </h1>

                        <div class="mt-3 flex items-center gap-2">
                            <Database class="h-5 w-5 text-blue-600" />

                            <p
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ capability.label }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 dark:border-blue-900 dark:bg-blue-950/30"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-blue-500 uppercase"
                        >
                            Estado actual
                        </p>

                        <p
                            class="mt-1 text-sm font-black text-blue-900 dark:text-blue-200"
                        >
                            {{ implementation_request.status_label }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1fr_0.72fr]">
                <div class="space-y-6">
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <h2
                            class="text-lg font-black text-slate-950 dark:text-white"
                        >
                            Contexto de la solicitud
                        </h2>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Building2 class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Empresa
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    {{ company.name }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <FileText class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Diagnóstico
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    #{{ assessment.id }}
                                    · {{ assessmentStatusLabel(assessment.status) }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Layers3 class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Plan
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    #{{ plan.id }}
                                    <template v-if="plan.version">
                                        · V{{ plan.version }}
                                    </template>
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <Database class="h-5 w-5 text-slate-500" />
                                <p class="mt-3 text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Fase
                                </p>
                                <p class="mt-1 text-sm font-bold">
                                    {{ phaseDisplayLabel(phase.sequence, phase.name) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="implementation_request.tenant_note"
                            class="mt-5 rounded-2xl border border-slate-200 p-5 dark:border-slate-800"
                        >
                            <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Nota de la empresa
                            </p>

                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">
                                {{ implementation_request.tenant_note }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <h2
                            class="text-lg font-black text-slate-950 dark:text-white"
                        >
                            Historial
                        </h2>

                        <div
                            v-if="events.length"
                            class="mt-6 space-y-5"
                        >
                            <div
                                v-for="event in events"
                                :key="event.id"
                                class="flex gap-4"
                            >
                                <div
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                </div>

                                <div class="min-w-0 flex-1 border-b border-slate-100 pb-5 last:border-0 dark:border-slate-800">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm font-black">
                                            {{ event.event_label }}
                                        </p>

                                        <p
                                            v-if="event.occurred_at"
                                            class="text-xs text-slate-400"
                                        >
                                            {{ event.occurred_at }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ event.actor_type_label }}

                                        <template v-if="event.actor?.name">
                                            · {{ event.actor.name }}
                                        </template>
                                    </p>

                                    <p
                                        v-if="event.notes"
                                        class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ event.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                            Solicitud iniciada por
                        </p>

                        <div class="mt-4 flex gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900"
                            >
                                <UserRound class="h-5 w-5" />
                            </div>

                            <div>
                                <p class="text-sm font-black">
                                    {{ requested_by?.name ?? 'Usuario de la empresa' }}
                                </p>

                                <p
                                    v-if="requested_by?.email"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ requested_by.email }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="implementation_request.requested_at"
                            class="mt-5 flex items-start gap-3 border-t border-slate-100 pt-5 dark:border-slate-800"
                        >
                            <Clock3 class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />

                            <div>
                                <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                    Recibida
                                </p>

                                <p class="mt-1 text-xs font-semibold">
                                    {{ implementation_request.requested_at }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-center gap-2">
                            <UserRoundCheck class="h-5 w-5 text-blue-600" />

                            <h2 class="font-black text-slate-950 dark:text-white">
                                Responsable LAUDA
                            </h2>
                        </div>

                        <select
                            v-model="assignedUserId"
                            class="mt-4 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <option :value="null">
                                Seleccionar responsable
                            </option>

                            <option
                                v-for="admin in admin_users"
                                :key="admin.id"
                                :value="admin.id"
                            >
                                {{ admin.name }} · {{ admin.email }}
                            </option>
                        </select>

                        <button
                            type="button"
                            class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-black transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-800 dark:hover:bg-slate-900"
                            :disabled="!assignedUserId || assigning"
                            @click="assignResponsible"
                        >
                            {{
                                assigning
                                    ? 'Guardando...'
                                    : 'Asignar responsable'
                            }}
                        </button>
                    </section>

                    <section
                        v-if="
                            actions.can_mutate
                            && actions.allowed_transitions.length
                        "
                        class="rounded-[2rem] border border-blue-200 bg-blue-50/60 p-6 dark:border-blue-900 dark:bg-blue-950/20"
                    >
                        <p class="text-[10px] font-black tracking-widest text-blue-600 uppercase dark:text-blue-400">
                            Gestión de solicitud
                        </p>

                        <textarea
                            v-model="transitionNotes"
                            rows="3"
                            placeholder="Nota interna opcional sobre este cambio de estado"
                            class="mt-4 w-full rounded-xl border border-blue-200 bg-white p-3 text-sm dark:border-blue-900 dark:bg-slate-950"
                        />

                        <button
                            v-if="canTransition('under_lauda_review')"
                            type="button"
                            class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                            :disabled="transitioning"
                            @click="
                                transitionRequest(
                                    'under_lauda_review',
                                )
                            "
                        >
                            Recibir e iniciar revisión
                        </button>

                        <button
                            v-if="canTransition('definition_preparation')"
                            type="button"
                            class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                            :disabled="transitioning"
                            @click="
                                transitionRequest(
                                    'definition_preparation',
                                )
                            "
                        >
                            Iniciar preparación de definición
                        </button>

                        <div class="mt-4 rounded-xl border border-blue-200 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-900 dark:bg-slate-950/40 dark:text-slate-400">
                            Los cambios de estado de esta etapa corresponden a revisión
                            funcional. Por sí solos no crean una Definición ni
                            inician implementación, contratación, facturación o
                            suscripciones.
                        </div>
                    </section>

                    <section
                        v-else
                        class="rounded-[2rem] border border-slate-200/70 bg-slate-50 p-5 text-sm leading-6 text-slate-500 dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        No hay cambios de estado administrativos disponibles en este
                        momento. El siguiente paso se gestiona desde la
                        Definición funcional.
                    </section>
                </aside>
            </div>
        </div>
    
        <section
            v-if="
                props.actions.can_create_definition_revision
                && props.definition_revision_context
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300"
                        >
                            Cambios solicitados por la empresa
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Preparar nueva versión de la Definition
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            La empresa solicitó ajustes sobre la
                            Definition V{{
                                props.definition_revision_context
                                    .previous_definition_version
                            }}.
                            Esa versión se conservará sin cambios como
                            evidencia histórica.
                        </p>

                        <div
                            v-if="
                                props.definition_revision_context
                                    .tenant_change_reason
                            "
                            class="mt-4 rounded-xl border border-amber-200 bg-white/80 p-4 dark:border-amber-900 dark:bg-slate-950/50"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Ajustes solicitados
                            </p>

                            <p
                                class="mt-2 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    props.definition_revision_context
                                        .tenant_change_reason
                                }}
                            </p>
                        </div>

                        <p
                            class="mt-4 text-xs leading-5 text-muted-foreground"
                        >
                            Esta acción crea la siguiente versión como
                            borrador de trabajo, copia el contenido
                            funcional anterior como punto de partida y
                            reinicia las confirmaciones humanas.
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            La nueva versión no se envía automáticamente
                            a la empresa y no inicia contratación,
                            facturación, activación, suscripción ni
                            ejecución.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            !props.actions.definition_revision_endpoint
                        "
                        @click="createImplementationDefinitionRevision"
                    >
                        Preparar nueva versión
                    </button>
                </div>
            </div>
        </section>


        <section
            v-if="props.implementation_request.status === 'definition_preparation'"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                        >
                            Definición funcional
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Alcance de la capacidad solicitada
                        </h2>

                        <template v-if="props.definition">
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Ya existe la Definición V{{ props.definition.version }}
                                para
                                <span class="font-semibold">
                                    {{ capability.label }}
                                </span>.
                                Estado:
                                <span class="font-semibold">
                                    {{ definitionStatusLabel(props.definition.status) }}
                                </span>.
                            </p>
                        </template>

                        <template v-else>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                La solicitud está lista para que LAUDA cree
                                explícitamente el borrador funcional de la
                                capacidad solicitada.
                            </p>
                        </template>

                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                            Crear el borrador y preparar su contenido son acciones
                            separadas. Ninguna envía la Definición a la empresa ni
                            inicia activación, ejecución, contratación, facturación,
                            pagos o suscripciones.
                        </p>

                        <div
                            v-if="
                                props.definition &&
                                props.definition.content_prepared
                            "
                            class="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground"
                        >
                            <span class="rounded-full border px-3 py-1">
                                Entregables preparados:
                                {{ props.definition.deliverable_count }}
                            </span>

                            <span class="rounded-full border px-3 py-1">
                                Dependencias preparadas:
                                {{ props.definition.dependency_count }}
                            </span>
                        </div>
                    </div>

                    <button
                        v-if="
                            !props.definition &&
                            props.actions.can_create_definition
                        "
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="!props.actions.definition_create_endpoint"
                        @click="createImplementationDefinition"
                    >
                        Crear borrador funcional de Definición
                    </button>

                    <button
                        v-else-if="
                            props.definition &&
                            !props.definition.content_prepared &&
                            props.actions.can_generate_definition
                        "
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="!props.actions.definition_generate_endpoint"
                        @click="generateImplementationDefinition"
                    >
                        Preparar contenido de la Definición
                    </button>

                    <div
                        v-else-if="
                            props.definition &&
                            props.definition.content_prepared
                        "
                        class="flex shrink-0 flex-wrap items-center gap-2"
                    >
                        <span
                            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            Contenido preparado para revisión
                        </span>

                        <button
                            v-if="
                                props.definition.status === 'draft' &&
                                props.implementation_request.status === 'definition_preparation' &&
                                props.actions.definition_generate_endpoint
                            "
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900"
                            @click="generateImplementationDefinition(true)"
                        >
                            Volver a preparar contenido
                        </button>
                    </div>

                    <div
                        v-else-if="props.definition"
                        class="shrink-0 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-800"
                    >
                        Definición V{{ props.definition.version }} creada
                    </div>
                </div>
            </div>
        </section>


        <section
            v-if="
                props.definition &&
                props.definition.content_prepared
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        Revisión humana LAUDA
                    </p>

                    <h2 class="mt-2 text-lg font-bold">
                        Confirmar Definition funcional
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Revisa responsables, alcance, entregables,
                        dependencias, insumos y accesos antes de
                        continuar con el proceso.
                    </p>

                    <p class="mt-2 text-xs leading-5 text-muted-foreground">
                        Guardar esta revisión no marca la Definition
                        como lista y no la envía al tenant. La solicitud
                        permanece en preparación de definición.
                    </p>
                </div>

                <div
                    v-if="
                        props.definition_revision_context
                        && props.definition_revision_context
                            .current_definition_version
                    "
                    class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900 dark:bg-amber-950/20"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300"
                    >
                        Cambios solicitados por la empresa
                    </p>

                    <p class="mt-2 text-sm leading-6">
                        Estás editando la Definition V{{
                            props.definition_revision_context
                                .current_definition_version
                        }}
                        a partir de los ajustes solicitados sobre la V{{
                            props.definition_revision_context
                                .previous_definition_version
                        }}.
                    </p>

                    <p
                        v-if="
                            props.definition_revision_context
                                .tenant_change_reason
                        "
                        class="mt-3 whitespace-pre-line rounded-xl border border-amber-200 bg-white/80 p-4 text-sm leading-6 dark:border-amber-900 dark:bg-slate-950/50"
                    >
                        {{
                            props.definition_revision_context
                                .tenant_change_reason
                        }}
                    </p>

                    <p
                        class="mt-3 text-xs leading-5 text-muted-foreground"
                    >
                        La versión anterior permanece preservada.
                        Solo esta nueva versión puede modificarse.
                    </p>
                </div>

                <div class="mt-6">
                    <div class="max-w-3xl">
                        <p class="text-sm font-bold">
                            Edición funcional de la nueva versión
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            Estos campos muestran exactamente las
                            estructuras funcionales existentes de la
                            Definition. El editor JSON permite conservar
                            cualquier estructura anidada sin introducir
                            un segundo esquema paralelo.
                        </p>

                        <p
                            class="mt-2 text-xs leading-5 text-muted-foreground"
                        >
                            scope_mode, capability_key y el bloqueo
                            request-scoped se vuelven a fijar en el
                            servidor al guardar.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-5">
                        <label class="block">
                            <span class="text-sm font-semibold">
                                Alcance funcional
                            </span>

                            <textarea
                                v-model="functionalScopeJson"
                                rows="12"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold">
                                Entregables
                            </span>

                            <textarea
                                v-model="functionalDeliverablesJson"
                                rows="12"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold">
                                Dependencias
                            </span>

                            <textarea
                                v-model="functionalDependenciesJson"
                                rows="10"
                                spellcheck="false"
                                class="mt-2 w-full rounded-xl border bg-background px-4 py-3 font-mono text-xs leading-5"
                                :disabled="
                                    humanReviewForm.processing
                                    || !props.actions.can_review_definition
                                "
                            />
                        </label>
                    </div>

                    <div
                        v-if="functionalEditorError"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                    >
                        {{ functionalEditorError }}
                    </div>
                </div>


                <div
                    v-if="
                        humanReviewForm
                            .responsibility_model
                            .assignments
                            .length
                    "
                    class="mt-6 space-y-3"
                >
                    <p class="text-sm font-bold">
                        Responsabilidades
                    </p>

                    <div
                        v-for="(
                            assignment,
                            index
                        ) in humanReviewForm.responsibility_model.assignments"
                        :key="
                            assignment.initiative_id
                            ?? index
                        "
                        class="grid gap-3 rounded-xl border p-4 lg:grid-cols-[1fr_260px] dark:border-slate-800"
                    >
                        <div>
                            <p class="text-sm font-semibold">
                                {{
                                    assignment.initiative_title
                                    ?? assignment.initiative_id
                                }}
                            </p>

                            <p
                                v-if="assignment.suggested_owner_role"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Sugerencia:
                                {{
                                    assignment.suggested_owner_role
                                }}
                            </p>
                        </div>

                        <select
                            v-model="
                                humanReviewForm
                                    .responsibility_model
                                    .assignments[index]
                                    .responsible_party
                            "
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            :disabled="
                                humanReviewForm.processing
                                || !props.actions.can_review_definition
                            "
                        >
                            <option value="">
                                Seleccionar responsable
                            </option>

                            <option value="lauda">
                                LAUDA
                            </option>

                            <option value="client">
                                Cliente
                            </option>

                            <option value="shared">
                                Compartido
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="
                        props.capability.key
                            === 'data_transformation_bi'
                    "
                    class="mt-8 space-y-8"
                >
                    <section>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold">
                                    Evidencia de insumos
                                </p>

                                <p
                                    class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                >
                                    Identifica cada fuente de origen y cómo sus datos
                                    serán entregados a LAUDA. El intake estándar admite
                                    CSV o XLSX. La conexión, extracción o conversión
                                    desde sistemas de origen no forma parte automática
                                    de este alcance. Si el cliente no puede generar
                                    estos archivos, marca asistencia de extracción
                                    requerida. No ingreses contraseñas, tokens,
                                    API keys ni credenciales.
                                </p>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <a
                                    :href="`/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake-template/xlsx`"
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold"
                                >
                                    Descargar plantilla Excel
                                </a>

                                <a
                                    :href="`/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake-template/csv`"
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold"
                                >
                                    Descargar paquete CSV
                                </a>

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-semibold disabled:cursor-not-allowed"
                                    :disabled="humanReviewForm.processing"
                                    @click="addInputValidationEvidence"
                                >
                                    Agregar otra fuente de datos
                                </button>
                            </div>
                        </div>

                        <div
                            class="mt-5 rounded-xl border bg-muted/20 p-4 dark:border-slate-800"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p class="text-sm font-bold">
                                        Validar archivo estándar
                                    </p>

                                    <p
                                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                    >
                                        Comprueba la estructura, columnas,
                                        formatos, duplicados y relaciones antes
                                        de cualquier importación. Puedes validar
                                        la plantilla Excel (.xlsx) o el paquete
                                        CSV de LAUDA (.zip). Los CSV individuales
                                        no se cargan directamente en este paso.
                                        El archivo se procesa temporalmente y no
                                        se conserva.
                                    </p>
                                </div>

                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold text-muted-foreground"
                                >
                                    Máx. 2 MB
                                </span>
                            </div>

                            <div
                                class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]"
                            >
                                <input
                                    type="file"
                                    accept=".xlsx,.zip,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip"
                                    class="block w-full cursor-pointer rounded-lg border bg-background px-3 py-2 text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold"
                                    :disabled="standardIntakeValidating || standardIntakeIngesting"
                                    @change="selectStandardIntakeFile"
                                />

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-foreground px-4 py-2 text-sm font-semibold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="
                                        !standardIntakeFile
                                        || standardIntakeValidating
                                        || standardIntakeIngesting
                                    "
                                    @click="validateStandardIntakeFile"
                                >
                                    {{
                                        standardIntakeValidating
                                            ? 'Validando...'
                                            : 'Validar archivo'
                                    }}
                                </button>
                            </div>

                            <p
                                v-if="standardIntakeFile"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Seleccionado:
                                <span class="font-semibold text-foreground">
                                    {{ standardIntakeFile.name }}
                                </span>
                                ·
                                {{
                                    standardIntakeFileSizeLabel(
                                        standardIntakeFile.size,
                                    )
                                }}
                            </p>

                            <div
                                v-if="standardIntakeHttpError"
                                class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                            >
                                {{ standardIntakeHttpError }}
                            </div>

                            <div
                                v-if="
                                    standardIntakeReport
                                    && standardIntakeReport.validation
                                "
                                class="mt-5 space-y-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                                    :class="
                                        standardIntakeReport.validation.valid
                                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/20'
                                            : 'border-red-200 bg-red-50 dark:border-red-900/60 dark:bg-red-950/20'
                                    "
                                >
                                    <div>
                                        <p
                                            class="text-sm font-black"
                                            :class="
                                                standardIntakeReport
                                                    .validation
                                                    .valid
                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                    : 'text-red-700 dark:text-red-300'
                                            "
                                        >
                                            {{
                                                standardIntakeReport
                                                    .validation
                                                    .valid
                                                    ? 'PASS · Archivo válido'
                                                    : 'FAIL · Requiere correcciones'
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                standardIntakeReport.file
                                                    ?.name
                                                ?? standardIntakeFile?.name
                                            }}
                                            ·
                                            {{
                                                standardIntakeFormatLabel(
                                                    standardIntakeReport
                                                        .validation
                                                        .format,
                                                )
                                            }}
                                            <template
                                                v-if="
                                                    standardIntakeReport
                                                        .file
                                                        ?.size_bytes
                                                    !== undefined
                                                "
                                            >
                                                ·
                                                {{
                                                    standardIntakeFileSizeLabel(
                                                        standardIntakeReport
                                                            .file
                                                            .size_bytes,
                                                    )
                                                }}
                                            </template>
                                        </p>
                                    </div>

                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-bold"
                                    >
                                        Schema v{{
                                            standardIntakeReport
                                                .validation
                                                .schema_version
                                            ?? 1
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        standardIntakeReport
                                            .validation
                                            .valid
                                    "
                                    class="rounded-lg border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-900/60 dark:bg-sky-950/20"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="max-w-3xl">
                                            <p
                                                class="text-sm font-bold text-sky-800 dark:text-sky-300"
                                            >
                                                Archivo listo para staging
                                            </p>

                                            <p
                                                class="mt-1 text-xs leading-5 text-muted-foreground"
                                            >
                                                La validación anterior fue temporal
                                                y no guardó el archivo. El ingreso a
                                                staging es una acción separada y
                                                explícita: conservará el archivo en
                                                almacenamiento privado y registrará
                                                sus filas validadas en el área de
                                                preparación de datos. No crea todavía
                                                el modelo BI final.
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="cursor-pointer rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="
                                                !standardIntakeFile
                                                || standardIntakeValidating
                                                || standardIntakeIngesting
                                            "
                                            @click="ingestStandardIntakeFile"
                                        >
                                            {{
                                                standardIntakeIngesting
                                                    ? 'Ingresando...'
                                                    : 'Ingresar a staging'
                                            }}
                                        </button>
                                    </div>

                                    <p
                                        class="mt-3 text-[11px] leading-5 text-muted-foreground"
                                    >
                                        Esta acción no cambia el estado de la
                                        solicitud, no modifica la Definición y no
                                        activa automáticamente ningún servicio.
                                    </p>
                                </div>

                                <div
                                    v-if="standardIntakeIngestionError"
                                    class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                >
                                    {{ standardIntakeIngestionError }}
                                </div>

                                <div
                                    v-if="
                                        standardIntakeIngestionReport
                                            ?.ingestion
                                    "
                                    class="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-black text-emerald-700 dark:text-emerald-300"
                                            >
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .reused
                                                        ? 'Batch existente reutilizado'
                                                        : 'Ingreso a staging completado'
                                                }}
                                            </p>

                                            <p
                                                v-if="
                                                    standardIntakeIngestionReport
                                                        .message
                                                "
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    standardIntakeIngestionReport
                                                        .message
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                            >
                                                Batch #{{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .batch_id
                                                }}
                                            </span>

                                            <span
                                                class="rounded-full border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:border-emerald-900 dark:text-emerald-300"
                                            >
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .status
                                                        === 'completed'
                                                        ? 'Completado'
                                                        : standardIntakeIngestionReport
                                                            .ingestion
                                                            .status
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                    >
                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p
                                                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                                            >
                                                Esquema
                                            </p>

                                            <p class="mt-1 text-sm font-bold">
                                                v{{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .schema_version
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p
                                                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                                            >
                                                Formato
                                            </p>

                                            <p class="mt-1 text-sm font-bold">
                                                {{
                                                    standardIntakeFormatLabel(
                                                        standardIntakeIngestionReport
                                                            .ingestion
                                                            .format,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p
                                                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                                            >
                                                Dominios
                                            </p>

                                            <p class="mt-1 text-sm font-bold">
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .domain_count
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p
                                                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                                            >
                                                Archivo
                                            </p>

                                            <p
                                                class="mt-1 truncate text-sm font-bold"
                                                :title="
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .original_filename
                                                "
                                            >
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .original_filename
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-2 sm:grid-cols-3"
                                    >
                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p class="text-xs text-muted-foreground">
                                                Filas de origen
                                            </p>

                                            <p class="mt-1 text-lg font-black">
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .source_row_count
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p class="text-xs text-muted-foreground">
                                                Filas en staging
                                            </p>

                                            <p class="mt-1 text-lg font-black">
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .staged_row_count
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-lg border bg-background/70 p-3"
                                        >
                                            <p class="text-xs text-muted-foreground">
                                                Filas rechazadas
                                            </p>

                                            <p class="mt-1 text-lg font-black">
                                                {{
                                                    standardIntakeIngestionReport
                                                        .ingestion
                                                        .rejected_row_count
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/20"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div class="max-w-3xl">
                                                <p
                                                    class="text-sm font-black text-indigo-800 dark:text-indigo-300"
                                                >
                                                    Procesamiento posterior a staging
                                                </p>

                                                <p
                                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                                >
                                                    El staging ya está almacenado. El
                                                    siguiente paso analiza calidad,
                                                    completitud y consistencia sin
                                                    modificar las filas de origen.
                                                    La normalización permanece como una
                                                    acción separada y solo se habilita
                                                    cuando no existen incidencias
                                                    bloqueantes.
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    standardIntakeProfiling
                                                    || standardIntakeNormalizing
                                                "
                                                @click="profileStandardIntakeBatch"
                                            >
                                                {{
                                                    standardIntakeProfiling
                                                        ? 'Analizando...'
                                                        : standardIntakeProfileIsForCurrentBatch()
                                                          ? 'Revisar calidad nuevamente'
                                                          : 'Analizar calidad'
                                                }}
                                            </button>
                                        </div>

                                        <p
                                            class="mt-3 text-[11px] leading-5 text-muted-foreground"
                                        >
                                            El análisis no cambia el estado de la
                                            solicitud ni de la Definición y no activa
                                            servicios comerciales.
                                        </p>

                                        <div
                                            v-if="standardIntakeProcessingError"
                                            class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                        >
                                            {{ standardIntakeProcessingError }}
                                        </div>

                                        <div
                                            v-if="
                                                standardIntakeProfileIsForCurrentBatch()
                                                && standardIntakeProfileReport
                                                    ?.processing
                                            "
                                            class="mt-4 space-y-4"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-bold"
                                                    >
                                                        Evaluación de calidad
                                                    </p>

                                                    <p
                                                        v-if="
                                                            standardIntakeProfileReport
                                                                ?.message
                                                        "
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .message
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                                    >
                                                        Run #{{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .run_id
                                                        }}
                                                    </span>

                                                    <span
                                                        class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .status
                                                        }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                            >
                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Filas perfiladas
                                                    </p>
                                                    <p
                                                        class="mt-1 text-lg font-black"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .profiled_row_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Incidencias
                                                    </p>
                                                    <p
                                                        class="mt-1 text-lg font-black"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .issue_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Bloqueantes
                                                    </p>
                                                    <p
                                                        class="mt-1 text-lg font-black"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .blocking_issue_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Advertencias
                                                    </p>
                                                    <p
                                                        class="mt-1 text-lg font-black"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .warning_issue_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                class="grid gap-2 sm:grid-cols-2"
                                            >
                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Dominios perfilados
                                                    </p>
                                                    <p
                                                        class="mt-1 text-sm font-bold"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .domain_profile_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Campos perfilados
                                                    </p>
                                                    <p
                                                        class="mt-1 text-sm font-bold"
                                                    >
                                                        {{
                                                            standardIntakeProfileReport
                                                                .processing
                                                                .field_profile_count
                                                            ?? 0
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    (
                                                        standardIntakeProfileReport
                                                            .processing
                                                            .blocking_issue_count
                                                        ?? 0
                                                    ) > 0
                                                "
                                                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300"
                                            >
                                                Existen incidencias bloqueantes.
                                                La normalización permanecerá
                                                deshabilitada hasta que la fuente sea
                                                corregida y se ingrese nuevamente al
                                                staging.
                                            </div>

                                            <div
                                                v-else
                                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-bold text-emerald-800 dark:text-emerald-300"
                                                    >
                                                        Calidad habilitada para normalización
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        No se detectaron incidencias
                                                        bloqueantes. La normalización
                                                        generará datos derivados y no
                                                        sobrescribirá el staging.
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                    :disabled="
                                                        !canNormalizeStandardIntake()
                                                    "
                                                    @click="
                                                        normalizeStandardIntakeBatch
                                                    "
                                                >
                                                    {{
                                                        standardIntakeNormalizing
                                                            ? 'Normalizando...'
                                                            : standardIntakeNormalizationReport
                                                                  ?.processing
                                                                  ?.status
                                                              === 'completed'
                                                              ? 'Normalización completada'
                                                              : 'Normalizar datos'
                                                    }}
                                                </button>
                                            </div>

                                            <div
                                                v-if="
                                                    standardIntakeNormalizationReport
                                                        ?.processing
                                                "
                                                class="rounded-lg border border-emerald-200 bg-background/80 p-4 dark:border-emerald-900/60"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center justify-between gap-3"
                                                >
                                                    <div>
                                                        <p
                                                            class="text-sm font-black text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            Normalización completada
                                                        </p>

                                                        <p
                                                            v-if="
                                                                standardIntakeNormalizationReport
                                                                    ?.message
                                                            "
                                                            class="mt-1 text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                standardIntakeNormalizationReport
                                                                    .message
                                                            }}
                                                        </p>
                                                    </div>

                                                    <span
                                                        class="rounded-full border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:border-emerald-900 dark:text-emerald-300"
                                                    >
                                                        {{
                                                            standardIntakeNormalizationReport
                                                                .processing
                                                                .status
                                                        }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="mt-3 grid gap-2 sm:grid-cols-2"
                                                >
                                                    <div
                                                        class="rounded-lg border bg-background/70 p-3"
                                                    >
                                                        <p
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            Filas normalizadas
                                                        </p>
                                                        <p
                                                            class="mt-1 text-lg font-black"
                                                        >
                                                            {{
                                                                standardIntakeNormalizationReport
                                                                    .processing
                                                                    .normalized_row_count
                                                                ?? 0
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg border bg-background/70 p-3"
                                                    >
                                                        <p
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            Cambios de normalización
                                                        </p>
                                                        <p
                                                            class="mt-1 text-lg font-black"
                                                        >
                                                            {{
                                                                standardIntakeNormalizationReport
                                                                    .processing
                                                                    .normalization_change_count
                                                                ?? 0
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <p class="text-xs font-bold">
                                                Staging por dominio
                                            </p>

                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    standardIntakeIngestionDomainEntries()
                                                        .length
                                                }}
                                                dominios
                                            </span>
                                        </div>

                                        <div
                                            class="mt-3 grid gap-2 lg:grid-cols-2"
                                        >
                                            <div
                                                v-for="[
                                                    domainKey,
                                                    domain,
                                                ] in standardIntakeIngestionDomainEntries()"
                                                :key="`standard-intake-ingestion-domain-${domainKey}`"
                                                class="rounded-lg border bg-background/70 p-3"
                                            >
                                                <div
                                                    class="flex items-center justify-between gap-3"
                                                >
                                                    <p class="text-sm font-semibold">
                                                        {{
                                                            standardIntakeDomainLabel(
                                                                domainKey,
                                                            )
                                                        }}
                                                    </p>

                                                    <span
                                                        class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                                    >
                                                        {{
                                                            domain.staged_row_count
                                                        }}
                                                        staged
                                                    </span>
                                                </div>

                                                <div
                                                    class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                                >
                                                    <span>
                                                        Origen:
                                                        {{
                                                            domain.source_row_count
                                                        }}
                                                    </span>

                                                    <span>
                                                        Rechazadas:
                                                        {{
                                                            domain.rejected_row_count
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        standardIntakeReport
                                            .validation
                                            .errors
                                            ?.length
                                    "
                                    class="rounded-lg border border-red-200 p-3 dark:border-red-900/60"
                                >
                                    <p
                                        class="text-xs font-bold text-red-700 dark:text-red-300"
                                    >
                                        Errores generales
                                    </p>

                                    <ul
                                        class="mt-2 list-disc space-y-1 pl-5 text-xs"
                                    >
                                        <li
                                            v-for="(
                                                issue,
                                                index
                                            ) in standardIntakeReport
                                                .validation
                                                .errors"
                                            :key="`standard-intake-error-${index}`"
                                        >
                                            {{
                                                standardIntakeIssueText(
                                                    issue,
                                                )
                                            }}
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    v-if="
                                        standardIntakeReport
                                            .validation
                                            .warnings
                                            ?.length
                                    "
                                    class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900/60 dark:bg-amber-950/20"
                                >
                                    <p
                                        class="text-xs font-bold text-amber-800 dark:text-amber-300"
                                    >
                                        Advertencias generales
                                    </p>

                                    <ul
                                        class="mt-2 list-disc space-y-1 pl-5 text-xs"
                                    >
                                        <li
                                            v-for="(
                                                issue,
                                                index
                                            ) in standardIntakeReport
                                                .validation
                                                .warnings"
                                            :key="`standard-intake-warning-${index}`"
                                        >
                                            {{
                                                standardIntakeIssueText(
                                                    issue,
                                                )
                                            }}
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    v-if="
                                        standardIntakeReport
                                            .validation
                                            .content
                                            ?.executed
                                    "
                                    class="space-y-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p class="text-xs font-bold">
                                            Resultado por dominio
                                        </p>

                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                standardIntakeDomainEntries()
                                                    .length
                                            }}
                                            dominios evaluados
                                        </span>
                                    </div>

                                    <div
                                        class="grid gap-3 lg:grid-cols-2"
                                    >
                                        <div
                                            v-for="[
                                                domainKey,
                                                domain,
                                            ] in standardIntakeDomainEntries()"
                                            :key="`standard-intake-domain-${domainKey}`"
                                            class="rounded-lg border p-3 dark:border-slate-800"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-semibold"
                                                    >
                                                        {{
                                                            standardIntakeDomainLabel(
                                                                domainKey,
                                                            )
                                                        }}
                                                    </p>

                                                    <p
                                                        class="mt-0.5 text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            domain.row_count
                                                            ?? 0
                                                        }}
                                                        filas
                                                    </p>
                                                </div>

                                                <span
                                                    class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                                    :class="
                                                        domain.valid
                                                            ? 'border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-300'
                                                            : 'border-red-200 text-red-700 dark:border-red-900 dark:text-red-300'
                                                    "
                                                >
                                                    {{
                                                        domain.valid
                                                            ? 'PASS'
                                                            : 'FAIL'
                                                    }}
                                                </span>
                                            </div>

                                            <ul
                                                v-if="
                                                    domain.errors
                                                        ?.length
                                                "
                                                class="mt-3 list-disc space-y-1 pl-5 text-xs text-red-700 dark:text-red-300"
                                            >
                                                <li
                                                    v-for="(
                                                        issue,
                                                        index
                                                    ) in domain.errors"
                                                    :key="`standard-intake-domain-error-${domainKey}-${index}`"
                                                >
                                                    {{
                                                        standardIntakeIssueText(
                                                            issue,
                                                        )
                                                    }}
                                                </li>
                                            </ul>

                                            <ul
                                                v-if="
                                                    domain
                                                        .duplicate_keys
                                                        ?.length
                                                "
                                                class="mt-3 list-disc space-y-1 pl-5 text-xs text-red-700 dark:text-red-300"
                                            >
                                                <li
                                                    v-for="(
                                                        issue,
                                                        index
                                                    ) in domain.duplicate_keys"
                                                    :key="`standard-intake-domain-duplicate-${domainKey}-${index}`"
                                                >
                                                    {{
                                                        standardIntakeIssueText(
                                                            issue,
                                                        )
                                                    }}
                                                </li>
                                            </ul>

                                            <ul
                                                v-if="
                                                    domain
                                                        .relation_errors
                                                        ?.length
                                                "
                                                class="mt-3 list-disc space-y-1 pl-5 text-xs text-red-700 dark:text-red-300"
                                            >
                                                <li
                                                    v-for="(
                                                        issue,
                                                        index
                                                    ) in domain.relation_errors"
                                                    :key="`standard-intake-domain-relation-${domainKey}-${index}`"
                                                >
                                                    {{
                                                        standardIntakeIssueText(
                                                            issue,
                                                        )
                                                    }}
                                                </li>
                                            </ul>

                                            <ul
                                                v-if="
                                                    standardIntakeDomainWarnings(
                                                        domainKey,
                                                    ).length
                                                "
                                                class="mt-3 list-disc space-y-1 pl-5 text-xs text-amber-700 dark:text-amber-300"
                                            >
                                                <li
                                                    v-for="(
                                                        issue,
                                                        index
                                                    ) in standardIntakeDomainWarnings(
                                                        domainKey,
                                                    )"
                                                    :key="`standard-intake-domain-warning-${domainKey}-${index}`"
                                                >
                                                    {{
                                                        standardIntakeIssueText(
                                                            issue,
                                                        )
                                                    }}
                                                </li>
                                            </ul>

                                            <p
                                                v-if="
                                                    !domain.errors
                                                        ?.length
                                                    && !domain
                                                        .duplicate_keys
                                                        ?.length
                                                    && !domain
                                                        .relation_errors
                                                        ?.length
                                                    && !standardIntakeDomainWarnings(
                                                        domainKey,
                                                    ).length
                                                "
                                                class="mt-3 text-xs text-muted-foreground"
                                            >
                                                Sin incidencias detectadas.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <p
                                    v-else
                                    class="rounded-lg border border-dashed p-3 text-xs text-muted-foreground"
                                >
                                    La validación de contenido no se ejecutó
                                    porque el archivo no superó la validación
                                    estructural.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                !humanReviewForm
                                    .readiness
                                    .validation_evidence
                                    .inputs
                                    .length
                            "
                            class="mt-4 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            Todavía no se ha registrado evidencia de insumos.
                        </div>

                        <div
                            v-for="(
                                item,
                                index
                            ) in humanReviewForm.readiness.validation_evidence.inputs"
                            :key="`input-evidence-${index}`"
                            :id="`input-evidence-${index}`"
                            class="mt-4 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Sistema de origen (informativo)
                                    </span>
                                    <input
                                        v-model="item.source_name"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Ej. ERP, sistema contable o base operativa"
                                    />
                                    <span
                                        class="mt-1 block text-xs leading-5 text-muted-foreground"
                                    >
                                        El sistema de origen es solo informativo.
                                        LAUDA recibe el intake estándar mediante
                                        archivos CSV/XLSX conforme a la estructura
                                        requerida.
                                    </span>
                                </label>


                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Rol de los datos
                                    </span>

                                    <select
                                        v-model="item.source_role"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">
                                            Seleccionar...
                                        </option>
                                        <option value="primary">
                                            Principal
                                        </option>
                                        <option value="historical">
                                            Histórica
                                        </option>
                                        <option value="complementary">
                                            Complementaria
                                        </option>
                                        <option value="derived">
                                            Derivada
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Formato de entrega a LAUDA
                                    </span>

                                    <select
                                        v-model="item.delivery_format"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">
                                            Pendiente de definir
                                        </option>
                                        <option value="csv">
                                            CSV
                                        </option>
                                        <option value="xlsx">
                                            Excel (.xlsx)
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span
                                        class="text-xs font-semibold"
                                    >
                                        Asistencia de extracción
                                    </span>

                                    <select
                                        v-model="
                                            item.extraction_assistance_required
                                        "
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    >
                                        <option :value="null">
                                            Pendiente de definir
                                        </option>

                                        <option :value="false">
                                            No requiere asistencia
                                        </option>

                                        <option :value="true">
                                            Sí requiere asistencia
                                        </option>
                                    </select>

                                    <span
                                        class="mt-1 block text-xs leading-5 text-muted-foreground"
                                    >
                                        Indica si el cliente necesita apoyo para
                                        generar la entrega estándar CSV/XLSX.
                                        La extracción o conversión se evalúa
                                        como una necesidad funcional separada.
                                    </span>
                                </label>


                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Estado
                                    </span>
                                    <select
                                        v-model="item.status"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="pending">Pendiente</option>
                                        <option value="validated">Validado</option>
                                        <option value="blocked">Bloqueado</option>
                                        <option value="not_applicable">No aplica</option>
                                    </select>
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="text-xs font-semibold">
                                        Dominios de datos
                                    </span>
                                    <textarea
                                        :value="
                                            Array.isArray(item.data_domains)
                                                ? item.data_domains.join(', ')
                                                : ''
                                        "
                                        rows="2"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="clientes, productos, inventario, ventas"
                                        @input="
                                            setInputValidationEvidenceDomains(
                                                index,
                                                $event,
                                            )
                                        "
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Responsable / propietario
                                    </span>
                                    <input
                                        v-model="item.owner"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Área o rol responsable"
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Cobertura histórica
                                    </span>
                                    <input
                                        v-model="item.historical_coverage"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Ej. enero 2022 a la fecha"
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Granularidad
                                    </span>
                                    <input
                                        v-model="item.granularity"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Ej. transacción / línea"
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Notas
                                    </span>
                                    <input
                                        v-model="item.notes"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Hallazgos o limitaciones"
                                    />
                                </label>
                            </div>

                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    :disabled="humanReviewForm.processing"
                                    @click="
                                        removeInputValidationEvidence(
                                            index,
                                        )
                                    "
                                >
                                    Quitar fuente
                                </button>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-bold">
                                    Evidencia de entrega de datos
                                </p>

                                <p
                                    class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                >
                                    Registra el mecanismo autorizado para entregar
                                    los archivos CSV/XLSX a LAUDA. El flujo estándar
                                    trabaja con archivos estructurados y no requiere
                                    conexión directa al sistema fuente. No almacenes
                                    usuarios, contraseñas, tokens ni secretos.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg border px-3 py-2 text-xs font-semibold"
                                :disabled="humanReviewForm.processing"
                                @click="addAccessValidationEvidence"
                            >
                                Agregar entrega
                            </button>
                        </div>

                        <div
                            v-if="
                                !humanReviewForm
                                    .readiness
                                    .validation_evidence
                                    .accesses
                                    .length
                            "
                            class="mt-4 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            Todavía no se ha registrado evidencia de entrega de datos.
                        </div>

                        <div
                            v-for="(
                                item,
                                index
                            ) in humanReviewForm.readiness.validation_evidence.accesses"
                            :key="`access-evidence-${index}`"
                            class="mt-4 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Fuente
                                    </span>
                                    <input
                                        v-model="item.source_name"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Ej. ERP / SQL Server"
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Mecanismo de entrega
                                    </span>
                                    <input
                                        v-model="item.access_method"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Carga segura, SFTP, carpeta compartida, entrega CSV/XLSX..."
                                    />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Estado
                                    </span>
                                    <select
                                        v-model="item.status"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="pending">Pendiente</option>
                                        <option value="validated">Validado</option>
                                        <option value="blocked">Bloqueado</option>
                                        <option value="not_applicable">No aplica</option>
                                    </select>
                                </label>

                                <label
                                    class="flex items-center gap-3 rounded-lg border p-3"
                                >
                                    <input
                                        v-model="item.authorized"
                                        type="checkbox"
                                    />
                                    <span class="text-sm">
                                        Entrega autorizada
                                    </span>
                                </label>

                                <label
                                    class="flex items-center gap-3 rounded-lg border p-3"
                                >
                                    <input
                                        v-model="item.verified"
                                        type="checkbox"
                                    />
                                    <span class="text-sm">
                                        Entrega verificada
                                    </span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-semibold">
                                        Notas
                                    </span>
                                    <input
                                        v-model="item.notes"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Hallazgos o limitaciones"
                                    />
                                </label>
                            </div>

                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    :disabled="humanReviewForm.processing"
                                    @click="
                                        removeAccessValidationEvidence(
                                            index,
                                        )
                                    "
                                >
                                    Quitar entrega
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-6">
                    <p class="text-sm font-bold">
                        Confirmaciones humanas
                    </p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.scope_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Alcance confirmado
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.deliverables_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Entregables confirmados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.dependencies_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Dependencias confirmadas
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.inputs_validated"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Insumos validados
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.accesses_validated"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Entrega de datos validada
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 dark:border-slate-800"
                        >
                            <input
                                v-model="humanReviewForm.readiness.responsibilities_confirmed"
                                type="checkbox"
                                :disabled="humanReviewForm.processing"
                            />

                            <span class="text-sm">
                                Responsabilidades confirmadas
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    v-if="
                        Object.keys(
                            humanReviewForm.errors,
                        ).length
                    "
                    class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                >
                    <p class="font-semibold">
                        Revisa los campos indicados antes de guardar.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li
                            v-for="(message, field) in humanReviewForm.errors"
                            :key="field"
                        >
                            <span class="font-mono text-xs">
                                {{ field }}
                            </span>
                            ·
                            {{ message }}
                        </li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            humanReviewForm.processing
                            || !props.actions.can_review_definition
                            || !props.actions.definition_review_endpoint
                        "
                        @click="saveImplementationDefinitionHumanReview"
                    >
                        Guardar revisión humana
                    </button>
                </div>
            </div>
        </section>


        <section
            v-if="props.ready_for_commercial_context"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-2 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm dark:border-blue-950 dark:bg-blue-950/10"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-400"
                >
                    Cierre del ciclo funcional
                </p>

                <h2 class="mt-2 text-lg font-bold">
                    Definition V{{ props.ready_for_commercial_context.definition_version }}
                </h2>

                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    La Definition exacta acordada ya completó su cierre
                    funcional. El siguiente gate únicamente registra que
                    esta solicitud puede pasar, más adelante, a un proceso
                    comercial independiente.
                </p>

                <div
                    v-if="
                        props.actions.can_mark_ready_for_commercial
                        && props.ready_for_commercial_context.can_mark_ready_for_commercial
                    "
                    class="mt-5 rounded-xl border border-blue-200 bg-white/70 p-4 dark:border-blue-950 dark:bg-slate-950/60"
                >
                    <p class="text-sm font-semibold">
                        Listo para etapa comercial
                    </p>

                    <p
                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                    >
                        Este paso no crea propuesta, precio, contrato,
                        factura, pago, suscripción, activación ni ejecución.
                        Tampoco constituye aceptación comercial. Solo cierra
                        formalmente el ciclo funcional de esta solicitud.
                    </p>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                            :disabled="
                                readyForCommercialForm.processing
                                || !props.actions.can_mark_ready_for_commercial
                                || !props.actions.ready_for_commercial_endpoint
                            "
                            @click="markRequestReadyForCommercial"
                        >
                            <CheckCircle2 class="h-4 w-4" />

                            {{
                                readyForCommercialForm.processing
                                    ? 'Registrando cierre...'
                                    : 'Dejar listo para etapa comercial'
                            }}
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="
                        props.ready_for_commercial_context.request_status
                            === 'ready_for_commercial'
                    "
                    class="mt-5 rounded-xl border border-blue-200 bg-white/70 p-4 dark:border-blue-950 dark:bg-slate-950/60"
                >
                    <p class="text-sm font-semibold">
                        Ciclo funcional completado
                    </p>

                    <p
                        class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                    >
                        La solicitud quedó lista para que un proceso
                        comercial separado pueda iniciarse posteriormente.
                        No existe aceptación comercial, activación,
                        ejecución ni suscripción automática.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="props.functional_closure_context"
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-2 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm dark:border-emerald-950 dark:bg-emerald-950/10"
            >
                <div class="flex items-start gap-3">
                    <CheckCircle2
                        class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />

                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400"
                        >
                            Definition acordada por la empresa
                        </p>

                        <h2 class="mt-2 text-lg font-bold">
                            Definition V{{ props.functional_closure_context.definition_version }}
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                        >
                            Esta es la versión exacta fijada por el acuerdo del tenant.
                            El cierre funcional se aplicará a esta Definition, no a una
                            versión posterior que pudiera existir.
                        </p>

                        <div
                            class="mt-4 grid gap-3 text-sm sm:grid-cols-3"
                        >
                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Estado
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{ props.functional_closure_context.definition_status }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Definition ready
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        props.functional_closure_context.definition_ready
                                            ? 'Sí'
                                            : 'No'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-muted-foreground">
                                    Acuerdo del tenant
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        props.functional_closure_context.tenant_agreed_at
                                            ?? 'Registrado'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                props.actions.can_finalize_definition_functionally
                                && props.functional_closure_context.can_finalize
                            "
                            class="mt-5 rounded-xl border border-emerald-200 bg-white/70 p-4 dark:border-emerald-950 dark:bg-slate-950/60"
                        >
                            <p class="text-sm font-semibold">
                                Cierre funcional
                            </p>

                            <p
                                class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                            >
                                Finalizar esta Definition la marcará como funcionalmente
                                lista. No activa el servicio, no inicia ejecución, no crea
                                una suscripción y no mueve la solicitud a etapa comercial.
                            </p>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                    :disabled="
                                        functionalClosureForm.processing
                                        || !props.actions.can_finalize_definition_functionally
                                        || !props.actions.definition_functional_finalize_endpoint
                                    "
                                    @click="finalizeFunctionalDefinition"
                                >
                                    <CheckCircle2 class="h-4 w-4" />

                                    {{
                                        functionalClosureForm.processing
                                            ? 'Finalizando...'
                                            : 'Finalizar Definition funcional'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-else-if="
                                props.functional_closure_context.definition_status === 'ready'
                                && props.functional_closure_context.definition_ready
                            "
                            class="mt-5 rounded-xl border border-emerald-200 bg-white/70 p-4 text-sm dark:border-emerald-950 dark:bg-slate-950/60"
                        >
                            <p class="font-semibold">
                                Definition funcional finalizada
                            </p>

                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                La Definition acordada ya está lista funcionalmente.
                                La solicitud permanece en “Definición acordada” hasta
                                que LAUDA ejecute, por separado, el gate hacia etapa comercial.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="
                props.definition &&
                props.actions.can_submit_definition_for_tenant_review
            "
            class="mx-auto mt-6 w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8"
        >
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        Siguiente paso funcional
                    </p>

                    <h2 class="mt-2 text-lg font-bold">
                        Enviar Definition a la empresa
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        La revisión humana de LAUDA está completa.
                        Puedes enviar esta versión de la Definition
                        a la empresa para que la revise.
                    </p>

                    <p class="mt-2 text-xs leading-5 text-muted-foreground">
                        Este envío no significa que la empresa haya
                        aceptado la Definition, no la marca como ready
                        y no inicia contratación, facturación,
                        activación ni ejecución.
                    </p>
                </div>

                <div class="mt-5 max-w-3xl">
                    <label
                        class="text-sm font-semibold"
                        for="tenant-review-notes"
                    >
                        Nota para la empresa
                        <span class="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </label>

                    <textarea
                        id="tenant-review-notes"
                        v-model="tenantReviewSubmissionForm.notes"
                        rows="4"
                        maxlength="4000"
                        class="mt-2 w-full rounded-xl border bg-background px-3 py-2 text-sm"
                        placeholder="Contexto adicional para la revisión de esta Definition..."
                        :disabled="tenantReviewSubmissionForm.processing"
                    />
                </div>

                <div
                    v-if="
                        tenantReviewSubmissionForm.errors.notes
                    "
                    class="mt-2 text-sm text-red-600 dark:text-red-400"
                >
                    {{
                        tenantReviewSubmissionForm.errors.notes
                    }}
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        :disabled="
                            tenantReviewSubmissionForm.processing
                            || !props.actions.can_submit_definition_for_tenant_review
                            || !props.actions.definition_submit_tenant_review_endpoint
                        "
                        @click="submitDefinitionForTenantReview"
                    >
                        Enviar a revisión de la empresa
                    </button>
                </div>
            </div>
        </section>

</AppLayout>
</template>
