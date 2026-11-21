import type { BusinessQuoteDetail } from './businessQuoteAcceptance';
import type { CheckoutInvoice } from './businessQuoteCheckout';
export type BusinessActivation = {
    quote: BusinessQuoteDetail;
    invoice: CheckoutInvoice;
    admin: boolean;
    solutions: Record<
        string,
        { name: string; ready: boolean; reason: string | null }
    >;
    reasons: string[];
    state: 'pending' | 'active' | 'review' | 'expired';
    can_activate: boolean;
    recurring: {
        subtotal_cents: number;
        tax_cents: number;
        total_cents: number;
    };
    implementation_version: number | null;
    back_url: string;
    action_url: string | null;
    renewal_url: string | null;
    current_period: null | {
        number: number;
        starts_at: string;
        ends_at: string;
    };
    contract: null | {
        id: number;
        subscription_id: number;
        activated_at: string;
        period_ends_at: string;
        activated_name: string;
        technical_evidence: Record<string, { name: string; note: string }>;
        automatic_renewal: false;
    };
};
