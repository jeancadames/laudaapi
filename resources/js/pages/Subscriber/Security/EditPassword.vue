<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import PortalPage from '@/components/portal/PortalPage.vue';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    mustChangePassword?: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Seguridad', href: '/subscriber/security' },
];

const form = useForm({
    password: '',
    password_confirmation: '',
});

const isSubmitDisabled = computed(() => {
    return form.processing || !form.password || !form.password_confirmation;
});

function submit() {
    form.put('/subscriber/security/password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password', 'password_confirmation');
        },
        onError: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <Head title="Cambiar contraseña" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            title="Seguridad"
            description="Cambia la contraseña con la que entras a LAUDA."
            width="narrow"
        >
            <div class="space-y-6">
                <Alert
                    v-if="props.mustChangePassword"
                    class="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <ShieldAlert class="h-4 w-4" />
                    <AlertTitle>Cambio de contraseña requerido</AlertTitle>
                    <AlertDescription class="mt-2 leading-6">
                        Estás usando una contraseña temporal generada durante la
                        activación. Debes definir una nueva contraseña para
                        continuar usando tu cuenta con seguridad.
                    </AlertDescription>
                </Alert>

                <Card class="rounded-2xl border-slate-200 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl border p-2">
                                <LockKeyhole class="h-5 w-5" />
                            </div>

                            <div>
                                <CardTitle>Nueva contraseña</CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        props.mustChangePassword
                                            ? 'Elige tu propia contraseña. La temporal dejará de funcionar.'
                                            : 'Usa al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos.'
                                    }}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="password">Nueva contraseña</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Ingresa tu nueva contraseña"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation"
                                    >Confirmar nueva contraseña</Label
                                >
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Confirma tu nueva contraseña"
                                />
                                <InputError
                                    :message="form.errors.password_confirmation"
                                />
                            </div>

                            <div
                                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        props.mustChangePassword
                                            ? 'Al guardar, la contraseña temporal deja de funcionar.'
                                            : 'Al guardar, usarás la nueva contraseña la próxima vez que entres.'
                                    }}
                                </p>

                                <Button
                                    type="submit"
                                    :disabled="isSubmitDisabled"
                                >
                                    {{
                                        form.processing
                                            ? 'Guardando...'
                                            : 'Actualizar contraseña'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </PortalPage>
    </AppLayout>
</template>
