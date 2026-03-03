<script setup lang="ts">
import { Label } from '@/components/ui/label'

defineProps({
    error: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="flex-1">
        <Label for="terms" class="text-sm leading-relaxed text-muted-foreground block">
            Confirmo que he leído y acepto los
            <a href="/legal/terminos" target="_blank" class="text-primary underline hover:text-primary/80">
                Términos y Condiciones
            </a>
            y la
            <a href="/legal/privacidad" target="_blank" class="text-primary underline hover:text-primary/80">
                Política de Privacidad
            </a>.
        </Label>

        <p v-if="error" class="text-xs text-red-600 mt-2">
            {{ error }}
        </p>
    </div>
</template>