// resources/js/lib/navigation.ts

import type { Component } from 'vue'
import {
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
} from 'lucide-vue-next'

import type { NavItem } from '@/types'
import LaudaIcon from '@/components/icons/LaudaIcon.vue'

// ✅ Registry de iconos (Lucide + SVG Vue custom)
const ICONS = {
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
  WalletCards,
  Folder,
  BookOpen,

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
