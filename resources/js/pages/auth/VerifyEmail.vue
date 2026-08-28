<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2, MailCheck, RefreshCw, ShieldCheck } from 'lucide-vue-next';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const props = defineProps<{
    status?: string | null;
}>();

const verificationLinkWasSent =
    props.status === 'verification-link-sent';
</script>

<template>
    <AuthLayout
        title="Verifica tu correo"
        description="Confirma tu correo para continuar con la configuración inicial de tu cuenta LAUDAAPI."
    >
        <Head title="Verifica tu correo" />

        <div class="space-y-6">
            <div class="flex flex-col items-center text-center">
                <div
                    class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-red-600 text-white"
                >
                    <MailCheck class="h-7 w-7" />
                </div>

                <h1 class="text-xl font-black">
                    Revisa tu correo
                </h1>

                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Te enviamos un enlace de verificación.
                    Confírmalo para continuar al App Hub.
                </p>
            </div>

            <div
                v-if="verificationLinkWasSent"
                class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
            >
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                <span>
                    Correo de verificación reenviado correctamente.
                </span>
            </div>

            <Form
                v-bind="send.form()"
                v-slot="{ processing }"
                class="space-y-3"
            >
                <Button
                    :disabled="processing"
                    variant="secondary"
                    class="w-full"
                >
                    <Spinner v-if="processing" />
                    <RefreshCw v-else class="h-4 w-4" />
                    Reenviar correo
                </Button>

                <a
                    href="/onboarding"
                    class="inline-flex h-10 w-full items-center justify-center rounded-md border px-4 text-sm font-semibold"
                >
                    Ya verifiqué mi correo
                </a>
            </Form>

            <div class="flex gap-3 rounded-xl border p-4">
                <ShieldCheck
                    class="mt-0.5 h-5 w-5 shrink-0 text-red-600"
                />
                <p class="text-sm leading-6 text-muted-foreground">
                    El onboarding permanece protegido hasta verificar
                    el correo.
                </p>
            </div>

            <p class="text-center text-sm text-muted-foreground">
                ¿Usaste un correo incorrecto?
                <TextLink
                    :href="logout()"
                    as="button"
                >
                    Salir
                </TextLink>
            </p>
        </div>
    </AuthLayout>
</template>
