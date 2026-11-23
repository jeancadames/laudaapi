import type { NavConfigItem } from './navigation';

export const navigationByRole = {
    admin: {
        main: [
            { title: 'Dashboard', href: '/dashboard', icon: 'LayoutGrid' },
            { title: 'Pagos', href: '/admin/payments', icon: 'CreditCard' },
            { title: 'Facturas', href: '/admin/invoices', icon: 'ReceiptText' },
            { title: 'Empresas', href: '/admin/company', icon: 'Building2' },
            { title: 'Suscriptores', href: '/admin/subscribers', icon: 'Users' },
            { title: 'Suscripciones', href: '/admin/subscriptions', icon: 'CreditCard' },
            { title: 'Solicitudes de activación', href: '/admin/requests', icon: 'ClipboardCheck' },
            { title: 'Contactos', href: '/admin/contacts', icon: 'Contact' },
            { title: 'Auditoría', href: '/admin/auditlog', icon: 'Logs' },
            { title: 'Registro de errores', href: '/admin/errorlog', icon: 'Logs' },
            { title: 'Implementaciones Business', href: '/admin/business-implementations', icon: 'FileText' },
            {
                title: 'Catálogo y tarifas',
                href: '/admin/business-catalog',
                icon: 'ReceiptText',
            },
            {
                title: 'Diagnósticos 360',
                href: '/admin/diagnosis-requests',
                icon: 'Contact',
            },
            {
                title: 'Transformación 360',
                href: '/admin/transformation-360',
                icon: 'Layers',
            },

            {
                title: 'Solicitudes de Implementación',
                href: '/admin/transformation-360/implementation-requests',
                icon: 'Contact',
            },
            {
                title: 'Datos e Inteligencia BI',
                href: '/admin/transformation-360/data-bi',
                icon: 'Database',
            },
            {
                title: 'Evaluaciones de Branding',
                href: '/admin/branding-evaluations',
                icon: 'Contact',
            },
        ],
        footer: [],
    },

    subscriber_admin: {
        main: [
            { title: 'Inicio', href: '/app', icon: 'LayoutGrid' },
            { title: 'Control Panel', href: '/app/control', icon: 'Boxes' },
            {
                title: 'Diagnóstico 360',
                href: '/app/diagnostico-360',
                icon: 'FileText',
            },
            {
                title: 'Transformación 360',
                href: '/app/transformacion-360',
                icon: 'Layers',
            },
            {
                title: 'Branding e Identidad Digital',
                href: '/app/branding-identidad',
                icon: 'Palette',
            },
            {
                title: 'Transformación e Inteligencia de Datos para BI',
                href: '/app/transformacion-360/datos-bi',
                icon: 'Database',
            },
            { title: 'Armar mi paquete', href: '/app/business/self-service', icon: 'Boxes' },
            { title: 'Conexiones Business', href: '/business/identity', icon: 'PlugZap' },
            { title: 'Mis servicios', href: '/subscriber/services/my', icon: 'Layers' },
            { title: 'Uso', href: '/subscriber/usage', icon: 'Gauge' },
            { title: 'Seguridad', href: '/subscriber/security', icon: 'KeyRound' },
            { title: 'Usuarios', href: '/subscriber/users', icon: 'Users' },
            {
                title: 'Cotizaciones',
                href: '/app/business/quotes',
                icon: 'FileText',
            },
            {
                title: 'Mi suscripción',
                href: '/subscriber/subscription',
                icon: 'CreditCard',
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
        ],
        footer: [
            { title: 'Soporte', href: '/subscriber/support', icon: 'LifeBuoy' },
        ],
    },

    subscriber_user: {
        main: [{ title: 'Inicio', href: '/app', icon: 'LayoutGrid' }],
        footer: [],
    },

    // Alias de compatibilidad. El sidebar nuevo resuelve el lane exacto.
    subscriber: {
        main: [{ title: 'Inicio', href: '/app', icon: 'LayoutGrid' }],
        footer: [],
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
