<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue'
import { terms, privacy } from '@/routes/legal'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowRight,
    FileText,
    Mail,
    Scale,
    ShieldCheck,
} from 'lucide-vue-next'
</script>

<template>

    <Head title="Centro Legal | LaudaAPI" />

    <LegalLayout>
        <template #header>
            <p class="text-xs uppercase tracking-[0.25em] text-muted-foreground">
                Centro Legal
            </p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight">
                Documentación Legal de LaudaAPI
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                Consulta los documentos legales principales del ecosistema LaudaAPI,
                incluyendo términos de uso, privacidad y canales de contacto.
            </p>
        </template>

        <div class="space-y-10">
            <!-- Intro -->
            <section class="rounded-2xl border bg-card p-6 shadow-sm">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            <Scale class="h-3.5 w-3.5" />
                            Ecosistema LaudaAPI
                        </div>

                        <h2 class="mt-4 text-xl font-bold tracking-tight">
                            Centro de información legal
                        </h2>

                        <p class="mt-3 text-sm leading-7 text-muted-foreground">
                            LaudaAPI agrupa soluciones digitales como e-CF, Cumplimiento,
                            POS, CRM, Social, Status y otros módulos especializados.
                            Esta página funciona como punto de acceso a los documentos legales
                            principales del ecosistema.
                        </p>
                    </div>

                    <div class="rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground md:w-72">
                        <p class="font-semibold text-foreground">
                            Última actualización
                        </p>

                        <p class="mt-1">
                            Enero 2026
                        </p>
                    </div>
                </div>
            </section>

            <!-- Documentos -->
            <section>
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        Documentos disponibles
                    </p>

                    <h2 class="mt-2 text-2xl font-bold tracking-tight">
                        Consulta rápida
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm leading-7 text-muted-foreground">
                        Estos son los documentos legales activos actualmente. No se muestran
                        enlaces a documentos que todavía no tengan una página publicada.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <!-- Términos -->
                    <article class="rounded-2xl border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                            <FileText class="h-5 w-5" />
                        </div>

                        <h3 class="text-lg font-bold tracking-tight">
                            Términos y Condiciones
                        </h3>

                        <p class="mt-2 text-sm leading-7 text-muted-foreground">
                            Reglas de uso, responsabilidades, condiciones de acceso,
                            prueba gratuita, limitaciones y disposiciones aplicables al uso
                            de LaudaAPI.
                        </p>

                        <Link :href="terms().url" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">
                            Leer términos
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </article>

                    <!-- Privacidad -->
                    <article class="rounded-2xl border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                            <ShieldCheck class="h-5 w-5" />
                        </div>

                        <h3 class="text-lg font-bold tracking-tight">
                            Política de Privacidad
                        </h3>

                        <p class="mt-2 text-sm leading-7 text-muted-foreground">
                            Información sobre recopilación, uso, conservación, seguridad,
                            derechos del usuario y tratamiento de datos personales.
                        </p>

                        <Link :href="privacy().url" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">
                            Leer política de privacidad
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </article>
                </div>
            </section>

            <!-- Alcance -->
            <section class="rounded-2xl border bg-card p-6 shadow-sm">
                <h2 class="text-xl font-bold tracking-tight">
                    Alcance legal del ecosistema
                </h2>

                <div class="mt-4 space-y-4 text-sm leading-7 text-muted-foreground">
                    <p>
                        LaudaAPI.com funciona como portal principal del ecosistema.
                        Las operaciones específicas, configuraciones técnicas, activaciones
                        individuales y procesos internos de cada tenant pueden gestionarse
                        desde las aplicaciones correspondientes.
                    </p>

                    <p>
                        Cada solución puede tener flujos particulares de configuración,
                        activación, soporte o uso operativo, según el servicio contratado
                        y las necesidades del cliente.
                    </p>
                </div>
            </section>

            <!-- Contacto -->
            <section class="rounded-2xl border bg-muted/30 p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-sm font-semibold">
                            <Mail class="h-4 w-4 text-primary" />
                            Contacto legal y comercial
                        </div>

                        <p class="mt-2 text-sm leading-7 text-muted-foreground">
                            Para consultas legales, comerciales, privacidad o soporte,
                            puedes escribirnos directamente.
                        </p>
                    </div>

                    <a href="mailto:contacto@laudaapi.com" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90">
                        contacto@laudaapi.com
                    </a>
                </div>
            </section>
        </div>
    </LegalLayout>
</template>