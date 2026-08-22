<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { navigationByRole } from '@/config/navigationByRole';
import { subscriber } from '@/routes';
import type { NavItem } from '@/types';
import { mapToNavItems } from '@/utils/mapToNavItems';

type ActiveCompany = {
    id: number;
    name: string;
    slug: string;
    ws_subdomain: string | null;
    tax_id: number | null;
} | null;

type SidebarSection = {
    title?: string;
    items: NavItem[];
};

const page = usePage();
const user = (page.props as any)?.auth?.user;
const role = String(user?.role ?? '').toLowerCase();
const isAdmin = role === 'admin';

const activeCompany = computed(
    () => (page.props as any)?.activeCompany as ActiveCompany,
);

const companyName = computed(() => activeCompany.value?.name ?? '');
const companySlug = computed(() => activeCompany.value?.slug ?? '');
const companyTaxId = computed(() => activeCompany.value?.tax_id ?? '');

const adminMain: NavItem[] = mapToNavItems(navigationByRole.admin.main);
const adminFooter: NavItem[] = mapToNavItems(navigationByRole.admin.footer);
const subscriberMain: NavItem[] = mapToNavItems(
    navigationByRole.subscriber.main,
);
const subscriberFooter: NavItem[] = mapToNavItems(
    navigationByRole.subscriber.footer,
);

function byHrefs(items: NavItem[], hrefs: string[]): NavItem[] {
    return hrefs
        .map((href) => items.find((item) => String(item.href) === href))
        .filter((item): item is NavItem => Boolean(item));
}

const adminSections = computed<SidebarSection[]>(() => [
    { items: byHrefs(adminMain, ['/dashboard']) },
    {
        title: 'LAUDA 360',
        items: byHrefs(adminMain, ['/admin/diagnosis-requests']),
    },
    {
        title: 'Gestión comercial',
        items: byHrefs(adminMain, [
            '/admin/contacts',
            '/admin/requests',
            '/admin/company',
            '/admin/subscribers',
        ]),
    },
    {
        title: 'Facturación',
        items: byHrefs(adminMain, [
            '/admin/subscriptions',
            '/admin/invoices',
            '/admin/payments',
        ]),
    },
    {
        title: 'Ecosistema',
        items: byHrefs(adminMain, [
            '/admin/services/api-facturacion-electronica',
            '/admin/services/marketplace',
            '/admin/services/laudaone',
        ]),
    },
    { title: 'Sistema', items: adminFooter },
]);

const subscriberSections = computed<SidebarSection[]>(() => [
    { items: byHrefs(subscriberMain, ['/subscriber']) },
    {
        title: 'Activación',
        items: byHrefs(subscriberMain, ['/subscriber/activation']),
    },
    {
        title: 'Ecosistema',
        items: byHrefs(subscriberMain, [
            '/subscriber/services/api-facturacion-electronica',
            '/subscriber/services/marketplace',
            '/subscriber/services/laudaone',
            '/subscriber/services/my',
        ]),
    },
    {
        title: 'Facturación',
        items: byHrefs(subscriberMain, [
            '/subscriber/subscription',
            '/subscriber/invoices',
            '/subscriber/payments',
        ]),
    },
    {
        title: 'Empresa',
        items: byHrefs(subscriberMain, [
            '/subscriber/company',
            '/subscriber/payment-methods',
            '/subscriber/usage',
        ]),
    },
]);

const sections = computed<SidebarSection[]>(() =>
    isAdmin ? adminSections.value : subscriberSections.value,
);

const footerNavItems = computed<NavItem[]>(() =>
    isAdmin ? [] : subscriberFooter,
);

const homeHref = isAdmin ? '/dashboard' : subscriber().url;
const brandCaption = computed(() =>
    isAdmin ? 'PLATAFORMA 360' : 'ECOSISTEMA',
);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-r border-white/5 bg-slate-900 text-white"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-auto py-2">
                        <Link
                            :href="homeHref"
                            class="flex w-full items-center gap-2 group-data-[collapsible=icon]:justify-center"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 30 30"
                                class="h-5 w-5 shrink-0 text-[#F53003] group-data-[collapsible=icon]:mx-auto"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <defs>
                                    <clipPath id="clip-laudaapi-sidebar">
                                        <path
                                            d="M 1.964844 5 L 28.027344 5 L 28.027344 29.03125 L 1.964844 29.03125 Z M 1.964844 5"
                                        />
                                    </clipPath>
                                </defs>
                                <g clip-path="url(#clip-laudaapi-sidebar)">
                                    <path
                                        d="M 10.90625 7.480469 C 5.839844 8.390625 1.964844 12.847656 1.964844 18.167969 C 1.964844 24.140625 6.851562 29.027344 12.824219 29.027344 L 28.027344 29.027344 L 23.683594 24.683594 L 12.824219 24.683594 C 9.242188 24.683594 6.308594 21.75 6.308594 18.167969 C 6.308594 14.585938 9.242188 11.652344 12.824219 11.652344 L 14.996094 11.652344 L 14.996094 5.019531 L 10.652344 7.527344 C 10.738281 7.511719 10.820312 7.496094 10.90625 7.480469 Z"
                                    />
                                </g>
                                <path
                                    d="M 19.339844 7.308594 C 19.339844 10.203125 19.339844 13.101562 19.339844 15.996094 L 12.824219 15.996094 C 11.628906 15.996094 10.652344 16.972656 10.652344 18.167969 C 10.652344 19.363281 11.628906 20.339844 12.824219 20.339844 L 23.683594 20.339844 C 23.683594 15.996094 23.683594 11.652344 23.683594 7.308594 L 23.683594 0.00390625 L 19.339844 2.511719 Z"
                                />
                            </svg>

                            <div
                                class="flex min-w-0 flex-1 flex-col leading-tight group-data-[collapsible=icon]:hidden"
                            >
                                <span
                                    class="text-base font-black tracking-tight text-white"
                                >
                                    LAUDA
                                </span>
                                <span
                                    class="truncate text-[8px] font-bold tracking-[0.18em] text-red-400"
                                >
                                    {{ brandCaption }}
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div
                v-if="!isAdmin && companyName"
                class="mx-1 mb-1 rounded-lg border border-white/5 bg-white/5 px-3 py-2 group-data-[collapsible=icon]:hidden"
            >
                <p
                    class="text-[9px] font-bold tracking-[0.14em] text-white/35 uppercase"
                >
                    Empresa activa
                </p>
                <p
                    class="wrap-break-words mt-1 text-[11px] font-bold text-white/85"
                >
                    {{ companyName }}
                </p>
                <p
                    v-if="companySlug"
                    class="mt-0.5 truncate text-[9px] text-white/45"
                >
                    {{ companySlug }}
                </p>
                <div
                    class="mt-2 inline-flex max-w-full items-center rounded-md bg-white/5 px-2 py-1 text-[9px] text-white/55"
                >
                    <span class="mr-1 font-semibold text-white/35">RNC ·</span>
                    <span v-if="companyTaxId" class="truncate font-mono">
                        {{ companyTaxId }}
                    </span>
                    <Link
                        v-else
                        href="/subscriber/company"
                        class="font-bold text-red-400 transition hover:text-red-300"
                    >
                        Asignarlo
                    </Link>
                </div>
            </div>
        </SidebarHeader>

        <SidebarContent
            class="[&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-white/10 hover:[&::-webkit-scrollbar-thumb]:bg-white/20 [&::-webkit-scrollbar-track]:bg-transparent"
        >
            <template
                v-for="section in sections"
                :key="section.title ?? 'inicio'"
            >
                <div
                    v-if="section.title && section.items.length"
                    class="mt-3 px-3 text-[8.5px] font-black tracking-[0.15em] text-white/20 uppercase group-data-[collapsible=icon]:hidden"
                >
                    {{ section.title }}
                </div>
                <NavMain v-if="section.items.length" :items="section.items" />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
