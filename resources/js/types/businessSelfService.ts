/**
 * Autoservicio Business · contrato v1 (docs/autoservicio/contrato-v1.ts).
 *
 * Copia fiel de los tipos del contrato que consume la interfaz. La prueba
 * tests/Frontend/BusinessPackageBuilder.test.mjs compila estos tipos contra el
 * contrato y falla si dejan de coincidir.
 *
 * Laravel calcula; Vue solo presenta. Los importes son enteros en centavos de
 * DOP y ninguna cifra mostrada se calcula en el navegador.
 */

export type Product = 'ecf' | 'cumplimiento' | 'pos' | 'bys';
export type Cycle = 'monthly' | 'yearly';
export type Level = 'initial' | 'growth' | 'scale';

export interface Selection {
    products: Product[];
    level: Level;
    cycle: Cycle;
}

export interface Catalog {
    presets?: { code: string; name: string; products: Product[]; levels: Level[] }[];
    version: number;
    company_name: string;
    products: {
        code: Product;
        name: string;
        task: string;
        includes: string[];
        inclusion_codes: string[];
    }[];
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
    components: {
        offer_code: string;
        name: string;
        products: Product[];
        net_cents: number;
    }[];
    capacity: {
        documents: number;
        users: number;
        branches: number;
        registers: number;
    };
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
export type Payment =
    | {
          status: 'preparing' | 'processing' | 'paid' | 'reversed';
          action: null;
      }
    | { status: 'payable'; action: Link }
    | { status: 'failed'; message: string; action: Link | null };

export type Access =
    | { product: Product; status: 'pending'; message: string; action: null }
    | {
          product: Product;
          status: 'link_required' | 'consent_required' | 'ready';
          action: Link;
      }
    | {
          product: Product;
          status: 'unavailable';
          message: string;
          action: Link | null;
      };

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
    | {
          code: 'invalid_selection';
          message: string;
          fields: Record<string, string[]>;
      }
    | { code: 'existing_purchase'; message: string; purchase_id: string }
    | {
          code:
              | 'catalog_unavailable'
              | 'forbidden'
              | 'purchase_not_found'
              | 'proposal_expired'
              | 'transition_required'
              | 'key_conflict'
              | 'temporarily_unavailable';
          message: string;
      };

/**
 * Compra en curso que Inicio ofrece retomar. No está en contrato-v1.ts: es la
 * forma que envía AppGatewayController. Se usan su mensaje, etiqueta y URL tal
 * cual llegan.
 */
export type PendingPurchaseEntry = {
    id: string;
    message: string;
    next_action: { type: string; label: string; url: string } | null;
};

/** Entrada de Inicio (prop `business_self_service` de App/Home). */
export type SelfServiceHomeEntry = {
    configurator_url: string;
    /** Compra ya activada (`?compra=<uuid>`): «Ver mi paquete». Cede ante `pending_purchase`. */
    last_purchase_url?: string | null;
    pending_purchase: PendingPurchaseEntry | null;
};
