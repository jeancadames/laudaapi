// resources/js/utils/mapToNavItems.ts

import type { Component } from 'vue'
import {
  // ya tenías
  BookOpen,
  Building2,
  CheckCircle,
  ClipboardCheck,
  Contact,
  CreditCard,
  FileText,
  Folder,
  Gauge,
  KeyRound,
  LayoutGrid,
  LifeBuoy,
  Logs,
  PlugZap,
  ReceiptText,
  Webhook,
  WalletCards,

  // ✅ nuevos (seeder)
  ShieldCheck,
  File,
  Calendar,
  ShoppingCart,
  Users,
  CheckSquare,
  ShoppingBag,
  Truck,
  Coffee,
  Briefcase,
  Car,
  DollarSign,
  Layers,
  UserCheck,
  Landmark,
  Book,
  MapPin,
  Wrench,
} from 'lucide-vue-next'

import type { NavItem } from '@/types'
import LaudaIcon from '@/components/icons/LaudaIcon.vue'

// ✅ Registry de iconos (Lucide + custom SVGs Vue)
export const ICONS: Record<string, Component> = {
  // base
  LayoutGrid,
  Contact,
  ClipboardCheck,
  PlugZap,
  Webhook,
  Building2,
  ReceiptText,
  CreditCard,
  Logs,
  CheckCircle,
  FileText,
  Gauge,
  KeyRound,
  LifeBuoy,
  Folder,
  BookOpen,
  WalletCards,

  // ✅ seeder children
  ShieldCheck,
  File,
  Calendar,
  Users,
  CheckSquare,
  ShoppingBag,
  Truck,
  Coffee,
  Briefcase,
  Car,
  DollarSign,
  Layers,
  UserCheck,
  Book,
  MapPin,

  // ✅ seeder parents
  ShoppingCart,

  // ✅ aliases (por si el backend manda estos nombres)
  Bank: Landmark,      // lucide no tiene "Bank", usa Landmark
  Tool: Wrench,        // lucide no siempre tiene Tool; Wrench es el equivalente
  Grid: LayoutGrid,    // "grid" en seed -> LayoutGrid (o cámbialo por otro si prefieres)

  // ✅ Custom
  LaudaIcon,
}

export type IconName = keyof typeof ICONS

export type NavConfigItem = {
  title: string
  href: string
  icon?: IconName
  badge?: string | null

  target?: '_blank' | '_self'
  rel?: string

  children?: readonly NavConfigItem[]
}

/**
 * Convierte config readonly -> NavItem[]
 * - acepta readonly arrays (as const)
 * - icon string -> Component
 * - soporta children recursivo
 */
export function mapToNavItems(items: readonly NavConfigItem[]): NavItem[] {
  return items.map((item) => ({
    title: item.title,
    href: item.href,
    icon: item.icon ? ICONS[ item.icon ] : undefined,
    badge: item.badge ?? null,
    target: item.target,
    rel: item.rel,
    children: item.children ? mapToNavItems(item.children) : undefined,
  }))
}

/**
 * Resolver icono por nombre (string) con fallback
 * Útil cuando el backend manda string tipado débil.
 */
export function resolveIcon(iconName?: string): Component | undefined {
  if (!iconName) return undefined
  return ICONS[ iconName ] ?? undefined
}
