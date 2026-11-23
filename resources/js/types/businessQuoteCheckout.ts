import type { BusinessQuoteDetail } from './businessQuoteAcceptance';

export type CheckoutInvoice = {
    number: string;
    status: 'draft' | 'issued' | 'paid' | 'void' | 'overdue';
    issued_on: string | null;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    payment_url: string | null;
    payments: { amount_cents: number; paid_at: string }[];
};

export type BusinessTransfer = {
    instructions: string | null;
    status: 'pending' | 'approved' | 'rejected' | null;
    reason: string | null;
    submit_url: string;
};

export type BusinessQuoteCheckout = {
    purchase_url: string | null;
    transfer?: BusinessTransfer | null;
    quote: BusinessQuoteDetail;
    amounts: {
        lines: Record<
            'recurring' | 'one_time',
            {
                subtotal_cents: number;
                tax_cents: number;
                total_cents: number;
            }
        >;
        subtotal_cents: number;
        tax_cents: number;
        total_cents: number;
    };
    invoice: CheckoutInvoice | null;
    unavailable_reason: string | null;
    create_url: string;
    implementation_url: string | null;
};
