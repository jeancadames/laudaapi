import type { InertiaLinkProps } from '@inertiajs/vue3'
import type { Component } from 'vue'

export type BreadcrumbItem = {
    title: string
    href?: string
}

export type NavItem = {
    title: string
    href: NonNullable<InertiaLinkProps[ 'href' ]>

    /**
     * ✅ Soporta Lucide (component) + SVGs custom (Vue component)
     */
    icon?: Component

    isActive?: boolean

    // ✅ abrir en nueva pestaña
    target?: '_blank' | '_self'
    rel?: string

    // ✅ si más adelante usas children
    children?: NavItem[]

    // ✅ opcional si usas badge en menús
    badge?: string | null
}
