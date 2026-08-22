<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{
    access: {
        public_id: string
        company: string | null
        email: string
    }
    endpoint: string
}>()

const form = useForm({
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Crear contraseña | Diagnóstico LAUDA 360" />

    <main class="min-h-screen bg-background px-4 py-10 text-foreground sm:px-6">
        <div class="mx-auto flex min-h-[80vh] w-full max-w-2xl items-center justify-center">
            <div class="w-full rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
                <div class="mb-6">
                    <div class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        LAUDA 360 · Acceso privado
                    </div>
                    <h1 class="mt-4 text-2xl font-bold tracking-tight">Crea tu contraseña</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Este acceso corresponde al Diagnóstico LAUDA 360 de
                        <strong class="text-foreground">{{ props.access.company || 'tu empresa' }}</strong>.
                        El diagnóstico inicial es gratuito.
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Correo</label>
                        <input
                            :value="props.access.email"
                            type="email"
                            disabled
                            class="w-full rounded-lg border bg-muted px-3 py-2.5 text-sm text-muted-foreground"
                        />
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium">Contraseña</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                            class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">Mínimo 12 caracteres.</p>
                        <p v-if="form.errors.password" class="mt-1 text-xs font-medium text-destructive">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium">Confirmar contraseña</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                            class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Guardando...' : 'Crear contraseña y comenzar' }}
                    </button>
                </form>

                <p class="mt-5 text-center text-xs leading-relaxed text-muted-foreground">
                    Ningún servicio de pago se activa al crear este acceso.
                </p>
            </div>
        </div>
    </main>
</template>
