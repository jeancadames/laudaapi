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
        ],
        footer: [],
    },

    subscriber_admin: {
        main: [
            { title: 'Control Panel', href: '/app', icon: 'LayoutGrid' },
            { title: 'App Store', href: '/app#app-store', icon: 'Boxes' },
            { title: 'Usuarios', href: '/subscriber/users', icon: 'Users' },
            {
                title: 'Mi suscripción',
                href: '/subscriber/subscription',
                icon: 'BadgeCheck',
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
        main: [
            { title: 'Mis Apps', href: '/app', icon: 'Boxes' },
        ],
        footer: [],
    },

    subscriber_billing: {
        main: [
            { title: 'Mis Apps', href: '/app', icon: 'Boxes' },
            {
                title: 'Mi suscripción',
                href: '/subscriber/subscription',
                icon: 'BadgeCheck',
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
                title: 'Métodos de pago',
                href: '/subscriber/payment-methods',
                icon: 'WalletCards',
            },
        ],
        footer: [],
    },

    // Alias de compatibilidad. El sidebar nuevo resuelve el lane exacto.
    subscriber: {
        main: [
            { title: 'Mis Apps', href: '/app', icon: 'Boxes' },
        ],
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
