<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { terms } from '@/routes/legal'
import { privacy } from '@/routes/legal';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <AppLayout>
        <section class="space-y-10">
            <!-- Header -->
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-muted-foreground">
                    Centro Legal
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">
                    Documentación Legal de LaudaAPI
                </h1>
                <p class="mt-2 text-sm text-muted-foreground max-w-xl">
                    Encuentra aquí toda la información legal relacionada con el uso de LaudaAPI, incluyendo
                    términos, privacidad y políticas de protección de datos.
                </p>
            </div>

            <Separator />

            <!-- Cards -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Términos -->
                <Card class="transition hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Términos y Condiciones</CardTitle>
                        <CardDescription>
                            Reglas de uso, responsabilidades y condiciones de la prueba gratuita.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link :href="terms().url" class="text-primary underline text-sm font-medium hover:text-primary/80">
                            Leer documento →
                        </Link>
                    </CardContent>
                </Card>

                <!-- Privacidad -->
                <Card class="transition hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Política de Privacidad</CardTitle>
                        <CardDescription>
                            Cómo recopilamos, usamos y protegemos tu información personal.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link :href="privacy().url" class="text-primary underline text-sm font-medium hover:text-primary/80">
                            Leer documento →
                        </Link>
                    </CardContent>
                </Card>

                <!-- Cookies (opcional) -->
                <Card class="transition hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Política de Cookies</CardTitle>
                        <CardDescription>
                            Información sobre el uso de cookies y tecnologías similares.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link href="/legal/cookies" class="text-primary underline text-sm font-medium hover:text-primary/80">
                            Leer documento →
                        </Link>
                    </CardContent>
                </Card>

                <!-- Aviso Legal (opcional) -->
                <Card class="transition hover:shadow-md">
                    <CardHeader>
                        <CardTitle>Aviso Legal</CardTitle>
                        <CardDescription>
                            Información corporativa, propiedad intelectual y limitaciones legales.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link href="/legal/aviso" class="text-primary underline text-sm font-medium hover:text-primary/80">
                            Leer documento →
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </section>
    </AppLayout>
</template>
