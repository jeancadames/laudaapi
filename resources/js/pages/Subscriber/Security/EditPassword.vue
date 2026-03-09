<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { LockKeyhole, ShieldAlert } from 'lucide-vue-next'

import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'

const props = defineProps<{
    mustChangePassword?: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptores', href: subscriber().url },
    { title: 'Seguridad', href: '/subscriber/security' },
    { title: 'Cambiar contraseña', href: '/subscriber/security' },
]

const form = useForm({
    password: '',
    password_confirmation: '',
})

const isSubmitDisabled = computed(() => {
    return form.processing || !form.password || !form.password_confirmation
})

function submit() {
    form.put('/subscriber/security/password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password', 'password_confirmation')
        },
        onError: () => {
            form.reset('password', 'password_confirmation')
        },
    })
}
</script>

<template>

    <Head title="Cambiar contraseña" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <div class="mx-auto w-full max-w-3xl space-y-6">
                <Alert v-if="props.mustChangePassword" class="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                    <ShieldAlert class="h-4 w-4" />
                    <AlertTitle>Cambio de contraseña requerido</AlertTitle>
                    <AlertDescription class="mt-2 leading-6">
                        Estás usando una contraseña temporal generada durante la activación.
                        Debes definir una nueva contraseña para continuar usando tu cuenta con seguridad.
                    </AlertDescription>
                </Alert>

                <Card class="rounded-2xl">
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl border p-2">
                                <LockKeyhole class="h-5 w-5" />
                            </div>

                            <div>
                                <CardTitle>Definir nueva contraseña</CardTitle>
                                <CardDescription class="mt-1">
                                    Crea una nueva contraseña para tu cuenta. La contraseña temporal dejará de ser válida.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="password">Nueva contraseña</Label>
                                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" placeholder="Ingresa tu nueva contraseña" />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirmar nueva contraseña</Label>
                                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Confirma tu nueva contraseña" />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <div class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-muted-foreground">
                                    Al guardar, se desactivará la contraseña temporal de activación.
                                </p>

                                <Button type="submit" :disabled="isSubmitDisabled">
                                    {{ form.processing ? 'Guardando...' : 'Actualizar contraseña' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>