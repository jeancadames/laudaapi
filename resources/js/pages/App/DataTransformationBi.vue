<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Database,
    Layers3,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type DataTransformationBiCapability = {
    capability_key: 'data_transformation_bi';
    title: string;
    kind: 'professional_service';
    recommended: boolean;
    recommendation_status:
        | 'recommended'
        | 'not_recommended'
        | 'not_evaluated';
    recommendation_basis: string | null;
    data_dimension_score: number | null;
    data_priority: string | null;
    purpose: string | null;
    includes: string[];
    activation_policy: 'implementation_only';
    commercial_note: string | null;
    recommended_in_plan: boolean;
    phase_sequence: number | null;
    phase_name: string | null;
    roadmap_url: string | null;
    plan_url: string | null;
    detail_url: string | null;
};

type ProcessingHistory = {
    summary: {
        total_batches: number;
        total_runs: number;
        shown_batches: number;
        has_more: boolean;
    };
    entries: Array<{
        batch_id: number;
        is_latest: boolean;
        status: string;
        definition_version: number | null;
        schema_version: number;
        domain_count: number;
        source_row_count: number;
        staged_row_count: number;
        rejected_row_count: number;
        started_at: string | null;
        completed_at: string | null;
        failed_at: string | null;
        purged_at: string | null;
        created_at: string | null;
        run_count: number;
        runs: Array<{
            run_id: number;
            status: string;
            definition_version: number | null;
            schema_version: number;
            profiling_version: number;
            normalization_version: number;
            source_row_count: number;
            profiled_row_count: number;
            normalized_row_count: number;
            issue_count: number;
            blocking_issue_count: number;
            warning_issue_count: number;
            informational_issue_count: number;
            started_at: string | null;
            completed_at: string | null;
            failed_at: string | null;
            created_at: string | null;
        }>;
    }>;
};

type UsableDatasetStatus = {
    available: boolean;
    reason: string;
    dataset: {
        processing_run_id: number;
        intake_batch_id: number;
        definition_version: number | null;
        schema_version: number;
        profiling_version: number;
        normalization_version: number;
        normalized_row_count: number;
        has_rows: boolean;
        completed_at: string | null;
    } | null;
};


type SourceWorkspace = {
    session: {
        id: number;
        status: string;
    } | null;
    actions: {
        can_start_or_resume: boolean;
        can_manage_sources: boolean;
    };
    readiness: {
        inputs_validated: boolean;
        accesses_validated: boolean;
        source_count: number;
        complete_source_count: number;
    };
};

type DynamicSourceAssetFile = {
    id: number;
    status: string;
    original_filename: string;
    source_format: string;
    source_size_bytes: number;
    source_row_count: number;
    uploaded_at: string | null;
};

type DynamicSourceAsset = {
    id: number;
    display_name: string;
    source_object_name: string;
    description: string | null;
    origin_system: string | null;
    structure_format:
        | 'field_type_list'
        | 'sql_server_ddl'
        | 'other'
        | null;
    structure_text: string | null;
    delivery_format:
        | 'csv'
        | 'xlsx'
        | null;
    status: string;
    structure_status: string;
    data_status: string;
    data_file: DynamicSourceAssetFile | null;
    sort_order: number;
    structure_analyzed_at: string | null;
    data_received_at: string | null;
    profiled_at: string | null;
    failure_message: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type SourceWorkspaceTab =
    | 'information'
    | 'structure'
    | 'extraction'
    | 'file'
    | 'result';

type SqlServerExtractionPreview = {
    schema_name: string;
    table_name: string;
    field_count: number;
    query: string;
    export: {
        csv: {
            label: string;
            extension: string;
            instructions: string[];
        };
        xlsx: {
            label: string;
            extension: string;
            instructions: string[];
        };
    };
};

type SourceWorkspaceApiResponse = {
    ok?: boolean;
    message?: string;
    errors?: Record<string, string[]>;
    state?: {
        session?: {
            id: number;
            status: string;
        } | null;
        source_assets?: DynamicSourceAsset[];
        actions?: Record<string, boolean>;
    };
    preview?: SqlServerExtractionPreview;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
    };
    transformation360: {
        has_workflow: boolean;
        assessment_id: number | null;
        current_label: string | null;
        plan_public: boolean;
    };
    implementation_request: {
        id: number | null;
        status: string | null;
        status_label: string;
        requested_at: string | null;
        attempt: number | null;
        can_request: boolean;
        request_endpoint: string | null;
        changes_request_endpoint: string | null;
        agreement_endpoint: string | null;
        definition_review: {
            id: number;
            version: number;
            status: string;
            capability_key: string;
            scope: {
                scope_mode: string | null;
                capability_key: string | null;
                phases: Array<Record<string, any>>;
            };
            deliverables: Array<Record<string, any>>;
            dependencies: Array<Record<string, any>>;
            responsibilities: {
                party_assignment_status: string | null;
                assignments: Array<Record<string, any>>;
            };
            human_review: {
                state: string | null;
                completed: boolean;
                confirmations: {
                    scope_confirmed: boolean;
                    deliverables_confirmed: boolean;
                    dependencies_confirmed: boolean;
                    inputs_validated: boolean;
                    accesses_validated: boolean;
                    responsibilities_confirmed: boolean;
                };
                reviewed_at: string | null;
            };
            submitted_at: string | null;
        } | null;
    };
    data_preparation: {
        stage: string;
        stage_label: string;
        batch: {
            batch_id: number;
            status: string;
            domain_count: number;
            source_row_count: number;
            staged_row_count: number;
            rejected_row_count: number;
            completed_at: string | null;
        };
        processing: {
            run_id: number;
            status: string;
            profiled_row_count: number;
            normalized_row_count: number;
            issue_count: number;
            blocking_issue_count: number;
            warning_issue_count: number;
            informational_issue_count: number;
            normalization_completed: boolean;
            completed_at: string | null;
        } | null;

        domain_summary: {
            total: number;
            normalized: number;
            profiled: number;
            with_blocking_issues: number;
            with_warnings: number;
            clean: number;
        };

        field_summary: {
            total: number;
            healthy: number;
            informational: number;
            warning: number;
            blocking: number;
            attention: number;
            required_incomplete: number;
            with_invalid_values: number;
        };

        domains: Array<{
            key: string;
            label: string;
            preparation_status: string;
            preparation_label: string;
            quality_status: string;
            quality_label: string;
            row_count: number;
            field_count: number;
            identity_count: number;
            duplicate_identity_count: number;
            issue_count: number;
            blocking_issue_count: number;
            warning_issue_count: number;
            informational_issue_count: number;
            normalized_row_count: number;

            issues: Array<{
                code: string;
                label: string;
                guidance: string;
                severity: string;
                severity_label: string;
                count: number;
            }>;

            field_summary: {
                total: number;
                healthy: number;
                informational: number;
                warning: number;
                blocking: number;
                attention: number;
                required_incomplete: number;
                with_invalid_values: number;
            };

            fields: Array<{
                key: string;
                description: string | null;
                data_type: string;
                required: boolean;
                quality_status: string;
                quality_label: string;
                row_count: number;
                non_null_count: number;
                null_count: number;
                blank_count: number;
                missing_count: number;
                completeness_percent: number;
                distinct_count: number;
                invalid_count: number;
                issue_count: number;
                blocking_issue_count: number;
                warning_issue_count: number;
                informational_issue_count: number;

                issues: Array<{
                    code: string;
                    label: string;
                    guidance: string;
                    severity: string;
                    severity_label: string;
                    count: number;
                }>;
            }>;
        }>;
    } | null;

    source_workspace: SourceWorkspace;
    source_assets: DynamicSourceAsset[];

    processing_history: ProcessingHistory;
    usable_dataset: UsableDatasetStatus;
    capability: DataTransformationBiCapability;
}>();

const breadcrumbs = [
    {
        title: 'Transformación 360',
        href: '/app/transformacion-360',
    },
    {
        title: 'Transformación e Inteligencia de Datos para BI',
        href: '/app/transformacion-360/datos-bi',
    },
];

const recommendationLabel = computed(() => {
    if (
        props.capability.recommendation_status ===
        'recommended'
    ) {
        return 'Recomendado por tu Diagnóstico 360';
    }

    if (
        props.capability.recommendation_status ===
        'not_evaluated'
    ) {
        return 'Pendiente de Diagnóstico 360';
    }

    return 'No recomendado actualmente';
});

const priorityLabel = computed(() => {
    const labels: Record<string, string> = {
        critical: 'crítica',
        high: 'alta',
        medium: 'media',
        sustain: 'sostenimiento',
    };

    const priority =
        props.capability.data_priority;

    if (!priority) {
        return null;
    }

    return labels[priority] ?? priority;
});

const requestSubmitting = ref(false);

const requestStage = computed(() => {
    const status = props.implementation_request.status;

    const stages: Record<string, number> = {
        requested: 1,
        under_lauda_review: 2,
        definition_preparation: 3,
        awaiting_tenant_review: 4,
        changes_requested: 4,
        definition_agreed: 5,
        ready_for_commercial: 5,
    };

    return status ? (stages[status] ?? 0) : 0;
});

const requestProgress = computed(() => [
    {
        label: 'Solicitud recibida',
        reached: requestStage.value >= 1,
    },
    {
        label: 'Revisión LAUDA',
        reached: requestStage.value >= 2,
    },
    {
        label: 'Definición',
        reached: requestStage.value >= 3,
    },
    {
        label: 'Revisión de tu empresa',
        reached: requestStage.value >= 4,
    },
    {
        label: 'Definición acordada',
        reached: requestStage.value >= 5,
    },
]);

const requestImplementation = () => {
    const endpoint =
        props.implementation_request.request_endpoint;

    if (
        !endpoint ||
        !props.implementation_request.can_request ||
        requestSubmitting.value
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                requestSubmitting.value = true;
            },
            onFinish: () => {
                requestSubmitting.value = false;
            },
        },
    );
};


const tenantDefinitionReview = computed(
    () =>
        props.implementation_request
            .definition_review,
);

const definitionScopePhases = computed(
    () =>
        tenantDefinitionReview.value
            ?.scope
            ?.phases ?? [],
);

const humanReviewChecks = computed(() => {
    const confirmations =
        tenantDefinitionReview.value
            ?.human_review
            ?.confirmations;

    return [
        {
            label: 'Alcance confirmado',
            confirmed:
                confirmations
                    ?.scope_confirmed ?? false,
        },
        {
            label: 'Entregables confirmados',
            confirmed:
                confirmations
                    ?.deliverables_confirmed ?? false,
        },
        {
            label: 'Dependencias confirmadas',
            confirmed:
                confirmations
                    ?.dependencies_confirmed ?? false,
        },
        {
            label: 'Insumos validados',
            confirmed:
                confirmations
                    ?.inputs_validated ?? false,
        },
        {
            label: 'Accesos validados',
            confirmed:
                confirmations
                    ?.accesses_validated ?? false,
        },
        {
            label: 'Responsabilidades confirmadas',
            confirmed:
                confirmations
                    ?.responsibilities_confirmed ?? false,
        },
    ];
});

const definitionItemTitle = (
    item: Record<string, any>,
    fallback: string,
): string => {
    return String(
        item.title ??
            item.label ??
            item.name ??
            item.deliverable ??
            item.dependency ??
            item.initiative_title ??
            item.capability_label ??
            fallback,
    );
};

const definitionItemDescription = (
    item: Record<string, any>,
): string | null => {
    const value =
        item.description ??
        item.summary ??
        item.detail ??
        item.objective ??
        item.notes ??
        null;

    return value
        ? String(value)
        : null;
};

const phaseScopeItems = (
    phase: Record<string, any>,
): string[] => {
    const capabilities =
        Array.isArray(
            phase.capabilities,
        )
            ? phase.capabilities
            : [];

    return capabilities
        .flatMap(
            (
                capability: Record<string, any>,
            ) => {
                if (
                    Array.isArray(
                        capability.scope_items,
                    )
                ) {
                    return capability.scope_items;
                }

                if (
                    Array.isArray(
                        capability.includes,
                    )
                ) {
                    return capability.includes;
                }

                return [];
            },
        )
        .filter(
            (item: unknown) =>
                typeof item === 'string'
                && item.trim() !== '',
        )
        .map(
            (item: string) =>
                item.trim(),
        );
};

const responsibilityPartyLabel = (
    value: unknown,
): string => {
    const labels: Record<string, string> = {
        lauda: 'LAUDA',
        client: 'Tu empresa',
        shared: 'Compartida',
    };

    return labels[
        String(
            value ?? '',
        )
    ] ?? 'Por confirmar';
};


const changesRequestForm = useForm({
    reason: '',
});

const agreementSubmitting = ref(false);

const canAgreeDefinition = computed(
    () =>
        props.implementation_request.status
            === 'awaiting_tenant_review'
        && Boolean(
            tenantDefinitionReview.value,
        )
        && (
            tenantDefinitionReview.value
                ?.human_review
                ?.completed
            === true
        )
        && Boolean(
            props.implementation_request
                .agreement_endpoint,
        ),
);

function agreeDefinition(): void {
    const endpoint =
        props.implementation_request
            .agreement_endpoint;

    if (
        !canAgreeDefinition.value
        || !endpoint
        || agreementSubmitting.value
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,

            onStart: () => {
                agreementSubmitting.value = true;
            },

            onFinish: () => {
                agreementSubmitting.value = false;
            },
        },
    );
}

const canRequestDefinitionChanges = computed(
    () =>
        props.implementation_request.status
            === 'awaiting_tenant_review'
        && Boolean(
            tenantDefinitionReview.value,
        )
        && Boolean(
            props.implementation_request
                .changes_request_endpoint,
        ),
);

function requestDefinitionChanges(): void {
    const endpoint =
        props.implementation_request
            .changes_request_endpoint;

    if (
        !canRequestDefinitionChanges.value
        || !endpoint
    ) {
        return;
    }

    changesRequestForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}



/*
 * T1_TENANT_SOURCE_WORKSPACE
 *
 * Tenant-owned source intake.
 *
 * LAUDA never connects to the client's database from this UI.
 * Extraction assistance only generates a read-only query for the
 * client to execute locally.
 */
const sourceWorkspaceBase =
    '/app/transformacion-360/datos-bi/fuentes';

const sourceTabs: Array<{
    key: SourceWorkspaceTab;
    label: string;
}> = [
    {
        key: 'information',
        label: 'Información',
    },
    {
        key: 'structure',
        label: 'Estructura',
    },
    {
        key: 'extraction',
        label: 'Extracción',
    },
    {
        key: 'file',
        label: 'Archivo',
    },
    {
        key: 'result',
        label: 'Resultado',
    },
];

const selectedSourceId =
    ref<number | null>(
        props.source_assets[0]?.id
        ?? null,
    );

const activeSourceTab =
    ref<SourceWorkspaceTab>(
        'information',
    );

const sourceWorkspaceBusy =
    ref<string | null>(
        null,
    );

const sourceWorkspaceError =
    ref<string | null>(
        null,
    );

const sourceWorkspaceNotice =
    ref<string | null>(
        null,
    );

const sourceFile =
    ref<File | null>(
        null,
    );

const extractionPreview =
    ref<SqlServerExtractionPreview | null>(
        null,
    );

const createSourceForm = ref({
    display_name: '',
    source_object_name: '',
    description: '',
    origin_system: '',
    delivery_format: 'csv' as 'csv' | 'xlsx',
});

const editSourceForm = ref({
    display_name: '',
    source_object_name: '',
    description: '',
    origin_system: '',
    delivery_format: 'csv' as 'csv' | 'xlsx',
});

const structureForm = ref({
    structure_format:
        'field_type_list' as (
            | 'field_type_list'
            | 'sql_server_ddl'
            | 'other'
        ),

    structure_text: '',
});

const extractionForm = ref({
    schema_name: 'dbo',
    table_name: '',
});

const sourceWorkspaceSessionId =
    computed(
        () =>
            props.source_workspace
                .session
                ?.id
            ?? null,
    );

const canManageSources =
    computed(
        () =>
            props.source_workspace
                .actions
                .can_manage_sources
            === true,
    );

const selectedSource =
    computed(
        () =>
            props.source_assets.find(
                (sourceAsset) =>
                    sourceAsset.id
                    === selectedSourceId.value,
            )
            ?? null,
    );

const selectedExtractionExport =
    computed(() => {
        if (!extractionPreview.value) {
            return null;
        }

        const format =
            selectedSource.value
                ?.delivery_format
                === 'xlsx'
                ? 'xlsx'
                : 'csv';

        return extractionPreview.value
            .export[format];
    });

watch(
    selectedSource,
    (sourceAsset) => {
        extractionPreview.value = null;
        sourceFile.value = null;

        if (!sourceAsset) {
            editSourceForm.value = {
                display_name: '',
                source_object_name: '',
                description: '',
                origin_system: '',
                delivery_format: 'csv',
            };

            structureForm.value = {
                structure_format:
                    'field_type_list',
                structure_text: '',
            };

            extractionForm.value = {
                schema_name: 'dbo',
                table_name: '',
            };

            return;
        }

        editSourceForm.value = {
            display_name:
                sourceAsset.display_name,

            source_object_name:
                sourceAsset.source_object_name,

            description:
                sourceAsset.description
                ?? '',

            origin_system:
                sourceAsset.origin_system
                ?? '',

            delivery_format:
                sourceAsset.delivery_format
                === 'xlsx'
                    ? 'xlsx'
                    : 'csv',
        };

        structureForm.value = {
            structure_format:
                sourceAsset.structure_format
                ?? 'field_type_list',

            structure_text:
                sourceAsset.structure_text
                ?? '',
        };

        extractionForm.value = {
            schema_name: 'dbo',
            table_name:
                sourceAsset.source_object_name
                ?? '',
        };
    },
    {
        immediate: true,
    },
);

watch(
    () =>
        props.source_assets.map(
            (sourceAsset) =>
                sourceAsset.id,
        ),
    (sourceIds) => {
        if (
            selectedSourceId.value === null
            || !sourceIds.includes(
                selectedSourceId.value,
            )
        ) {
            selectedSourceId.value =
                sourceIds[0]
                ?? null;
        }
    },
);

function selectSource(
    sourceAssetId: number,
): void {
    selectedSourceId.value =
        sourceAssetId;

    activeSourceTab.value =
        'information';

    sourceWorkspaceError.value =
        null;

    sourceWorkspaceNotice.value =
        null;
}

function xsrfCookieValue(): string | null {
    const prefix =
        'XSRF-TOKEN=';

    const cookie =
        document.cookie
            .split('; ')
            .find(
                (item) =>
                    item.startsWith(
                        prefix,
                    ),
            );

    if (!cookie) {
        return null;
    }

    return decodeURIComponent(
        cookie.slice(
            prefix.length,
        ),
    );
}

function sourceWorkspaceHeaders(
    isFormData: boolean,
): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With':
            'XMLHttpRequest',
    };

    const csrfToken =
        document
            .querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )
            ?.content
        ?? null;

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] =
            csrfToken;
    } else {
        const xsrfToken =
            xsrfCookieValue();

        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] =
                xsrfToken;
        }
    }

    if (!isFormData) {
        headers['Content-Type'] =
            'application/json';
    }

    return headers;
}

async function sourceWorkspaceApi(
    endpoint: string,
    method: 'POST' | 'PATCH',
    body?:
        | Record<string, unknown>
        | FormData,
): Promise<SourceWorkspaceApiResponse> {
    const isFormData =
        body instanceof FormData;

    const response =
        await fetch(
            endpoint,
            {
                method,
                credentials:
                    'same-origin',

                headers:
                    sourceWorkspaceHeaders(
                        isFormData,
                    ),

                body:
                    body === undefined
                        ? undefined
                        : isFormData
                          ? body
                          : JSON.stringify(
                                body,
                            ),
            },
        );

    const payload: SourceWorkspaceApiResponse =
        await response
            .json()
            .catch(
                () => ({}),
            );

    if (
        !response.ok
        || payload.ok === false
    ) {
        const validationMessage =
            Object
                .values(
                    payload.errors
                    ?? {},
                )
                .flat()
                .find(
                    (message) =>
                        typeof message
                        === 'string',
                );

        throw new Error(
            validationMessage
            ?? payload.message
            ?? 'No se pudo completar la operación.',
        );
    }

    return payload;
}

async function runSourceWorkspaceAction(
    key: string,
    action:
        () =>
            Promise<SourceWorkspaceApiResponse>,
): Promise<
    SourceWorkspaceApiResponse
    | null
> {
    if (sourceWorkspaceBusy.value) {
        return null;
    }

    sourceWorkspaceBusy.value =
        key;

    sourceWorkspaceError.value =
        null;

    sourceWorkspaceNotice.value =
        null;

    try {
        const response =
            await action();

        sourceWorkspaceNotice.value =
            response.message
            ?? 'Operación completada correctamente.';

        return response;
    } catch (error) {
        sourceWorkspaceError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo completar la operación.';

        return null;
    } finally {
        sourceWorkspaceBusy.value =
            null;
    }
}

function reloadSourceWorkspace(): void {
    router.reload({
        only: [
            'source_workspace',
            'source_assets',
        ],
        preserveState: true,
    });
}

async function prepareSourceWorkspace(): Promise<void> {
    const result =
        await runSourceWorkspaceAction(
            'prepare',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/preparar`,
                    'POST',
                    {},
                ),
        );

    if (result) {
        reloadSourceWorkspace();
    }
}

async function createSourceAsset(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const displayName =
        createSourceForm.value
            .display_name
            .trim();

    const objectName =
        createSourceForm.value
            .source_object_name
            .trim();

    if (!sessionId) {
        sourceWorkspaceError.value =
            'Prepara primero el workspace de fuentes.';
        return;
    }

    if (
        displayName === ''
        || objectName === ''
    ) {
        sourceWorkspaceError.value =
            'Indica el nombre de la fuente y la tabla u objeto de origen.';
        return;
    }

    const result =
        await runSourceWorkspaceAction(
            'create',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes`,
                    'POST',
                    {
                        display_name:
                            displayName,

                        source_object_name:
                            objectName,

                        description:
                            createSourceForm.value
                                .description
                                .trim()
                            || null,

                        origin_system:
                            createSourceForm.value
                                .origin_system
                                .trim()
                            || null,

                        delivery_format:
                            createSourceForm.value
                                .delivery_format,
                    },
                ),
        );

    if (!result) {
        return;
    }

    const assets =
        result.state
            ?.source_assets
        ?? [];

    if (assets.length > 0) {
        selectedSourceId.value =
            assets[
                assets.length - 1
            ].id;
    }

    createSourceForm.value = {
        display_name: '',
        source_object_name: '',
        description: '',
        origin_system: '',
        delivery_format: 'csv',
    };

    reloadSourceWorkspace();
}

async function updateSourceAsset(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    const displayName =
        editSourceForm.value
            .display_name
            .trim();

    const objectName =
        editSourceForm.value
            .source_object_name
            .trim();

    if (
        displayName === ''
        || objectName === ''
    ) {
        sourceWorkspaceError.value =
            'El nombre de la fuente y la tabla u objeto de origen son obligatorios.';
        return;
    }

    const result =
        await runSourceWorkspaceAction(
            'update',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}`,
                    'PATCH',
                    {
                        display_name:
                            displayName,

                        source_object_name:
                            objectName,

                        description:
                            editSourceForm.value
                                .description
                                .trim()
                            || null,

                        origin_system:
                            editSourceForm.value
                                .origin_system
                                .trim()
                            || null,

                        delivery_format:
                            editSourceForm.value
                                .delivery_format,
                    },
                ),
        );

    if (result) {
        reloadSourceWorkspace();
    }
}

async function moveSourceAsset(
    direction: -1 | 1,
): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    const ids =
        props.source_assets.map(
            (item) =>
                item.id,
        );

    const currentIndex =
        ids.indexOf(
            sourceAsset.id,
        );

    const targetIndex =
        currentIndex + direction;

    if (
        currentIndex < 0
        || targetIndex < 0
        || targetIndex >= ids.length
    ) {
        return;
    }

    [
        ids[currentIndex],
        ids[targetIndex],
    ] = [
        ids[targetIndex],
        ids[currentIndex],
    ];

    const result =
        await runSourceWorkspaceAction(
            'reorder',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/reordenar`,
                    'PATCH',
                    {
                        source_asset_ids:
                            ids,
                    },
                ),
        );

    if (result) {
        reloadSourceWorkspace();
    }
}

async function archiveSourceAsset(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    const confirmed =
        window.confirm(
            `¿Archivar la fuente "${sourceAsset.display_name}"?`
            + ' Se conservará su historial, pero dejará de formar parte del workspace activo.',
        );

    if (!confirmed) {
        return;
    }

    const result =
        await runSourceWorkspaceAction(
            'archive',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}/archivar`,
                    'PATCH',
                    {},
                ),
        );

    if (!result) {
        return;
    }

    const remaining =
        result.state
            ?.source_assets
        ?? [];

    selectedSourceId.value =
        remaining[0]?.id
        ?? null;

    reloadSourceWorkspace();
}

async function saveSourceStructure(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    const structureText =
        structureForm.value
            .structure_text
            .trim();

    if (structureText === '') {
        sourceWorkspaceError.value =
            'Indica la estructura antes de guardarla.';
        return;
    }

    const result =
        await runSourceWorkspaceAction(
            'structure',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}/estructura`,
                    'PATCH',
                    {
                        structure_format:
                            structureForm.value
                                .structure_format,

                        structure_text:
                            structureText,
                    },
                ),
        );

    if (result) {
        reloadSourceWorkspace();
    }
}

async function previewSourceExtraction(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    if (
        !sourceAsset.structure_text
        || sourceAsset.structure_text
            .trim() === ''
    ) {
        sourceWorkspaceError.value =
            'Guarda primero la estructura de esta fuente.';
        return;
    }

    const schemaName =
        extractionForm.value
            .schema_name
            .trim();

    if (schemaName === '') {
        sourceWorkspaceError.value =
            'Indica el esquema de SQL Server.';
        return;
    }

    const result =
        await runSourceWorkspaceAction(
            'extraction',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}/extraccion-sql-server/previsualizar`,
                    'POST',
                    {
                        schema_name:
                            schemaName,

                        table_name:
                            extractionForm.value
                                .table_name
                                .trim()
                            || null,
                    },
                ),
        );

    if (
        result
        && result.preview
    ) {
        extractionPreview.value =
            result.preview;
    }
}

function selectSourceFile(
    event: Event,
): void {
    const input =
        event.target as HTMLInputElement;

    sourceFile.value =
        input.files?.[0]
        ?? null;
}

async function uploadSourceData(): Promise<void> {
    const sessionId =
        sourceWorkspaceSessionId.value;

    const sourceAsset =
        selectedSource.value;

    if (
        !sessionId
        || !sourceAsset
    ) {
        return;
    }

    if (!sourceFile.value) {
        sourceWorkspaceError.value =
            'Selecciona un archivo CSV o XLSX.';
        return;
    }

    const formData =
        new FormData();

    formData.append(
        'file',
        sourceFile.value,
    );

    const result =
        await runSourceWorkspaceAction(
            'upload',
            () =>
                sourceWorkspaceApi(
                    `${sourceWorkspaceBase}/sesiones/${sessionId}/fuentes/${sourceAsset.id}/archivo`,
                    'POST',
                    formData,
                ),
        );

    if (result) {
        sourceFile.value = null;
        reloadSourceWorkspace();
    }
}

async function copyExtractionQuery(): Promise<void> {
    const query =
        extractionPreview.value
            ?.query;

    if (!query) {
        return;
    }

    try {
        await navigator
            .clipboard
            .writeText(
                query,
            );

        sourceWorkspaceNotice.value =
            'Consulta copiada al portapapeles.';
    } catch {
        sourceWorkspaceError.value =
            'No se pudo copiar automáticamente. Selecciona la consulta y cópiala manualmente.';
    }
}

function sourceStatusLabel(
    status: string,
): string {
    return {
        draft: 'Borrador',
        active: 'Activa',
        ready: 'Lista',
        archived: 'Archivada',
        failed: 'Con incidencia',
    }[status]
        ?? status;
}

function sourceStructureStatusLabel(
    status: string,
): string {
    return {
        pending: 'Pendiente',
        provided: 'Registrada',
        analyzed: 'Analizada',
        failed: 'Con incidencia',
    }[status]
        ?? status;
}

function sourceDataStatusLabel(
    status: string,
): string {
    return {
        pending: 'Pendiente',
        received: 'Recibido',
        analyzed: 'Analizado',
        failed: 'Con incidencia',
    }[status]
        ?? status;
}

function sourceFileSize(
    bytes: number,
): string {
    if (
        !Number.isFinite(bytes)
        || bytes <= 0
    ) {
        return '0 KB';
    }

    const megabytes =
        bytes
        / 1024
        / 1024;

    if (megabytes >= 1) {
        return `${megabytes.toFixed(2)} MB`;
    }

    return `${(
        bytes / 1024
    ).toFixed(1)} KB`;
}

function processingHistoryStatusLabel(
    status: string,
): string {
    return {
        pending: 'Pendiente',
        processing: 'En proceso',
        completed: 'Completado',
        failed: 'Con incidencia',
        purged: 'Depurado',
    }[status]
        ?? status;
}

function processingHistoryDate(
    value: string | null,
): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'es-DO',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(date);
}

</script>

<template>
    <Head
        title="Transformación e Inteligencia de Datos para BI"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <!-- Regreso al módulo padre -->
            <div>
                <Link
                    href="/app/transformacion-360"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver a Transformación 360
                </Link>
            </div>

            <!-- Hero -->
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="p-6 sm:p-8 lg:p-10">
                    <div
                        class="flex flex-col gap-8 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="max-w-4xl">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    <Database class="h-5 w-5" />
                                </div>

                                <span
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black tracking-wide text-blue-700 uppercase dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    Servicio profesional
                                </span>

                                <span
                                    class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black tracking-wide text-red-700 uppercase dark:bg-red-950/30 dark:text-red-300"
                                >
                                    {{ recommendationLabel }}
                                </span>
                            </div>

                            <h1
                                class="mt-5 max-w-4xl text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white"
                            >
                                Transformación e Inteligencia
                                de Datos para BI
                            </h1>

                            <p
                                class="mt-2 text-sm font-semibold text-slate-500 dark:text-slate-400"
                            >
                                {{ company.name }}
                            </p>

                            <p
                                v-if="capability.purpose"
                                class="mt-5 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300"
                            >
                                {{ capability.purpose }}
                            </p>
                        </div>

                        <div
                            class="w-full rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 xl:w-72 dark:border-slate-800 dark:bg-slate-900/30"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Estado de la capacidad
                            </p>

                            <p
                                class="mt-3 text-base font-black leading-6 text-slate-950 dark:text-white"
                            >
                                {{ recommendationLabel }}
                            </p>

                            <div
                                class="mt-4 border-t border-slate-200/70 pt-4 dark:border-slate-800"
                            >
                                <p
                                    v-if="capability.recommended_in_plan"
                                    class="text-sm font-bold leading-6 text-emerald-700 dark:text-emerald-300"
                                >
                                    Incluida en tu Plan de Implementación
                                </p>

                                <p
                                    v-else
                                    class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Sin recomendación vigente dentro del Plan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="border-t border-slate-200/70 bg-slate-50/50 p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900/20"
                >
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- Diagnóstico -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Diagnóstico · Datos e Inteligencia
                            </p>

                            <div class="mt-auto pt-5">
                                <p
                                    class="text-3xl font-black tracking-tight text-slate-950 dark:text-white"
                                >
                                    <template
                                        v-if="
                                            capability.data_dimension_score !==
                                            null
                                        "
                                    >
                                        {{
                                            capability.data_dimension_score
                                        }}/100
                                    </template>

                                    <template v-else>
                                        Pendiente
                                    </template>
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Resultado de la dimensión Datos e
                                    Inteligencia.
                                </p>
                            </div>
                        </div>

                        <!-- Prioridad -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Prioridad
                            </p>

                            <div class="mt-auto pt-5">
                                <p
                                    class="text-3xl font-black tracking-tight text-slate-950 capitalize dark:text-white"
                                >
                                    {{
                                        priorityLabel ??
                                        'Pendiente'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Nivel de atención derivado del
                                    Diagnóstico 360.
                                </p>
                            </div>
                        </div>

                        <!-- Plan -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-blue-200/70 bg-blue-50/40 p-5 dark:border-blue-950 dark:bg-blue-950/10"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-blue-600/70 uppercase dark:text-blue-400"
                            >
                                Plan de Implementación
                            </p>

                            <div class="mt-auto pt-5">
                                <template
                                    v-if="
                                        capability.recommended_in_plan
                                    "
                                >
                                    <p
                                        class="text-base font-black leading-6 text-slate-950 dark:text-white"
                                    >
                                        Recomendado en tu Plan de Implementación
                                    </p>

                                    <p
                                        v-if="capability.phase_name"
                                        class="mt-2 text-sm font-bold leading-5 text-blue-700 dark:text-blue-300"
                                    >
                                        {{
                                            capability.phase_name
                                        }}
                                    </p>
                                </template>

                                <p
                                    v-else
                                    class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Esta capacidad todavía no está incluida en
                                    un Plan de Implementación vigente.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contenido principal -->
            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.8fr)] xl:items-start"
            >
                <div class="space-y-6">
                    <!-- Justificación -->
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-300"
                            >
                                <Sparkles class="h-5 w-5" />
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Diagnóstico 360
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    ¿Por qué se recomienda?
                                </h2>
                            </div>
                        </div>

                        <div
                            v-if="capability.recommendation_basis"
                            class="mt-5 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:bg-slate-900/30 dark:text-slate-300"
                        >
                            {{
                                capability.recommendation_basis
                            }}
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:bg-slate-900/30 dark:text-slate-300"
                        >
                            <template
                                v-if="
                                    transformation360.has_workflow
                                "
                            >
                                El Diagnóstico 360 vigente no está
                                recomendando esta capacidad en este momento.
                            </template>

                            <template v-else>
                                Completa tu Diagnóstico 360 para determinar
                                si esta capacidad debe formar parte de la
                                ruta recomendada para tu empresa.
                            </template>
                        </div>
                    </section>

                    <!-- Alcance -->
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-300"
                            >
                                <Layers3 class="h-5 w-5" />
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Datos BI
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Alcance considerado
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Áreas de información que pueden formar
                                    parte de la capa fundacional de datos de
                                    tu empresa.
                                </p>
                            </div>
                        </div>

                        <ul
                            class="mt-6 grid gap-3 md:grid-cols-2"
                        >
                            <li
                                v-for="item in capability.includes"
                                :key="item"
                                class="flex h-full gap-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="mt-1 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />

                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </section>
                    <!-- Definition presentada al tenant -->
                    <section
                        v-if="tenantDefinitionReview"
                        class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Revisión de tu empresa
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Definition funcional presentada
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Revisa la versión preparada por LAUDA
                                    para esta capacidad: alcance,
                                    entregables, dependencias y
                                    responsabilidades.
                                </p>
                            </div>

                            <div
                                class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right dark:border-emerald-900 dark:bg-emerald-950/20"
                            >
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Versión presentada
                                </p>

                                <p
                                    class="mt-1 text-lg font-black text-emerald-800 dark:text-emerald-200"
                                >
                                    V{{ tenantDefinitionReview.version }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="tenantDefinitionReview.submitted_at"
                            class="mt-4 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Enviada a revisión:
                            {{ tenantDefinitionReview.submitted_at }}
                        </p>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Alcance funcional
                            </h3>

                            <div
                                v-if="definitionScopePhases.length"
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="(
                                        phase,
                                        phaseIndex
                                    ) in definitionScopePhases"
                                    :key="
                                        phase.id ??
                                        phase.sequence ??
                                        phaseIndex
                                    "
                                    class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                phase,
                                                `Fase ${phaseIndex + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                phase,
                                            )
                                        "
                                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                phase,
                                            )
                                        }}
                                    </p>

                                    <ul
                                        v-if="
                                            phaseScopeItems(
                                                phase,
                                            ).length
                                        "
                                        class="mt-3 space-y-2"
                                    >
                                        <li
                                            v-for="item in phaseScopeItems(
                                                phase,
                                            )"
                                            :key="item"
                                            class="flex gap-2 text-xs leading-5 text-slate-600 dark:text-slate-300"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                            />

                                            <span>{{ item }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Entregables
                            </h3>

                            <div
                                class="mt-4 grid gap-3 md:grid-cols-2"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in tenantDefinitionReview.deliverables"
                                    :key="item.id ?? index"
                                    class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                item,
                                                `Entregable ${index + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                item,
                                            )
                                        "
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                item,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Dependencias
                            </h3>

                            <div
                                v-if="
                                    tenantDefinitionReview
                                        .dependencies
                                        .length
                                "
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in tenantDefinitionReview.dependencies"
                                    :key="item.id ?? index"
                                    class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                item,
                                                `Dependencia ${index + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                item,
                                            )
                                        "
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                item,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-else
                                class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                No se registraron dependencias
                                adicionales para esta versión.
                            </p>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Responsabilidades
                            </h3>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(
                                        assignment,
                                        index
                                    ) in tenantDefinitionReview.responsibilities.assignments"
                                    :key="
                                        assignment.initiative_id ??
                                        index
                                    "
                                    class="flex flex-col gap-2 rounded-2xl border border-slate-200/70 p-4 sm:flex-row sm:items-start sm:justify-between dark:border-slate-800"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                definitionItemTitle(
                                                    assignment,
                                                    `Responsabilidad ${index + 1}`,
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                assignment.suggested_owner_role
                                            "
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Referencia LAUDA:
                                            {{
                                                assignment.suggested_owner_role
                                            }}
                                        </p>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full border border-slate-200 px-3 py-1 text-[10px] font-black tracking-wide text-slate-700 uppercase dark:border-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            responsibilityPartyLabel(
                                                assignment.responsible_party,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h3
                                    class="text-sm font-black text-slate-950 dark:text-white"
                                >
                                    Revisión humana de LAUDA
                                </h3>

                                <span
                                    class="text-xs font-bold"
                                    :class="
                                        tenantDefinitionReview
                                            .human_review
                                            .completed
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-amber-700 dark:text-amber-300'
                                    "
                                >
                                    {{
                                        tenantDefinitionReview
                                            .human_review
                                            .completed
                                            ? 'Completada'
                                            : 'Pendiente'
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-4 grid gap-2 sm:grid-cols-2"
                            >
                                <div
                                    v-for="item in humanReviewChecks"
                                    :key="item.label"
                                    class="flex items-center gap-2 rounded-xl border border-slate-200/70 p-3 text-xs font-semibold dark:border-slate-800"
                                >
                                    <CheckCircle2
                                        class="h-4 w-4 shrink-0"
                                        :class="
                                            item.confirmed
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-slate-300 dark:text-slate-700'
                                        "
                                    />

                                    <span>{{ item.label }}</span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 rounded-2xl border border-blue-200/70 bg-blue-50/50 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-blue-950/10 dark:text-slate-300"
                        >
                            Esta Definition corresponde únicamente
                            al alcance funcional y técnico de esta
                            capacidad. No contiene precios ni implica
                            contratación, facturación, activación,
                            suscripción o inicio de ejecución.
                        </div>
                    </section>

                    <section
                        v-if="
                            implementation_request.status ===
                                'definition_agreed'
                            && tenantDefinitionReview
                        "
                        class="rounded-[2rem] border border-emerald-200/70 bg-emerald-50/40 p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-emerald-950/10"
                    >
                        <div class="flex items-start gap-3">
                            <CheckCircle2
                                class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Definition acordada
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Tu empresa acordó esta versión
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Esta versión queda registrada como
                                    la Definition funcional acordada por
                                    tu empresa. El acuerdo no activa el
                                    servicio, no inicia ejecución, no
                                    constituye aceptación comercial y
                                    no crea una suscripción.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="canAgreeDefinition"
                        class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            Tu decisión
                        </p>

                        <h2
                            class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                        >
                            ¿Esta Definition representa lo acordado?
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Al acordar esta versión confirmas que el
                            alcance funcional, entregables,
                            dependencias y responsabilidades
                            presentados representan lo acordado por tu
                            empresa para continuar al cierre funcional.
                        </p>

                        <div
                            class="mt-5 rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4 text-xs leading-5 text-slate-600 dark:border-emerald-950 dark:bg-emerald-950/10 dark:text-slate-300"
                        >
                            Acordar esta Definition no activa el
                            servicio, no inicia implementación o
                            ejecución, no constituye aceptación
                            comercial y no crea una suscripción.
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                :disabled="agreementSubmitting"
                                @click="agreeDefinition"
                            >
                                <CheckCircle2 class="h-4 w-4" />

                                {{
                                    agreementSubmitting
                                        ? 'Registrando acuerdo...'
                                        : 'Acordar esta Definition'
                                }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="canRequestDefinitionChanges"
                        class="rounded-[2rem] border border-amber-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-amber-950 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-amber-600 uppercase dark:text-amber-400"
                        >
                            Tu revisión
                        </p>

                        <h2
                            class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                        >
                            ¿Qué debemos ajustar?
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Describe de forma concreta los cambios que
                            necesitas en esta versión de la Definition.
                            LAUDA conservará esta versión y preparará
                            posteriormente una nueva versión para revisión.
                        </p>

                        <div class="mt-5">
                            <textarea
                                v-model="changesRequestForm.reason"
                                rows="5"
                                minlength="10"
                                maxlength="4000"
                                class="w-full rounded-2xl border border-slate-200 bg-background px-4 py-3 text-sm leading-6 dark:border-slate-800"
                                placeholder="Describe los cambios que necesitas en esta Definition..."
                                :disabled="changesRequestForm.processing"
                            />

                            <div
                                class="mt-2 flex items-center justify-between gap-3"
                            >
                                <p
                                    v-if="changesRequestForm.errors.reason"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ changesRequestForm.errors.reason }}
                                </p>

                                <p
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    Mínimo 10 caracteres.
                                </p>

                                <span
                                    class="shrink-0 text-xs text-slate-400"
                                >
                                    {{ changesRequestForm.reason.length }}/4000
                                </span>
                            </div>
                        </div>

                        <div
                            class="mt-5 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 text-xs leading-5 text-slate-600 dark:border-amber-950 dark:bg-amber-950/10 dark:text-slate-300"
                        >
                            Solicitar cambios no modifica esta versión
                            presentada y no inicia contratación,
                            facturación, activación ni ejecución.
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                :disabled="
                                    changesRequestForm.processing
                                    || changesRequestForm.reason.trim().length < 10
                                "
                                @click="requestDefinitionChanges"
                            >
                                Solicitar cambios
                            </button>
                        </div>
                    </section>


                    <!-- T1_TENANT_SOURCE_WORKSPACE -->
                    <section
                        v-if="implementation_request.id"
                        class="rounded-[2rem] border border-cyan-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-cyan-950 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-cyan-700 uppercase dark:text-cyan-400"
                                >
                                    Entrega de información
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Fuentes de datos
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Registra las fuentes que utiliza tu empresa,
                                    documenta su estructura y entrega los archivos
                                    CSV o XLSX que LAUDA transformará para BI.
                                </p>
                            </div>

                            <div
                                class="shrink-0 rounded-2xl border border-slate-200/70 bg-slate-50/60 px-4 py-3 text-right dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-black tracking-wide text-slate-400 uppercase"
                                >
                                    Preparación de fuentes
                                </p>

                                <p
                                    class="mt-1 text-sm font-black text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        source_workspace
                                            .readiness
                                            .complete_source_count
                                    }}/{{
                                        source_workspace
                                            .readiness
                                            .source_count
                                    }}
                                    completas
                                </p>

                                <p
                                    class="mt-1 text-[11px]"
                                    :class="
                                        source_workspace
                                            .readiness
                                            .inputs_validated
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-amber-600 dark:text-amber-400'
                                    "
                                >
                                    {{
                                        source_workspace
                                            .readiness
                                            .inputs_validated
                                            ? 'Entrega lista'
                                            : 'Entrega pendiente'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="sourceWorkspaceError"
                            class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/20 dark:text-red-300"
                        >
                            {{ sourceWorkspaceError }}
                        </div>

                        <div
                            v-if="sourceWorkspaceNotice"
                            class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 dark:border-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-300"
                        >
                            {{ sourceWorkspaceNotice }}
                        </div>

                        <!-- No session yet -->
                        <div
                            v-if="!source_workspace.session"
                            class="mt-6 rounded-2xl border border-dashed border-cyan-300 bg-cyan-50/40 p-6 dark:border-cyan-900 dark:bg-cyan-950/10"
                        >
                            <h3
                                class="text-base font-black text-slate-950 dark:text-white"
                            >
                                Prepara el espacio de entrega
                            </h3>

                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Este paso crea o reutiliza una sesión de trabajo
                                para organizar tus fuentes. No inicia staging,
                                perfilado, normalización ni ejecución de BI.
                            </p>

                            <button
                                v-if="
                                    source_workspace
                                        .actions
                                        .can_start_or_resume
                                "
                                type="button"
                                class="mt-5 inline-flex items-center justify-center rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-cyan-600 dark:hover:bg-cyan-500"
                                :disabled="
                                    sourceWorkspaceBusy !== null
                                "
                                @click="prepareSourceWorkspace"
                            >
                                {{
                                    sourceWorkspaceBusy === 'prepare'
                                        ? 'Preparando...'
                                        : 'Preparar fuentes de datos'
                                }}
                            </button>
                        </div>

                        <template v-else>
                            <div
                                class="mt-6 flex flex-wrap items-center gap-2 text-xs"
                            >
                                <span
                                    class="rounded-full border border-slate-200 px-3 py-1.5 font-bold text-slate-600 dark:border-slate-800 dark:text-slate-300"
                                >
                                    Sesión #{{ source_workspace.session.id }}
                                </span>

                                <span
                                    class="rounded-full border border-slate-200 px-3 py-1.5 font-bold text-slate-600 dark:border-slate-800 dark:text-slate-300"
                                >
                                    {{ source_workspace.session.status }}
                                </span>

                                <span
                                    v-if="!canManageSources"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 font-bold text-amber-700 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300"
                                >
                                    Solo lectura
                                </span>
                            </div>

                            <!-- Create source -->
                            <details
                                v-if="canManageSources"
                                class="mt-6 rounded-2xl border border-slate-200/70 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <summary
                                    class="cursor-pointer list-none px-5 py-4 text-sm font-black text-slate-900 dark:text-slate-100"
                                >
                                    + Agregar fuente
                                </summary>

                                <form
                                    class="grid gap-4 border-t border-slate-200/70 p-5 md:grid-cols-2 dark:border-slate-800"
                                    @submit.prevent="createSourceAsset"
                                >
                                    <label class="block">
                                        <span
                                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            Nombre de la fuente *
                                        </span>

                                        <input
                                            v-model="createSourceForm.display_name"
                                            type="text"
                                            maxlength="191"
                                            required
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                            placeholder="Ej. Clientes ERP"
                                        />
                                    </label>

                                    <label class="block">
                                        <span
                                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            Tabla u objeto origen *
                                        </span>

                                        <input
                                            v-model="createSourceForm.source_object_name"
                                            type="text"
                                            maxlength="191"
                                            required
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                            placeholder="Ej. Clientes"
                                        />
                                    </label>

                                    <label class="block">
                                        <span
                                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            Sistema / ERP origen
                                        </span>

                                        <input
                                            v-model="createSourceForm.origin_system"
                                            type="text"
                                            maxlength="191"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                            placeholder="Ej. SQL Server · Sistema comercial"
                                        />
                                    </label>

                                    <label class="block">
                                        <span
                                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            Formato previsto
                                        </span>

                                        <select
                                            v-model="createSourceForm.delivery_format"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                        >
                                            <option value="csv">
                                                CSV
                                            </option>
                                            <option value="xlsx">
                                                Excel XLSX
                                            </option>
                                        </select>
                                    </label>

                                    <label class="block md:col-span-2">
                                        <span
                                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            Descripción
                                        </span>

                                        <textarea
                                            v-model="createSourceForm.description"
                                            rows="3"
                                            maxlength="2000"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm leading-6 dark:border-slate-800"
                                            placeholder="Describe qué información contiene esta fuente."
                                        />
                                    </label>

                                    <div
                                        class="md:col-span-2 flex justify-end"
                                    >
                                        <button
                                            type="submit"
                                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                            :disabled="
                                                sourceWorkspaceBusy !== null
                                            "
                                        >
                                            {{
                                                sourceWorkspaceBusy === 'create'
                                                    ? 'Creando...'
                                                    : 'Crear fuente'
                                            }}
                                        </button>
                                    </div>
                                </form>
                            </details>

                            <!-- Source selector -->
                            <div
                                v-if="source_assets.length > 0"
                                class="mt-6"
                            >
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Fuentes activas
                                </p>

                                <div
                                    class="mt-3 flex gap-3 overflow-x-auto pb-2"
                                >
                                    <div
                                        v-for="(
                                            sourceAsset,
                                            sourceIndex
                                        ) in source_assets"
                                        :key="sourceAsset.id"
                                        class="min-w-56 rounded-2xl border p-3"
                                        :class="
                                            selectedSourceId === sourceAsset.id
                                                ? 'border-cyan-400 bg-cyan-50/60 dark:border-cyan-800 dark:bg-cyan-950/20'
                                                : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950'
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            @click="
                                                selectSource(
                                                    sourceAsset.id,
                                                )
                                            "
                                        >
                                            <p
                                                class="text-sm font-black text-slate-950 dark:text-white"
                                            >
                                                {{ sourceAsset.display_name }}
                                            </p>

                                            <p
                                                class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    sourceAsset.origin_system
                                                    || 'Origen no indicado'
                                                }}
                                                ·
                                                {{
                                                    sourceAsset.source_object_name
                                                    || 'Objeto pendiente'
                                                }}
                                            </p>

                                            <p
                                                class="mt-2 text-[10px] font-bold tracking-wide text-cyan-700 uppercase dark:text-cyan-400"
                                            >
                                                {{
                                                    sourceStatusLabel(
                                                        sourceAsset.status,
                                                    )
                                                }}
                                                ·
                                                {{
                                                    sourceDataStatusLabel(
                                                        sourceAsset.data_status,
                                                    )
                                                }}
                                            </p>
                                        </button>

                                        <div
                                            v-if="canManageSources"
                                            class="mt-3 flex gap-2 border-t border-slate-200/70 pt-3 dark:border-slate-800"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold disabled:opacity-30 dark:border-slate-800"
                                                :disabled="
                                                    sourceIndex === 0
                                                    || sourceWorkspaceBusy !== null
                                                "
                                                @click="
                                                    selectSource(
                                                        sourceAsset.id,
                                                    );
                                                    moveSourceAsset(-1);
                                                "
                                            >
                                                ↑
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold disabled:opacity-30 dark:border-slate-800"
                                                :disabled="
                                                    sourceIndex === source_assets.length - 1
                                                    || sourceWorkspaceBusy !== null
                                                "
                                                @click="
                                                    selectSource(
                                                        sourceAsset.id,
                                                    );
                                                    moveSourceAsset(1);
                                                "
                                            >
                                                ↓
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-6 rounded-2xl border border-dashed border-slate-300 p-6 text-sm leading-6 text-slate-500 dark:border-slate-700 dark:text-slate-400"
                            >
                                Todavía no has registrado fuentes. Agrega la
                                primera para comenzar a documentar y entregar
                                tus datos.
                            </div>

                            <!-- Selected source workspace -->
                            <div
                                v-if="selectedSource"
                                class="mt-6 overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800"
                            >
                                <div
                                    class="flex gap-1 overflow-x-auto border-b border-slate-200/70 bg-slate-50/50 p-2 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <button
                                        v-for="tab in sourceTabs"
                                        :key="tab.key"
                                        type="button"
                                        class="shrink-0 rounded-xl px-3 py-2 text-xs font-black transition"
                                        :class="
                                            activeSourceTab === tab.key
                                                ? 'bg-white text-cyan-700 shadow-sm dark:bg-slate-950 dark:text-cyan-300'
                                                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                        "
                                        @click="
                                            activeSourceTab =
                                                tab.key
                                        "
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>

                                <div class="p-5 sm:p-6">
                                    <!-- Información -->
                                    <form
                                        v-if="
                                            activeSourceTab ===
                                            'information'
                                        "
                                        class="grid gap-4 md:grid-cols-2"
                                        @submit.prevent="updateSourceAsset"
                                    >
                                        <label class="block">
                                            <span
                                                class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                            >
                                                Nombre
                                            </span>

                                            <input
                                                v-model="editSourceForm.display_name"
                                                type="text"
                                                maxlength="191"
                                                required
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm disabled:opacity-60 dark:border-slate-800"
                                                :disabled="!canManageSources"
                                            />
                                        </label>

                                        <label class="block">
                                            <span
                                                class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                            >
                                                Tabla u objeto origen
                                            </span>

                                            <input
                                                v-model="editSourceForm.source_object_name"
                                                type="text"
                                                maxlength="191"
                                                required
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm disabled:opacity-60 dark:border-slate-800"
                                                :disabled="!canManageSources"
                                            />
                                        </label>

                                        <label class="block">
                                            <span
                                                class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                            >
                                                Sistema / ERP
                                            </span>

                                            <input
                                                v-model="editSourceForm.origin_system"
                                                type="text"
                                                maxlength="191"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm disabled:opacity-60 dark:border-slate-800"
                                                :disabled="!canManageSources"
                                            />
                                        </label>

                                        <label class="block">
                                            <span
                                                class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                            >
                                                Formato
                                            </span>

                                            <select
                                                v-model="editSourceForm.delivery_format"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm disabled:opacity-60 dark:border-slate-800"
                                                :disabled="!canManageSources"
                                            >
                                                <option value="csv">
                                                    CSV
                                                </option>
                                                <option value="xlsx">
                                                    Excel XLSX
                                                </option>
                                            </select>
                                        </label>

                                        <label class="block md:col-span-2">
                                            <span
                                                class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                            >
                                                Descripción
                                            </span>

                                            <textarea
                                                v-model="editSourceForm.description"
                                                rows="3"
                                                maxlength="2000"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm leading-6 disabled:opacity-60 dark:border-slate-800"
                                                :disabled="!canManageSources"
                                            />
                                        </label>

                                        <div
                                            v-if="canManageSources"
                                            class="md:col-span-2 flex flex-wrap justify-between gap-3 border-t border-slate-200/70 pt-4 dark:border-slate-800"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-950 dark:text-red-400 dark:hover:bg-red-950/20"
                                                :disabled="
                                                    sourceWorkspaceBusy !== null
                                                "
                                                @click="archiveSourceAsset"
                                            >
                                                Archivar fuente
                                            </button>

                                            <button
                                                type="submit"
                                                class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                                :disabled="
                                                    sourceWorkspaceBusy !== null
                                                "
                                            >
                                                {{
                                                    sourceWorkspaceBusy === 'update'
                                                        ? 'Guardando...'
                                                        : 'Guardar información'
                                                }}
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Estructura -->
                                    <div
                                        v-else-if="
                                            activeSourceTab ===
                                            'structure'
                                        "
                                    >
                                        <div
                                            class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-blue-950/10 dark:text-slate-300"
                                        >
                                            La estructura es opcional para subir
                                            un archivo. Puedes registrarla para
                                            documentar campos o para generar una
                                            consulta de extracción.
                                        </div>

                                        <div class="mt-5">
                                            <label class="block">
                                                <span
                                                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                                >
                                                    Formato de estructura
                                                </span>

                                                <select
                                                    v-model="structureForm.structure_format"
                                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm disabled:opacity-60 dark:border-slate-800"
                                                    :disabled="!canManageSources"
                                                >
                                                    <option value="field_type_list">
                                                        Lista campo / tipo
                                                    </option>
                                                    <option value="sql_server_ddl">
                                                        SQL Server DDL
                                                    </option>
                                                    <option value="other">
                                                        Otro
                                                    </option>
                                                </select>
                                            </label>

                                            <label class="mt-4 block">
                                                <span
                                                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                                >
                                                    Definición
                                                </span>

                                                <textarea
                                                    v-model="structureForm.structure_text"
                                                    rows="12"
                                                    maxlength="50000"
                                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-3 font-mono text-xs leading-5 disabled:opacity-60 dark:border-slate-800"
                                                    :disabled="!canManageSources"
                                                    placeholder="Ej. IdCliente INT&#10;Nombre VARCHAR(200)&#10;FechaCreacion DATETIME"
                                                />
                                            </label>

                                            <div
                                                v-if="canManageSources"
                                                class="mt-4 flex justify-end"
                                            >
                                                <button
                                                    type="button"
                                                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                                    :disabled="
                                                        sourceWorkspaceBusy !== null
                                                    "
                                                    @click="saveSourceStructure"
                                                >
                                                    {{
                                                        sourceWorkspaceBusy === 'structure'
                                                            ? 'Guardando...'
                                                            : 'Guardar estructura'
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Extracción -->
                                    <div
                                        v-else-if="
                                            activeSourceTab ===
                                            'extraction'
                                        "
                                    >
                                        <div
                                            class="rounded-xl border border-cyan-200 bg-cyan-50/50 p-4 text-sm leading-6 text-slate-600 dark:border-cyan-950 dark:bg-cyan-950/10 dark:text-slate-300"
                                        >
                                            <strong
                                                class="text-slate-900 dark:text-white"
                                            >
                                                LAUDA no se conecta a tu servidor.
                                            </strong>
                                            Esta herramienta genera una consulta
                                            SELECT de solo lectura para que la
                                            ejecutes localmente en SQL Server y
                                            exportes el resultado.
                                        </div>

                                        <div
                                            class="mt-5 grid gap-4 md:grid-cols-2"
                                        >
                                            <label class="block">
                                                <span
                                                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                                >
                                                    Esquema
                                                </span>

                                                <input
                                                    v-model="extractionForm.schema_name"
                                                    type="text"
                                                    maxlength="128"
                                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                                    placeholder="dbo"
                                                />
                                            </label>

                                            <label class="block">
                                                <span
                                                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                                >
                                                    Tabla
                                                </span>

                                                <input
                                                    v-model="extractionForm.table_name"
                                                    type="text"
                                                    maxlength="128"
                                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-background px-3 py-2.5 text-sm dark:border-slate-800"
                                                    :placeholder="
                                                        selectedSource.source_object_name
                                                    "
                                                />
                                            </label>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button
                                                type="button"
                                                class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-cyan-600"
                                                :disabled="
                                                    sourceWorkspaceBusy !== null
                                                    || !selectedSource.structure_text
                                                "
                                                @click="previewSourceExtraction"
                                            >
                                                {{
                                                    sourceWorkspaceBusy === 'extraction'
                                                        ? 'Generando...'
                                                        : 'Generar consulta'
                                                }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="extractionPreview"
                                            class="mt-5 space-y-4 border-t border-slate-200/70 pt-5 dark:border-slate-800"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-3"
                                            >
                                                <p
                                                    class="text-sm font-black text-slate-900 dark:text-white"
                                                >
                                                    SELECT generado ·
                                                    {{ extractionPreview.field_count }}
                                                    campos
                                                </p>

                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold dark:border-slate-800"
                                                    @click="copyExtractionQuery"
                                                >
                                                    Copiar consulta
                                                </button>
                                            </div>

                                            <textarea
                                                :value="extractionPreview.query"
                                                rows="10"
                                                readonly
                                                class="w-full rounded-xl border border-slate-200 bg-slate-950 px-4 py-3 font-mono text-xs leading-5 text-slate-100 dark:border-slate-800"
                                            />

                                            <div
                                                v-if="selectedExtractionExport"
                                                class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800"
                                            >
                                                <p
                                                    class="text-xs font-black text-slate-900 dark:text-white"
                                                >
                                                    Exportar como
                                                    {{ selectedExtractionExport.label }}
                                                </p>

                                                <ol
                                                    class="mt-3 list-decimal space-y-2 pl-5 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                >
                                                    <li
                                                        v-for="instruction in selectedExtractionExport.instructions"
                                                        :key="instruction"
                                                    >
                                                        {{ instruction }}
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Archivo -->
                                    <div
                                        v-else-if="
                                            activeSourceTab ===
                                            'file'
                                        "
                                    >
                                        <div
                                            class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-blue-950/10 dark:text-slate-300"
                                        >
                                            Sube el resultado completo en CSV o
                                            XLSX. La estructura previa no es
                                            obligatoria. Tamaño máximo: 32 MB.
                                        </div>

                                        <div
                                            v-if="selectedSource.data_file"
                                            class="mt-5 rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                        >
                                            <p
                                                class="text-sm font-black text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    selectedSource.data_file
                                                        .original_filename
                                                }}
                                            </p>

                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    selectedSource.data_file
                                                        .source_format
                                                        .toUpperCase()
                                                }}
                                                ·
                                                {{
                                                    sourceFileSize(
                                                        selectedSource.data_file
                                                            .source_size_bytes,
                                                    )
                                                }}
                                                ·
                                                {{
                                                    selectedSource.data_file
                                                        .source_row_count
                                                }}
                                                filas detectadas
                                            </p>
                                        </div>

                                        <div
                                            v-if="canManageSources"
                                            class="mt-5"
                                        >
                                            <input
                                                type="file"
                                                accept=".csv,.xlsx"
                                                class="block w-full rounded-xl border border-slate-200 bg-background px-3 py-3 text-sm dark:border-slate-800"
                                                @change="selectSourceFile"
                                            />

                                            <div
                                                class="mt-4 flex justify-end"
                                            >
                                                <button
                                                    type="button"
                                                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                                    :disabled="
                                                        sourceWorkspaceBusy !== null
                                                        || !sourceFile
                                                    "
                                                    @click="uploadSourceData"
                                                >
                                                    {{
                                                        sourceWorkspaceBusy === 'upload'
                                                            ? 'Subiendo...'
                                                            : selectedSource.data_file
                                                              ? 'Reemplazar archivo'
                                                              : 'Subir archivo'
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Resultado -->
                                    <div
                                        v-else-if="
                                            activeSourceTab ===
                                            'result'
                                        "
                                    >
                                        <div
                                            class="grid gap-3 sm:grid-cols-3"
                                        >
                                            <div
                                                class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800"
                                            >
                                                <p
                                                    class="text-[10px] font-black tracking-wide text-slate-400 uppercase"
                                                >
                                                    Fuente
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        sourceStatusLabel(
                                                            selectedSource.status,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800"
                                            >
                                                <p
                                                    class="text-[10px] font-black tracking-wide text-slate-400 uppercase"
                                                >
                                                    Estructura
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        sourceStructureStatusLabel(
                                                            selectedSource.structure_status,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800"
                                            >
                                                <p
                                                    class="text-[10px] font-black tracking-wide text-slate-400 uppercase"
                                                >
                                                    Datos
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        sourceDataStatusLabel(
                                                            selectedSource.data_status,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-4 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 text-sm leading-6 dark:border-slate-800 dark:bg-slate-900/20"
                                        >
                                            <p
                                                class="font-black text-slate-900 dark:text-white"
                                            >
                                                Readiness derivado
                                            </p>

                                            <p
                                                class="mt-2 text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    source_workspace
                                                        .readiness
                                                        .complete_source_count
                                                }}
                                                de
                                                {{
                                                    source_workspace
                                                        .readiness
                                                        .source_count
                                                }}
                                                fuentes activas tienen un archivo
                                                CSV/XLSX vigente recibido.
                                            </p>

                                            <p
                                                class="mt-2 font-bold"
                                                :class="
                                                    source_workspace
                                                        .readiness
                                                        .inputs_validated
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-amber-600 dark:text-amber-400'
                                                "
                                            >
                                                {{
                                                    source_workspace
                                                        .readiness
                                                        .inputs_validated
                                                        ? 'Fuentes listas para continuar.'
                                                        : 'Todavía faltan entregas por completar.'
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="selectedSource.failure_message"
                                            class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-700 dark:border-red-950 dark:bg-red-950/20 dark:text-red-300"
                                        >
                                            {{
                                                selectedSource.failure_message
                                            }}
                                        </div>

                                        <p
                                            class="mt-4 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                                        >
                                            El estado técnico es calculado por
                                            LAUDA. No se puede marcar manualmente
                                            una fuente como validada o lista.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <p
                            class="mt-6 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            Tu empresa administra la información y sus entregas.
                            LAUDA conserva el profiling técnico, normalización,
                            relaciones, modelo canónico y procesamiento posterior.
                        </p>
                    </section>

                    <!-- P7_DATA_PREPARATION_STATUS -->
                    <section
                        v-if="data_preparation"
                        class="rounded-[2rem] border border-indigo-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-indigo-950 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-indigo-600 uppercase dark:text-indigo-400"
                                >
                                    Preparación de datos
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Estado de tus datos para BI
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Resumen operativo del último procesamiento
                                    realizado para esta solicitud.
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-[10px] font-black tracking-wide text-indigo-700 uppercase dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-300"
                            >
                                {{ data_preparation.stage_label }}
                            </span>
                        </div>

                        <div
                            class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Dominios
                                </p>
                                <p
                                    class="mt-1 text-2xl font-black text-slate-950 dark:text-white"
                                >
                                    {{ data_preparation.batch.domain_count }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Filas en staging
                                </p>
                                <p
                                    class="mt-1 text-2xl font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        data_preparation
                                            .batch
                                            .staged_row_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Filas perfiladas
                                </p>
                                <p
                                    class="mt-1 text-2xl font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        data_preparation
                                            .processing
                                            ?.profiled_row_count
                                        ?? 0
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Filas normalizadas
                                </p>
                                <p
                                    class="mt-1 text-2xl font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        data_preparation
                                            .processing
                                            ?.normalized_row_count
                                        ?? 0
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="data_preparation.processing"
                            class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                            >
                                <p class="text-xs text-slate-500">
                                    Incidencias
                                </p>
                                <p class="mt-1 text-lg font-black">
                                    {{
                                        data_preparation
                                            .processing
                                            .issue_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                            >
                                <p class="text-xs text-slate-500">
                                    Bloqueantes
                                </p>
                                <p class="mt-1 text-lg font-black">
                                    {{
                                        data_preparation
                                            .processing
                                            .blocking_issue_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                            >
                                <p class="text-xs text-slate-500">
                                    Advertencias
                                </p>
                                <p class="mt-1 text-lg font-black">
                                    {{
                                        data_preparation
                                            .processing
                                            .warning_issue_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                            >
                                <p class="text-xs text-slate-500">
                                    Informativas
                                </p>
                                <p class="mt-1 text-lg font-black">
                                    {{
                                        data_preparation
                                            .processing
                                            .informational_issue_count
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- P8_DOMAIN_PREPARATION_STATUS -->
                        <div
                            v-if="
                                data_preparation.domains.length > 0
                            "
                            class="mt-6 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <div
                                class="flex flex-wrap items-end justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Estado por dominio
                                    </p>

                                    <h3
                                        class="mt-1 text-base font-black text-slate-950 dark:text-white"
                                    >
                                        Preparación de cada fuente
                                    </h3>
                                </div>

                                <p
                                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        data_preparation
                                            .domain_summary
                                            .normalized
                                    }}/{{
                                        data_preparation
                                            .domain_summary
                                            .total
                                    }}
                                    dominios normalizados
                                </p>
                            </div>

                            <div
                                class="mt-4 grid gap-3 md:grid-cols-2"
                            >
                                <div
                                    v-for="
                                        domain in data_preparation.domains
                                    "
                                    :key="domain.key"
                                    class="rounded-2xl border border-slate-200/70 bg-slate-50/40 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-black text-slate-950 dark:text-white"
                                            >
                                                {{ domain.label }}
                                            </p>

                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    domain.row_count
                                                }}
                                                filas ·
                                                {{
                                                    domain.field_count
                                                }}
                                                campos
                                            </p>
                                        </div>

                                        <span
                                            class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase"
                                        >
                                            {{
                                                domain.preparation_label
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-4 grid grid-cols-2 gap-2 text-xs"
                                    >
                                        <div
                                            class="rounded-xl border border-slate-200/70 bg-white p-3 dark:border-slate-800 dark:bg-slate-950/50"
                                        >
                                            <p
                                                class="text-slate-500 dark:text-slate-400"
                                            >
                                                Normalizadas
                                            </p>

                                            <p
                                                class="mt-1 font-black text-slate-950 dark:text-white"
                                            >
                                                {{
                                                    domain.normalized_row_count
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-slate-200/70 bg-white p-3 dark:border-slate-800 dark:bg-slate-950/50"
                                        >
                                            <p
                                                class="text-slate-500 dark:text-slate-400"
                                            >
                                                Calidad
                                            </p>

                                            <p
                                                class="mt-1 font-black text-slate-950 dark:text-white"
                                            >
                                                {{
                                                    domain.quality_label
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <p
                                        v-if="domain.issue_count > 0"
                                        class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            domain.blocking_issue_count
                                        }}
                                        bloqueantes ·
                                        {{
                                            domain.warning_issue_count
                                        }}
                                        advertencias ·
                                        {{
                                            domain.informational_issue_count
                                        }}
                                        informativas
                                    </p>

                                    <p
                                        v-else
                                        class="mt-3 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                                    >
                                        Sin incidencias registradas.
                                    </p>

                                    <p
                                        v-if="
                                            domain
                                                .duplicate_identity_count > 0
                                        "
                                        class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                    >
                                        {{
                                            domain
                                                .duplicate_identity_count
                                        }}
                                        identidades duplicadas detectadas.
                                    </p>


                                    <!-- P10_QUALITY_GUIDANCE -->
                                    <div
                                        v-if="domain.issues.length > 0"
                                        class="mt-4 space-y-2"
                                    >
                                        <div
                                            v-for="
                                                issue in domain.issues
                                            "
                                            :key="
                                                `${domain.key}-${issue.code}-${issue.severity}`
                                            "
                                            class="rounded-xl border border-slate-200/70 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/40"
                                        >
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <p
                                                    class="text-xs font-black text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        issue.label
                                                    }}
                                                </p>

                                                <span
                                                    class="rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                                >
                                                    {{
                                                        issue.severity_label
                                                    }}
                                                </span>

                                                <span
                                                    class="text-[10px] font-semibold text-slate-400"
                                                >
                                                    {{
                                                        issue.count
                                                    }}
                                                    incidencia<span
                                                        v-if="
                                                            issue.count !== 1
                                                        "
                                                    >s</span>
                                                </span>
                                            </div>

                                            <p
                                                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    issue.guidance
                                                }}
                                            </p>
                                        </div>
                                    </div>

<!-- P9_FIELD_QUALITY_STATUS -->
                                    <details
                                        v-if="domain.fields.length > 0"
                                        class="mt-4 rounded-xl border border-slate-200/70 bg-white/70 dark:border-slate-800 dark:bg-slate-950/40"
                                    >
                                        <summary
                                            class="cursor-pointer list-none px-4 py-3 text-xs font-black text-slate-700 dark:text-slate-200"
                                        >
                                            Campos ·
                                            {{
                                                domain.field_summary.total
                                            }}

                                            <span
                                                v-if="
                                                    domain
                                                        .field_summary
                                                        .blocking > 0
                                                "
                                                class="ml-2 font-semibold text-red-600 dark:text-red-400"
                                            >
                                                {{
                                                    domain
                                                        .field_summary
                                                        .blocking
                                                }}
                                                requieren corrección
                                            </span>

                                            <span
                                                v-else-if="
                                                    domain
                                                        .field_summary
                                                        .warning > 0
                                                "
                                                class="ml-2 font-semibold text-amber-600 dark:text-amber-400"
                                            >
                                                {{
                                                    domain
                                                        .field_summary
                                                        .warning
                                                }}
                                                con advertencias
                                            </span>
                                        </summary>

                                        <div
                                            class="space-y-2 border-t border-slate-200/70 p-3 dark:border-slate-800"
                                        >
                                            <div
                                                v-for="
                                                    field in domain.fields
                                                "
                                                :key="
                                                    `${domain.key}-${field.key}`
                                                "
                                                class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                                            >
                                                <div
                                                    class="flex flex-wrap items-start justify-between gap-3"
                                                >
                                                    <div
                                                        class="min-w-0"
                                                    >
                                                        <div
                                                            class="flex flex-wrap items-center gap-2"
                                                        >
                                                            <code
                                                                class="text-xs font-black text-slate-950 dark:text-white"
                                                            >
                                                                {{
                                                                    field.key
                                                                }}
                                                            </code>

                                                            <span
                                                                v-if="
                                                                    field.required
                                                                "
                                                                class="rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                                            >
                                                                Requerido
                                                            </span>

                                                            <span
                                                                class="text-[10px] font-semibold text-slate-400"
                                                            >
                                                                {{
                                                                    field.data_type
                                                                }}
                                                            </span>
                                                        </div>

                                                        <p
                                                            v-if="
                                                                field.description
                                                            "
                                                            class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                field.description
                                                            }}
                                                        </p>
                                                    </div>

                                                    <span
                                                        class="shrink-0 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase"
                                                    >
                                                        {{
                                                            field.quality_label
                                                        }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                                >
                                                    <div
                                                        class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-slate-900/40"
                                                    >
                                                        <p
                                                            class="text-slate-400"
                                                        >
                                                            Completitud
                                                        </p>

                                                        <p
                                                            class="mt-1 font-black"
                                                        >
                                                            {{
                                                                field.completeness_percent
                                                            }}%
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-slate-900/40"
                                                    >
                                                        <p
                                                            class="text-slate-400"
                                                        >
                                                            Faltantes
                                                        </p>

                                                        <p
                                                            class="mt-1 font-black"
                                                        >
                                                            {{
                                                                field.missing_count
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-slate-900/40"
                                                    >
                                                        <p
                                                            class="text-slate-400"
                                                        >
                                                            Inválidos
                                                        </p>

                                                        <p
                                                            class="mt-1 font-black"
                                                        >
                                                            {{
                                                                field.invalid_count
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-slate-900/40"
                                                    >
                                                        <p
                                                            class="text-slate-400"
                                                        >
                                                            Distintos
                                                        </p>

                                                        <p
                                                            class="mt-1 font-black"
                                                        >
                                                            {{
                                                                field.distinct_count
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <p
                                                    v-if="
                                                        field.issue_count > 0
                                                    "
                                                    class="mt-3 text-[11px] leading-5 text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        field.blocking_issue_count
                                                    }}
                                                    bloqueantes ·
                                                    {{
                                                        field.warning_issue_count
                                                    }}
                                                    advertencias ·
                                                    {{
                                                        field.informational_issue_count
                                                    }}
                                                    informativas
                                                </p>

                                                <div
                                                    v-if="
                                                        field.issues.length > 0
                                                    "
                                                    class="mt-3 space-y-2"
                                                >
                                                    <div
                                                        v-for="
                                                            issue in field.issues
                                                        "
                                                        :key="
                                                            `${domain.key}-${field.key}-${issue.code}-${issue.severity}`
                                                        "
                                                        class="rounded-lg border border-slate-200/70 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/40"
                                                    >
                                                        <div
                                                            class="flex flex-wrap items-center gap-2"
                                                        >
                                                            <p
                                                                class="text-[11px] font-black text-slate-800 dark:text-slate-100"
                                                            >
                                                                {{
                                                                    issue.label
                                                                }}
                                                            </p>

                                                            <span
                                                                class="rounded-full border px-2 py-0.5 text-[8px] font-black uppercase"
                                                            >
                                                                {{
                                                                    issue.severity_label
                                                                }}
                                                            </span>

                                                            <span
                                                                class="text-[9px] font-semibold text-slate-400"
                                                            >
                                                                {{
                                                                    issue.count
                                                                }}
                                                                incidencia<span
                                                                    v-if="
                                                                        issue.count !== 1
                                                                    "
                                                                >s</span>
                                                            </span>
                                                        </div>

                                                        <p
                                                            class="mt-1 text-[11px] leading-5 text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                issue.guidance
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </details>

                                </div>
                            </div>
                        </div>

                        <p
                            class="mt-5 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            Esta vista muestra únicamente estado y conteos de
                            preparación. No expone registros de origen, datos
                            normalizados ni modifica el estado de la solicitud.
                        </p>
                    </section>

                    <!-- P13_USABLE_DATASET_STATUS -->
                    <section
                        v-if="implementation_request.id"
                        class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Dataset preparado
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Dataset utilizable actual
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Esta referencia identifica la última versión
                                    normalizada que LAUDA considera íntegra y
                                    utilizable. Puede ser anterior al último intento
                                    si una carga más reciente aún está en proceso o
                                    requiere correcciones.
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-full border px-3 py-1.5 text-[10px] font-black tracking-wide uppercase"
                            >
                                {{
                                    usable_dataset.available
                                        ? 'Disponible'
                                        : 'No disponible'
                                }}
                            </span>
                        </div>

                        <div
                            v-if="
                                usable_dataset.available
                                && usable_dataset.dataset
                            "
                            class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Ejecución
                                </p>
                                <p
                                    class="mt-1 text-lg font-black text-slate-950 dark:text-white"
                                >
                                    Run #{{
                                        usable_dataset.dataset
                                            .processing_run_id
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Batch
                                </p>
                                <p
                                    class="mt-1 text-lg font-black text-slate-950 dark:text-white"
                                >
                                    #{{
                                        usable_dataset.dataset
                                            .intake_batch_id
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Filas normalizadas
                                </p>
                                <p
                                    class="mt-1 text-lg font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        usable_dataset.dataset
                                            .normalized_row_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Completado
                                </p>
                                <p
                                    class="mt-1 text-sm font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        processingHistoryDate(
                                            usable_dataset.dataset
                                                .completed_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-6 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-4 text-sm leading-6 text-slate-500 dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-400"
                        >
                            Todavía no existe una ejecución normalizada completada
                            e íntegra para esta solicitud. El estado del último
                            intento se mantiene de forma independiente en
                            “Preparación de datos”.
                        </div>

                        <p
                            class="mt-5 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            Esta vista muestra únicamente la identidad técnica y
                            conteos agregados del dataset preparado. No expone
                            registros normalizados ni datos de origen.
                        </p>
                    </section>

                    <!-- P12_PROCESSING_HISTORY -->
                    <section
                        v-if="processing_history.entries.length > 0"
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Trazabilidad de datos
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Historial de procesamiento
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Las versiones corregidas conservan sus batches
                                    y ejecuciones anteriores. Aquí solo se muestran
                                    estados y métricas agregadas.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full border border-slate-200 px-3 py-1.5 text-[10px] font-black dark:border-slate-800"
                                >
                                    {{
                                        processing_history.summary
                                            .total_batches
                                    }}
                                    batches
                                </span>

                                <span
                                    class="rounded-full border border-slate-200 px-3 py-1.5 text-[10px] font-black dark:border-slate-800"
                                >
                                    {{
                                        processing_history.summary
                                            .total_runs
                                    }}
                                    runs
                                </span>
                            </div>
                        </div>

                        <details
                            class="mt-6 rounded-2xl border border-slate-200/70 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/20"
                        >
                            <summary
                                class="cursor-pointer list-none px-5 py-4 text-sm font-black text-slate-800 dark:text-slate-100"
                            >
                                Ver historial de batches y ejecuciones
                            </summary>

                            <div
                                class="space-y-4 border-t border-slate-200/70 p-4 sm:p-5 dark:border-slate-800"
                            >
                                <article
                                    v-for="batch in processing_history.entries"
                                    :key="batch.batch_id"
                                    class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <p
                                                    class="text-sm font-black text-slate-950 dark:text-white"
                                                >
                                                    Batch #{{ batch.batch_id }}
                                                </p>

                                                <span
                                                    class="rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                                >
                                                    {{
                                                        batch.is_latest
                                                            ? 'Actual'
                                                            : 'Anterior'
                                                    }}
                                                </span>
                                            </div>

                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    processingHistoryStatusLabel(
                                                        batch.status,
                                                    )
                                                }}
                                                ·
                                                {{
                                                    processingHistoryDate(
                                                        batch.completed_at
                                                        ?? batch.created_at,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <span
                                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                        >
                                            {{ batch.run_count }}
                                            run<span
                                                v-if="batch.run_count !== 1"
                                            >s</span>
                                        </span>
                                    </div>

                                    <div
                                        class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                    >
                                        <div
                                            class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900/50"
                                        >
                                            <p class="text-slate-400">
                                                Dominios
                                            </p>
                                            <p class="mt-1 font-black">
                                                {{ batch.domain_count }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900/50"
                                        >
                                            <p class="text-slate-400">
                                                Filas origen
                                            </p>
                                            <p class="mt-1 font-black">
                                                {{ batch.source_row_count }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900/50"
                                        >
                                            <p class="text-slate-400">
                                                Staging
                                            </p>
                                            <p class="mt-1 font-black">
                                                {{ batch.staged_row_count }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900/50"
                                        >
                                            <p class="text-slate-400">
                                                Rechazadas
                                            </p>
                                            <p class="mt-1 font-black">
                                                {{ batch.rejected_row_count }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="batch.runs.length > 0"
                                        class="mt-4 space-y-2"
                                    >
                                        <div
                                            v-for="run in batch.runs"
                                            :key="run.run_id"
                                            class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-800"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <p
                                                    class="text-xs font-black text-slate-800 dark:text-slate-100"
                                                >
                                                    Run #{{ run.run_id }}
                                                    ·
                                                    {{
                                                        processingHistoryStatusLabel(
                                                            run.status,
                                                        )
                                                    }}
                                                </p>

                                                <p
                                                    class="text-[10px] text-slate-400"
                                                >
                                                    {{
                                                        processingHistoryDate(
                                                            run.completed_at
                                                            ?? run.created_at,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                <span>
                                                    {{
                                                        run.profiled_row_count
                                                    }}
                                                    perfiladas
                                                </span>

                                                <span>
                                                    {{
                                                        run.normalized_row_count
                                                    }}
                                                    normalizadas
                                                </span>

                                                <span>
                                                    {{ run.issue_count }}
                                                    incidencias
                                                </span>

                                                <span
                                                    v-if="
                                                        run.blocking_issue_count
                                                        > 0
                                                    "
                                                >
                                                    {{
                                                        run.blocking_issue_count
                                                    }}
                                                    bloqueantes
                                                </span>

                                                <span
                                                    v-if="
                                                        run.warning_issue_count
                                                        > 0
                                                    "
                                                >
                                                    {{
                                                        run.warning_issue_count
                                                    }}
                                                    advertencias
                                                </span>

                                                <span
                                                    v-if="
                                                        run.informational_issue_count
                                                        > 0
                                                    "
                                                >
                                                    {{
                                                        run.informational_issue_count
                                                    }}
                                                    informativas
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <p
                                        v-else
                                        class="mt-4 text-xs text-slate-400"
                                    >
                                        Este batch todavía no tiene una ejecución
                                        de procesamiento registrada.
                                    </p>
                                </article>

                                <p
                                    v-if="processing_history.summary.has_more"
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Se muestran los
                                    {{
                                        processing_history.summary
                                            .shown_batches
                                    }}
                                    batches más recientes de
                                    {{
                                        processing_history.summary
                                            .total_batches
                                    }}.
                                </p>
                            </div>
                        </details>
                    </section>

                </div>

                <!-- Columna de contexto -->
                <aside class="space-y-6 xl:sticky xl:top-6">
                    <section
                        class="rounded-[2rem] border border-blue-200/70 bg-blue-50/50 p-6 shadow-sm dark:border-blue-950 dark:bg-blue-950/10"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-blue-600 uppercase dark:text-blue-400"
                        >
                            Próximo paso
                        </p>

                        <!-- Solicitud activa -->
                        <template
                            v-if="
                                implementation_request.status &&
                                implementation_request.status !==
                                    'cancelled'
                            "
                        >
                            <div
                                class="mt-4 flex items-start justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400"
                                    >
                                        Estado de la solicitud
                                    </p>

                                    <h2
                                        class="mt-1 text-lg font-black leading-6 text-slate-950 dark:text-white"
                                    >
                                        {{
                                            implementation_request.status_label
                                        }}
                                    </h2>
                                </div>

                                <span
                                    class="shrink-0 rounded-full border border-blue-200 bg-white px-3 py-1 text-[10px] font-black tracking-wide text-blue-700 uppercase dark:border-blue-900 dark:bg-slate-950/40 dark:text-blue-300"
                                >
                                    En proceso
                                </span>
                            </div>

                            <p
                                v-if="
                                    implementation_request.requested_at
                                "
                                class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                Solicitud enviada:
                                {{
                                    implementation_request.requested_at
                                }}
                            </p>

                            <div
                                class="mt-5 space-y-3 border-t border-blue-200/70 pt-5 dark:border-blue-950"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in requestProgress"
                                    :key="item.label"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[11px] font-black"
                                        :class="
                                            item.reached
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-slate-200 bg-white text-slate-400 dark:border-slate-800 dark:bg-slate-950'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="item.reached"
                                            class="h-4 w-4"
                                        />

                                        <span v-else>
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <span
                                        class="text-sm font-semibold"
                                        :class="
                                            item.reached
                                                ? 'text-slate-800 dark:text-slate-200'
                                                : 'text-slate-400 dark:text-slate-600'
                                        "
                                    >
                                        {{ item.label }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="
                                    implementation_request.status ===
                                    'ready_for_commercial'
                                "
                                class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs leading-5 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300"
                            >
                                La definición funcional y técnica está
                                completada. El siguiente proceso se
                                gestionará de forma separada.
                            </div>

                            <div
                                v-else
                                class="mt-5 rounded-xl border border-blue-200/70 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-slate-950/40 dark:text-slate-400"
                            >
                                La solicitud no activa servicios, no
                                inicia ejecución y no genera cargos,
                                facturas ni suscripciones.
                            </div>
                        </template>

                        <!-- Solicitud disponible -->
                        <template
                            v-else-if="
                                implementation_request.can_request
                            "
                        >
                            <h2
                                class="mt-2 text-lg font-black leading-6 text-slate-950 dark:text-white"
                            >
                                {{
                                    implementation_request.status ===
                                    'cancelled'
                                        ? 'Volver a solicitar implementación'
                                        : 'Solicitar implementación'
                                }}
                            </h2>

                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Cuando decidas avanzar, envía una solicitud
                                expresa para que LAUDA revise el alcance de
                                Datos e Inteligencia BI antes de preparar la
                                definición de implementación.
                            </p>

                            <button
                                type="button"
                                class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                :disabled="requestSubmitting"
                                @click="requestImplementation"
                            >
                                {{
                                    requestSubmitting
                                        ? 'Enviando solicitud...'
                                        : implementation_request.status ===
                                            'cancelled'
                                          ? 'Volver a solicitar implementación'
                                          : 'Solicitar implementación'
                                }}
                            </button>

                            <div
                                class="mt-4 rounded-xl border border-blue-200/70 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-slate-950/40 dark:text-slate-400"
                            >
                                La solicitud no activa el servicio ni genera
                                cargos. LAUDA revisará el alcance antes de
                                avanzar.
                            </div>
                        </template>

                        <!-- No elegible todavía -->
                        <template v-else>
                            <h2
                                class="mt-2 text-lg font-black leading-6 text-slate-950 dark:text-white"
                            >
                                Solicitud no disponible todavía
                            </h2>

                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Para solicitar esta implementación, Datos e
                                Inteligencia BI debe formar parte de un Plan
                                de Implementación presentado para tu empresa.
                            </p>
                        </template>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Alcance de esta vista
                        </p>

                        <p
                            class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Aquí puedes consultar la recomendación,
                            prioridad, fase y alcance funcional de Datos e
                            Inteligencia BI dentro de Transformación 360.
                        </p>

                        <p
                            class="mt-4 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            Esta etapa no inicia ejecución, contratación,
                            facturación, pagos ni suscripciones.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
