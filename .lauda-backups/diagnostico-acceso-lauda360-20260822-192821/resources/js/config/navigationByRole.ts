// resources/js/config/navigationByRole.ts

import type { NavConfigItem } from "./navigation"

/**
 * Tipado fuerte:
 * - `satisfies` valida que el shape sea correcto sin perder literales.
 * - `icon` queda restringido a IconName (si escribes mal un icon → error TS).
 * - Los arrays quedan readonly por el `as const`.
 */
export const navigationByRole = {
  admin: {
    main: [
      { title: 'Dashboard', href: '/dashboard', icon: 'LayoutGrid' },
      { title: 'Solicitudes Contacto', href: '/admin/contacts', icon: 'Contact' },
      { title: 'Solicitudes Activación', href: '/admin/requests', icon: 'ClipboardCheck' },
      { title: 'Suscripciones', href: '/admin/subscriptions', icon: 'PlugZap' },
      { title: 'Suscriptores', href: '/admin/subscribers', icon: 'PlugZap' },

      // Servicios
      { title: 'API Facturacion Electronica', href: '/admin/services/api-facturacion-electronica', icon: 'Webhook' },
      { title: 'API Hub Marketplace', href: '/admin/services/marketplace', icon: 'Webhook' },
      { title: 'API LaudaOne', href: '/admin/services/laudaone', icon: 'Webhook' },

      // Company
      { title: 'Clientes', href: '/admin/company', icon: 'Building2' },
      { title: 'Facturas', href: '/admin/invoices', icon: 'ReceiptText' },
      { title: 'Pagos', href: '/admin/payments', icon: 'CreditCard' },
    ],
    footer: [
      { title: 'Logs Auditoria', href: '/admin/auditlog', icon: 'Logs' },
      { title: 'Logs Errores', href: '/admin/errorlog', icon: 'Logs' },
    ],
  },

  subscriber: {
    main: [
      { title: 'Dashboard', href: '/subscriber', icon: 'LayoutGrid' },

      // Activación / onboarding (opcional)
      { title: 'Solicitud de Activación', href: '/subscriber/activation', icon: 'ClipboardCheck' },

      // Servicios (Subscriber)
      { title: 'API Facturacion Electronica', href: '/subscriber/services/api-facturacion-electronica', icon: 'Webhook' },
      { title: 'API Hub Marketplace', href: '/subscriber/services/marketplace', icon: 'Webhook' },
      { title: 'API LaudaOne', href: '/subscriber/services/laudaone', icon: 'Webhook' },

      // Mis servicios
      { title: 'Mis Servicios', href: '/subscriber/services/my', icon: 'CheckCircle' },

      // Suscripción
      { title: 'Mi Suscripción', href: '/subscriber/subscription', icon: 'PlugZap' },

      // Facturación
      { title: 'Facturas', href: '/subscriber/invoices', icon: 'ReceiptText' },
      { title: 'Pagos', href: '/subscriber/payments', icon: 'CreditCard' },

      // Configuración (Company + Tax Profile unificado)
      { title: 'Empresa', href: '/subscriber/company', icon: 'Building2' },
      { title: 'Métodos de pago', href: '/subscriber/payment-methods', icon: 'WalletCards' },


      // Uso y credenciales (solo deja estos si ya existen las rutas)
      { title: 'Uso y Límites', href: '/subscriber/usage', icon: 'Gauge' },
    ],

    footer: [
      { title: 'LaudaERP', href: '/erp', icon: 'LaudaIcon', target: '_blank' }, // ✅
      { title: 'Soporte', href: '/subscriber/support', icon: 'LifeBuoy' },
    ],
  },
} as const satisfies Record<
  string,
  {
    main: readonly NavConfigItem[]
    footer: readonly NavConfigItem[]
  }
>

export type NavigationByRole = typeof navigationByRole
export type RoleKey = keyof NavigationByRole
