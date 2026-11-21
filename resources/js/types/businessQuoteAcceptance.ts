import type {
    Capacity,
    Implementation,
    Product,
    QuoteResult,
} from './businessCatalog';

export type QuoteAcceptance = {
    accepted_at: string;
    accepted_name: string;
    snapshot_hash: string;
};

export type BusinessQuoteSummary = {
    public_id: string;
    company_id: number;
    company_name: string;
    offer_name: string;
    level_name: string;
    currency: string;
    timezone: string;
    recurring_cents: number;
    billing_cycle: 'monthly' | 'yearly';
    issued_at: string;
    valid_until: string;
    status: 'available' | 'accepted' | 'unavailable';
    url: string;
};

export type BusinessQuoteDetail = BusinessQuoteSummary & {
    snapshot: QuoteResult & {
        timezone: string;
        products: Record<string, Product>;
        level: Capacity;
        implementation: Implementation;
        terms: string;
        tax_rate: number;
        addons: Record<
            string,
            {
                name: string;
                quantity: number;
                recurring: boolean;
                amount_cents: number;
            }
        >;
    };
    snapshot_hash: string;
    can_accept: boolean;
    unavailable_reason: string | null;
    accept_url: string;
    checkout_url: string | null;
    acceptance: QuoteAcceptance | null;
};
