<script setup lang="ts">
/**
 * Confirmación para quitar el acceso a una aplicación. Se muestra junto a la
 * aplicación afectada y recibe el foco para que el teclado y los lectores de
 * pantalla lleguen directamente a ella.
 */
import { nextTick, onMounted, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';

const props = defineProps<{
    company: string;
    service: string;
    whole: boolean;
    busy: boolean;
}>();
defineEmits<{ confirm: []; cancel: [] }>();

const panel = ref<HTMLElement | null>(null);
onMounted(async () => {
    await nextTick();
    panel.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    panel.value?.focus({ preventScroll: true });
});
const titleId = `revoke-title-${props.service.replace(/\W+/g, '-').toLowerCase()}`;
</script>

<template>
    <section
        ref="panel"
        role="region"
        tabindex="-1"
        :aria-labelledby="titleId"
        class="w-full rounded-xl border border-amber-300 bg-amber-50 p-4 outline-none dark:border-amber-900/60 dark:bg-amber-950/30"
    >
        <h3 :id="titleId" class="font-semibold text-slate-950 dark:text-white">
            {{
                whole
                    ? `Desconectar ${service} de toda la empresa`
                    : `Quitar tu acceso a ${service}`
            }}
        </h3>
        <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">
            {{ company }}.
            {{
                whole
                    ? 'Nadie de la empresa podrá abrir esta aplicación desde LAUDA.'
                    : 'Ya no podrás abrir esta aplicación desde LAUDA.'
            }}
            No se borra nada dentro de la aplicación y podrás volver a
            conectarla cuando quieras.
        </p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <button
                type="button"
                :class="portalButton.dark"
                :disabled="busy"
                @click="$emit('confirm')"
            >
                {{
                    busy
                        ? 'Procesando…'
                        : whole
                          ? 'Sí, desconectar'
                          : 'Sí, quitar mi acceso'
                }}
            </button>
            <button
                type="button"
                :class="portalButton.link"
                :disabled="busy"
                @click="$emit('cancel')"
            >
                Mantener el acceso
            </button>
        </div>
    </section>
</template>
