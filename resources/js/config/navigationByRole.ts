import type { NavConfigItem } from './navigation';

export const navigationByRole = {
    admin: {
        main: [
            { title: 'Dashboard', href: '/dashboard', icon: 'LayoutGrid' },

            // LAUDA 360
            {
                title: 'Diagnósticos 360',
                href: '/admin/diagnosis-requests',
                icon: 'Contact',
            },

            // Gestión comercial
            {
                title: 'Solicitudes generales',
                href: '/admin/contacts',
                icon: 'Contact',
            },
            {
                title: 'Activaciones',
                href: '/admin/requests',
                icon: 'ClipboardCheck',
            },
            { title: 'Empresas', href: '/admin/company', icon: 'Building2' },
            {
                title: 'Suscriptores',
                href: '/admin/subscribers',
                icon: 'PlugZap',
            },

            // Facturación
            {
                title: 'Suscripciones',
                href: '/admin/subscriptions',
                icon: 'PlugZap',
            },
            { title: 'Facturas', href: '/admin/invoices', icon: 'ReceiptText' },
            { title: 'Pagos', href: '/admin/payments', icon: 'CreditCard' },

            // Ecosistema
            {
                title: 'API Facturación Electrónica',
                href: '/admin/services/api-facturacion-electronica',
                icon: 'Webhook',
            },
            {
                title: 'API Hub Marketplace',
                href: '/admin/services/marketplace',
                icon: 'Webhook',
            },
            {
                title: 'API LaudaOne',
                href: '/admin/services/laudaone',
                icon: 'Webhook',
            },
        ],
        footer: [
            { title: 'Auditoría', href: '/admin/auditlog', icon: 'Logs' },
            { title: 'Errores', href: '/admin/errorlog', icon: 'Logs' },
        ],
    },

    subscriber: {
        main: [
            { title: 'Dashboard', href: '/subscriber', icon: 'LayoutGrid' },

            // Activación
            {
                title: 'Solicitud de Activación',
                href: '/subscriber/activation',
                icon: 'ClipboardCheck',
            },

            // Ecosistema
            {
                title: 'API Facturación Electrónica',
                href: '/subscriber/services/api-facturacion-electronica',
                icon: 'Webhook',
            },
            {
                title: 'API Hub Marketplace',
                href: '/subscriber/services/marketplace',
                icon: 'Webhook',
            },
            {
                title: 'API LaudaOne',
                href: '/subscriber/services/laudaone',
                icon: 'Webhook',
            },
            {
                title: 'Mis Servicios',
                href: '/subscriber/services/my',
                icon: 'CheckCircle',
            },

            // Facturación
            {
                title: 'Mi Suscripción',
                href: '/subscriber/subscription',
                icon: 'PlugZap',
            },
            {
                title: 'Facturas',
                href: '/subscriber/invoices',
                icon: 'ReceiptText',
            },
            {
                title: 'Pagos',
                href: '/subscriber/payments',
                icon: 'CreditCard',
            },

            // Empresa
            {
                title: 'Empresa',
                href: '/subscriber/company',
                icon: 'Building2',
            },
            {
                title: 'Métodos de pago',
                href: '/subscriber/payment-methods',
                icon: 'WalletCards',
            },
            {
                title: 'Uso y Límites',
                href: '/subscriber/usage',
                icon: 'Gauge',
            },
        ],
        footer: [
            {
                title: 'LaudaERP',
                href: '/erp',
                icon: 'LaudaIcon',
                target: '_blank',
            },
            { title: 'Soporte', href: '/subscriber/support', icon: 'LifeBuoy' },
        ],
    },
} as const satisfies Record<
    string,
    {
        main: readonly NavConfigItem[];
        footer: readonly NavConfigItem[];
    }
>;

export type NavigationByRole = typeof navigationByRole;
export type RoleKey = keyof NavigationByRole;
