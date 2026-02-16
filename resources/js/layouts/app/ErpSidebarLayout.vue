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
    auto: boolean

    status: 'green' | 'yellow' | 'red' | 'expired'
    secondsLeft: number
    expiresAt: string | null
    lastError?: string | null
    lastRequestedAt?: string | null

    is_online?: boolean
}

type CertType = 'cer' | 'p12' | 'pfx'

type CertRequirements = {
    has_all_required_types?: boolean
    can_enable_auto_token?: boolean
    why_blocked?: string | null

    present?: Record<CertType, boolean>
    missing?: CertType[]

    has_all?: boolean
}

const page = usePage()

const token = computed(() => (page.props.dgiiToken as TokenInfo) ?? null)
const certReq = computed(() => (page.props.cert_requirements as CertRequirements) ?? null)

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

/** can_toggle_auto = “entitlement”/permiso (plan/estado del item) */
const canToggleByEntitlement = computed(() => {
    if (!token.value) return false
    if (typeof token.value.can_toggle_auto === 'boolean') return token.value.can_toggle_auto
    return true
})

/** ✅ disponibilidad real del prop */
const certReqAvailable = computed(() => !!certReq.value)

/** ✅ Requisito técnico: 3 tipos presentes */
const hasRequiredCerts = computed(() => {
    if (!certReq.value) return false
    if (typeof certReq.value.has_all_required_types === 'boolean') return certReq.value.has_all_required_types
    if (typeof certReq.value.has_all === 'boolean') return certReq.value.has_all
    return false
})

/**
 * ✅ Veredicto final para ENCENDER:
 * - Si existe can_enable_auto_token, úsalo (incluye firmador usable)
 * - Si no, cae a hasRequiredCerts
 * - ✅ FAIL-CLOSED: si NO viene cert_requirements => BLOQUEA
 */
const canEnableByCerts = computed(() => {
    if (!certReq.value) return false
    if (typeof certReq.value.can_enable_auto_token === 'boolean') return certReq.value.can_enable_auto_token
    return hasRequiredCerts.value
})

const missingCertsText = computed(() => {
    const missing = certReq.value?.missing ?? []
    return missing.length ? missing.map((t) => `.${t}`).join(', ') : ''
})

/** --------------------------
 * ✅ UI: Badge + Pills seguros
 * - ok / incomplete / unknown
 * -------------------------- */
const certUiState = computed<'ok' | 'incomplete' | 'unknown'>(() => {
    if (!certReqAvailable.value) return 'unknown'
    return hasRequiredCerts.value ? 'ok' : 'incomplete'
})

const certBadgeText = computed(() => {
    if (certUiState.value === 'unknown') return 'NO VERIFICADO'
    return certUiState.value === 'ok' ? 'OK' : 'INCOMPLETO'
})

const certBadgeClass = computed(() => {
    if (certUiState.value === 'unknown') {
        return 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
    }
    return certUiState.value === 'ok'
        ? 'border-emerald-200 text-emerald-700 dark:border-emerald-900/60 dark:text-emerald-200'
        : 'border-amber-200 text-amber-800 dark:border-amber-900/60 dark:text-amber-200'
})

const presentCer = computed<boolean | null>(() => (certReqAvailable.value ? !!certReq.value?.present?.cer : null))
const presentP12 = computed<boolean | null>(() => (certReqAvailable.value ? !!certReq.value?.present?.p12 : null))
const presentPfx = computed<boolean | null>(() => (certReqAvailable.value ? !!certReq.value?.present?.pfx : null))

const pillClass = (present: boolean | null) => {
    if (present === null) return 'bg-slate-100/80 text-slate-700 dark:bg-slate-800/70 dark:text-slate-100'
    return present
        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
        : 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
}

const dotClass = (present: boolean | null) => {
    if (present === null) return 'bg-slate-400'
    return present ? 'bg-emerald-500' : 'bg-amber-500'
}

const certUiHint = computed(() => {
    if (!certReqAvailable.value) return 'No se pudo verificar certificados en esta vista.'
    if (canEnableByCerts.value) return 'Certificados OK para activar automático.'
    if (certReq.value?.why_blocked) return certReq.value.why_blocked
    return `Requiere .cer, .p12 y .pfx. Faltan: ${missingCertsText.value || '—'}.`
})

/** ✅ Switch (reka-ui) */
const autoLocal = ref(false)
const isSavingAuto = ref(false)
const syncingFromBackend = ref(false)

// backend -> UI
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

/**
 * ✅ Política:
 * - OFF: encender solo si entitlement OK && certs OK && !saving
 * - ON: siempre apagar, salvo saving
 */
const canEnableAutoNow = computed(() => {
    if (!canToggleByEntitlement.value) return false
    if (!canEnableByCerts.value) return false
    if (isSavingAuto.value) return false
    return true
})

const canToggleAuto = computed(() => {
    if (!token.value) return false
    if (isSavingAuto.value) return false
    if (!autoLocal.value) return canEnableAutoNow.value
    return true
})

const toggleAutoHint = computed(() => {
    if (!token.value) return ''
    if (isSavingAuto.value) return 'Guardando...'
    if (!canToggleByEntitlement.value) return 'Disponible cuando el servicio esté activo (no pendiente).'

    if (!autoLocal.value && !canEnableByCerts.value) return certUiHint.value

    return autoLocal.value ? 'Se renovará automáticamente cuando esté por vencer.' : 'Manual: se solicita bajo demanda.'
})

// UI -> backend (solo usuario)
watch(autoLocal, (next, prev) => {
    if (syncingFromBackend.value) return
    if (!token.value) return

    // Intento de ENCENDER sin requisitos => revertir
    if (next === true) {
        if (!canToggleByEntitlement.value || !canEnableByCerts.value) {
            syncingFromBackend.value = true
            autoLocal.value = prev
            queueMicrotask(() => (syncingFromBackend.value = false))
            return
        }
    }

    if (isSavingAuto.value) {
        syncingFromBackend.value = true
        autoLocal.value = prev
        queueMicrotask(() => (syncingFromBackend.value = false))
        return
    }

    isSavingAuto.value = true

    router.put(
        '/erp/services/certificacion-emisor/token/auto',
        { enabled: next ? 1 : 0 },
        {
            preserveScroll: true,
            preserveState: false,

            onSuccess: () => {
                router.reload({ only: [ 'dgiiToken', 'cert_requirements' ] })
            },

            onError: () => {
                syncingFromBackend.value = true
                autoLocal.value = prev
                queueMicrotask(() => (syncingFromBackend.value = false))
            },

            onFinish: () => {
                isSavingAuto.value = false
            },
        }
    )
})

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

const presentCount = computed(() => {
    const vals = [ presentCer.value, presentP12.value, presentPfx.value ]
    // si alguno es null, no contamos como presente
    return vals.filter(v => v === true).length
})

const totalRequired = 3

const certProgressText = computed(() => {
    if (!certReqAvailable.value) return '—/3'
    return `${presentCount.value}/${totalRequired}`
})

const certStatePill = computed(() => {
    if (!certReqAvailable.value) {
        return {
            text: 'NO VERIFICADO',
            cls: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-100',
        }
    }

    // si tiene tipos completos pero no puede habilitar (firmador no usable) => bloqueado
    if (hasRequiredCerts.value && !canEnableByCerts.value) {
        return {
            text: 'BLOQUEADO',
            cls: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200',
        }
    }

    if (certUiState.value === 'ok') {
        return {
            text: 'LISTO',
            cls: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200',
        }
    }

    return {
        text: 'INCOMPLETO',
        cls: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200',
    }
})

const certCompactHint = computed(() => {
    if (!certReqAvailable.value) return 'No se pudo verificar en esta vista.'
    if (canEnableByCerts.value) return 'Todo OK: puedes activar automático.'
    // prioridad al why_blocked del backend
    if (certReq.value?.why_blocked) return certReq.value.why_blocked
    if (!hasRequiredCerts.value) return `Faltan: ${missingCertsText.value || '—'}`
    return 'Certificados presentes pero falta habilitar firmador.'
})

</script>

<template>
    <SidebarProvider>
        <TooltipProvider>
            <AppShell variant="sidebar">
                <ErpSidebar />

                <AppContent variant="sidebar" class="overflow-x-hidden">
                    <div class="flex items-center justify-between gap-3 pr-4">
                        <AppSidebarHeader :breadcrumbs="breadcrumbs" />

                        <Tooltip v-if="showDgii && token">
                            <TooltipTrigger as-child>
                                <Badge :variant="variant" class="shrink-0 cursor-default select-none overflow-hidden px-0 py-0 inline-flex items-stretch
                                           bg-white/60 text-slate-700 border-slate-200
                                           hover:bg-white/80 transition-colors
                                           dark:bg-slate-900/40 dark:text-slate-100 dark:border-slate-700 dark:hover:bg-slate-900/60" aria-label="Estado token DGII">
                                    <span class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1 text-xs font-semibold tracking-wide" :class="!isOnline
                                        ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                        : (autoLocal
                                            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                            : 'bg-slate-100/80 text-slate-700 dark:bg-slate-800/70 dark:text-slate-100')">
                                        <span class="h-2 w-2 rounded-full" :class="!isOnline ? 'bg-amber-500' : (autoLocal ? 'bg-emerald-500' : 'bg-red-500')" />
                                        <CloudAlert v-if="!isOnline" class="h-4 w-4 text-amber-600 dark:text-amber-300" aria-label="DGII offline" />
                                        <Cloud v-else class="h-4 w-4 opacity-80" aria-label="DGII online" />
                                        <span>DGII</span>
                                    </span>

                                    <span class="hidden sm:block w-px self-stretch bg-slate-200/80 dark:bg-slate-700/80" aria-hidden="true" />

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
                                        <div class="text-xs opacity-80">{{ autoLocal ? 'Auto' : 'Manual' }}</div>
                                    </div>

                                    <div class="flex items-center justify-between gap-4">
                                        <div class="space-y-0.5">
                                            <div class="text-sm font-medium">Token automático</div>
                                            <div class="text-xs opacity-80">{{ toggleAutoHint }}</div>
                                        </div>

                                        <Switch v-model="autoLocal" :disabled="!canToggleAuto" class="data-[state=checked]:bg-green-600" />
                                    </div>

                                    <div class="rounded-lg border border-slate-200/70 bg-white/70 p-2.5
            dark:border-slate-800/80 dark:bg-slate-950/35">

                                        <!-- Header -->
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">
                                                Certificados requeridos
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <!-- progress -->
                                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-700
                   dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-100">
                                                    {{ certProgressText }}
                                                </span>

                                                <!-- state -->
                                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold" :class="certStatePill.cls">
                                                    {{ certStatePill.text }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Rows -->
                                        <div class="mt-2 space-y-1.5">
                                            <!-- CER -->
                                            <div class="flex items-center justify-between gap-3 rounded-md border px-2 py-1.5" :class="presentCer === null
                                                ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/35 dark:text-slate-100'
                                                : presentCer
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200'
                                                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200'">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full" :class="dotClass(presentCer)" />
                                                    <span class="text-[11px] font-semibold">.cer</span>
                                                </div>
                                                <span class="text-[10px] opacity-80">
                                                    {{ presentCer === null ? '—' : (presentCer ? 'Presente' : 'Falta') }}
                                                </span>
                                            </div>

                                            <!-- P12 -->
                                            <div class="flex items-center justify-between gap-3 rounded-md border px-2 py-1.5" :class="presentP12 === null
                                                ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/35 dark:text-slate-100'
                                                : presentP12
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200'
                                                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200'">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full" :class="dotClass(presentP12)" />
                                                    <span class="text-[11px] font-semibold">.p12</span>
                                                </div>
                                                <span class="text-[10px] opacity-80">
                                                    {{ presentP12 === null ? '—' : (presentP12 ? 'Presente' : 'Falta') }}
                                                </span>
                                            </div>

                                            <!-- PFX -->
                                            <div class="flex items-center justify-between gap-3 rounded-md border px-2 py-1.5" :class="presentPfx === null
                                                ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/35 dark:text-slate-100'
                                                : presentPfx
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200'
                                                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200'">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full" :class="dotClass(presentPfx)" />
                                                    <span class="text-[11px] font-semibold">.pfx</span>
                                                </div>
                                                <span class="text-[10px] opacity-80">
                                                    {{ presentPfx === null ? '—' : (presentPfx ? 'Presente' : 'Falta') }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Compact hint -->
                                        <div v-if="certUiState !== 'ok' || (certReqAvailable && !canEnableByCerts)" class="mt-2 rounded-md border border-amber-200/70 bg-amber-50/70 px-2 py-1.5
              text-[11px] leading-snug text-amber-950
              dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200">
                                            {{ certCompactHint }}
                                        </div>

                                        <!-- Optional: positive confirmation -->
                                        <div v-else class="mt-2 text-[11px] text-slate-600 dark:text-slate-300">
                                            Firmador listo. Token automático disponible.
                                        </div>
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