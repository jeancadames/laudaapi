<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    reason: 'expired' | 'invalid';
    activated: boolean;
    login_url: string;
    home_url: string;
}>();

const isExpired = props.reason === 'expired';
</script>

<template>
    <Head title="Enlace de acceso | Diagnóstico LAUDA 360" />

    <main
        class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10"
    >
        <section
            class="w-full max-w-xl rounded-3xl border bg-background p-6 shadow-sm sm:p-8"
        >
            <p
                class="text-xs font-black tracking-[0.18em] text-primary uppercase"
            >
                LAUDA 360
            </p>

            <h1 class="mt-3 text-2xl font-black tracking-tight">
                {{
                    isExpired
                        ? 'Este enlace de activación expiró'
                        : 'Este enlace de activación no es válido'
                }}
            </h1>

            <template v-if="activated">
                <p class="mt-4 text-sm leading-6 text-muted-foreground">
                    La cuenta ya fue activada anteriormente. El vencimiento de
                    este enlace no limita su acceso al Diagnóstico LAUDA 360.
                </p>

                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Ingrese normalmente con su correo y contraseña.
                </p>
            </template>

            <template v-else>
                <p class="mt-4 text-sm leading-6 text-muted-foreground">
                    {{
                        isExpired
                            ? 'La invitación privada está disponible durante 72 horas desde su envío.'
                            : 'No pudimos validar la firma de esta invitación privada.'
                    }}
                </p>

                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Si todavía no ha activado su cuenta, LAUDA puede reenviar
                    una nueva invitación con 72 horas de vigencia.
                </p>
            </template>

            <div class="mt-7 flex flex-col gap-3 border-t pt-5 sm:flex-row">
                <Link
                    :href="login_url"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl bg-primary px-5 py-2 text-sm font-bold text-primary-foreground"
                >
                    Iniciar sesión
                </Link>

                <Link
                    :href="home_url"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl border px-5 py-2 text-sm font-bold"
                >
                    Volver a LAUDA 360
                </Link>
            </div>

            <p class="mt-5 text-xs leading-5 text-muted-foreground">
                El vencimiento aplica únicamente al enlace de activación. Una
                vez activada la cuenta, el acceso continúa mediante inicio de
                sesión normal.
            </p>
        </section>
    </main>
</template>
