import type { Amount, Cycle, Product } from '@/types/businessSelfService';

/**
 * Textos de apoyo del configurador. La tarea, el nombre de la aplicación y las
 * inclusiones llegan del catálogo del servidor; aquí solo hay ayudas breves que
 * el contrato v1 todavía no transporta. Ninguna contiene importes.
 */
const productHelp: Partial<Record<Product, string>> = {
    ecf: 'Emite comprobantes fiscales electrónicos ante la DGII.',
    cumplimiento:
        'Obligaciones, calendario y seguimiento fiscal. La ejecución profesional de obligaciones se contrata por separado.',
    pos: 'Ventas, inventario, crédito y despacho.',
    bys: 'Compras, suplidores y abastecimiento conectados.',
};

/**
 * Redacción en frase por código (`inclusion_codes`). Las etiquetas del servidor
 * empiezan con mayúscula; dentro de «Incluye …» se usa la forma en minúscula
 * salvo los nombres propios. Un código desconocido usa la etiqueta tal cual.
 */
const inclusionText: Record<string, { inline: string; help?: string }> = {
    facil_ecf: {
        inline: 'Fácil ECF',
        help: 'Prepara tus facturas con un formulario, sin programar.',
    },
    standard_ecommerce: { inline: 'comercio electrónico estándar' },
};

export const helpFor = (code: Product) => productHelp[code] ?? '';

export const inclusionCopy = (label: string, code?: string) => {
    const known = code ? inclusionText[code] : undefined;
    return {
        offer: `Incluye ${known?.inline ?? label}`,
        granted: `${label} incluido`,
        help: known?.help ?? '',
    };
};

/** Inclusiones de un producto del catálogo, emparejando etiqueta y código. */
export const inclusionsOf = (product: {
    includes: string[];
    inclusion_codes: string[];
}) =>
    product.includes.map((label, index) =>
        inclusionCopy(label, product.inclusion_codes[index]),
    );

export const cycleCopy: Record<
    Cycle,
    { option: string; price: string; first: string; adverb: string }
> = {
    monthly: {
        option: 'Mensual',
        price: 'Precio mensual',
        first: 'Primer mes',
        adverb: 'mensual',
    },
    yearly: {
        option: 'Anual',
        price: 'Precio anual',
        first: 'Primer año',
        adverb: 'anual',
    },
};

/** RD$2,000 cuando el importe es entero; RD$2,000.50 cuando lleva centavos. */
export const formatMoney = (cents: number, currency: string) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(cents / 100);

/** El contrato omite la fila de impuestos cuando ambos importes son cero. */
export const showsTax = (...amounts: Amount[]) =>
    amounts.some((amount) => amount.tax_cents !== 0);

export const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: 'America/Santo_Domingo',
    }).format(new Date(iso));
