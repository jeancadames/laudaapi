import type { BusinessQuoteDetail } from './businessQuoteAcceptance';
import type { CheckoutInvoice } from './businessQuoteCheckout';

export type ImplementationAction =
    | 'request'
    | 'start'
    | 'block'
    | 'resume'
    | 'deliver'
    | 'complete'
    | 'request_changes'
    | 'cancel';
export type BusinessImplementation = {
    quote: BusinessQuoteDetail;
    invoice: CheckoutInvoice;
    admin: boolean;
    payment_hold: string | null;
    actions: ImplementationAction[];
    action_url: string;
    activation_url: string | null;
    order: null | {
        id: number;
        status: string;
        status_label: string;
        version: number;
        assigned_name: string | null;
        created_at: string;
        events: {
            action: string;
            status_label: string;
            actor_name: string;
            note: string;
            at: string;
            version: number;
        }[];
    };
};
