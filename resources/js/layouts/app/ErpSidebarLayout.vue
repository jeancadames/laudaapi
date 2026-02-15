<script setup lang="ts">
import AppContent from '@/components/AppContent.vue'
import AppShell from '@/components/AppShell.vue'
import ErpSidebar from '@/components/ErpSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import type { BreadcrumbItem } from '@/types'

import { SidebarProvider } from '@/components/ui/sidebar'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { CloudAlert, Cloud, Power, PowerOff } from 'lucide-vue-next'


type Props = { breadcrumbs?: BreadcrumbItem[] }
withDefaults(defineProps<Props>(), { breadcrumbs: () => [] })

type TokenInfo = null | {
    enabled?: boolean
    enabled_by?: string | null
    enabled_services?: string[]
    enabled_by_item_status?: 'active' | 'trialing' | 'pending' | null

    can_toggle_auto?: boolean
    auto: boolean // dgii_company_settings.dgii_token_auto

    status: 'green' | 'yellow' | 'red' | 'expired'
    secondsLeft: number
    expiresAt: string | null
    lastError?: string | null
    lastRequestedAt?: string | null
}

const page = usePage()
const token = computed(() => (page.props.dgiiToken as TokenInfo) ?? null)

/** ✅ Mostrar badge solo si DGII está habilitado */
const showDgii = computed(() => {
    if (!token.value) return false
    if (typeof token.value.enabled === 'boolean') return token.value.enabled
    return true
})

const enabledByText = computed(() => {
    const v = token.value?.enabled_by
    if (!v) return '—'
    if (v === 'api-facturacion-electronica') return 'API Facturación Electrónica'
    if (v === 'certificacion-emisor-electronico') return 'Certificación Emisor Electrónico'
    return v
})

const entitlementBadge = computed(() => {
    const st = token.value?.enabled_by_item_status
    if (st === 'trialing') return 'TRIAL'
    if (st === 'pending') return 'PAGO'
    return null
})

const canToggleAuto = computed(() => {
    if (!token.value) return false
    if (typeof token.value.can_toggle_auto === 'boolean') return token.value.can_toggle_auto
    return true
})

/**
 * ✅ FIX DEFINITIVO (reka-ui):
 * - Switch con v-model:checked
 * - watch(autoLocal) dispara el PUT (solo cambios de usuario)
 * - watch(token.auto) sincroniza backend->UI SIN disparar PUT (guard)
 */
const autoLocal = ref(false)
const isSavingAuto = ref(false)

// evita loops: cuando sincronizamos desde backend no hacemos PUT
const syncingFromBackend = ref(false)

// 1) backend -> UI
watch(
    () => token.value?.auto,
    (v) => {
        syncingFromBackend.value = true
        autoLocal.value = !!v
        queueMicrotask(() => {
            syncingFromBackend.value = false
        })
    },
    { immediate: true }
)

const toggleAutoHint = computed(() => {
    if (!token.value) return ''
    if (isSavingAuto.value) return 'Guardando...'
    if (!canToggleAuto.value) return 'Disponible cuando el servicio esté activo (no pendiente).'
    return autoLocal.value ? 'Se renovará cuando esté por vencer.' : 'Manual: se solicita bajo demanda.'
})

// 2) UI -> backend (solo cambios del usuario)
watch(
    autoLocal,
    (next, prev) => {
        // si fue sync backend->UI, no hacemos PUT
        if (syncingFromBackend.value) return
        if (!token.value) return

        // bloqueado => revertir
        if (!canToggleAuto.value) {
            syncingFromBackend.value = true
            autoLocal.value = prev
            queueMicrotask(() => (syncingFromBackend.value = false))
            return
        }

        // evita spam / doble request
        if (isSavingAuto.value) {
            syncingFromBackend.value = true
            autoLocal.value = prev
            queueMicrotask(() => (syncingFromBackend.value = false))
            return
        }

        isSavingAuto.value = true

        router.put(
            '/erp/services/certificacion-emisor/token/auto',
            { enabled: next ? 1 : 0 }, // ✅ boolean validator friendly
            {
                preserveScroll: true,
                preserveState: false, // ✅ importante: evita estado “pegado”

                onSuccess: () => {
                    // trae la verdad desde DB
                    router.reload({ only: [ 'dgiiToken' ] })
                },

                onError: () => {
                    // revertimos si falla
                    syncingFromBackend.value = true
                    autoLocal.value = prev
                    queueMicrotask(() => (syncingFromBackend.value = false))
                },

                onFinish: () => {
                    isSavingAuto.value = false
                },
            }
        )
    }
)

// ----------------------
// Countdown local badge
// ----------------------
const secondsLeft = ref(0)
let t: ReturnType<typeof setInterval> | null = null

const syncSeconds = () => {
    if (!token.value) return (secondsLeft.value = 0)
    if (token.value.status === 'expired') return (secondsLeft.value = 0)
    secondsLeft.value = Math.max(0, token.value.secondsLeft ?? 0)
}

const mmss = computed(() => {
    const mm = String(Math.floor(secondsLeft.value / 60)).padStart(2, '0')
    const ss = String(secondsLeft.value % 60).padStart(2, '0')
    return `${mm}:${ss}`
})

const label = computed(() => {
    if (!token.value) return 'DGII: N/A'
    const plan = entitlementBadge.value ? ` · ${entitlementBadge.value}` : ''
    if (!token.value.auto && token.value.status === 'expired') return `DGII: Manual (vencido)${plan}`
    if (token.value.status === 'expired') return `DGII: Vencido${plan}`
    return `DGII: ${mmss.value}${plan}`
})

const variant = computed(() => {
    const s = token.value?.status
    if (s === 'green') return 'default'
    if (s === 'yellow') return 'secondary'
    if (s === 'red' || s === 'expired') return 'destructive'
    return 'outline'
})

const statusText = computed(() => {
    const s = token.value?.status
    if (!token.value) return 'N/A'
    if (s === 'green') return 'Válido'
    if (s === 'yellow') return 'Por vencer'
    if (s === 'red') return 'Crítico'
    return 'Vencido'
})

const expiresAtText = computed(() => {
    if (!token.value?.expiresAt) return '—'
    try {
        return new Date(token.value.expiresAt).toLocaleString()
    } catch {
        return token.value.expiresAt
    }
})

const lastRequestedAtText = computed(() => {
    if (!token.value?.lastRequestedAt) return '—'
    try {
        return new Date(token.value.lastRequestedAt).toLocaleString()
    } catch {
        return token.value.lastRequestedAt
    }
})

onMounted(() => {
    syncSeconds()
    t = setInterval(() => {
        if (secondsLeft.value > 0) secondsLeft.value--
    }, 1000)
})

onUnmounted(() => {
    if (t) clearInterval(t)
})

watch(() => token.value?.expiresAt, syncSeconds)
watch(() => token.value?.secondsLeft, syncSeconds)
watch(() => token.value?.status, syncSeconds)

const isOnline = computed(() => {
    if (!token.value) return true
    return (token.value as any).is_online !== false
})

</script>

<template>
    <SidebarProvider>
        <TooltipProvider>
            <AppShell variant="sidebar">
                <ErpSidebar />

                <AppContent variant="sidebar" class="overflow-x-hidden">
                    <!-- ✅ padding-right para que NO quede pegado a la derecha -->
                    <div class="flex items-center justify-between gap-3 pr-4">
                        <AppSidebarHeader :breadcrumbs="breadcrumbs" />

                        <Tooltip v-if="showDgii && token">
                            <TooltipTrigger as-child>
                                <Badge variant="outline" class="shrink-0 cursor-default select-none overflow-hidden px-0 py-0 inline-flex items-stretch
         bg-white/60 text-slate-700 border-slate-200
         hover:bg-white/80 transition-colors
         dark:bg-slate-900/40 dark:text-slate-100 dark:border-slate-700 dark:hover:bg-slate-900/60" aria-label="Estado token DGII">
                                    <!-- LEFT: DGII (solo >= sm) -->
                                    <span class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1 text-xs font-semibold tracking-wide" :class="!isOnline
                                        ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                        : (autoLocal
                                            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                            : 'bg-slate-100/80 text-slate-700 dark:bg-slate-800/70 dark:text-slate-100')">
                                        <!-- dot -->
                                        <span class="h-2 w-2 rounded-full" :class="!isOnline ? 'bg-amber-500' : (autoLocal ? 'bg-emerald-500' : 'bg-red-500')" />

                                        <!-- ✅ icono en vez de texto OFFLINE -->
                                        <CloudAlert v-if="!isOnline" class="h-4 w-4 text-amber-600 dark:text-amber-300" aria-label="DGII offline" />
                                        <Cloud v-else class="h-4 w-4 opacity-80" aria-label="DGII online" />

                                        <span>DGII</span>
                                    </span>

                                    <!-- DIVIDER (solo >= sm) -->
                                    <span class="hidden sm:block w-px self-stretch bg-slate-200/80 dark:bg-slate-700/80" aria-hidden="true" />

                                    <!-- RIGHT: ON/OFF (siempre visible) -->
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold" :class="autoLocal
                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'bg-red-50 text-red-800 dark:bg-red-950/35 dark:text-red-200'" :aria-label="autoLocal ? 'Automático encendido' : 'Automático apagado'">
                                        <Power v-if="autoLocal" class="h-4 w-4 text-emerald-600 dark:text-emerald-300" />
                                        <PowerOff v-else class="h-4 w-4 text-red-600 dark:text-red-300" />
                                        <span class="leading-none">{{ autoLocal ? 'ON' : 'OFF' }}</span>
                                    </span>
                                </Badge>
                            </TooltipTrigger>

                            <TooltipContent class="max-w-90">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="font-medium">Token DGII</div>
                                        <div class="text-xs opacity-80">
                                            {{ autoLocal ? 'Auto' : 'Manual' }}
                                        </div>
                                    </div>

                                    <!-- ✅ Switch Auto Token -->
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="space-y-0.5">
                                            <div class="text-sm font-medium">Token automático</div>
                                            <div class="text-xs opacity-80">
                                                {{ toggleAutoHint }}
                                            </div>
                                        </div>

                                        <Switch v-model="autoLocal" :disabled="!canToggleAuto || isSavingAuto" class="data-[state=checked]:bg-green-600" />
                                    </div>

                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between gap-4" v-if="token.enabled_by">
                                            <span class="opacity-80">Habilitado por</span>
                                            <span class="text-right">{{ enabledByText }}</span>
                                        </div>

                                        <div class="flex justify-between gap-4" v-if="entitlementBadge">
                                            <span class="opacity-80">Plan</span>
                                            <span class="text-right">{{ entitlementBadge }}</span>
                                        </div>

                                        <div class="flex justify-between gap-4">
                                            <span class="opacity-80">Estado</span>
                                            <span>{{ statusText }}</span>
                                        </div>

                                        <div class="flex justify-between gap-4" v-if="token.status !== 'expired'">
                                            <span class="opacity-80">Vence en</span>
                                            <span class="font-mono">{{ mmss }}</span>
                                        </div>

                                        <div class="flex justify-between gap-4">
                                            <span class="opacity-80">Expira</span>
                                            <span class="text-right">{{ expiresAtText }}</span>
                                        </div>

                                        <div class="flex justify-between gap-4">
                                            <span class="opacity-80">Última solicitud</span>
                                            <span class="text-right">{{ lastRequestedAtText }}</span>
                                        </div>
                                    </div>

                                    <div v-if="token.lastError" class="text-xs">
                                        <div class="opacity-80 mb-1">Último error</div>
                                        <div class="wrap-break-word rounded-md border p-2">
                                            {{ token.lastError }}
                                        </div>
                                    </div>
                                </div>
                            </TooltipContent>
                        </Tooltip>
                    </div>

                    <slot />
                </AppContent>
            </AppShell>
        </TooltipProvider>
    </SidebarProvider>
</template>