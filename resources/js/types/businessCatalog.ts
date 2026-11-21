import type {
    BusinessTransitionPlan,
    BusinessTransitionReview,
} from './businessTransition';

export type CapacityDimension =
    | 'documents'
    | 'branches'
    | 'registers'
    | 'users';
export type ProductCode = 'ecf' | 'pos' | 'bys' | 'cumplimiento';
export type Product = {
    name: string;
    description: string;
    service_id: number | null;
    available: boolean;
    features: string[];
};
export type LevelPrice = {
    enabled: boolean;
    monthly_cents: number | null;
    annual_cents: number | null;
};
export type Offer = {
    name: string;
    available: boolean;
    products: ProductCode[];
    pricing_method: 'own' | 'sum';
    package_discount_bps: number;
    levels: Record<string, LevelPrice>;
};
export type CapacityPolicy = {
    schema_version: 1;
    scope: 'company';
    users: 'shared_identity' | 'per_solution';
    owner_counts: boolean;
    branches: 'active_branches';
    registers: 'concurrent_sessions';
    excluded_channels: ['cantina', 'autoservicio'];
};
export type Capacity = Record<CapacityDimension, number> & { name: string };
export type Addon = {
    name: string;
    unit: string;
    block_size: number;
    price_cents: number | null;
    recurring: boolean;
    product: ProductCode | null;
    capacity: Partial<Record<CapacityDimension, number>>;
};
export type Implementation = {
    name: string;
    price_cents: number | null;
    hours: number;
    scope: string;
    offers: string[];
};
export type Discount = {
    name: string;
    percent_bps: number;
    company_id: number | null;
    reason: string;
    compatible_annual: boolean;
    compatible_package: boolean;
    offers: string[];
    starts_at: string;
    ends_at: string;
};
export type BusinessCatalog = {
    currency: string;
    timezone: string;
    annual_discount_bps: number;
    quote_valid_days: number | null;
    tax_rate: number | null;
    terms: string;
    products: Partial<Record<ProductCode, Product>>;
    offers: Record<string, Offer>;
    capacities: Record<string, Capacity>;
    capacity_policy?: CapacityPolicy;
    addons: Record<string, Addon>;
    implementations: Record<string, Implementation>;
    discounts: Record<string, Discount>;
};
export type CatalogVersion = {
    id: number;
    version: number;
    status: 'draft' | 'published' | 'retired';
    lock_version: number;
    effective_at: string | null;
    payload: BusinessCatalog;
};
export type QuoteSelection = {
    offer: string;
    level: string;
    cycle: 'monthly' | 'yearly';
    implementation: string;
    addons: Record<string, number>;
    discount: string | null;
};
export type QuoteResult = {
    pos_includes_standard_ecommerce: boolean;
    currency: string;
    capacity: Record<CapacityDimension, number>;
    capacity_policy?: CapacityPolicy | null;
    recurring_cents: number;
    one_time_cents: number;
    annual_discount_cents: number;
    commercial_discount_cents: number;
    billing_cycle: string;
    taxes: null | Record<
        'recurring' | 'one_time',
        { tax_amount: number; line_total: number }
    >;
    active_services: { name: string; amount: string }[];
    transition_requires_review: boolean;
    transition_review?: BusinessTransitionReview | null;
    transition?: BusinessTransitionPlan;
    recommendation: null | {
        name: string;
        savings_cents: number;
        recurring_cents: number;
        selection: QuoteSelection;
    };
};
