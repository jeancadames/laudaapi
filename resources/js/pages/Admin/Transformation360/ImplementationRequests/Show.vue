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

    standard_intake_persisted_state: {
        ingestion: StandardIntakeIngestionHttpResponse | null;
        profile: StandardIntakeProcessingHttpResponse | null;
        normalization: StandardIntakeProcessingHttpResponse | null;
    } | null;

    // D15C_INTAKE_V2_PROP
    standard_intake_v2_state: StandardIntakeV2State | null;

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
const SOURCE_NATIVE_INTAKE_MAX_BYTES = 32 * 1024 * 1024;

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

// D15C_INTAKE_V2_TYPES
type StandardIntakeV2DomainDefinition = {
    key: string;
    label: string;
    description: string;
};

// D15F_ERROR_FEEDBACK_TYPES
type StandardIntakeV2ValidationFeedback = {
    valid?: boolean;
    error_count?: number;
    warning_count?: number;
    errors?: string[];
    warnings?: string[];
};

type StandardIntakeV2SourceColumn = {
    index?: number;
    key?: string;
    header?: string | null;
};

type StandardIntakeV2SourceSheet = {
    index?: number;
    name?: string | null;
    total_row_count?: number;
    row_count?: number;
    column_count?: number;
    headers?: Array<string | null>;
    columns?: StandardIntakeV2SourceColumn[];
};

type StandardIntakeV2SourceNativeFile = {
    id: number;
    status: string;
    original_filename: string;
    source_format?: 'csv' | 'xlsx' | string;
    source_size_bytes: number;
    source_row_count: number;
    reader_configuration?: Record<string, unknown> | null;
    source_structure_snapshot?: {
        sheets?: StandardIntakeV2SourceSheet[];
    } | null;
    uploaded_at?: string | null;
};

type StandardIntakeV2DomainDelivery = {
    id?: number;
    domain_key: string;
    delivery_mode: 'uploaded' | 'no_data' | 'carry_forward' | null;
    status: 'pending' | 'validating' | 'valid' | 'invalid';
    original_filename?: string | null;
    source_format?: 'csv' | 'xlsx' | null;
    source_size_bytes?: number | null;
    source_row_count?: number;
    accepted_row_count?: number;
    carry_forward_processing_run_id?: number | null;
    carry_forward_intake_batch_id?: number | null;
    validated_at?: string | null;
    validation_feedback?: StandardIntakeV2ValidationFeedback | null;
    source_native_supported?: boolean;
    source_native?: StandardIntakeV2SourceNativeFile | null;
};

type StandardIntakeV2RelationalSummary = {
    valid?: boolean;
    error_count?: number;
    warning_count?: number;
    errors?: string[];
    warnings?: string[];
};

type StandardIntakeV2SessionState = {
    id: number;
    status: string;
    resulting_intake_batch_id?: number | null;
    resolved_manifest_sha256?: string | null;
    deliveries: StandardIntakeV2DomainDelivery[];
    all_logical_decisions_resolved?: boolean;
    relational_validation?: StandardIntakeV2RelationalSummary | null;
    started_at?: string | null;
    ready_at?: string | null;
    finalized_at?: string | null;
};

type StandardIntakeV2UsableDataset = {
    available: boolean;
    reason?: string | null;
    dataset?: {
        processing_run_id?: number;
        intake_batch_id?: number;
        normalized_row_count?: number;
    } | null;
};

type StandardIntakeV2Actions = {
    can_start_or_resume?: boolean;
    can_start_new_session?: boolean;
    can_edit_domains?: boolean;
    can_manage_sources?: boolean;
    can_resolve?: boolean;
    can_materialize?: boolean;
};

type DynamicSourceObservedSheet = {
    index: number;
    name?: string | null;
    total_row_count?: number;
    row_count?: number;
    column_count?: number;
    headers?: string[];
};

type DynamicSourceDataFile = {
    id: number;
    status: string;
    original_filename: string;
    source_format: 'csv' | 'xlsx';
    source_mime_type?: string | null;
    source_size_bytes: number;
    source_sha256: string;
    reader_configuration?: Record<string, unknown> | null;
    source_structure_snapshot?: {
        sheets?: DynamicSourceObservedSheet[];
    } | null;
    source_row_count: number;
    uploaded_at?: string | null;
};

type DynamicSourceDataUploadFormState = {
    file: File | null;
    busy: boolean;
    error: string | null;
    message: string | null;
    input_key: number;
};

type DynamicSourceAsset = {
    id: number;
    display_name: string;
    source_object_name: string;
    description?: string | null;
    origin_system?: string | null;
    structure_format?: string | null;
    structure_text?: string | null;
    delivery_format?: 'csv' | 'xlsx' | null;
    status: string;
    structure_status: string;
    data_status: string;
    structure_snapshot?: Record<string, unknown> | null;
    profiling_snapshot?: Record<string, unknown> | null;
    data_file?: DynamicSourceDataFile | null;
    sort_order: number;
    structure_analyzed_at?: string | null;
    data_received_at?: string | null;
    profiled_at?: string | null;
    failure_code?: string | null;
    failure_message?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
};

type DynamicSourceWorkspaceTab =
    | 'information'
    | 'structure'
    | 'extraction'
    | 'file'
    | 'analysis'
    | 'mapping';

type DynamicSourceStructureFormat =
    | 'field_type_list'
    | 'sql_server_ddl'
    | 'other';

type DynamicSourceStructureFormState = {
    structure_format: DynamicSourceStructureFormat;
    structure_text: string;
    busy: boolean;
    error: string | null;
};

type DynamicSourceSqlServerFormState = {
    schema_name: string;
    table_name: string;
    export_format: SqlServerExtractionExportFormat;
    busy: boolean;
    error: string | null;
    copied: boolean;
    preview: SqlServerExtractionPreview | null;
};

type DynamicSourceAssetForm = {
    display_name: string;
    source_object_name: string;
    description: string;
    origin_system: string;
    delivery_format: 'csv' | 'xlsx' | null;
};

type StandardIntakeV2State = {
    version: number;
    schema_version: number;
    domains: StandardIntakeV2DomainDefinition[];
    session: StandardIntakeV2SessionState | null;
    source_assets: DynamicSourceAsset[];
    usable_dataset: StandardIntakeV2UsableDataset;
    actions: StandardIntakeV2Actions;
};

type StandardIntakeV2HttpResponse = {
    ok: boolean;
    message?: string;
    errors?: Record<string, string[]>;
    state?: StandardIntakeV2State;
    source_asset?: DynamicSourceAsset;
    data_file?: DynamicSourceDataFile;
    resolution?: {
        valid?: boolean;
        errors?: unknown[];
        warnings?: unknown[];
    };
    ingestion?: StandardIntakeIngestionResult;
};

const standardIntakeFile = ref<File | null>(null);
const standardIntakeValidating = ref(false);
const standardIntakeHttpError = ref<string | null>(null);
const standardIntakeReport = ref<StandardIntakeHttpResponse | null>(null);

const standardIntakeIngesting = ref(false);
const standardIntakeIngestionError = ref<string | null>(null);
const standardIntakeIngestionReport =
    ref<StandardIntakeIngestionHttpResponse | null>(
        props.standard_intake_persisted_state
            ?.ingestion
        ?? null,
    );

const standardIntakeValidationUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/validate`;

const standardIntakeIngestionUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake/ingest`;

type SqlServerExtractionField = {
    name: string;
    quoted: string;
};

type SqlServerExtractionExportFormat =
    'csv'
    | 'xlsx';

type SqlServerExtractionExportGuidance = {
    label: string;
    extension: string;
    instructions: string[];
};

type SqlServerExtractionPreview = {
    source_type: 'sql_server';
    schema_name: string;
    table_name: string;
    field_count: number;
    fields: SqlServerExtractionField[];
    query: string;
    export: {
        csv: SqlServerExtractionExportGuidance;
        xlsx: SqlServerExtractionExportGuidance;
    };
};

type SqlServerExtractionFormState = {
    open: boolean;
    schema_name: string;
    table_name: string;
    structure_text: string;
    export_format: SqlServerExtractionExportFormat;
    busy: boolean;
    error: string | null;
    copied: boolean;
    preview: SqlServerExtractionPreview | null;
};

const sqlServerExtractionForms =
    ref<Record<string, SqlServerExtractionFormState>>({});

function sqlServerExtractionForm(
    domain: string,
): SqlServerExtractionFormState {
    if (!sqlServerExtractionForms.value[domain]) {
        sqlServerExtractionForms.value[domain] = {
            open: false,
            schema_name: 'dbo',
            table_name: '',
            structure_text: '',
            export_format: 'csv',
            busy: false,
            error: null,
            copied: false,
            preview: null,
        };
    }

    return sqlServerExtractionForms.value[domain];
}

function toggleSqlServerExtraction(
    domain: string,
): void {
    const form =
        sqlServerExtractionForm(
            domain,
        );

    form.open =
        !form.open;

    form.error =
        null;
}

function sqlServerExtractionInstructions(
    domain: string,
): string[] {
    const form =
        sqlServerExtractionForm(
            domain,
        );

    if (!form.preview) {
        return [];
    }

    return form.preview
        .export[
            form.export_format
        ]
        ?.instructions
        ?? [];
}

const dynamicSourceFormOpen =
    ref(false);

const dynamicSourceEditingId =
    ref<number | null>(null);

const dynamicSourceBusy =
    ref<string | null>(null);

const dynamicSourceError =
    ref<string | null>(null);

const dynamicSourceForm =
    ref<DynamicSourceAssetForm>({
        display_name: '',
        source_object_name: '',
        description: '',
        origin_system: '',
        delivery_format: null,
    });

function dynamicSourceAssets(): DynamicSourceAsset[] {
    return standardIntakeV2State.value
        ?.source_assets
        ?? [];
}

function dynamicSourceCanManage(): boolean {
    return (
        standardIntakeV2SessionId() !== null
        && standardIntakeV2State.value
            ?.actions
            ?.can_manage_sources
            === true
    );
}

function dynamicSourceResetForm(): void {
    dynamicSourceEditingId.value =
        null;

    dynamicSourceForm.value = {
        display_name: '',
        source_object_name: '',
        description: '',
        origin_system: '',
        delivery_format: null,
    };

    dynamicSourceError.value =
        null;
}

function openDynamicSourceCreateForm(): void {
    dynamicSourceResetForm();

    dynamicSourceFormOpen.value =
        true;
}

function openDynamicSourceEditForm(
    asset: DynamicSourceAsset,
): void {
    dynamicSourceEditingId.value =
        asset.id;

    dynamicSourceForm.value = {
        display_name:
            asset.display_name,

        source_object_name:
            asset.source_object_name,

        description:
            asset.description
            ?? '',

        origin_system:
            asset.origin_system
            ?? '',

        delivery_format:
            asset.delivery_format
            ?? null,
    };

    dynamicSourceError.value =
        null;

    dynamicSourceFormOpen.value =
        true;
}

function closeDynamicSourceForm(): void {
    if (dynamicSourceBusy.value !== null) {
        return;
    }

    dynamicSourceFormOpen.value =
        false;

    dynamicSourceResetForm();
}

function dynamicSourceStructureLabel(
    status: string | undefined,
): string {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        provided: 'Recibida',
        analyzed: 'Analizada',
    };

    return status
        ? labels[status] ?? status
        : 'Pendiente';
}

function dynamicSourceDataLabel(
    status: string | undefined,
): string {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        received: 'Recibido',
        analyzed: 'Analizado',
    };

    return status
        ? labels[status] ?? status
        : 'Pendiente';
}

function dynamicSourceDeliveryLabel(
    format: string | null | undefined,
): string {
    if (format === 'csv') {
        return 'CSV';
    }

    if (format === 'xlsx') {
        return 'Excel (.xlsx)';
    }

    return 'Por definir';
}

function upsertDynamicSourceAsset(
    asset: DynamicSourceAsset,
): void {
    if (!standardIntakeV2State.value) {
        return;
    }

    const current =
        standardIntakeV2State.value
            .source_assets
        ?? [];

    const existingIndex =
        current.findIndex(
            (item) =>
                item.id === asset.id,
        );

    const next =
        existingIndex === -1
            ? [
                ...current,
                asset,
            ]
            : current.map(
                (item) =>
                    item.id === asset.id
                        ? asset
                        : item,
            );

    standardIntakeV2State.value.source_assets =
        [...next].sort(
            (left, right) =>
                left.sort_order - right.sort_order
                || left.id - right.id,
        );
}

async function saveDynamicSourceAsset(): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || !dynamicSourceCanManage()
    ) {
        dynamicSourceError.value =
            'La sesión no permite gestionar fuentes en este momento.';

        return;
    }

    const displayName =
        dynamicSourceForm.value
            .display_name
            .trim();

    const sourceObjectName =
        dynamicSourceForm.value
            .source_object_name
            .trim();

    if (
        displayName === ''
        || sourceObjectName === ''
    ) {
        dynamicSourceError.value =
            'Completa el nombre de la fuente y la tabla o archivo de origen.';

        return;
    }

    const editingId =
        dynamicSourceEditingId.value;

    const url =
        editingId === null
            ? `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets`
            : `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/${editingId}`;

    dynamicSourceBusy.value =
        editingId === null
            ? 'create'
            : `update:${editingId}`;

    dynamicSourceError.value =
        null;

    try {
        const payload =
            await standardIntakeV2Request(
                url,
                {
                    method:
                        editingId === null
                            ? 'POST'
                            : 'PATCH',

                    body:
                        JSON.stringify({
                            display_name:
                                displayName,

                            source_object_name:
                                sourceObjectName,

                            description:
                                dynamicSourceForm.value
                                    .description
                                    .trim()
                                || null,

                            origin_system:
                                dynamicSourceForm.value
                                    .origin_system
                                    .trim()
                                || null,

                            delivery_format:
                                dynamicSourceForm.value
                                    .delivery_format,
                        }),
                },
            );

        if (!payload.source_asset) {
            throw new Error(
                'La respuesta no contiene la fuente guardada.',
            );
        }

        upsertDynamicSourceAsset(
            payload.source_asset,
        );

        dynamicSourceFormOpen.value =
            false;

        dynamicSourceResetForm();
    } catch (error) {
        dynamicSourceError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo guardar la fuente.';
    } finally {
        dynamicSourceBusy.value =
            null;
    }
}

async function archiveDynamicSourceAsset(
    asset: DynamicSourceAsset,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || !dynamicSourceCanManage()
    ) {
        return;
    }

    const confirmed =
        window.confirm(
            `Archivar la fuente "${asset.display_name}"?`,
        );

    if (!confirmed) {
        return;
    }

    dynamicSourceBusy.value =
        `archive:${asset.id}`;

    dynamicSourceError.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/${asset.id}/archive`,
            {
                method: 'PATCH',
            },
        );

        if (standardIntakeV2State.value) {
            standardIntakeV2State.value.source_assets =
                dynamicSourceAssets().filter(
                    (item) =>
                        item.id !== asset.id,
                );
        }

        if (
            dynamicSourceEditingId.value
            === asset.id
        ) {
            dynamicSourceFormOpen.value =
                false;

            dynamicSourceResetForm();
        }
    } catch (error) {
        dynamicSourceError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo archivar la fuente.';
    } finally {
        dynamicSourceBusy.value =
            null;
    }
}

async function moveDynamicSourceAsset(
    assetId: number,
    direction: -1 | 1,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || !dynamicSourceCanManage()
        || dynamicSourceBusy.value !== null
        || !standardIntakeV2State.value
    ) {
        return;
    }

    const current =
        [...dynamicSourceAssets()];

    const index =
        current.findIndex(
            (asset) =>
                asset.id === assetId,
        );

    const targetIndex =
        index + direction;

    if (
        index < 0
        || targetIndex < 0
        || targetIndex >= current.length
    ) {
        return;
    }

    const previous =
        [...current];

    [
        current[index],
        current[targetIndex],
    ] = [
        current[targetIndex],
        current[index],
    ];

    standardIntakeV2State.value.source_assets =
        current.map(
            (asset, sortOrder) => ({
                ...asset,
                sort_order: sortOrder,
            }),
        );

    dynamicSourceBusy.value =
        'reorder';

    dynamicSourceError.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/reorder`,
            {
                method: 'PATCH',

                body:
                    JSON.stringify({
                        source_asset_ids:
                            current.map(
                                (asset) =>
                                    asset.id,
                            ),
                    }),
            },
        );
    } catch (error) {
        standardIntakeV2State.value.source_assets =
            previous;

        dynamicSourceError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo actualizar el orden de las fuentes.';
    } finally {
        dynamicSourceBusy.value =
            null;
    }
}

const dynamicSourceSelectedId =
    ref<number | null>(null);

const dynamicSourceActiveTab =
    ref<DynamicSourceWorkspaceTab>(
        'information',
    );

const dynamicSourceStructureForms =
    ref<Record<number, DynamicSourceStructureFormState>>({});

const dynamicSourceSqlServerForms =
    ref<Record<number, DynamicSourceSqlServerFormState>>({});

const dynamicSourceDataUploadForms =
    ref<Record<number, DynamicSourceDataUploadFormState>>({});

function dynamicSourceSelectedAsset(): DynamicSourceAsset | null {
    if (dynamicSourceSelectedId.value === null) {
        return null;
    }

    return dynamicSourceAssets()
        .find(
            (asset) =>
                asset.id === dynamicSourceSelectedId.value,
        )
        ?? null;
}

function openDynamicSourceWorkspace(
    asset: DynamicSourceAsset,
    tab: DynamicSourceWorkspaceTab = 'information',
): void {
    dynamicSourceSelectedId.value =
        asset.id;

    dynamicSourceActiveTab.value =
        tab;

    dynamicSourceStructureForm(
        asset,
    );

    dynamicSourceSqlServerForm(
        asset,
    );
}

function dynamicSourceDataUploadForm(
    asset: DynamicSourceAsset,
): DynamicSourceDataUploadFormState {
    if (!dynamicSourceDataUploadForms.value[asset.id]) {
        dynamicSourceDataUploadForms.value[asset.id] = {
            file:
                null,

            busy:
                false,

            error:
                null,

            message:
                null,

            input_key:
                0,
        };
    }

    return dynamicSourceDataUploadForms.value[asset.id];
}

function selectDynamicSourceDataFile(
    asset: DynamicSourceAsset,
    event: Event,
): void {
    const input =
        event.target as HTMLInputElement;

    const form =
        dynamicSourceDataUploadForm(
            asset,
        );

    form.file =
        input.files?.[0]
        ?? null;

    form.error =
        null;

    form.message =
        null;
}

function dynamicSourceDataSheets(
    asset: DynamicSourceAsset,
): DynamicSourceObservedSheet[] {
    const sheets =
        asset.data_file
            ?.source_structure_snapshot
            ?.sheets;

    return Array.isArray(
        sheets,
    )
        ? sheets
        : [];
}

function dynamicSourceDataSheetLabel(
    sheet: DynamicSourceObservedSheet,
    fallbackIndex: number,
): string {
    const sheetName =
        typeof sheet.name === 'string'
        && sheet.name.trim() !== ''
            ? sheet.name.trim()
            : `Hoja ${
                Number.isFinite(
                    sheet.index,
                )
                    ? sheet.index + 1
                    : fallbackIndex + 1
            }`;

    const details: string[] = [];

    if (
        typeof sheet.row_count === 'number'
        && Number.isFinite(
            sheet.row_count,
        )
    ) {
        details.push(
            `${sheet.row_count.toLocaleString('es-DO')} filas`,
        );
    }

    if (
        typeof sheet.column_count === 'number'
        && Number.isFinite(
            sheet.column_count,
        )
    ) {
        details.push(
            `${sheet.column_count.toLocaleString('es-DO')} columnas`,
        );
    }

    return details.length
        ? `${sheetName} · ${details.join(' · ')}`
        : sheetName;
}

function dynamicSourceFileSizeLabel(
    bytes: number | null | undefined,
): string {
    if (
        typeof bytes !== 'number'
        || !Number.isFinite(
            bytes,
        )
        || bytes < 0
    ) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes.toLocaleString('es-DO')} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${
            (
                bytes
                / 1024
            ).toFixed(1)
        } KB`;
    }

    return `${
        (
            bytes
            / 1024
            / 1024
        ).toFixed(2)
    } MB`;
}

function dynamicSourceDateTimeLabel(
    value: string | null | undefined,
): string {
    if (!value) {
        return '—';
    }

    const date =
        new Date(
            value,
        );

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return date.toLocaleString(
        'es-DO',
    );
}

async function uploadDynamicSourceData(
    asset: DynamicSourceAsset,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    const form =
        dynamicSourceDataUploadForm(
            asset,
        );

    form.error =
        null;

    form.message =
        null;

    if (
        sessionId === null
        || !dynamicSourceCanManage()
    ) {
        form.error =
            'La sesión no permite cargar archivos en este momento.';

        return;
    }

    if (!form.file) {
        form.error =
            'Selecciona un archivo CSV o XLSX.';

        return;
    }

    const lowerName =
        form.file.name
            .toLowerCase();

    if (
        !lowerName.endsWith(
            '.csv',
        )
        && !lowerName.endsWith(
            '.xlsx',
        )
    ) {
        form.error =
            'Solo se admiten archivos CSV o XLSX.';

        return;
    }

    if (
        form.file.size
        > 32 * 1024 * 1024
    ) {
        form.error =
            'El archivo excede el máximo permitido de 32 MB.';

        return;
    }

    const body =
        new FormData();

    body.append(
        'file',
        form.file,
    );

    form.busy =
        true;

    try {
        const payload =
            await standardIntakeV2Request(
                `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/${asset.id}/data-file`,
                {
                    method:
                        'POST',

                    body,
                },
            );

        if (
            !payload.source_asset
            || !payload.data_file
        ) {
            throw new Error(
                'La respuesta no contiene el archivo procesado.',
            );
        }

        const updatedAsset: DynamicSourceAsset = {
            ...payload.source_asset,
            data_file:
                payload.data_file,
        };

        upsertDynamicSourceAsset(
            updatedAsset,
        );

        dynamicSourceSelectedId.value =
            updatedAsset.id;

        form.file =
            null;

        form.input_key +=
            1;

        form.message =
            payload.message
            ?? 'Archivo recibido correctamente.';
    } catch (error) {
        form.error =
            error instanceof Error
                ? error.message
                : 'No se pudo cargar el archivo de la fuente.';
    } finally {
        form.busy =
            false;
    }
}

function dynamicSourceStructureForm(
    asset: DynamicSourceAsset,
): DynamicSourceStructureFormState {
    if (!dynamicSourceStructureForms.value[asset.id]) {
        const rawStructureFormat =
            asset.structure_format;

        const existingFormat: DynamicSourceStructureFormat =
            rawStructureFormat === 'field_type_list'
            || rawStructureFormat === 'sql_server_ddl'
            || rawStructureFormat === 'other'
                ? rawStructureFormat
                : 'field_type_list';

        dynamicSourceStructureForms.value[asset.id] = {
            structure_format:
                existingFormat,

            structure_text:
                asset.structure_text
                ?? '',

            busy:
                false,

            error:
                null,
        };
    }

    return dynamicSourceStructureForms.value[asset.id];
}

function dynamicSourceStructureFormatLabel(
    format: string | null | undefined,
): string {
    const labels: Record<string, string> = {
        field_type_list:
            'Lista de campos y tipos',

        sql_server_ddl:
            'CREATE TABLE SQL Server',

        other:
            'Otra estructura',
    };

    return format
        ? labels[format] ?? format
        : 'Sin formato';
}

async function saveDynamicSourceStructure(
    asset: DynamicSourceAsset,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    const form =
        dynamicSourceStructureForm(
            asset,
        );

    if (
        sessionId === null
        || !dynamicSourceCanManage()
    ) {
        form.error =
            'La sesión no permite modificar la estructura en este momento.';

        return;
    }

    if (form.structure_text.trim() === '') {
        form.error =
            'Indica la estructura de la tabla o archivo.';

        return;
    }

    form.busy =
        true;

    form.error =
        null;

    try {
        const payload =
            await standardIntakeV2Request(
                `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/${asset.id}/structure`,
                {
                    method:
                        'PATCH',

                    body:
                        JSON.stringify({
                            structure_format:
                                form.structure_format,

                            structure_text:
                                form.structure_text,
                        }),
                },
            );

        if (!payload.source_asset) {
            throw new Error(
                'La respuesta no contiene la fuente actualizada.',
            );
        }

        upsertDynamicSourceAsset(
            payload.source_asset,
        );

        form.structure_format =
            (
                payload.source_asset
                    .structure_format
                ?? form.structure_format
            ) as DynamicSourceStructureFormat;

        form.structure_text =
            payload.source_asset
                .structure_text
            ?? form.structure_text;

        dynamicSourceSelectedId.value =
            payload.source_asset.id;
    } catch (error) {
        form.error =
            error instanceof Error
                ? error.message
                : 'No se pudo guardar la estructura.';
    } finally {
        form.busy =
            false;
    }
}

function dynamicSourceSqlServerForm(
    asset: DynamicSourceAsset,
): DynamicSourceSqlServerFormState {
    if (!dynamicSourceSqlServerForms.value[asset.id]) {
        dynamicSourceSqlServerForms.value[asset.id] = {
            schema_name:
                'dbo',

            table_name:
                asset.source_object_name,

            export_format:
                asset.delivery_format === 'xlsx'
                    ? 'xlsx'
                    : 'csv',

            busy:
                false,

            error:
                null,

            copied:
                false,

            preview:
                null,
        };
    }

    return dynamicSourceSqlServerForms.value[asset.id];
}

function dynamicSourceSqlServerInstructions(
    asset: DynamicSourceAsset,
): string[] {
    const form =
        dynamicSourceSqlServerForm(
            asset,
        );

    if (!form.preview) {
        return [];
    }

    return form.preview
        .export[
            form.export_format
        ]
        ?.instructions
        ?? [];
}

async function generateDynamicSourceSqlServerPreview(
    asset: DynamicSourceAsset,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    const structureForm =
        dynamicSourceStructureForm(
            asset,
        );

    const sqlForm =
        dynamicSourceSqlServerForm(
            asset,
        );

    sqlForm.error =
        null;

    sqlForm.copied =
        false;

    if (sessionId === null) {
        sqlForm.error =
            'No hay una sesión activa.';

        return;
    }

    if (
        !asset.structure_text
        || asset.structure_status === 'pending'
    ) {
        sqlForm.error =
            'Guarda primero la estructura de esta fuente.';

        return;
    }

    if (
        structureForm.structure_text
            .trim()
        !== (
            asset.structure_text
            ?? ''
        ).trim()
    ) {
        sqlForm.error =
            'Hay cambios de estructura sin guardar. Guarda la estructura antes de generar la consulta.';

        return;
    }

    if (
        sqlForm.schema_name.trim() === ''
    ) {
        sqlForm.error =
            'Indica el esquema de SQL Server.';

        return;
    }

    sqlForm.busy =
        true;

    try {
        const payload =
            await standardIntakeV2Request(
                `${standardIntakeV2BaseUrl}/sessions/${sessionId}/source-assets/${asset.id}/sql-server-extraction/preview`,
                {
                    method:
                        'POST',

                    body:
                        JSON.stringify({
                            schema_name:
                                sqlForm.schema_name
                                    .trim(),

                            table_name:
                                sqlForm.table_name
                                    .trim()
                                || null,
                        }),
                },
            ) as StandardIntakeV2HttpResponse & {
                preview?: SqlServerExtractionPreview;
            };

        if (!payload.preview) {
            throw new Error(
                'No se recibió la consulta de extracción.',
            );
        }

        sqlForm.preview =
            payload.preview;
    } catch (error) {
        sqlForm.error =
            error instanceof Error
                ? error.message
                : 'No se pudo preparar la consulta SQL Server.';
    } finally {
        sqlForm.busy =
            false;
    }
}

async function copyDynamicSourceSqlServerQuery(
    asset: DynamicSourceAsset,
): Promise<void> {
    const form =
        dynamicSourceSqlServerForm(
            asset,
        );

    const query =
        form.preview
            ?.query
            ?.trim()
        ?? '';

    if (!query) {
        return;
    }

    form.error =
        null;

    try {
        await navigator.clipboard.writeText(
            query,
        );

        form.copied =
            true;

        window.setTimeout(
            () => {
                form.copied =
                    false;
            },
            1600,
        );
    } catch {
        form.error =
            'No se pudo copiar automáticamente. Selecciona la consulta y cópiala manualmente.';
    }
}

// D15C_INTAKE_V2_LOGIC
const standardIntakeV2State =
    ref<StandardIntakeV2State | null>(
        props.standard_intake_v2_state
        ?? null,
    );

const standardIntakeV2Busy =
    ref<string | null>(null);

const standardIntakeV2Error =
    ref<string | null>(null);

const standardIntakeV2CanonicalOpen =
    ref<boolean>(
        standardIntakeV2State.value?.session == null,
    );

const standardIntakeV2Files =
    ref<Record<string, File | null>>({});


// D15F_ERROR_FEEDBACK_LOGIC
const standardIntakeV2DomainErrors =
    ref<Record<string, string[]>>({});

function standardIntakeV2ClearDomainError(
    domain: string,
): void {
    const next = {
        ...standardIntakeV2DomainErrors.value,
    };

    delete next[domain];

    standardIntakeV2DomainErrors.value =
        next;
}

function standardIntakeV2SetDomainMessage(
    domain: string,
    message: string,
): void {
    const normalized =
        message.trim();

    if (!normalized) {
        standardIntakeV2ClearDomainError(
            domain,
        );

        return;
    }

    standardIntakeV2DomainErrors.value = {
        ...standardIntakeV2DomainErrors.value,
        [domain]: [
            normalized,
        ],
    };
}

function standardIntakeV2SetDomainError(
    domain: string,
    error: unknown,
    fallback: string,
): void {
    standardIntakeV2SetDomainMessage(
        domain,
        error instanceof Error
            ? error.message
            : fallback,
    );
}

function standardIntakeV2DomainErrorsFor(
    domain: string,
): string[] {
    const transient =
        standardIntakeV2DomainErrors.value[
            domain
        ]
        ?? [];

    const persisted =
        standardIntakeV2Delivery(
            domain,
        )
            ?.validation_feedback
            ?.errors
        ?? [];

    return Array.from(
        new Set([
            ...transient,
            ...persisted,
        ]),
    ).filter(
        (message) =>
            typeof message === 'string'
            && message.trim() !== '',
    );
}

function standardIntakeV2DomainWarningsFor(
    domain: string,
): string[] {
    return (
        standardIntakeV2Delivery(
            domain,
        )
            ?.validation_feedback
            ?.warnings
        ?? []
    ).filter(
        (message) =>
            typeof message === 'string'
            && message.trim() !== '',
    );
}

function standardIntakeV2RelationErrors(): string[] {
    return (
        standardIntakeV2State.value
            ?.session
            ?.relational_validation
            ?.errors
        ?? []
    ).filter(
        (message) =>
            typeof message === 'string'
            && message.trim() !== '',
    );
}

function standardIntakeV2RelationWarnings(): string[] {
    return (
        standardIntakeV2State.value
            ?.session
            ?.relational_validation
            ?.warnings
        ?? []
    ).filter(
        (message) =>
            typeof message === 'string'
            && message.trim() !== '',
    );
}

const standardIntakeV2BaseUrl =
    `/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake-v2`;

function standardIntakeV2SessionId(): number | null {
    const id =
        standardIntakeV2State.value
            ?.session
            ?.id;

    return typeof id === 'number'
        && Number.isInteger(id)
        && id > 0
        ? id
        : null;
}

function standardIntakeV2DomainCount(): number {
    return standardIntakeV2State.value
        ?.domains
        ?.length
        ?? 0;
}

function standardIntakeV2ResolvedCount(): number {
    return standardIntakeV2State.value
        ?.session
        ?.deliveries
        ?.filter(
            (delivery) =>
                delivery.status === 'valid',
        )
        .length
        ?? 0;
}

function standardIntakeV2Delivery(
    domain: string,
): StandardIntakeV2DomainDelivery | null {
    return standardIntakeV2State.value
        ?.session
        ?.deliveries
        ?.find(
            (delivery) =>
                delivery.domain_key === domain,
        )
        ?? null;
}

function standardIntakeV2SelectedFile(
    domain: string,
): File | null {
    return standardIntakeV2Files.value[domain]
        ?? null;
}

function standardIntakeV2CanEditDomains(): boolean {
    return standardIntakeV2State.value
        ?.actions
        ?.can_edit_domains
        === true;
}

function standardIntakeV2CanCarryForward(): boolean {
    return (
        standardIntakeV2CanEditDomains()
        && standardIntakeV2State.value
            ?.usable_dataset
            ?.available
            === true
    );
}

function standardIntakeV2ModeLabel(
    mode: StandardIntakeV2DomainDelivery['delivery_mode'],
): string {
    switch (mode) {
        case 'uploaded':
            return 'Archivo cargado';

        case 'no_data':
            return 'Sin datos';

        case 'carry_forward':
            return 'Datos preparados';

        default:
            return 'Pendiente';
    }
}

function standardIntakeV2StatusLabel(
    status: StandardIntakeV2DomainDelivery['status'] | undefined,
): string {
    switch (status) {
        case 'valid':
            return 'Válido';

        case 'invalid':
            return 'Requiere corrección';

        case 'validating':
            return 'Validando';

        default:
            return 'Pendiente';
    }
}

function standardIntakeV2SourceNative(
    domain: string,
): StandardIntakeV2SourceNativeFile | null {
    return standardIntakeV2Delivery(
        domain,
    )?.source_native ?? null;
}

function standardIntakeV2SourceSheets(
    domain: string,
): StandardIntakeV2SourceSheet[] {
    return (
        standardIntakeV2SourceNative(
            domain,
        )?.source_structure_snapshot
            ?.sheets
        ?? []
    );
}

function standardIntakeV2SourceStatusLabel(
    status: string | undefined,
): string {
    const labels: Record<string, string> = {
        uploaded: 'Archivo recibido',
        profiling: 'Analizando',
        profiled: 'Perfilado',
        mapping: 'Mapeo en curso',
        ready: 'Mapeo listo',
        transforming: 'Transformando',
        transformed: 'Transformado',
        failed: 'Con error',
    };

    return status
        ? labels[status] ?? status
        : 'Archivo recibido';
}

function standardIntakeV2CardStatusLabel(
    domain: string,
): string {
    const source =
        standardIntakeV2SourceNative(
            domain,
        );

    if (source) {
        return standardIntakeV2SourceStatusLabel(
            source.status,
        );
    }

    return standardIntakeV2StatusLabel(
        standardIntakeV2Delivery(
            domain,
        )?.status,
    );
}

function standardIntakeV2SourceFormatLabel(
    format: string | undefined,
): string {
    if (format === 'csv') {
        return 'CSV';
    }

    if (format === 'xlsx') {
        return 'Excel XLSX';
    }

    return format || '—';
}

function standardIntakeV2SourceSheetLabel(
    domain: string,
    sheet: StandardIntakeV2SourceSheet,
    index: number,
): string {
    if (
        standardIntakeV2SourceNative(
            domain,
        )?.source_format === 'csv'
    ) {
        return 'CSV';
    }

    const name =
        typeof sheet.name === 'string'
            ? sheet.name.trim()
            : '';

    return name || `Hoja ${index + 1}`;
}

function standardIntakeV2SourceSheetRows(
    sheet: StandardIntakeV2SourceSheet,
): number {
    if (
        typeof sheet.row_count === 'number'
        && sheet.row_count >= 0
    ) {
        return sheet.row_count;
    }

    if (
        typeof sheet.total_row_count === 'number'
        && sheet.total_row_count > 0
    ) {
        return Math.max(
            0,
            sheet.total_row_count - 1,
        );
    }

    return 0;
}

function standardIntakeV2SourceSheetColumns(
    sheet: StandardIntakeV2SourceSheet,
): number {
    if (
        typeof sheet.column_count === 'number'
        && sheet.column_count >= 0
    ) {
        return sheet.column_count;
    }

    if (Array.isArray(sheet.columns)) {
        return sheet.columns.length;
    }

    if (Array.isArray(sheet.headers)) {
        return sheet.headers.length;
    }

    return 0;
}

function standardIntakeV2SourceColumnLabels(
    sheet: StandardIntakeV2SourceSheet,
): string[] {
    if (Array.isArray(sheet.columns)) {
        return sheet.columns.map(
            (column, index) => {
                const header =
                    typeof column.header === 'string'
                        ? column.header.trim()
                        : '';

                return (
                    header
                    || `Columna ${column.index ?? index + 1}`
                );
            },
        );
    }

    if (Array.isArray(sheet.headers)) {
        return sheet.headers.map(
            (header, index) => {
                const value =
                    typeof header === 'string'
                        ? header.trim()
                        : '';

                return (
                    value
                    || `Columna ${index + 1}`
                );
            },
        );
    }

    return [];
}

function standardIntakeV2SessionStatusLabel(
    status: string | null | undefined,
): string {
    const labels: Record<string, string> = {
        draft: 'Borrador',
        ready: 'Lista',
        finalizing: 'Finalizando',
        finalized: 'Finalizada',
        failed: 'Con error',
        cancelled: 'Cancelada',
    };

    return status
        ? labels[status] ?? status
        : '—';
}

function standardIntakeV2ErrorMessage(
    payload: StandardIntakeV2HttpResponse | null,
    fallback: string,
): string {
    // D15F_RELATIONAL_HTTP_FEEDBACK
    const httpErrors =
        Object.values(
            payload?.errors
            ?? {},
        )
            .flatMap(
                (messages) =>
                    Array.isArray(messages)
                        ? messages
                        : [],
            )
            .filter(
                (message) =>
                    typeof message === 'string'
                    && message.trim() !== '',
            );

    const relationalErrors =
        (
            payload
                ?.resolution
                ?.errors
            ?? []
        )
            .map(
                (issue) =>
                    standardIntakeIssueText(
                        issue,
                    ),
            )
            .filter(
                (message) =>
                    message.trim() !== '',
            );

    const detail =
        Array.from(
            new Set([
                ...httpErrors,
                ...relationalErrors,
            ]),
        ).join(' ');

    const message =
        typeof payload?.message === 'string'
        && payload.message.trim() !== ''
            ? payload.message.trim()
            : '';

    return [
        message,
        detail,
    ]
        .filter(Boolean)
        .join(' ')
        || fallback;
}

async function standardIntakeV2Request(
    url: string,
    options: RequestInit = {},
): Promise<StandardIntakeV2HttpResponse> {
    const headers =
        new Headers(
            options.headers
            ?? {},
        );

    headers.set(
        'Accept',
        'application/json',
    );

    headers.set(
        'X-Requested-With',
        'XMLHttpRequest',
    );

    Object.entries(
        standardIntakeCsrfHeaders(),
    ).forEach(
        ([key, value]) =>
            headers.set(
                key,
                value,
            ),
    );

    if (
        options.body !== undefined
        && !(
            options.body
            instanceof FormData
        )
        && !headers.has(
            'Content-Type',
        )
    ) {
        headers.set(
            'Content-Type',
            'application/json',
        );
    }

    const response =
        await fetch(
            url,
            {
                ...options,
                method:
                    options.method
                    ?? 'POST',
                credentials:
                    'same-origin',
                headers,
            },
        );

    let payload:
        StandardIntakeV2HttpResponse
        | null = null;

    try {
        payload = (await response.json()) as StandardIntakeV2HttpResponse;
    } catch {
        payload = null;
    }

    if (payload?.state) {
        standardIntakeV2State.value =
            payload.state;
    }

    if (
        !response.ok
        || payload?.ok !== true
    ) {
        throw new Error(
            standardIntakeV2ErrorMessage(
                payload,
                `La operación Intake v2 falló con HTTP ${response.status}.`,
            ),
        );
    }

    return payload;
}

async function generateSqlServerExtractionPreview(
    domain: string,
): Promise<void> {
    const form =
        sqlServerExtractionForm(
            domain,
        );

    form.error =
        null;

    form.copied =
        false;

    if (
        form.schema_name.trim() === ''
        || form.table_name.trim() === ''
        || form.structure_text.trim() === ''
    ) {
        form.error =
            'Completa el esquema, la tabla y la estructura antes de generar la consulta.';

        return;
    }

    form.busy =
        true;

    try {
        const url =
            `/admin/transformation-360/implementation-requests/${props.implementation_request.id}`
            + `/standard-intake-v2/domains/${encodeURIComponent(domain)}`
            + '/sql-server-extraction/preview';

        const payload =
            await standardIntakeV2Request(
                url,
                {
                    body:
                        JSON.stringify({
                            schema_name:
                                form.schema_name.trim(),

                            table_name:
                                form.table_name.trim(),

                            structure_text:
                                form.structure_text,
                        }),
                },
            ) as StandardIntakeV2HttpResponse & {
                preview?: SqlServerExtractionPreview;
            };

        if (!payload.preview) {
            throw new Error(
                'LAUDA no devolvió una vista previa de la consulta.',
            );
        }

        form.preview =
            payload.preview;
    } catch (error) {
        form.preview =
            null;

        form.error =
            error instanceof Error
                ? error.message
                : 'No se pudo generar la consulta de extracción.';
    } finally {
        form.busy =
            false;
    }
}

async function copySqlServerExtractionQuery(
    domain: string,
): Promise<void> {
    const form =
        sqlServerExtractionForm(
            domain,
        );

    const query =
        form.preview?.query
        ?? '';

    if (query === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(
            query,
        );

        form.copied =
            true;

        window.setTimeout(
            () => {
                form.copied =
                    false;
            },
            2000,
        );
    } catch {
        form.error =
            'No se pudo copiar automáticamente. Selecciona el query y cópialo manualmente.';
    }
}

function selectStandardIntakeV2File(
    domain: string,
    event: Event,
): void {
    standardIntakeV2Error.value =
        null;

    standardIntakeV2ClearDomainError(
        domain,
    );

    const input =
        event.target as HTMLInputElement;

    const file =
        input.files?.[0]
        ?? null;

    if (!file) {
        standardIntakeV2Files.value = {
            ...standardIntakeV2Files.value,
            [domain]: null,
        };

        return;
    }

    const maxBytes =
        standardIntakeV2Delivery(
            domain,
        )?.source_native_supported
            ? SOURCE_NATIVE_INTAKE_MAX_BYTES
            : STANDARD_INTAKE_MAX_BYTES;

    const maxMegabytes =
        Math.round(
            maxBytes
            / 1024
            / 1024,
        );

    const extension =
        file.name
            .split('.')
            .pop()
            ?.toLowerCase()
        ?? '';

    if (
        ![
            'csv',
            'xlsx',
        ].includes(
            extension,
        )
    ) {
        standardIntakeV2SetDomainMessage(
            domain,
            'Cada dominio debe cargarse como CSV o Excel XLSX.',
        );

        input.value = '';

        return;
    }

    if (
        file.size
        > maxBytes
    ) {
        standardIntakeV2SetDomainMessage(
            domain,
            `El archivo del dominio supera el límite actual de ${maxMegabytes} MB.`,
        );

        input.value = '';

        return;
    }

    standardIntakeV2Files.value = {
        ...standardIntakeV2Files.value,
        [domain]: file,
    };
}

async function startStandardIntakeV2Session(): Promise<void> {
    if (standardIntakeV2Busy.value) {
        return;
    }

    standardIntakeV2Busy.value =
        'session';

    standardIntakeV2Error.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/session`,
        );

        standardIntakeV2Files.value =
            {};

        standardIntakeV2DomainErrors.value =
            {};
    } catch (error) {
        standardIntakeV2Error.value =
            error instanceof Error
                ? error.message
                : 'No se pudo preparar la sesión Intake v2.';
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

async function uploadStandardIntakeV2Domain(
    domain: string,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    const file =
        standardIntakeV2SelectedFile(
            domain,
        );

    if (
        sessionId === null
        || !file
        || standardIntakeV2Busy.value
    ) {
        return;
    }

    standardIntakeV2ClearDomainError(
        domain,
    );

    standardIntakeV2Busy.value =
        `upload:${domain}`;

    standardIntakeV2Error.value =
        null;

    const formData =
        new FormData();

    formData.append(
        'file',
        file,
        file.name,
    );

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/domains/${encodeURIComponent(domain)}/upload`,
            {
                body:
                    formData,
            },
        );

        standardIntakeV2Files.value = {
            ...standardIntakeV2Files.value,
            [domain]: null,
        };
    } catch (error) {
        standardIntakeV2SetDomainError(
            domain,
            error,
            'No se pudo procesar el archivo del dominio.',
        );
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

async function noDataStandardIntakeV2Domain(
    domain: string,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || standardIntakeV2Busy.value
    ) {
        return;
    }

    standardIntakeV2ClearDomainError(
        domain,
    );

    standardIntakeV2Busy.value =
        `no-data:${domain}`;

    standardIntakeV2Error.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/domains/${encodeURIComponent(domain)}/no-data`,
        );

        standardIntakeV2Files.value = {
            ...standardIntakeV2Files.value,
            [domain]: null,
        };
    } catch (error) {
        standardIntakeV2SetDomainError(
            domain,
            error,
            'No se pudo marcar el dominio sin datos.',
        );
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

async function carryForwardStandardIntakeV2Domain(
    domain: string,
): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || standardIntakeV2Busy.value
    ) {
        return;
    }

    standardIntakeV2ClearDomainError(
        domain,
    );

    standardIntakeV2Busy.value =
        `carry:${domain}`;

    standardIntakeV2Error.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/domains/${encodeURIComponent(domain)}/carry-forward`,
        );

        standardIntakeV2Files.value = {
            ...standardIntakeV2Files.value,
            [domain]: null,
        };
    } catch (error) {
        standardIntakeV2SetDomainError(
            domain,
            error,
            'No se pudo reutilizar el dominio del dataset anterior.',
        );
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

async function resolveStandardIntakeV2Session(): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || standardIntakeV2Busy.value
    ) {
        return;
    }

    standardIntakeV2Busy.value =
        'resolve';

    standardIntakeV2Error.value =
        null;

    try {
        await standardIntakeV2Request(
            `${standardIntakeV2BaseUrl}/sessions/${sessionId}/resolve`,
        );
    } catch (error) {
        standardIntakeV2Error.value =
            error instanceof Error
                ? error.message
                : 'La validación relacional no pudo completarse.';
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

async function materializeStandardIntakeV2Session(): Promise<void> {
    const sessionId =
        standardIntakeV2SessionId();

    if (
        sessionId === null
        || standardIntakeV2Busy.value
    ) {
        return;
    }

    standardIntakeV2Busy.value =
        'materialize';

    standardIntakeV2Error.value =
        null;

    try {
        const payload =
            await standardIntakeV2Request(
                `${standardIntakeV2BaseUrl}/sessions/${sessionId}/materialize`,
            );

        if (payload.ingestion) {
            /*
             * Handoff to the existing post-staging pipeline.
             *
             * Profiling and normalization remain explicit/manual.
             * Nothing is auto-executed here.
             */
            standardIntakeIngestionReport.value = {
                ok:
                    true,
                message:
                    payload.message,
                ingestion:
                    payload.ingestion,
            };

            standardIntakeProcessingBatchId.value =
                payload.ingestion.batch_id;

            standardIntakeProfileReport.value =
                null;

            standardIntakeNormalizationReport.value =
                null;

            standardIntakeProcessingError.value =
                null;
        }
    } catch (error) {
        standardIntakeV2Error.value =
            error instanceof Error
                ? error.message
                : 'No se pudo preparar el staging canónico.';
    } finally {
        standardIntakeV2Busy.value =
            null;
    }
}

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
    /*
     * P11_CORRECTION_REPROCESSING_LOOP
     *
     * A newly selected source starts a clean client-side
     * preparation context. Persisted database history is
     * never deleted here.
     */
    standardIntakeReport.value = null;
    standardIntakeHttpError.value = null;
    standardIntakeIngestionReport.value = null;
    standardIntakeIngestionError.value = null;
    standardIntakeProfileReport.value = null;
    standardIntakeNormalizationReport.value = null;
    standardIntakeProcessingBatchId.value = null;
    standardIntakeProcessingError.value = null;

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
    ref<StandardIntakeProcessingHttpResponse | null>(
        props.standard_intake_persisted_state
            ?.profile
        ?? null,
    );

const standardIntakeNormalizationReport =
    ref<StandardIntakeProcessingHttpResponse | null>(
        props.standard_intake_persisted_state
            ?.normalization
        ?? null,
    );

const standardIntakeProcessingBatchId =
    ref<number | null>(
        props.standard_intake_persisted_state
            ?.ingestion
            ?.ingestion
            ?.batch_id
        ?? null,
    );

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

function standardIntakeInformationalIssueCount(): number {
    const processing =
        standardIntakeProfileReport.value
            ?.processing;

    if (!processing) {
        return 0;
    }

    return Math.max(
        0,
        (processing.issue_count ?? 0)
        - (processing.blocking_issue_count ?? 0)
        - (processing.warning_issue_count ?? 0),
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
                        @click="generateImplementationDefinition(false)"
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
                              class="flex flex-wrap items-center justify-end gap-2"
                          >
                              <a
                                  :href="`/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake-template/xlsx`"
                                  class="rounded-lg border px-3 py-2 text-xs font-semibold"
                              >
                                  Referencia canónica Excel
                              </a>

                              <a
                                  :href="`/admin/transformation-360/implementation-requests/${props.implementation_request.id}/standard-intake-template/csv`"
                                  class="rounded-lg border px-3 py-2 text-xs font-semibold"
                              >
                                  Referencia canónica CSV
                              </a>
                          </div>


                        <!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->
                        <section
                            class="mt-5 rounded-2xl border border-sky-200 bg-sky-50/40 p-4 dark:border-sky-900/70 dark:bg-sky-950/10"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div class="max-w-3xl">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="text-base font-black">
                                            Fuentes de datos
                                        </h3>

                                        <span
                                            v-if="standardIntakeV2State?.session"
                                            class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                        >
                                            Sesión
                                            #{{ standardIntakeV2State.session.id }}
                                            ·
                                            {{
                                                standardIntakeV2SessionStatusLabel(
                                                    standardIntakeV2State.session.status,
                                                )
                                            }}
                                        </span>

                                        <span
                                            v-if="standardIntakeV2State?.session"
                                            class="rounded-full border border-sky-200 bg-white px-2.5 py-1 text-[10px] font-bold text-sky-700 dark:border-sky-900 dark:bg-slate-950 dark:text-sky-300"
                                        >
                                            {{
                                                dynamicSourceAssets().length
                                            }}
                                            fuente(s)
                                        </span>
                                    </div>

                                    <p
                                        class="mt-2 text-sm leading-6 text-muted-foreground"
                                    >
                                        Registra cada tabla o archivo tal como existe
                                        en el sistema del cliente. No necesitas adaptar
                                        los datos al modelo LAUDA en esta etapa. El
                                        sistema de origen es informativo y los datos se
                                        entregan posteriormente en CSV o Excel.
                                    </p>

                                    <p
                                        class="mt-2 text-xs leading-5 text-muted-foreground"
                                    >
                                        Ejemplos:
                                        <strong>Maestro de clientes · CTES</strong>,
                                        <strong>Facturas · FAC.DBF</strong>,
                                        <strong>Detalle de facturas · FACDET.DBF</strong>.
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="
                                            !standardIntakeV2State?.session
                                            || standardIntakeV2State
                                                ?.actions
                                                ?.can_start_new_session
                                        "
                                        type="button"
                                        class="cursor-pointer rounded-lg border bg-background px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            standardIntakeV2Busy
                                            !== null
                                        "
                                        @click="startStandardIntakeV2Session"
                                    >
                                        {{
                                            standardIntakeV2Busy
                                                === 'session'
                                                ? 'Preparando...'
                                                : 'Iniciar sesión'
                                        }}
                                    </button>

                                    <button
                                        v-if="dynamicSourceCanManage()"
                                        type="button"
                                        class="cursor-pointer rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            dynamicSourceBusy
                                            !== null
                                        "
                                        @click="openDynamicSourceCreateForm"
                                    >
                                        + Agregar fuente
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="dynamicSourceError"
                                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                            >
                                {{ dynamicSourceError }}
                            </div>

                            <div
                                v-if="dynamicSourceFormOpen"
                                class="mt-4 rounded-xl border bg-background p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="text-sm font-black">
                                            {{
                                                dynamicSourceEditingId === null
                                                    ? 'Agregar fuente de datos'
                                                    : 'Editar fuente de datos'
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs leading-5 text-muted-foreground"
                                        >
                                            Describe la fuente como existe realmente
                                            en el sistema del cliente.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="dynamicSourceBusy !== null"
                                        @click="closeDynamicSourceForm"
                                    >
                                        Cerrar
                                    </button>
                                </div>

                                <div
                                    class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-4"
                                >
                                    <label class="block">
                                        <span class="text-xs font-semibold">
                                            Nombre de la fuente
                                        </span>

                                        <input
                                            v-model="
                                                dynamicSourceForm.display_name
                                            "
                                            type="text"
                                            maxlength="191"
                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                            placeholder="Ej. Maestro de clientes"
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-semibold">
                                            Tabla o archivo de origen
                                        </span>

                                        <input
                                            v-model="
                                                dynamicSourceForm.source_object_name
                                            "
                                            type="text"
                                            maxlength="255"
                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                            placeholder="Ej. CTES, FAC.DBF, clientes.xlsx"
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-semibold">
                                            Origen de los datos
                                        </span>

                                        <input
                                            v-model="
                                                dynamicSourceForm.origin_system
                                            "
                                            type="text"
                                            maxlength="191"
                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                            placeholder="Ej. SQL Server, FoxPro, Clarion, Mónica"
                                        />

                                        <span
                                            class="mt-1 block text-[11px] leading-5 text-muted-foreground"
                                        >
                                            Solo informativo. No representa una conexión.
                                        </span>
                                    </label>

                                    <div>
                                        <span class="text-xs font-semibold">
                                            Formato de entrega
                                        </span>

                                        <div
                                            class="mt-1 grid grid-cols-2 gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                :class="
                                                    dynamicSourceForm.delivery_format
                                                        === 'csv'
                                                        ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                        : 'bg-background'
                                                "
                                                @click="
                                                    dynamicSourceForm.delivery_format =
                                                        'csv'
                                                "
                                            >
                                                CSV
                                            </button>

                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                :class="
                                                    dynamicSourceForm.delivery_format
                                                        === 'xlsx'
                                                        ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                        : 'bg-background'
                                                "
                                                @click="
                                                    dynamicSourceForm.delivery_format =
                                                        'xlsx'
                                                "
                                            >
                                                Excel (.xlsx)
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <label class="mt-4 block">
                                    <span class="text-xs font-semibold">
                                        Breve descripción
                                    </span>

                                    <textarea
                                        v-model="
                                            dynamicSourceForm.description
                                        "
                                        maxlength="4000"
                                        rows="3"
                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                        placeholder="Ej. Maestro general de clientes, condiciones de crédito y datos de contacto."
                                    ></textarea>
                                </label>

                                <div
                                    v-if="dynamicSourceError"
                                    class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                >
                                    {{ dynamicSourceError }}
                                </div>

                                <div
                                    class="mt-4 flex flex-wrap justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg border px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="dynamicSourceBusy !== null"
                                        @click="closeDynamicSourceForm"
                                    >
                                        Cancelar
                                    </button>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg bg-foreground px-4 py-2 text-sm font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="dynamicSourceBusy !== null"
                                        @click="saveDynamicSourceAsset"
                                    >
                                        {{
                                            dynamicSourceBusy === 'create'
                                            || (
                                                dynamicSourceEditingId !== null
                                                && dynamicSourceBusy
                                                    === `update:${dynamicSourceEditingId}`
                                            )
                                                ? 'Guardando...'
                                                : dynamicSourceEditingId === null
                                                  ? 'Agregar fuente'
                                                  : 'Guardar cambios'
                                        }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="
                                    standardIntakeV2State?.session
                                    && dynamicSourceAssets().length === 0
                                    && !dynamicSourceFormOpen
                                "
                                class="mt-4 rounded-xl border border-dashed bg-background/60 p-6 text-center"
                            >
                                <Database
                                    class="mx-auto h-8 w-8 text-muted-foreground"
                                />

                                <p class="mt-3 text-sm font-black">
                                    Todavía no hay fuentes registradas
                                </p>

                                <p
                                    class="mx-auto mt-1 max-w-xl text-xs leading-5 text-muted-foreground"
                                >
                                    Agrega las tablas o archivos que el cliente
                                    utiliza. No existe un límite por dominio:
                                    puedes registrar tantas fuentes como sean
                                    necesarias.
                                </p>

                                <button
                                    v-if="dynamicSourceCanManage()"
                                    type="button"
                                    class="mt-4 cursor-pointer rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white"
                                    @click="openDynamicSourceCreateForm"
                                >
                                    + Agregar primera fuente
                                </button>
                            </div>

                            <div
                                v-if="dynamicSourceAssets().length"
                                class="mt-5 overflow-x-auto pb-2"
                            >
                                <div
                                    class="flex min-w-max items-stretch gap-3"
                                >
                                    <article
                                        v-for="(
                                            asset,
                                            assetIndex
                                        ) in dynamicSourceAssets()"
                                        :key="`source-asset-${asset.id}`"
                                        class="flex w-[340px] shrink-0 flex-col rounded-xl border bg-background p-4"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-black"
                                                >
                                                    {{ asset.display_name }}
                                                </p>

                                                <p
                                                    class="mt-1 truncate font-mono text-xs text-muted-foreground"
                                                >
                                                    {{ asset.source_object_name }}
                                                </p>
                                            </div>

                                            <span
                                                class="shrink-0 rounded-full border px-2 py-1 text-[10px] font-bold uppercase text-muted-foreground"
                                            >
                                                {{
                                                    asset.origin_system
                                                    || 'Origen no indicado'
                                                }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-3 min-h-10 text-xs leading-5 text-muted-foreground"
                                        >
                                            {{
                                                asset.description
                                                || 'Sin descripción.'
                                            }}
                                        </p>

                                        <div
                                            class="mt-4 grid grid-cols-3 gap-2"
                                        >
                                            <div
                                                class="rounded-lg border bg-muted/20 p-2"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Estructura
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-black"
                                                >
                                                    {{
                                                        dynamicSourceStructureLabel(
                                                            asset.structure_status,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-lg border bg-muted/20 p-2"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Datos
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-black"
                                                >
                                                    {{
                                                        dynamicSourceDataLabel(
                                                            asset.data_status,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-lg border bg-muted/20 p-2"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Entrega
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-black"
                                                >
                                                    {{
                                                        dynamicSourceDeliveryLabel(
                                                            asset.delivery_format,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-4 flex flex-wrap gap-1.5"
                                        >
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Información
                                            </span>
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Estructura
                                            </span>
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Extracción
                                            </span>
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Archivo
                                            </span>
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Análisis
                                            </span>
                                            <span
                                                class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            >
                                                Mapeo
                                            </span>
                                        </div>

                                        <div
                                            class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-5"
                                        >
                                            <div class="flex gap-1">
                                                <button
                                                    type="button"
                                                    title="Mover a la izquierda"
                                                    class="cursor-pointer rounded-lg border px-2 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-40"
                                                    :disabled="
                                                        assetIndex === 0
                                                        || dynamicSourceBusy !== null
                                                    "
                                                    @click="
                                                        moveDynamicSourceAsset(
                                                            asset.id,
                                                            -1,
                                                        )
                                                    "
                                                >
                                                    ←
                                                </button>

                                                <button
                                                    type="button"
                                                    title="Mover a la derecha"
                                                    class="cursor-pointer rounded-lg border px-2 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-40"
                                                    :disabled="
                                                        assetIndex
                                                            === dynamicSourceAssets().length - 1
                                                        || dynamicSourceBusy !== null
                                                    "
                                                    @click="
                                                        moveDynamicSourceAsset(
                                                            asset.id,
                                                            1,
                                                        )
                                                    "
                                                >
                                                    →
                                                </button>
                                            </div>

                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-bold text-white"
                                                    @click="
                                                        openDynamicSourceWorkspace(
                                                            asset,
                                                        )
                                                    "
                                                >
                                                    Gestionar
                                                </button>

                                                <button
                                                    v-if="dynamicSourceCanManage()"
                                                    type="button"
                                                    class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                                    :disabled="
                                                        dynamicSourceBusy !== null
                                                    "
                                                    @click="
                                                        openDynamicSourceEditForm(
                                                            asset,
                                                        )
                                                    "
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    v-if="dynamicSourceCanManage()"
                                                    type="button"
                                                    class="cursor-pointer rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:text-red-300"
                                                    :disabled="
                                                        dynamicSourceBusy !== null
                                                    "
                                                    @click="
                                                        archiveDynamicSourceAsset(
                                                            asset,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        dynamicSourceBusy
                                                            === `archive:${asset.id}`
                                                            ? 'Archivando...'
                                                            : 'Archivar'
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <!-- D17_DYNAMIC_SOURCE_DETAIL_WORKSPACE -->
                            <section
                                v-if="dynamicSourceSelectedAsset()"
                                class="mt-5 overflow-hidden rounded-xl border bg-background"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-4 border-b p-4"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-bold uppercase tracking-wide text-muted-foreground"
                                        >
                                            Fuente seleccionada
                                        </p>

                                        <h4 class="mt-1 text-base font-black">
                                            {{
                                                dynamicSourceSelectedAsset()
                                                    ?.display_name
                                            }}
                                        </h4>

                                        <div
                                            class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                        >
                                            <span class="font-mono">
                                                {{
                                                    dynamicSourceSelectedAsset()
                                                        ?.source_object_name
                                                }}
                                            </span>

                                            <span>
                                                {{
                                                    dynamicSourceSelectedAsset()
                                                        ?.origin_system
                                                    || 'Origen no indicado'
                                                }}
                                            </span>

                                            <span>
                                                {{
                                                    dynamicSourceDeliveryLabel(
                                                        dynamicSourceSelectedAsset()
                                                            ?.delivery_format,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold"
                                        @click="
                                            dynamicSourceSelectedId =
                                                null
                                        "
                                    >
                                        Cerrar detalle
                                    </button>
                                </div>

                                <div
                                    class="overflow-x-auto border-b bg-muted/10"
                                >
                                    <div
                                        class="flex min-w-max gap-1 p-2"
                                    >
                                        <button
                                            v-for="tab in [
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
                                                    label: 'Archivo CSV/XLSX',
                                                },
                                                {
                                                    key: 'analysis',
                                                    label: 'Análisis',
                                                },
                                                {
                                                    key: 'mapping',
                                                    label: 'Mapeo LAUDA',
                                                },
                                            ]"
                                            :key="tab.key"
                                            type="button"
                                            class="cursor-pointer whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold"
                                            :class="
                                                dynamicSourceActiveTab
                                                    === tab.key
                                                    ? 'bg-foreground text-background'
                                                    : 'border bg-background text-muted-foreground'
                                            "
                                            @click="
                                                dynamicSourceActiveTab =
                                                    tab.key as DynamicSourceWorkspaceTab
                                            "
                                        >
                                            {{ tab.label }}
                                        </button>
                                    </div>
                                </div>

                                <div class="p-4">
                                    <!-- INFORMATION -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'information'
                                        "
                                    >
                                        <div
                                            class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                        >
                                            <div
                                                class="rounded-lg border p-3"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Nombre
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        dynamicSourceSelectedAsset()
                                                            ?.display_name
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-lg border p-3"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Tabla / archivo
                                                </p>
                                                <p
                                                    class="mt-1 break-all font-mono text-sm font-black"
                                                >
                                                    {{
                                                        dynamicSourceSelectedAsset()
                                                            ?.source_object_name
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-lg border p-3"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Origen
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        dynamicSourceSelectedAsset()
                                                            ?.origin_system
                                                        || 'No indicado'
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 text-[10px] text-muted-foreground"
                                                >
                                                    Informativo solamente
                                                </p>
                                            </div>

                                            <div
                                                class="rounded-lg border p-3"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Entrega
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-black"
                                                >
                                                    {{
                                                        dynamicSourceDeliveryLabel(
                                                            dynamicSourceSelectedAsset()
                                                                ?.delivery_format,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-4 rounded-lg border p-3"
                                        >
                                            <p
                                                class="text-xs font-bold"
                                            >
                                                Descripción
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                {{
                                                    dynamicSourceSelectedAsset()
                                                        ?.description
                                                    || 'Sin descripción.'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- STRUCTURE -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'structure'
                                            && dynamicSourceSelectedAsset()
                                        "
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div>
                                                <p class="text-sm font-black">
                                                    Estructura de la fuente
                                                </p>

                                                <p
                                                    class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                                >
                                                    Puedes registrar primero la
                                                    estructura de la tabla o archivo.
                                                    Esto permite a LAUDA conocer los
                                                    campos antes de recibir los datos.
                                                    La estructura es texto de referencia:
                                                    nunca se ejecuta.
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                            >
                                                {{
                                                    dynamicSourceStructureLabel(
                                                        dynamicSourceSelectedAsset()
                                                            ?.structure_status,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="mt-4"
                                        >
                                            <p
                                                class="text-xs font-semibold"
                                            >
                                                Formato de la estructura
                                            </p>

                                            <div
                                                class="mt-2 flex flex-wrap gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                    :class="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format
                                                            === 'field_type_list'
                                                            ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                            : 'bg-background'
                                                    "
                                                    @click="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format =
                                                            'field_type_list'
                                                    "
                                                >
                                                    Lista de campos y tipos
                                                </button>

                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                    :class="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format
                                                            === 'sql_server_ddl'
                                                            ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                            : 'bg-background'
                                                    "
                                                    @click="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format =
                                                            'sql_server_ddl'
                                                    "
                                                >
                                                    CREATE TABLE SQL Server
                                                </button>

                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                    :class="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format
                                                            === 'other'
                                                            ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                            : 'bg-background'
                                                    "
                                                    @click="
                                                        dynamicSourceStructureForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).structure_format =
                                                            'other'
                                                    "
                                                >
                                                    Otra estructura
                                                </button>
                                            </div>
                                        </div>

                                        <label class="mt-4 block">
                                            <span
                                                class="text-xs font-semibold"
                                            >
                                                Definición de estructura
                                            </span>

                                            <textarea
                                                v-model="
                                                    dynamicSourceStructureForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).structure_text
                                                "
                                                maxlength="50000"
                                                rows="12"
                                                class="mt-1 w-full rounded-lg border bg-background px-3 py-2 font-mono text-xs leading-5"
                                                placeholder="[CODIGO] varchar(20)&#10;[NOMBRE] varchar(150)&#10;[RNC] varchar(20)"
                                            ></textarea>

                                            <span
                                                class="mt-1 block text-[11px] leading-5 text-muted-foreground"
                                            >
                                                Puedes pegar una lista de campos,
                                                un CREATE TABLE o cualquier descripción
                                                estructural útil. No pegues datos,
                                                credenciales, usuarios ni contraseñas.
                                            </span>
                                        </label>

                                        <div
                                            v-if="
                                                dynamicSourceStructureForm(
                                                    dynamicSourceSelectedAsset()!,
                                                ).error
                                            "
                                            class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                        >
                                            {{
                                                dynamicSourceStructureForm(
                                                    dynamicSourceSelectedAsset()!,
                                                ).error
                                            }}
                                        </div>

                                        <div
                                            class="mt-4 flex flex-wrap items-center justify-between gap-3"
                                        >
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Formato actual:
                                                <strong>
                                                    {{
                                                        dynamicSourceStructureFormatLabel(
                                                            dynamicSourceStructureForm(
                                                                dynamicSourceSelectedAsset()!,
                                                            ).structure_format,
                                                        )
                                                    }}
                                                </strong>
                                            </p>

                                            <button
                                                v-if="dynamicSourceCanManage()"
                                                type="button"
                                                class="cursor-pointer rounded-lg bg-foreground px-4 py-2 text-sm font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    dynamicSourceStructureForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).busy
                                                "
                                                @click="
                                                    saveDynamicSourceStructure(
                                                        dynamicSourceSelectedAsset()!,
                                                    )
                                                "
                                            >
                                                {{
                                                    dynamicSourceStructureForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).busy
                                                        ? 'Guardando estructura...'
                                                        : 'Guardar estructura'
                                                }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- EXTRACTION -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'extraction'
                                            && dynamicSourceSelectedAsset()
                                        "
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div>
                                                <p class="text-sm font-black">
                                                    Asistencia de extracción
                                                </p>

                                                <p
                                                    class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                                >
                                                    Esta herramienta es opcional e
                                                    independiente del origen informado.
                                                    LAUDA no conecta al servidor del
                                                    cliente. Solo prepara una consulta
                                                    SELECT que el cliente ejecuta
                                                    localmente.
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase text-muted-foreground"
                                            >
                                                Sin conexión remota
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                !dynamicSourceSelectedAsset()
                                                    ?.structure_text
                                            "
                                            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300"
                                        >
                                            Guarda primero la estructura de esta
                                            fuente en la pestaña
                                            <strong>Estructura</strong>.
                                        </div>

                                        <div
                                            v-else
                                            class="mt-4"
                                        >
                                            <div
                                                class="rounded-xl border bg-muted/20 p-4"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center justify-between gap-3"
                                                >
                                                    <div>
                                                        <p class="text-sm font-black">
                                                            SQL Server
                                                        </p>

                                                        <p
                                                            class="mt-1 text-xs text-muted-foreground"
                                                        >
                                                            Genera un SELECT de solo lectura
                                                            usando la estructura ya guardada.
                                                        </p>
                                                    </div>

                                                    <span
                                                        class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                                    >
                                                        SELECT solamente
                                                    </span>
                                                </div>

                                                <div
                                                    class="mt-4 grid gap-3 md:grid-cols-2"
                                                >
                                                    <label class="block">
                                                        <span
                                                            class="text-xs font-semibold"
                                                        >
                                                            Esquema
                                                        </span>

                                                        <input
                                                            v-model="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).schema_name
                                                            "
                                                            type="text"
                                                            maxlength="128"
                                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                                            placeholder="dbo"
                                                        />
                                                    </label>

                                                    <label class="block">
                                                        <span
                                                            class="text-xs font-semibold"
                                                        >
                                                            Tabla
                                                        </span>

                                                        <input
                                                            v-model="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).table_name
                                                            "
                                                            type="text"
                                                            maxlength="128"
                                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                                            :placeholder="
                                                                dynamicSourceSelectedAsset()
                                                                    ?.source_object_name
                                                            "
                                                        />
                                                    </label>
                                                </div>

                                                <div
                                                    v-if="
                                                        dynamicSourceSqlServerForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).error
                                                    "
                                                    class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                                >
                                                    {{
                                                        dynamicSourceSqlServerForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).error
                                                    }}
                                                </div>

                                                <div class="mt-4">
                                                    <button
                                                        type="button"
                                                        class="cursor-pointer rounded-lg bg-foreground px-4 py-2 text-sm font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                                        :disabled="
                                                            dynamicSourceSqlServerForm(
                                                                dynamicSourceSelectedAsset()!,
                                                            ).busy
                                                        "
                                                        @click="
                                                            generateDynamicSourceSqlServerPreview(
                                                                dynamicSourceSelectedAsset()!,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            dynamicSourceSqlServerForm(
                                                                dynamicSourceSelectedAsset()!,
                                                            ).busy
                                                                ? 'Generando...'
                                                                : 'Preparar extracción SQL Server'
                                                        }}
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    dynamicSourceSqlServerForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).preview
                                                "
                                                class="mt-4 space-y-4"
                                            >
                                                <div
                                                    class="rounded-xl border p-4"
                                                >
                                                    <p
                                                        class="text-xs font-black"
                                                    >
                                                        Campos detectados ·
                                                        {{
                                                            dynamicSourceSqlServerForm(
                                                                dynamicSourceSelectedAsset()!,
                                                            ).preview?.field_count
                                                        }}
                                                    </p>

                                                    <div
                                                        class="mt-2 flex flex-wrap gap-1.5"
                                                    >
                                                        <span
                                                            v-for="
                                                                field in dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).preview?.fields
                                                            "
                                                            :key="
                                                                `dynamic-source-${dynamicSourceSelectedAsset()?.id}-field-${field.name}`
                                                            "
                                                            class="rounded-md border px-2 py-1 font-mono text-[11px]"
                                                        >
                                                            {{ field.name }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div
                                                    class="rounded-xl border p-4"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-center justify-between gap-2"
                                                    >
                                                        <p
                                                            class="text-xs font-black"
                                                        >
                                                            Consulta de extracción
                                                        </p>

                                                        <button
                                                            type="button"
                                                            class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold"
                                                            @click="
                                                                copyDynamicSourceSqlServerQuery(
                                                                    dynamicSourceSelectedAsset()!,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).copied
                                                                    ? 'Copiado'
                                                                    : 'Copiar consulta'
                                                            }}
                                                        </button>
                                                    </div>

                                                    <pre
                                                        class="mt-3 max-h-80 overflow-auto whitespace-pre rounded-lg border bg-muted/20 p-3 font-mono text-xs leading-5"
                                                    >{{ dynamicSourceSqlServerForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).preview?.query }}</pre>
                                                </div>

                                                <div
                                                    class="rounded-xl border p-4"
                                                >
                                                    <p class="text-xs font-black">
                                                        Formato de entrega
                                                    </p>

                                                    <div
                                                        class="mt-2 grid max-w-sm grid-cols-2 gap-2"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                            :class="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).export_format
                                                                    === 'csv'
                                                                    ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                                    : 'bg-background'
                                                            "
                                                            @click="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).export_format =
                                                                    'csv'
                                                            "
                                                        >
                                                            CSV
                                                        </button>

                                                        <button
                                                            type="button"
                                                            class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                                            :class="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).export_format
                                                                    === 'xlsx'
                                                                    ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300'
                                                                    : 'bg-background'
                                                            "
                                                            @click="
                                                                dynamicSourceSqlServerForm(
                                                                    dynamicSourceSelectedAsset()!,
                                                                ).export_format =
                                                                    'xlsx'
                                                            "
                                                        >
                                                            Excel (.xlsx)
                                                        </button>
                                                    </div>

                                                    <div
                                                        class="mt-3 rounded-lg border bg-muted/20 p-3"
                                                    >
                                                        <p
                                                            class="text-xs font-bold"
                                                        >
                                                            Cómo entregar el archivo
                                                        </p>

                                                        <ol
                                                            class="mt-2 list-decimal space-y-1 pl-5 text-xs leading-5 text-muted-foreground"
                                                        >
                                                            <li
                                                                v-for="(
                                                                    instruction,
                                                                    index
                                                                ) in dynamicSourceSqlServerInstructions(
                                                                    dynamicSourceSelectedAsset()!,
                                                                )"
                                                                :key="
                                                                    `dynamic-source-export-${index}`
                                                                "
                                                            >
                                                                {{ instruction }}
                                                            </li>
                                                        </ol>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- FILE -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'file'
                                        "
                                        class="space-y-4"
                                    >
                                        <div
                                            class="rounded-xl border p-4"
                                        >
                                            <div
                                                class="flex flex-wrap items-start justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-black"
                                                    >
                                                        Archivo CSV/XLSX
                                                    </p>

                                                    <p
                                                        class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground"
                                                    >
                                                        Carga el archivo real de esta fuente.
                                                        No necesita estar normalizado al modelo
                                                        LAUDA y no requiere haber registrado
                                                        previamente una estructura manual.
                                                    </p>
                                                </div>

                                                <span
                                                    class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                                >
                                                    Máx. 32 MB
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                dynamicSourceSelectedAsset()
                                                    ?.data_file
                                            "
                                            class="rounded-xl border bg-background/70 p-4"
                                        >
                                            <div
                                                class="flex flex-wrap items-start justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="text-xs font-bold uppercase tracking-wide text-muted-foreground"
                                                    >
                                                        Archivo actual
                                                    </p>

                                                    <p
                                                        class="mt-1 break-all text-sm font-black"
                                                    >
                                                        {{
                                                            dynamicSourceSelectedAsset()
                                                                ?.data_file
                                                                ?.original_filename
                                                        }}
                                                    </p>
                                                </div>

                                                <span
                                                    class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                                >
                                                    {{
                                                        dynamicSourceSelectedAsset()
                                                            ?.data_file
                                                            ?.source_format
                                                            ?.toUpperCase()
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                                            >
                                                <div
                                                    class="rounded-lg border p-3"
                                                >
                                                    <p
                                                        class="text-[10px] font-bold uppercase text-muted-foreground"
                                                    >
                                                        Tamaño
                                                    </p>

                                                    <p
                                                        class="mt-1 text-sm font-semibold"
                                                    >
                                                        {{
                                                            dynamicSourceFileSizeLabel(
                                                                dynamicSourceSelectedAsset()
                                                                    ?.data_file
                                                                    ?.source_size_bytes,
                                                            )
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border p-3"
                                                >
                                                    <p
                                                        class="text-[10px] font-bold uppercase text-muted-foreground"
                                                    >
                                                        Filas detectadas
                                                    </p>

                                                    <p
                                                        class="mt-1 text-sm font-semibold"
                                                    >
                                                        {{
                                                            (
                                                                dynamicSourceSelectedAsset()
                                                                    ?.data_file
                                                                    ?.source_row_count
                                                                ?? 0
                                                            ).toLocaleString(
                                                                'es-DO',
                                                            )
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border p-3"
                                                >
                                                    <p
                                                        class="text-[10px] font-bold uppercase text-muted-foreground"
                                                    >
                                                        Hojas detectadas
                                                    </p>

                                                    <p
                                                        class="mt-1 text-sm font-semibold"
                                                    >
                                                        {{
                                                            dynamicSourceDataSheets(
                                                                dynamicSourceSelectedAsset()!,
                                                            ).length
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="rounded-lg border p-3"
                                                >
                                                    <p
                                                        class="text-[10px] font-bold uppercase text-muted-foreground"
                                                    >
                                                        Recibido
                                                    </p>

                                                    <p
                                                        class="mt-1 text-sm font-semibold"
                                                    >
                                                        {{
                                                            dynamicSourceDateTimeLabel(
                                                                dynamicSourceSelectedAsset()
                                                                    ?.data_file
                                                                    ?.uploaded_at,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    dynamicSourceDataSheets(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).length
                                                "
                                                class="mt-4"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    Estructura observada por hoja
                                                </p>

                                                <div
                                                    class="mt-2 flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        v-for="(
                                                            sheet,
                                                            index
                                                        ) in dynamicSourceDataSheets(
                                                            dynamicSourceSelectedAsset()!,
                                                        )"
                                                        :key="
                                                            `dynamic-source-sheet-${sheet.index}-${index}`
                                                        "
                                                        class="rounded-full border px-2.5 py-1 text-xs"
                                                    >
                                                        {{
                                                            dynamicSourceDataSheetLabel(
                                                                sheet,
                                                                index,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-4 rounded-lg border p-3"
                                            >
                                                <p
                                                    class="text-[10px] font-bold uppercase text-muted-foreground"
                                                >
                                                    SHA-256
                                                </p>

                                                <p
                                                    class="mt-1 break-all font-mono text-[11px]"
                                                >
                                                    {{
                                                        dynamicSourceSelectedAsset()
                                                            ?.data_file
                                                            ?.source_sha256
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="rounded-xl border border-dashed p-4 text-xs leading-5 text-muted-foreground"
                                        >
                                            Todavía no se ha recibido un archivo para
                                            esta fuente. Puedes cargar directamente un
                                            CSV o XLSX; los encabezados y hojas se
                                            utilizarán para descubrir su estructura.
                                        </div>

                                        <div
                                            class="rounded-xl border border-dashed p-4"
                                        >
                                            <p
                                                class="text-xs font-bold"
                                            >
                                                {{
                                                    dynamicSourceSelectedAsset()
                                                        ?.data_file
                                                        ? 'Reemplazar archivo'
                                                        : 'Seleccionar archivo'
                                                }}
                                            </p>

                                            <input
                                                :key="
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).input_key
                                                "
                                                type="file"
                                                accept=".csv,.xlsx"
                                                class="mt-3 block w-full text-xs"
                                                :disabled="
                                                    !dynamicSourceCanManage()
                                                    || dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).busy
                                                "
                                                @change="
                                                    selectDynamicSourceDataFile(
                                                        dynamicSourceSelectedAsset()!,
                                                        $event,
                                                    )
                                                "
                                            />

                                            <div
                                                v-if="
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).file
                                                "
                                                class="mt-3 rounded-lg border bg-background p-3 text-xs"
                                            >
                                                <strong>
                                                    Seleccionado:
                                                </strong>

                                                {{
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).file?.name
                                                }}

                                                ·

                                                {{
                                                    dynamicSourceFileSizeLabel(
                                                        dynamicSourceDataUploadForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).file?.size,
                                                    )
                                                }}
                                            </div>

                                            <p
                                                v-if="
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).error
                                                "
                                                class="mt-3 text-xs font-semibold"
                                            >
                                                {{
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).error
                                                }}
                                            </p>

                                            <p
                                                v-if="
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).message
                                                "
                                                class="mt-3 text-xs font-semibold"
                                            >
                                                {{
                                                    dynamicSourceDataUploadForm(
                                                        dynamicSourceSelectedAsset()!,
                                                    ).message
                                                }}
                                            </p>

                                            <div
                                                class="mt-4 flex flex-wrap items-center gap-3"
                                            >
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    :disabled="
                                                        !dynamicSourceCanManage()
                                                        || !dynamicSourceDataUploadForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).file
                                                        || dynamicSourceDataUploadForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).busy
                                                    "
                                                    @click="
                                                        uploadDynamicSourceData(
                                                            dynamicSourceSelectedAsset()!,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        dynamicSourceDataUploadForm(
                                                            dynamicSourceSelectedAsset()!,
                                                        ).busy
                                                            ? 'Procesando...'
                                                            : dynamicSourceSelectedAsset()
                                                                ?.data_file
                                                                ? 'Reemplazar archivo'
                                                                : 'Subir archivo'
                                                    }}
                                                </Button>

                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Solo CSV o XLSX. El archivo se
                                                    conserva en almacenamiento privado.
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ANALYSIS -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'analysis'
                                        "
                                        class="rounded-xl border border-dashed p-6"
                                    >
                                        <p class="text-sm font-black">
                                            Análisis de la fuente
                                        </p>

                                        <p
                                            class="mt-2 max-w-2xl text-xs leading-5 text-muted-foreground"
                                        >
                                            Se habilitará después de recibir el
                                            archivo CSV/XLSX: estructura observada,
                                            filas, calidad, nulos, duplicados y otras
                                            señales de perfilado.
                                        </p>
                                    </div>

                                    <!-- MAPPING -->
                                    <div
                                        v-if="
                                            dynamicSourceActiveTab
                                            === 'mapping'
                                        "
                                        class="rounded-xl border border-dashed p-6"
                                    >
                                        <p class="text-sm font-black">
                                            Mapeo al modelo LAUDA
                                        </p>

                                        <p
                                            class="mt-2 max-w-2xl text-xs leading-5 text-muted-foreground"
                                        >
                                            Aquí relacionaremos posteriormente los
                                            campos y fuentes reales con el modelo
                                            canónico usado por staging, normalización,
                                            BI e inteligencia empresarial.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <div
                                v-if="standardIntakeV2State?.session"
                                class="mt-4 rounded-xl border bg-background/70 p-3 text-xs leading-5 text-muted-foreground"
                            >
                                <strong>Flujo:</strong>
                                Información
                                →
                                Estructura
                                →
                                Extracción
                                →
                                CSV/XLSX
                                →
                                Análisis
                                →
                                Mapeo al modelo LAUDA.
                                La estructura puede registrarse manualmente o
                                descubrirse al recibir el archivo; cada fuente se
                                carga de forma independiente.
                            </div>
                        </section>

                        <!-- D15C_INTAKE_V2_UI -->
                        <section
                            class="mt-5 rounded-2xl border border-sky-200 bg-sky-50/40 p-4 dark:border-sky-900/70 dark:bg-sky-950/10"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div class="max-w-3xl">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="text-base font-black"
                                        >
                                            Modelo objetivo LAUDA · procesamiento interno
                                        </h3>

                                        <span
                                            v-if="
                                                standardIntakeV2State
                                                    ?.session
                                            "
                                            class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                        >
                                            Sesión #{{
                                                standardIntakeV2State
                                                    .session
                                                    .id
                                            }}
                                            ·
                                            {{
                                                standardIntakeV2SessionStatusLabel(
                                                    standardIntakeV2State
                                                        .session
                                                        .status,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-2 text-sm leading-6 text-muted-foreground"
                                    >
                                        Esta sección conserva el contrato canónico
                                        utilizado por staging, normalización y BI.
                                        No representa las tablas o archivos que el
                                        cliente debe entregar. Las fuentes reales
                                        se registran arriba y posteriormente se
                                        mapean hacia este modelo objetivo.
                                    </p>
                                </div>

                                <button
                                    v-if="
                                        !standardIntakeV2State
                                            ?.session
                                        || standardIntakeV2State
                                            ?.actions
                                            ?.can_start_new_session
                                    "
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="
                                        standardIntakeV2Busy
                                        !== null
                                    "
                                    @click="
                                        startStandardIntakeV2Session
                                    "
                                >
                                    {{
                                        standardIntakeV2Busy
                                            === 'session'
                                            ? 'Preparando...'
                                            : standardIntakeV2State
                                                  ?.session
                                                  ?.status
                                              === 'finalized'
                                              ? 'Nueva sesión'
                                              : 'Iniciar sesión'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="
                                        standardIntakeV2Busy
                                        !== null
                                    "
                                    @click="
                                        standardIntakeV2CanonicalOpen =
                                            !standardIntakeV2CanonicalOpen
                                    "
                                >
                                    {{
                                        standardIntakeV2CanonicalOpen
                                            ? 'Ocultar modelo interno'
                                            : 'Mostrar modelo interno'
                                    }}
                                </button>
</div>
                            <div
                                v-show="standardIntakeV2CanonicalOpen"
                                data-d17-canonical-body
                            >


                            <div
                                v-if="
                                    standardIntakeV2State
                                        ?.usable_dataset
                                        ?.available
                                "
                                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/20 dark:text-emerald-300"
                            >
                                <span class="font-black">
                                    Datos preparados disponibles.
                                </span>
                                Run
                                {{
                                    standardIntakeV2State
                                        .usable_dataset
                                        .dataset
                                        ?.processing_run_id
                                    ?? '—'
                                }}
                                · Batch
                                {{
                                    standardIntakeV2State
                                        .usable_dataset
                                        .dataset
                                        ?.intake_batch_id
                                    ?? '—'
                                }}
                                ·
                                {{
                                    standardIntakeV2State
                                        .usable_dataset
                                        .dataset
                                        ?.normalized_row_count
                                    ?? 0
                                }}
                                filas normalizadas.
                            </div>

                            <div
                                v-if="standardIntakeV2Error"
                                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                            >
                                {{ standardIntakeV2Error }}
                            </div>

                            <div
                                v-if="
                                    standardIntakeV2State
                                        ?.session
                                "
                                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-background/70 p-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Decisiones resueltas
                                    </p>

                                    <p
                                        class="mt-1 text-xl font-black"
                                    >
                                        {{
                                            standardIntakeV2ResolvedCount()
                                        }}
                                        /
                                        {{
                                            standardIntakeV2DomainCount()
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="flex flex-wrap gap-2"
                                >
                                    <button
                                        v-if="
                                            standardIntakeV2State
                                                ?.actions
                                                ?.can_resolve
                                        "
                                        type="button"
                                        class="cursor-pointer rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            standardIntakeV2Busy
                                            !== null
                                        "
                                        @click="
                                            resolveStandardIntakeV2Session
                                        "
                                    >
                                        {{
                                            standardIntakeV2Busy
                                                === 'resolve'
                                                ? 'Validando relaciones...'
                                                : 'Validar relaciones'
                                        }}
                                    </button>

                                    <button
                                        v-if="
                                            standardIntakeV2State
                                                ?.actions
                                                ?.can_materialize
                                        "
                                        type="button"
                                        class="cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            standardIntakeV2Busy
                                            !== null
                                        "
                                        @click="
                                            materializeStandardIntakeV2Session
                                        "
                                    >
                                        {{
                                            standardIntakeV2Busy
                                                === 'materialize'
                                                ? 'Preparando staging...'
                                                : 'Preparar staging'
                                        }}
                                    </button>
                                </div>
                            </div>

                            <!-- D15F_RELATIONAL_FEEDBACK_UI -->
                            <div
                                v-if="
                                    standardIntakeV2State
                                        ?.session
                                        ?.relational_validation
                                "
                                class="mt-4 rounded-xl border p-3 text-sm"
                                :class="
                                    standardIntakeV2State
                                        ?.session
                                        ?.relational_validation
                                        ?.valid
                                        ? 'border-emerald-200 bg-emerald-50/70 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/20 dark:text-emerald-300'
                                        : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300'
                                "
                            >
                                <p class="font-black">
                                    {{
                                        standardIntakeV2State
                                            ?.session
                                            ?.relational_validation
                                            ?.valid
                                            ? 'Relaciones entre dominios validadas.'
                                            : 'Hay relaciones entre dominios que requieren corrección.'
                                    }}
                                </p>

                                <p class="mt-1 text-xs">
                                    {{
                                        standardIntakeV2State
                                            ?.session
                                            ?.relational_validation
                                            ?.error_count
                                        ?? 0
                                    }}
                                    error(es) ·
                                    {{
                                        standardIntakeV2State
                                            ?.session
                                            ?.relational_validation
                                            ?.warning_count
                                        ?? 0
                                    }}
                                    advertencia(s)
                                </p>

                                <ul
                                    v-if="
                                        standardIntakeV2RelationErrors()
                                            .length
                                    "
                                    class="mt-2 list-disc space-y-1 pl-5 text-xs"
                                >
                                    <li
                                        v-for="
                                            (message, index) in
                                            standardIntakeV2RelationErrors()
                                        "
                                        :key="`relation-error-${index}`"
                                    >
                                        {{ message }}
                                    </li>
                                </ul>

                                <ul
                                    v-if="
                                        standardIntakeV2RelationWarnings()
                                            .length
                                    "
                                    class="mt-2 list-disc space-y-1 pl-5 text-xs"
                                >
                                    <li
                                        v-for="
                                            (message, index) in
                                            standardIntakeV2RelationWarnings()
                                        "
                                        :key="`relation-warning-${index}`"
                                    >
                                        {{ message }}
                                    </li>
                                </ul>

                                <p
                                    v-if="
                                        standardIntakeV2State
                                            ?.session
                                            ?.relational_validation
                                            ?.valid
                                        === false
                                    "
                                    class="mt-2 text-xs font-semibold"
                                >
                                    Corrige la decisión o el archivo del
                                    dominio correspondiente y vuelve a
                                    validar las relaciones.
                                </p>
                            </div>

                            <div
                                v-if="
                                    standardIntakeV2State
                                        ?.session
                                        ?.status
                                    === 'finalized'
                                "
                                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/20 dark:text-emerald-300"
                            >
                                <span class="font-black">
                                    Staging preparado.
                                </span>
                                Batch
                                {{
                                    standardIntakeV2State
                                        .session
                                        .resulting_intake_batch_id
                                    ?? '—'
                                }}.
                                El análisis de calidad y la normalización
                                continúan como acciones manuales separadas.
                            </div>

                            <div
                                class="mt-4 grid gap-3 xl:grid-cols-2"
                            >
                                <article
                                    v-for="
                                        domain in
                                        standardIntakeV2State
                                            ?.domains
                                        ?? []
                                    "
                                    :key="domain.key"
                                    class="rounded-xl border bg-background p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-black"
                                            >
                                                {{ domain.label }}
                                            </h4>

                                            <p
                                                class="mt-1 text-xs leading-5 text-muted-foreground"
                                            >
                                                {{
                                                    domain.description
                                                }}
                                            </p>
                                        </div>

                                        <span
                                            class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase"
                                            :class="
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )?.status
                                                    === 'valid'
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                    : standardIntakeV2Delivery(
                                                          domain.key,
                                                      )?.status
                                                        === 'invalid'
                                                        ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300'
                                                        : 'border-slate-300 bg-muted/40 text-muted-foreground'
                                            "
                                        >
                                            {{
                                                standardIntakeV2StatusLabel(
                                                    standardIntakeV2Delivery(
                                                        domain.key,
                                                    )?.status,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            standardIntakeV2Delivery(
                                                domain.key,
                                            )
                                            && !standardIntakeV2SourceNative(
                                                domain.key,
                                            )
                                        "
                                        class="mt-3 rounded-lg border bg-muted/20 p-3 text-xs"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-x-4 gap-y-1"
                                        >
                                            <span>
                                                <strong>
                                                    Decisión:
                                                </strong>
                                                {{
                                                    standardIntakeV2ModeLabel(
                                                        standardIntakeV2Delivery(
                                                            domain.key,
                                                        )
                                                            ?.delivery_mode
                                                        ?? null,
                                                    )
                                                }}
                                            </span>

                                            <span>
                                                <strong>
                                                    Filas:
                                                </strong>
                                                {{
                                                    standardIntakeV2Delivery(
                                                        domain.key,
                                                    )
                                                        ?.accepted_row_count
                                                    ?? 0
                                                }}
                                            </span>
                                        </div>

                                        <p
                                            v-if="
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.original_filename
                                            "
                                            class="mt-2 break-all text-muted-foreground"
                                        >
                                            {{
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.original_filename
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.delivery_mode
                                                === 'carry_forward'
                                            "
                                            class="mt-2 text-muted-foreground"
                                        >
                                            Run
                                            {{
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.carry_forward_processing_run_id
                                                ?? '—'
                                            }}
                                            · Batch
                                            {{
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.carry_forward_intake_batch_id
                                                ?? '—'
                                            }}
                                        </p>
                                    </div>

                                    <!-- D17_SOURCE_NATIVE_FILE_UI -->
                                    <div
                                        v-if="
                                            standardIntakeV2SourceNative(
                                                domain.key,
                                            )
                                        "
                                        class="mt-3 rounded-lg border border-sky-200 bg-sky-50/50 p-3 text-xs dark:border-sky-900/60 dark:bg-sky-950/20"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="font-black text-sky-800 dark:text-sky-300"
                                                >
                                                    Archivo fuente recibido
                                                </p>

                                                <p
                                                    class="mt-1 break-all font-semibold"
                                                >
                                                    {{
                                                        standardIntakeV2SourceNative(
                                                            domain.key,
                                                        )
                                                            ?.original_filename
                                                    }}
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full border border-sky-200 px-2 py-1 text-[10px] font-bold text-sky-700 dark:border-sky-900 dark:text-sky-300"
                                            >
                                                {{
                                                    standardIntakeV2SourceStatusLabel(
                                                        standardIntakeV2SourceNative(
                                                            domain.key,
                                                        )
                                                            ?.status,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground"
                                        >
                                            <span>
                                                <strong>Formato:</strong>
                                                {{
                                                    standardIntakeV2SourceFormatLabel(
                                                        standardIntakeV2SourceNative(
                                                            domain.key,
                                                        )
                                                            ?.source_format,
                                                    )
                                                }}
                                            </span>

                                            <span>
                                                <strong>Tamaño:</strong>
                                                {{
                                                    standardIntakeFileSizeLabel(
                                                        standardIntakeV2SourceNative(
                                                            domain.key,
                                                        )
                                                            ?.source_size_bytes
                                                            ?? 0,
                                                    )
                                                }}
                                            </span>

                                            <span>
                                                <strong>Filas:</strong>
                                                {{
                                                    standardIntakeV2SourceNative(
                                                        domain.key,
                                                    )
                                                        ?.source_row_count
                                                    ?? 0
                                                }}
                                            </span>

                                            <span>
                                                <strong>Hojas:</strong>
                                                {{
                                                    standardIntakeV2SourceSheets(
                                                        domain.key,
                                                    ).length
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                standardIntakeV2SourceSheets(
                                                    domain.key,
                                                ).length
                                            "
                                            class="mt-3 space-y-2"
                                        >
                                            <p class="font-black">
                                                Estructura detectada
                                            </p>

                                            <div
                                                v-for="
                                                    (sheet, sheetIndex) in
                                                    standardIntakeV2SourceSheets(
                                                        domain.key,
                                                    )
                                                "
                                                :key="`${domain.key}-source-${sheetIndex}`"
                                                class="rounded-md border bg-background/70 p-2"
                                            >
                                                <div
                                                    class="flex flex-wrap justify-between gap-2"
                                                >
                                                    <strong>
                                                        {{
                                                            standardIntakeV2SourceSheetLabel(
                                                                domain.key,
                                                                sheet,
                                                                sheetIndex,
                                                            )
                                                        }}
                                                    </strong>

                                                    <span
                                                        class="text-muted-foreground"
                                                    >
                                                        {{
                                                            standardIntakeV2SourceSheetRows(
                                                                sheet,
                                                            )
                                                        }}
                                                        filas ·
                                                        {{
                                                            standardIntakeV2SourceSheetColumns(
                                                                sheet,
                                                            )
                                                        }}
                                                        columnas
                                                    </span>
                                                </div>

                                                <div
                                                    v-if="
                                                        standardIntakeV2SourceColumnLabels(
                                                            sheet,
                                                        ).length
                                                    "
                                                    class="mt-2 flex flex-wrap gap-1"
                                                >
                                                    <span
                                                        v-for="
                                                            (
                                                                column,
                                                                columnIndex
                                                            ) in
                                                            standardIntakeV2SourceColumnLabels(
                                                                sheet,
                                                            ).slice(0, 8)
                                                        "
                                                        :key="`${domain.key}-${sheetIndex}-${columnIndex}`"
                                                        class="rounded border px-1.5 py-0.5 text-[10px]"
                                                    >
                                                        {{ column }}
                                                    </span>

                                                    <span
                                                        v-if="
                                                            standardIntakeV2SourceColumnLabels(
                                                                sheet,
                                                            ).length > 8
                                                        "
                                                        class="text-[10px] text-muted-foreground"
                                                    >
                                                        +{{
                                                            standardIntakeV2SourceColumnLabels(
                                                                sheet,
                                                            ).length - 8
                                                        }}
                                                        más
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- D15F_DOMAIN_FEEDBACK_UI -->
                                    <div
                                        v-if="
                                            standardIntakeV2DomainErrorsFor(
                                                domain.key,
                                            ).length
                                        "
                                        class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                    >
                                        <p class="font-black">
                                            Requiere corrección
                                        </p>

                                        <ul
                                            class="mt-1 list-disc space-y-1 pl-5"
                                        >
                                            <li
                                                v-for="
                                                    (message, index) in
                                                    standardIntakeV2DomainErrorsFor(
                                                        domain.key,
                                                    )
                                                "
                                                :key="`${domain.key}-error-${index}`"
                                            >
                                                {{ message }}
                                            </li>
                                        </ul>
                                    </div>

                                    <div
                                        v-if="
                                            standardIntakeV2DomainWarningsFor(
                                                domain.key,
                                            ).length
                                        "
                                        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300"
                                    >
                                        <p class="font-black">
                                            Advertencias
                                        </p>

                                        <ul
                                            class="mt-1 list-disc space-y-1 pl-5"
                                        >
                                            <li
                                                v-for="
                                                    (message, index) in
                                                    standardIntakeV2DomainWarningsFor(
                                                        domain.key,
                                                    )
                                                "
                                                :key="`${domain.key}-warning-${index}`"
                                            >
                                                {{ message }}
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- D15E_DOMAIN_TEMPLATE_UI -->
                                    <p
                                        v-if="
                                            standardIntakeV2Delivery(
                                                domain.key,
                                            )
                                                ?.source_native_supported
                                        "
                                        class="mt-3 text-[11px] font-semibold text-muted-foreground"
                                    >
                                        Estructura objetivo LAUDA
                                        (referencia para la transformación)
                                    </p>
                                    <div
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <a
                                            :href="`${standardIntakeV2BaseUrl}/templates/${encodeURIComponent(domain.key)}/csv`"
                                            class="rounded-lg border px-3 py-2 text-xs font-bold"
                                        >
                                            Plantilla CSV
                                        </a>

                                        <a
                                            :href="`${standardIntakeV2BaseUrl}/templates/${encodeURIComponent(domain.key)}/xlsx`"
                                            class="rounded-lg border px-3 py-2 text-xs font-bold"
                                        >
                                            Plantilla XLSX
                                        </a>

                                        <button
                                            v-if="
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.source_native_supported
                                            "
                                            type="button"
                                            class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold"
                                            @click="
                                                toggleSqlServerExtraction(
                                                    domain.key,
                                                )
                                            "
                                        >
                                            {{
                                                sqlServerExtractionForm(
                                                    domain.key,
                                                ).open
                                                    ? 'Cerrar asistencia SQL Server'
                                                    : 'Preparar extracción SQL Server'
                                            }}
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            standardIntakeV2Delivery(
                                                domain.key,
                                            )
                                                ?.source_native_supported
                                            && sqlServerExtractionForm(
                                                domain.key,
                                            ).open
                                        "
                                        class="mt-4 rounded-xl border bg-muted/20 p-4"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-3"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-bold"
                                                >
                                                    Preparar extracción desde SQL Server
                                                </p>

                                                <p
                                                    class="mt-1 max-w-3xl text-xs leading-5 text-muted-foreground"
                                                >
                                                    Pega únicamente la estructura de la tabla.
                                                    LAUDA preparará un SELECT de solo lectura.
                                                    No necesitamos acceso al servidor, usuario,
                                                    contraseña ni cadena de conexión.
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[11px] font-semibold text-muted-foreground"
                                            >
                                                Solo lectura
                                            </span>
                                        </div>

                                        <div
                                            class="mt-4 grid gap-3 md:grid-cols-2"
                                        >
                                            <label
                                                class="block"
                                            >
                                                <span
                                                    class="text-xs font-semibold"
                                                >
                                                    Esquema
                                                </span>

                                                <input
                                                    v-model="
                                                        sqlServerExtractionForm(
                                                            domain.key,
                                                        ).schema_name
                                                    "
                                                    type="text"
                                                    maxlength="128"
                                                    placeholder="dbo"
                                                    class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                                />
                                            </label>

                                            <label
                                                class="block"
                                            >
                                                <span
                                                    class="text-xs font-semibold"
                                                >
                                                    Tabla
                                                </span>

                                                <input
                                                    v-model="
                                                        sqlServerExtractionForm(
                                                            domain.key,
                                                        ).table_name
                                                    "
                                                    type="text"
                                                    maxlength="128"
                                                    placeholder="CTES"
                                                    class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                                />
                                            </label>
                                        </div>

                                        <label
                                            class="mt-3 block"
                                        >
                                            <span
                                                class="text-xs font-semibold"
                                            >
                                                Estructura de la tabla
                                            </span>

                                            <textarea
                                                v-model="
                                                    sqlServerExtractionForm(
                                                        domain.key,
                                                    ).structure_text
                                                "
                                                maxlength="50000"
                                                rows="9"
                                                class="mt-1 w-full rounded-lg border bg-background px-3 py-2 font-mono text-xs leading-5"
                                                placeholder="[CODIGO] varchar(20)&#10;[NOMBRE] varchar(150)&#10;[RNC] varchar(20)"
                                            ></textarea>

                                            <span
                                                class="mt-1 block text-[11px] leading-5 text-muted-foreground"
                                            >
                                                Puedes pegar un CREATE TABLE de SQL Server
                                                o una lista de campos con sus tipos.
                                                No pegues datos, credenciales ni consultas
                                                de actualización.
                                            </span>
                                        </label>

                                        <div
                                            v-if="
                                                sqlServerExtractionForm(
                                                    domain.key,
                                                ).error
                                            "
                                            class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300"
                                        >
                                            {{
                                                sqlServerExtractionForm(
                                                    domain.key,
                                                ).error
                                            }}
                                        </div>

                                        <div
                                            class="mt-3 flex flex-wrap gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg bg-foreground px-3 py-2 text-xs font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    sqlServerExtractionForm(
                                                        domain.key,
                                                    ).busy
                                                "
                                                @click="
                                                    generateSqlServerExtractionPreview(
                                                        domain.key,
                                                    )
                                                "
                                            >
                                                {{
                                                    sqlServerExtractionForm(
                                                        domain.key,
                                                    ).busy
                                                        ? 'Generando...'
                                                        : 'Generar consulta'
                                                }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="
                                                sqlServerExtractionForm(
                                                    domain.key,
                                                ).preview
                                            "
                                            class="mt-5 space-y-4"
                                        >
                                            <div>
                                                <p
                                                    class="text-xs font-bold"
                                                >
                                                    Campos detectados
                                                    ·
                                                    {{
                                                        sqlServerExtractionForm(
                                                            domain.key,
                                                        ).preview?.field_count
                                                    }}
                                                </p>

                                                <div
                                                    class="mt-2 flex flex-wrap gap-1.5"
                                                >
                                                    <span
                                                        v-for="
                                                            field in sqlServerExtractionForm(
                                                                domain.key,
                                                            ).preview?.fields
                                                        "
                                                        :key="
                                                            `${domain.key}-sql-field-${field.name}`
                                                        "
                                                        class="rounded-md border bg-background px-2 py-1 font-mono text-[11px]"
                                                    >
                                                        {{ field.name }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div>
                                                <div
                                                    class="flex flex-wrap items-center justify-between gap-2"
                                                >
                                                    <p
                                                        class="text-xs font-bold"
                                                    >
                                                        Consulta de extracción
                                                    </p>

                                                    <button
                                                        type="button"
                                                        class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold"
                                                        @click="
                                                            copySqlServerExtractionQuery(
                                                                domain.key,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            sqlServerExtractionForm(
                                                                domain.key,
                                                            ).copied
                                                                ? 'Copiado'
                                                                : 'Copiar consulta'
                                                        }}
                                                    </button>
                                                </div>

                                                <pre
                                                    class="mt-2 max-h-80 overflow-auto whitespace-pre rounded-lg border bg-background p-3 font-mono text-xs leading-5"
                                                >{{ sqlServerExtractionForm(
                                                    domain.key,
                                                ).preview?.query }}</pre>
                                            </div>

                                            <div>
                                                <label
                                                    class="block max-w-xs"
                                                >
                                                    <span
                                                        class="text-xs font-semibold"
                                                    >
                                                        Formato de entrega
                                                    </span>

                                                    <select
                                                        v-model="
                                                            sqlServerExtractionForm(
                                                                domain.key,
                                                            ).export_format
                                                        "
                                                        class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                                    >
                                                        <option
                                                            value="csv"
                                                        >
                                                            CSV
                                                        </option>

                                                        <option
                                                            value="xlsx"
                                                        >
                                                            Excel (.xlsx)
                                                        </option>
                                                    </select>
                                                </label>

                                                <div
                                                    class="mt-3 rounded-lg border bg-background p-3"
                                                >
                                                    <p
                                                        class="text-xs font-bold"
                                                    >
                                                        Cómo entregar el archivo
                                                    </p>

                                                    <ol
                                                        class="mt-2 list-decimal space-y-1 pl-5 text-xs leading-5 text-muted-foreground"
                                                    >
                                                        <li
                                                            v-for="
                                                                (
                                                                    instruction,
                                                                    index
                                                                ) in sqlServerExtractionInstructions(
                                                                    domain.key,
                                                                )
                                                            "
                                                            :key="
                                                                `${domain.key}-sql-export-${index}`
                                                            "
                                                        >
                                                            {{ instruction }}
                                                        </li>
                                                    </ol>
                                                </div>

                                                <p
                                                    class="mt-3 text-[11px] leading-5 text-muted-foreground"
                                                >
                                                    El query se ejecuta únicamente en
                                                    el entorno del cliente. Después de
                                                    exportar el resultado, súbelo abajo
                                                    como archivo fuente de este dominio.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            standardIntakeV2CanEditDomains()
                                        "
                                        class="mt-4 space-y-3"
                                    >
                                        <p
                                            v-if="
                                                standardIntakeV2Delivery(
                                                    domain.key,
                                                )
                                                    ?.source_native_supported
                                            "
                                            class="text-[11px] leading-5 text-muted-foreground"
                                        >
                                            Archivo fuente del cliente.
                                            Puede conservar las columnas y la
                                            estructura original de su sistema.
                                            LAUDA realizará la transformación
                                            hacia el modelo objetivo.
                                        </p>
                                        <div>
                                            <input
                                                type="file"
                                                accept=".csv,.xlsx"
                                                class="block w-full cursor-pointer rounded-lg border bg-background px-3 py-2 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold"
                                                :disabled="
                                                    standardIntakeV2Busy
                                                    !== null
                                                "
                                                @change="
                                                    selectStandardIntakeV2File(
                                                        domain.key,
                                                        $event,
                                                    )
                                                "
                                            />

                                            <p
                                                v-if="
                                                    standardIntakeV2SelectedFile(
                                                        domain.key,
                                                    )
                                                "
                                                class="mt-1 break-all text-[11px] text-muted-foreground"
                                            >
                                                Seleccionado:
                                                <strong>
                                                    {{
                                                        standardIntakeV2SelectedFile(
                                                            domain.key,
                                                        )
                                                            ?.name
                                                    }}
                                                </strong>
                                            </p>
                                        </div>

                                        <div
                                            class="flex flex-wrap gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg bg-foreground px-3 py-2 text-xs font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    !standardIntakeV2SelectedFile(
                                                        domain.key,
                                                    )
                                                    || standardIntakeV2Busy
                                                        !== null
                                                "
                                                @click="
                                                    uploadStandardIntakeV2Domain(
                                                        domain.key,
                                                    )
                                                "
                                            >
                                                {{
                                                    standardIntakeV2Busy
                                                        === `upload:${domain.key}`
                                                        ? 'Subiendo...'
                                                        : standardIntakeV2Delivery(
                                                                domain.key,
                                                            )
                                                              ?.source_native_supported
                                                            ? 'Subir archivo fuente'
                                                            : 'Subir CSV/XLSX'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    standardIntakeV2Busy
                                                    !== null
                                                "
                                                @click="
                                                    noDataStandardIntakeV2Domain(
                                                        domain.key,
                                                    )
                                                "
                                            >
                                                No tengo datos
                                            </button>

                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg border border-emerald-300 px-3 py-2 text-xs font-bold text-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-900 dark:text-emerald-300"
                                                :disabled="
                                                    !standardIntakeV2CanCarryForward()
                                                    || standardIntakeV2Busy
                                                        !== null
                                                "
                                                @click="
                                                    carryForwardStandardIntakeV2Domain(
                                                        domain.key,
                                                    )
                                                "
                                            >
                                                Reutilizar datos preparados
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <p
                                v-if="
                                    !standardIntakeV2State
                                        ?.session
                                "
                                class="mt-4 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                Inicia una sesión para gestionar los siete
                                dominios.
                            </p>

                            </div>
</section>

                        <div
                            v-if="
                                standardIntakeReport
                                || standardIntakeIngestionReport?.ingestion
                            "
                            class="mt-5 rounded-xl border bg-muted/20 p-4 dark:border-slate-800"
                        >
                            <!-- P6_R5_PERSISTED_PROCESSING_PANEL -->
                            <div
                                v-if="
                                    !standardIntakeReport
                                    && standardIntakeIngestionReport?.ingestion
                                "
                                class="mt-5 space-y-4 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/20"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-black text-indigo-800 dark:text-indigo-300"
                                        >
                                            Último procesamiento persistido
                                        </p>

                                        <p
                                            class="mt-1 text-xs leading-5 text-muted-foreground"
                                        >
                                            Este estado fue recuperado del último staging
                                            completado. Puedes continuar desde aquí. Si la
                                            fuente necesita correcciones, selecciona arriba
                                            una versión corregida y repite el flujo de
                                            validación, staging y análisis de calidad.
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
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
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <div class="rounded-lg border bg-background/70 p-3">
                                        <p class="text-xs text-muted-foreground">
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

                                    <div class="rounded-lg border bg-background/70 p-3">
                                        <p class="text-xs text-muted-foreground">
                                            Dominios
                                        </p>
                                        <p class="mt-1 text-lg font-black">
                                            {{
                                                standardIntakeIngestionReport
                                                    .ingestion
                                                    .domain_count
                                            }}
                                        </p>
                                    </div>

                                    <div class="rounded-lg border bg-background/70 p-3">
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

                                    <div class="rounded-lg border bg-background/70 p-3">
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
                                    class="rounded-xl border bg-background/70 p-4"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p class="text-sm font-bold">
                                                Calidad y normalización
                                            </p>

                                            <p
                                                v-if="
                                                    standardIntakeProfileReport
                                                        ?.processing
                                                "
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Run #{{
                                                    standardIntakeProfileReport
                                                        .processing
                                                        .run_id
                                                }}
                                                ·
                                                {{
                                                    standardIntakeNormalizationReport
                                                        ?.processing
                                                        ?.status
                                                    ?? standardIntakeProfileReport
                                                        .processing
                                                        .status
                                                }}
                                            </p>
                                        </div>

                                        <button
                                            v-if="
                                                !standardIntakeProfileReport
                                                    ?.processing
                                            "
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
                                                    : 'Analizar calidad'
                                            }}
                                        </button>
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
                                            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
                                        >
                                            <div class="rounded-lg border bg-background p-3">
                                                <p class="text-xs text-muted-foreground">
                                                    Perfiladas
                                                </p>
                                                <p class="mt-1 text-lg font-black">
                                                    {{
                                                        standardIntakeProfileReport
                                                            .processing
                                                            .profiled_row_count
                                                        ?? 0
                                                    }}
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-background p-3">
                                                <p class="text-xs text-muted-foreground">
                                                    Incidencias
                                                </p>
                                                <p class="mt-1 text-lg font-black">
                                                    {{
                                                        standardIntakeProfileReport
                                                            .processing
                                                            .issue_count
                                                        ?? 0
                                                    }}
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-background p-3">
                                                <p class="text-xs text-muted-foreground">
                                                    Bloqueantes
                                                </p>
                                                <p class="mt-1 text-lg font-black">
                                                    {{
                                                        standardIntakeProfileReport
                                                            .processing
                                                            .blocking_issue_count
                                                        ?? 0
                                                    }}
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-background p-3">
                                                <p class="text-xs text-muted-foreground">
                                                    Advertencias
                                                </p>
                                                <p class="mt-1 text-lg font-black">
                                                    {{
                                                        standardIntakeProfileReport
                                                            .processing
                                                            .warning_issue_count
                                                        ?? 0
                                                    }}
                                                </p>
                                            </div>

                                            <div class="rounded-lg border bg-background p-3">
                                                <p class="text-xs text-muted-foreground">
                                                    Informativas
                                                </p>
                                                <p class="mt-1 text-lg font-black">
                                                    {{
                                                        standardIntakeInformationalIssueCount()
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                standardIntakeNormalizationReport
                                                    ?.processing
                                                    ?.status
                                                === 'completed'
                                            "
                                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-bold text-emerald-800 dark:text-emerald-300"
                                                >
                                                    Normalización completada
                                                </p>
                                                <p class="mt-1 text-xs text-muted-foreground">
                                                    Filas normalizadas:
                                                    {{
                                                        standardIntakeNormalizationReport
                                                            .processing
                                                            .normalized_row_count
                                                        ?? 0
                                                    }}
                                                    · Cambios:
                                                    {{
                                                        standardIntakeNormalizationReport
                                                            .processing
                                                            .normalization_change_count
                                                        ?? 0
                                                    }}
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:border-emerald-900 dark:text-emerald-300"
                                            >
                                                completed
                                            </span>
                                        </div>

                                        <div
                                            v-else-if="
                                                (
                                                    standardIntakeProfileReport
                                                        .processing
                                                        .blocking_issue_count
                                                    ?? 0
                                                ) > 0
                                            "
                                            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300"
                                        >
                                            Existen incidencias bloqueantes. La fuente debe
                                            corregirse antes de normalizar. Selecciona arriba
                                            la versión corregida y repite: Validar archivo →
                                            Ingresar a staging → Analizar calidad. Si el
                                            contenido cambia, LAUDA crea un nuevo batch y
                                            conserva el procesamiento anterior como historial.
                                        </div>

                                        <div
                                            v-else
                                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                                        >
                                            <p
                                                class="text-sm font-bold text-emerald-800 dark:text-emerald-300"
                                            >
                                                Calidad habilitada para normalización
                                            </p>

                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    !canNormalizeStandardIntake()
                                                "
                                                @click="normalizeStandardIntakeBatch"
                                            >
                                                {{
                                                    standardIntakeNormalizing
                                                        ? 'Normalizando...'
                                                        : 'Normalizar datos'
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
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
                                                            standardIntakeNormalizationReport
                                                                ?.processing
                                                                ?.status
                                                            ?? standardIntakeProfileReport
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
                                                <div
                                                    class="rounded-lg border bg-background/70 p-3"
                                                >
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Informativas
                                                    </p>
                                                    <p
                                                        class="mt-1 text-lg font-black"
                                                    >
                                                        {{
                                                            standardIntakeInformationalIssueCount()
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
