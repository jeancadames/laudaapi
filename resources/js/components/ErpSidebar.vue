<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import NavFooter from '@/components/NavFooter.vue'
import NavMain from './NavMain.vue'
import NavUser from '@/components/NavUser.vue'

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'

import ErpNavGroup from '@/components/ErpNavGroup.vue'
import { mapToNavItems, ICONS } from '@/utils/mapToNavItems'
import type { NavItem } from '@/types'
import type { Component } from 'vue'
import AppLogoErp from './AppLogoErp.vue'
import SidebarGroup from './ui/sidebar/SidebarGroup.vue'

type ErpItem = { title: string; href: string; icon?: string; badge?: string | null }
type ErpGroup = { title: string; slug: string; icon?: string; items: ErpItem[] }

const page = usePage()
const props = page.props as any

const homeHref = '/erp'

// backend: props.nav.erp.groups (payload: { erp: { groups: [...] } })
const groupsRaw = computed<ErpGroup[]>(() => (props?.nav?.erp?.groups ?? []) as ErpGroup[])

// ✅ Dashboard arriba (primera opción)
const topNavItems = computed<NavItem[]>(() =>
    mapToNavItems([ { title: 'Dashboard', href: '/erp', icon: 'LayoutGrid' } ]),
)

// ✅ Footer ERP separado de subscriber
const footerNavItems: NavItem[] = mapToNavItems([ { title: 'Soporte', href: '/erp/support', icon: 'LifeBuoy' } ])

/**
 * ✅ Normaliza string de icono a una key válida del registry ICONS
 * Soporta:
 * - "Webhook" / "webhook"
 * - "file-text" / "file_text" => "FileText"
 * - "shield-check" => "ShieldCheck"
 */
function normalizeIconKey(iconName?: string): string | undefined {
    if (!iconName) return undefined

    const raw = String(iconName).trim()
    if (!raw) return undefined

    // 1) match exact
    if ((ICONS as any)[ raw ]) return raw

    // 2) intenta Capitalizar primera letra si viene en lower
    const cap = raw.charAt(0).toUpperCase() + raw.slice(1)
    if ((ICONS as any)[ cap ]) return cap

    // 3) kebab/snake -> PascalCase
    const pascal = raw
        .toLowerCase()
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (m) => m.toUpperCase())
        .replace(/\s+/g, '')

    if ((ICONS as any)[ pascal ]) return pascal

    return undefined
}

function resolveIcon(iconName?: string): Component | undefined {
    const key = normalizeIconKey(iconName)
    if (!key) return undefined
    return (ICONS as any)[ key ] as Component
}

/**
 * ✅ Pre-resolver iconos por slug (no recalcular en template)
 */
const groupIconBySlug = computed<Record<string, Component | undefined>>(() => {
    const out: Record<string, Component | undefined> = {}
    for (const g of groupsRaw.value) out[ g.slug ] = resolveIcon(g.icon)
    return out
})

// ✅ Collapsible state persistente
const storageKey = 'erp.sidebar.openMap'
const openMap = ref<Record<string, boolean>>({
    'api-facturacion-electronica': true,
    marketplace: true,
    laudaone: true,
})

onMounted(() => {
    try {
        const raw = localStorage.getItem(storageKey)
        if (!raw) return
        const parsed = JSON.parse(raw)
        if (parsed && typeof parsed === 'object') openMap.value = { ...openMap.value, ...parsed }
    } catch { }
})

watch(
    openMap,
    (v) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(v))
        } catch { }
    },
    { deep: true },
)

function toggle(slug: string) {
    openMap.value[ slug ] = !(openMap.value[ slug ] ?? true)
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <!-- HEADER -->
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogoErp />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- CONTENT -->
        <SidebarContent class="space-y-2">
            <!-- ✅ Top: Dashboard -->
            <div>
                <NavMain :items="topNavItems" />
            </div>

            <!-- separador -->
            <div class="mx-3 h-px bg-slate-200/70 dark:bg-slate-800/70 group-data-[collapsible=icon]:mx-2" />

            <!-- ✅ Empty state (se oculta cuando colapsa) -->
            <div v-if="groupsRaw.length === 0" class="px-3 py-3 group-data-[collapsible=icon]:hidden">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">No tienes módulos ERP disponibles</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Activa módulos desde el catálogo para verlos aquí.</p>

                    <div class="mt-3">
                        <a href="/subscriber/services/my" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                            Activar módulos
                        </a>
                    </div>
                </div>
            </div>

            <!-- ✅ GROUPS -->
            <SidebarGroup class="px-2 py-0">
                <ErpNavGroup v-for="g in groupsRaw" :key="g.slug" :title="g.title" :slug="g.slug" :icon="groupIconBySlug[ g.slug ]" :items="mapToNavItems(g.items)" :open="openMap[ g.slug ] ?? true" :onToggle="toggle" />
            </SidebarGroup>
        </SidebarContent>


        <!-- FOOTER -->
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
