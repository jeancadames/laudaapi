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

const page = usePage()
const user = (page.props as any)?.auth?.user
const role = String(user?.role ?? '').toLowerCase()

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
