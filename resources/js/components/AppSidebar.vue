<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue'
import NavMain from '@/components/NavMain.vue'
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
import type { NavItem } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import AppLogo from './AppLogo.vue'
import { navigationByRole } from '@/config/navigationByRole'
import { mapToNavItems } from '@/utils/mapToNavItems'
import { subscriber } from '@/routes'
import { computed } from 'vue'

type ActiveCompany = {
    id: number
    name: string
    slug: string
    ws_subdomain: string | null
} | null

const page = usePage()
const user = (page.props as any)?.auth?.user
const role = String(user?.role ?? '').toLowerCase()

const activeCompany = computed(() => (page.props as any)?.activeCompany as ActiveCompany)

const companyName = computed(() => activeCompany.value?.name ?? '')
const companySlug = computed(() => activeCompany.value?.slug ?? '')

const adminMain: NavItem[] = mapToNavItems(navigationByRole.admin.main)
const adminFooter: NavItem[] = mapToNavItems(navigationByRole.admin.footer)

const subscriberMain: NavItem[] = mapToNavItems(navigationByRole.subscriber.main)
const subscriberFooter: NavItem[] = mapToNavItems(navigationByRole.subscriber.footer)

const mainNavItems: NavItem[] = role === 'admin' ? adminMain : subscriberMain
const footerNavItems: NavItem[] = role === 'admin' ? adminFooter : subscriberFooter

// ✅ home correcto
const homeHref = role === 'admin' ? '/dashboard' : subscriber().url // => /subscriber
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div
                v-if="companyName"
                class="mx-2 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/30 px-3 py-2 group-data-[collapsible=icon]:hidden"
            >
                <p class="text-[10px] font-medium uppercase tracking-[0.16em] text-sidebar-foreground/50">
                    Empresa activa
                </p>

                <p class="mt-1 wrap-break-words text-sm font-semibold leading-5 text-sidebar-foreground">
                    {{ companyName }}
                </p>

                <p
                    v-if="companySlug"
                    class="mt-1 truncate text-[11px] text-sidebar-foreground/60"
                >
                    {{ companySlug }}
                </p>
            </div>

        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
