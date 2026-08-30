<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'
import Toaster from '@/components/ui/toast/Toaster.vue'
import { toast } from '@/components/ui/toast/use-toast'

type FlashPayload = {
    success?: unknown
    warning?: unknown
    error?: unknown
    info?: unknown
}

type FlashKind = 'success' | 'warning' | 'error' | 'info'

const page = usePage()

function message(value: unknown): string | null {
    if (typeof value !== 'string') return null

    const normalized = value.trim()

    return normalized !== '' ? normalized : null
}

function showFlash(raw: unknown): void {
    const flash = (raw ?? {}) as FlashPayload

    const candidates: Array<[FlashKind, string | null]> = [
        ['error', message(flash.error)],
        ['warning', message(flash.warning)],
        ['success', message(flash.success)],
        ['info', message(flash.info)],
    ]

    const current = candidates.find(([, value]) => value !== null)

    if (!current) return

    const [kind, description] = current

    const titles: Record<FlashKind, string> = {
        success: 'Listo',
        warning: 'Atención',
        error: 'Error',
        info: 'Información',
    }

    const variants: Record<FlashKind, 'success' | 'warning' | 'destructive' | 'info'> = {
        success: 'success',
        warning: 'warning',
        error: 'destructive',
        info: 'info',
    }

    toast({
        title: titles[kind],
        description: description ?? undefined,
        variant: variants[kind],
        duration: 5000,
    })
}

let removeSuccessListener: (() => void) | null = null

onMounted(() => {
    showFlash(page.props.flash)

    removeSuccessListener = router.on('success', (event) => {
        showFlash(event.detail.page.props.flash)
    })
})

onUnmounted(() => {
    removeSuccessListener?.()
    removeSuccessListener = null
})
</script>

<template>
    <Toaster />
</template>
