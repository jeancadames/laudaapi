import * as LucideIcons from 'lucide-vue-next'
import type { Component } from 'vue'

function toPascalCase(input: string) {
  return input
    .trim()
    .replace(/[_\s-]+(.)/g, (_, c) => String(c).toUpperCase())
    .replace(/^(.)/, (c) => c.toUpperCase())
}

// Nombres custom (BD) -> Lucide
const ALIASES: Record<string, string> = {
  bank: 'Landmark',
  tool: 'Wrench',
}

export function resolveIcon(name?: string | null): Component | undefined {
  if (!name) return undefined

  const raw = name.trim()

  const normalized =
    ALIASES[raw] ??
    (raw.includes('-') || raw.includes('_') || raw.includes(' ')
      ? toPascalCase(raw)
      : raw)

  const icons = LucideIcons as unknown as Record<string, Component | undefined>
  return icons[normalized]
}
