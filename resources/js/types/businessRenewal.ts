export type RenewalTerms = {
    period_number: number;
    period_starts_at: string;
    period_ends_at: string;
    billing_cycle: 'monthly' | 'yearly';
    base_cents: number;
    discount_cents: number;
    subtotal_cents: number;
    tax_bps: number;
    tax_cents: number;
    total_cents: number;
    commercial_note: string;
};
export type RenewalOffer = {
    id: number;
    revision: number;
    terms: RenewalTerms;
    terms_hash: string;
    valid_until: string;
    offered_at: string;
    superseded: boolean;
    acceptance: null | { name: string; at: string };
    invoice: null | {
        number: string;
        status: string;
        total_cents: number;
        paid_cents: number;
        balance_cents: number;
        covered: boolean;
    };
    can_accept: boolean;
    can_invoice: boolean;
};
export type BusinessRenewal = {
    company_name: string;
    currency: string;
    timezone: string;
    admin: boolean;
    hold: string | null;
    offers: RenewalOffer[];
    current_period: { number: number; starts_at: string; ends_at: string };
    can_propose: boolean;
    next: {
        period_number: number;
        revision: number;
        period_starts_at: string;
        period_ends_at: string;
    };
    back_url: string;
    propose_url: string | null;
    accept_url: string | null;
    invoice_url: string | null;
};
