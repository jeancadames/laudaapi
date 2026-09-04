import type { NavConfigItem } from './navigation';

export const navigationByRole = {
    admin: {
        main: [
            { title: 'Dashboard', href: '/dashboard', icon: 'LayoutGrid' },
            {
                title: 'Diagnósticos 360',
                href: '/admin/diagnosis-requests',
                icon: 'Contact',
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
                icon: 'Layers',
            },
            { title: 'Usuarios', href: '/subscriber/users', icon: 'Users' },
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
