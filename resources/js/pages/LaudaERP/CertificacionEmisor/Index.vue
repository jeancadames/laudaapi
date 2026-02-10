<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{
    company: { id: number; name: string | null; rnc: string | null }
    setting: { environment: 'precert' | 'cert' | 'prod'; use_directory: boolean; endpoints: Record<string, any> }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Servicios', href: '/erp' }, // cambia a /erp/services cuando exista
    { title: 'Certificación Emisor', href: '/erp/services/certificacion-emisor' },
]

const tab = ref<'guia' | 'certificados' | 'endpoints' | 'sets-ecf' | 'sets-comercial'>('guia')

/**
 * ✅ Guía integrada (DGII PDF)
 * Fuente: Guia-Basica-para-ser-Emisor-Electronico.pdf
 */
const guia = {
    meta: {
        title: 'Guía Básica para ser Emisor Electrónico',
        sourceLabel: 'DGII (PDF)',
        sourceUrl:
            'https://dgii.gov.do/publicacionesOficiales/bibliotecaVirtual/contribuyentes/facturacion/Documents/Facturaci%C3%B3n%20Electr%C3%B3nica/Guia-Basica-para-ser-Emisor-Electronico.pdf',
        published: 'Enero 2025',
    },

    definicion: 'Es todo aquel contribuyente autorizado por Impuestos Internos para emitir Comprobantes Fiscales Electrónicos (e-CF).',

    requisitos: [
        'Estar inscrito en el Registro Nacional de Contribuyentes (RNC).',
        'Estar registrado como contribuyente con obligaciones tributarias a su cargo.',
        'Poseer clave de acceso a la Oficina Virtual (OFV).',
        'Completar el Formulario de Solicitud de Autorización (FI-GDF-016, Vers. C).',
        'Contar con Certificado Digital para Procesos Tributarios (prestadora autorizada por INDOTEL) a nombre del representante.',
        'Cumplir exigencias técnicas y aprobar satisfactoriamente el proceso de certificación.',
        'Cumplir Norma General 06-2018 sobre Comprobantes Fiscales.',
    ],

    etapas: [
        {
            key: 'solicitud',
            title: '1) Solicitud',
            bullets: [
                'Completar y enviar el “Formulario de Solicitud”.',
                'DGII valida requisitos y responde por el buzón OFV con enlace del portal de Facturación Electrónica + usuario y clave.',
            ],
        },
        {
            key: 'sets',
            title: '2) Sets de Pruebas',
            bullets: [
                'Comprobar que el software puede emitir/recibir e-CF, Acuse de Recibo y Aprobación Comercial.',
                'Validar que puede generar Representación Impresa (RI) conforme normativa.',
                'Pruebas en el portal de Certificación; al completarlas, procede la Declaración Jurada.',
            ],
            sets: [ 'Pruebas de Datos', 'Pruebas de Simulación', 'Pruebas de Comunicación' ],
        },
        {
            key: 'dj',
            title: '3) Declaración Jurada',
            bullets: [
                'Formulario electrónico con responsabilidad legal bajo fe de juramento.',
                'Declara que las pruebas fueron realizadas íntegramente, sin fraude ni irregularidades.',
                'Luego pasa a la etapa de Certificación.',
            ],
        },
        {
            key: 'certificacion',
            title: '4) Certificación',
            bullets: [
                'Obtención de la Autorización para ser Emisor Electrónico (para emitir e-CF).',
                'Se habilita el menú de Facturación Electrónica en OFV para solicitar e-NCF e iniciar emisión.',
            ],
        },
    ],

    obligaciones: [
        'Firmar digitalmente los e-CF emitidos usando Certificado Digital vigente.',
        'Emitir la Representación Impresa (RI) del e-CF al receptor no electrónico.',
        'Recibir e-CF de proveedores que sean emitidos válidamente.',
        'Exhibir a la DGII informaciones digitales o físicas requeridas (Código Tributario).',
        'Conservar los e-CF conforme Código Tributario.',
    ],

    contingencias: [
        {
            title: 'Falta de conectividad',
            desc: 'Generar e-CF offline y enviar en un plazo no mayor a 72 horas.',
        },
        {
            title: 'Cuando no sea posible la emisión del e-CF',
            desc: 'Usar secuencias autorizadas de comprobantes no electrónicos y enviar e-CF reemplazantes en un plazo no mayor a 30 días; la contingencia no puede exceder 15 días.',
        },
        {
            title: 'Mecanismos DGII no disponibles',
            desc: 'Almacenar los e-CF y enviarlos cuando se restablezca la comunicación con la DGII.',
        },
    ],

    facturadorGratuito: {
        title: 'Facturador Gratuito',
        desc:
            'Herramienta digital para emitir facturas electrónicas (facturas, notas de crédito y débito) cumpliendo normativa.',
        requisitos: [
            'Inscrito en RNC.',
            'Clave OFV y dispositivo de seguridad (token/tarjeta/token digital u otro).',
            'Autorización para emitir NCF.',
            'Estar al día en obligaciones tributarias y deberes formales.',
            'Certificado Digital para procedimiento tributario (INDOTEL).',
            'Computador o móvil con internet.',
            'No haber sido autorizado a emitir e-CF con otro sistema distinto al Facturador Gratuito.',
            'Facturar máximo 150 facturas al mes.',
            'Completar FI-GDF-018 (Solicitud uso Facturador Gratuito, Vers. A).',
        ],
        beneficios: [
            'Ahorro de costos (evita software comercial).',
            'Cumplimiento legal.',
            'Acceso rápido (emisión y envío ágil).',
            'Seguridad e integridad de la información.',
        ],
    },
}
</script>

<template>

    <Head title="Certificación Emisor (DGII e-CF)" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <header class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">Certificación Emisor (DGII e-CF)</h1>
                        <p class="text-sm text-muted-foreground">
                            Empresa: <span class="font-medium">{{ company.name ?? '—' }}</span>
                            <span class="mx-2">•</span>
                            RNC: <span class="font-medium">{{ company.rnc ?? '—' }}</span>
                        </p>
                    </div>

                    <Badge variant="secondary" class="capitalize">
                        Ambiente: {{ setting.environment }}
                    </Badge>
                </div>
            </header>

            <Tabs v-model="tab" class="w-full">
                <TabsList class="grid w-full grid-cols-5">
                    <TabsTrigger value="guia">Guía DGII</TabsTrigger>
                    <TabsTrigger value="certificados">Certificados</TabsTrigger>
                    <TabsTrigger value="endpoints">Endpoints</TabsTrigger>
                    <TabsTrigger value="sets-ecf">Set e-CF</TabsTrigger>
                    <TabsTrigger value="sets-comercial">Aprobación</TabsTrigger>
                </TabsList>

                <!-- ✅ GUÍA DGII (integrada) -->
                <TabsContent value="guia" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Guía DGII (integrada)</CardTitle>
                            <CardDescription>Requisitos, etapas, obligaciones y contingencias.</CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-6">
                            <!-- Fuente -->
                            <div class="flex flex-col gap-1 rounded-lg border p-4">
                                <div class="text-sm font-medium">{{ guia.meta.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Fuente: {{ guia.meta.sourceLabel }} • {{ guia.meta.published }}
                                    <span class="mx-2">•</span>
                                    <a :href="guia.meta.sourceUrl" target="_blank" class="underline underline-offset-4">
                                        Abrir PDF
                                    </a>
                                </div>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ guia.definicion }}
                                </p>
                            </div>

                            <!-- Requisitos -->
                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Requisitos (antes de iniciar)</div>
                                <ul class="list-disc space-y-2 pl-5 text-sm">
                                    <li v-for="(r, i) in guia.requisitos" :key="i">{{ r }}</li>
                                </ul>
                            </div>

                            <!-- Etapas -->
                            <div class="space-y-3">
                                <div class="text-sm font-semibold">Etapas para ser Emisor Electrónico</div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div v-for="step in guia.etapas" :key="step.key" class="rounded-lg border p-4">
                                        <div class="mb-2 font-medium">{{ step.title }}</div>

                                        <ul class="list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(b, i) in step.bullets" :key="i">{{ b }}</li>
                                        </ul>

                                        <div v-if="(step as any).sets?.length" class="mt-3">
                                            <div class="text-xs font-semibold text-muted-foreground">Sets establecidos</div>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <Badge v-for="s in (step as any).sets" :key="s" variant="secondary">{{ s }}</Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Obligaciones -->
                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Obligaciones del Emisor Electrónico</div>
                                <ul class="list-disc space-y-2 pl-5 text-sm">
                                    <li v-for="(o, i) in guia.obligaciones" :key="i">{{ o }}</li>
                                </ul>
                            </div>

                            <!-- Contingencias -->
                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Contingencias</div>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div v-for="(c, i) in guia.contingencias" :key="i" class="rounded-lg border p-4">
                                        <div class="font-medium">{{ c.title }}</div>
                                        <p class="mt-2 text-sm text-muted-foreground">{{ c.desc }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Facturador Gratuito -->
                            <div class="rounded-lg border p-4 space-y-4">
                                <div>
                                    <div class="text-sm font-semibold">{{ guia.facturadorGratuito.title }}</div>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ guia.facturadorGratuito.desc }}</p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <div class="text-sm font-medium">Requisitos</div>
                                        <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(r, i) in guia.facturadorGratuito.requisitos" :key="i">{{ r }}</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium">Beneficios</div>
                                        <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(b, i) in guia.facturadorGratuito.beneficios" :key="i">{{ b }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Certificados -->
                <TabsContent value="certificados" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Certificados</CardTitle>
                            <CardDescription>Sube P12/PFX/CER, valida lectura, vencimiento y datos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">Pendiente: UI CRUD certificados.</p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Endpoints -->
                <TabsContent value="endpoints" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ambientes y Endpoints</CardTitle>
                            <CardDescription>Precert / Cert / Prod, directorio DGII y health checks.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">Pendiente: formulario de endpoints + test.</p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Set e-CF -->
                <TabsContent value="sets-ecf" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Set de Pruebas e-CF</CardTitle>
                            <CardDescription>Sube Excel, genera XML por fila, firma, envía, trackId/estado.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">Pendiente: upload Excel + grid de filas.</p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Aprobación -->
                <TabsContent value="sets-comercial" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Aprobación / Rechazo Comercial</CardTitle>
                            <CardDescription>Procesamiento separado del set comercial y tracking.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">Pendiente: upload + jobs + tracking.</p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>
