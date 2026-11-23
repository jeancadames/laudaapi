// Contrato de autoservicio Business v1. Los valores exportados son ejemplos sintéticos para desarrollo.
export type Product = 'ecf' | 'cumplimiento' | 'pos' | 'bys';
export type Cycle = 'monthly' | 'yearly';
export type Level = 'initial' | 'growth' | 'scale';

export interface Selection {
    products: Product[];
    level: Level;
    cycle: Cycle;
}

export interface Catalog {
    version: number;
    company_name: string;
    products: { code: Product; name: string; task: string; includes: string[]; inclusion_codes: string[] }[];
    cycles: Cycle[];
    levels: { code: Level; name: string }[];
    terms: string;
    resume_purchase_id: string | null;
}

// Prop Inertia de Business/Package/Build. Las URLs de la página son provistas por Laravel.
export interface Configurator {
    company: { id: number; name: string };
    availability: 'available' | 'catalog_unpublished';
    catalog: Catalog | null;
    catalog_url: string;
    calculate_url: string;
    confirm_url: string | null;
    resume_purchase_id?: string | null;
    home_url: string;
}

export interface Preview {
    proposal_ref: string;
    expires_at: string;
    catalog_version: number;
    selection: Selection;
    // Un importe base por componente y por ciclo, con los descuentos de la oferta ya aplicados.
    components: { offer_code: string; name: string; products: Product[]; net_cents: number }[];
    capacity: { documents: number; users: number; branches: number; registers: number };
    capacity_cycle: 'monthly';
    implementation: { code: 'guided'; name: string; scope: string };
    terms: string;
    currency: 'DOP';
    recurring: Amount;
    one_time: Amount;
    due_today: Amount;
    due_today_cents: number;
    automatic_renewal: boolean;
}

export interface Amount {
    net_cents: number;
    tax_cents: number;
    total_cents: number;
}

export interface Confirm {
    proposal_ref: string;
    accepted: true;
    idempotency_key: string;
}

type Link = { label: string; url: string };

export interface HomeSelfServiceProps {
    business_self_service?: {
        configurator_url: string;
        last_purchase_url: string | null;
        pending_purchase: {
            id: string;
            message: string;
            next_action: (Link & { type: string }) | null;
        } | null;
    };
}

export type Payment =
    | { status: 'preparing' | 'processing' | 'paid' | 'reversed'; action: null }
    | { status: 'payable'; action: Link }
    | { status: 'failed'; message: string; action: Link | null };

export type Access =
    | { product: Product; status: 'pending'; message: string; action: null }
    | { product: Product; status: 'link_required' | 'consent_required' | 'ready'; action: Link }
    | { product: Product; status: 'unavailable'; message: string; action: Link | null };

export interface PackagePeriod {
    total_cents: number | null;
    access_until: string | null;
}

export interface Purchase {
    current_period?: PackagePeriod | null;
    scheduled_change?: { effective_at: string; products: Product[]; manage_url: string } | null;
    manage_url?: string | null;
    previous_package_url?: string | null;
    purchase_id: string;
    invoice_number: string | null;
    currency: 'DOP';
    summary: Preview;
    balance_cents: number;
    payment: Payment;
    access: Access[];
}

export type Problem =
    | { code: 'price_changed'; message: string; replacement: Preview }
    | { code: 'invalid_selection'; message: string; fields: Record<string, string[]> }
    | { code: 'existing_purchase'; message: string; purchase_id: string }
    | { code: 'catalog_unavailable' | 'forbidden' | 'purchase_not_found' | 'proposal_expired'
          | 'transition_required' | 'key_conflict' | 'temporarily_unavailable'; message: string };

export const catalog: Catalog = {
    version: 1, company_name: 'Empresa de prueba', resume_purchase_id: null,
    products: [
        { code: 'ecf', name: 'e-CF', task: 'Emitir facturas electrónicas', includes: ['Fácil ECF'], inclusion_codes: ['facil_ecf'] },
        { code: 'cumplimiento', name: 'Cumplimiento', task: 'Gestionar mis obligaciones fiscales', includes: [], inclusion_codes: [] },
        { code: 'pos', name: 'POS', task: 'Registrar ventas en mi negocio', includes: ['Comercio electrónico estándar'], inclusion_codes: ['standard_ecommerce'] },
        { code: 'bys', name: 'BYS', task: 'Gestionar compras de bienes y servicios', includes: [], inclusion_codes: [] },
    ],
    cycles: ['monthly', 'yearly'],
    levels: [{ code: 'initial', name: 'Inicial' }, { code: 'growth', name: 'Crecimiento' }, { code: 'scale', name: 'Escala' }],
    terms: 'Condiciones de prueba para el período elegido.',
};

export const preview: Preview = {
    proposal_ref: 'preview_fixture_a', expires_at: '2030-01-15T17:00:00Z', catalog_version: 1,
    selection: { products: ['ecf', 'cumplimiento'], level: 'initial', cycle: 'monthly' },
    components: [{ offer_code: 'fiscal', name: 'Fiscal', products: ['ecf', 'cumplimiento'], net_cents: 200000 }],
    capacity: { documents: 500, users: 5, branches: 0, registers: 0 }, capacity_cycle: 'monthly',
    implementation: { code: 'guided', name: 'Guiada', scope: 'Guías y plantillas para configurar tu empresa.' },
    currency: 'DOP', terms: catalog.terms, automatic_renewal: false,
    recurring: { net_cents: 200000, tax_cents: 0, total_cents: 200000 },
    one_time: { net_cents: 0, tax_cents: 0, total_cents: 0 }, due_today: { net_cents: 200000, tax_cents: 0, total_cents: 200000 }, due_today_cents: 200000,
};

export const customPreview: Preview = {
    ...preview, proposal_ref: 'preview_fixture_b',
    selection: { ...preview.selection, products: ['ecf', 'cumplimiento', 'bys'] },
    components: [...preview.components, { offer_code: 'bys', name: 'BYS', products: ['bys'], net_cents: 100000 }],
    recurring: { net_cents: 300000, tax_cents: 0, total_cents: 300000 }, due_today: { net_cents: 300000, tax_cents: 0, total_cents: 300000 }, due_today_cents: 300000,
};

export const confirm: Confirm = {
    proposal_ref: preview.proposal_ref, accepted: true, idempotency_key: '25f522a1-a14c-4ba6-b35c-c49957fb2563',
};

export const payable: Purchase = {
    purchase_id: 'a8a13d0a-b601-4485-831e-39e70462b240', invoice_number: 'QA-001',
    currency: 'DOP', summary: preview, balance_cents: 200000,
    payment: { status: 'payable', action: { label: 'Enviar comprobante', url: '/app/business/quotes/a8a13d0a-b601-4485-831e-39e70462b240/checkout' } },
    access: preview.selection.products.map(product => ({
        product, status: 'pending', message: 'Esperando el pago.', action: null,
    })),
};

export const waitingForPayment: Purchase = { ...payable, payment: { status: 'processing', action: null } };
export const failedPayment: Purchase = {
    ...payable, payment: { status: 'failed', message: 'La referencia del comprobante no coincide con el ingreso.', action: payable.payment.action },
};
export const paidNeedsLink: Purchase = {
    ...payable, balance_cents: 0, payment: { status: 'paid', action: null },
    access: preview.selection.products.map(product => ({
        product, status: 'link_required', action: { label: 'Vincular mi empresa', url: '/business/identity' },
    })),
};
export const active: Purchase = {
    ...paidNeedsLink,
    access: preview.selection.products.map(product => ({
        product, status: 'ready', action: { label: product === 'ecf' ? 'Abrir e-CF' : 'Abrir Cumplimiento',
            url: '/business/identity/companies/1/services/' + product + '/launch' },
    })),
};

export const catalogUnavailable: Problem = { code: 'catalog_unavailable', message: 'El catálogo estará disponible más adelante.' };
export const invalidSelection: Problem = {
    code: 'invalid_selection', message: 'Revisa tu selección.', fields: { products: ['Elige al menos una aplicación.'] },
};
export const priceChanged: Problem = {
    code: 'price_changed', message: 'El precio cambió. Revisa el resumen actualizado.',
    replacement: { ...preview, catalog_version: 2, proposal_ref: 'preview_fixture_new_price',
        components: [{ ...preview.components[0], net_cents: 210000 }],
        recurring: { net_cents: 210000, tax_cents: 0, total_cents: 210000 }, due_today: { net_cents: 210000, tax_cents: 0, total_cents: 210000 }, due_today_cents: 210000 },
};
