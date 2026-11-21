// resources/js/lib/navigation.ts

import type { Component } from 'vue'
import {
  BookOpen,
  Boxes,
  Building2,
  CheckCircle,
  ClipboardCheck,
  Contact,
  CreditCard,
  Database,
  FileText,
  Folder,
  Gauge,
  KeyRound,
  LayoutGrid,
  Layers,
  LifeBuoy,
  Logs,
  Palette,
  PlugZap,
  ReceiptText,
  Users,
  Webhook,
  WalletCards,
} from 'lucide-vue-next'

import type { NavItem } from '@/types'
import LaudaIcon from '@/components/icons/LaudaIcon.vue'

// ✅ Registry de iconos (Lucide + SVG Vue custom)
const ICONS = {
  LayoutGrid,
  Layers,
  Contact,
  ClipboardCheck,
  PlugZap,
  Webhook,
  Building2,
  ReceiptText,
  Users,
  CreditCard,
  Database,
  Logs,
  Palette,
  CheckCircle,
  FileText,
  Gauge,
  KeyRound,
  LifeBuoy,
  WalletCards,
  Folder,
  BookOpen,
  Boxes,

  // ✅ Custom
  LaudaIcon,
} satisfies Record<string, Component>

export type IconName = keyof typeof ICONS
export type NavTarget = '_blank' | '_self'

export type NavConfigItem = {
  title: string
  href: string
  icon?: IconName
  badge?: string | null
  target?: NavTarget
  rel?: string
  children?: readonly NavConfigItem[]
}

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
