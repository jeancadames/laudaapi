export type BusinessTransitionPlan = {
    mode: 'after_paid_periods';
    currency: string;
    company_id: number;
    subscriber_id: number;
    source_hash: string;
    not_before: string;
    target_subscription_id?: number;
    credit_cents: number;
    refund_cents: number;
    proration_cents: number;
    subscriptions: { id: number; billing_cycle: string }[];
    periods: {
        subscription_id: number;
        subscription_item_id: number;
        service_id: number;
        name: string;
        invoice_id: number;
        invoice_number: string;
        paid_cents: number;
        starts_at: string;
        ends_at: string;
        basis: 'invoice_period' | 'standalone_calendar';
    }[];
};

export type BusinessTransitionReview =
    | ({ eligible: true; reason: null } & BusinessTransitionPlan)
    | { eligible: false; reason: string };
