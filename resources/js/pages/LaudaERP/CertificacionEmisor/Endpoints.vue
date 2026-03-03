<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogTrigger,
} from '@/components/ui/dialog'

type Setting = {
    environment: 'precert' | 'cert' | 'prod'
    cf_prefix: string
    use_directory: boolean
    endpoints: Record<string, any> | null
    base_urls?: Record<string, string> | null
}

type CatalogRow = {
    key: string
    label: string
    method: string
    host_key: 'ecf' | 'fc' | 'status'
    path: string
    preview: string
    placeholders: string[]
}

const props = defineProps<{
    company: { id: number; name: string | null; rnc: string | null }
    setting: Setting
    catalog: CatalogRow[]
}>()

const page = usePage()

const tokenForm = useForm({})

const tokenError = computed(() => (page.props.flash as any)?.error ?? null)
const tokenSuccess = computed(() => (page.props.flash as any)?.success ?? null)
const tokenDebug = computed(() => (page.props.flash as any)?.dgii_token_debug ?? null)

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Servicios', href: '/erp' },
    { title: 'Certificación Emisor', href: '/erp/services/certificacion-emisor' },
    { title: 'Endpoints', href: '/erp/services/certificacion-emisor/endpoints' },
]

const hasEndpoints = computed(() => !!props.setting?.endpoints && Object.keys(props.setting.endpoints).length > 0)
const endpointsCount = computed(() => (props.setting?.endpoints ? Object.keys(props.setting.endpoints).length : 0))
const hasBaseUrls = computed(() => !!props.setting?.base_urls && Object.keys(props.setting.base_urls).length > 0)


/* -----------------------------
  JSON helpers
------------------------------ */
function prettyJson(val: any) {
    try {
        return JSON.stringify(val ?? {}, null, 2)
    } catch {
        return String(val ?? '')
    }
}

function safeParseJson(s: string): { ok: boolean; value: any; error?: string } {
    try {
        const v = s?.trim() ? JSON.parse(s) : {}
        return { ok: true, value: v }
    } catch {
        return { ok: false, value: null, error: 'JSON inválido' }
    }
}

/* -----------------------------
  Catalog -> endpoints template
  Usa paths del catálogo y el host base desde base_urls.ecf
------------------------------ */
function endpointsFromCatalog(): Record<string, any> {
    const out: Record<string, any> = {}

    out.UrlDGII = props.setting.base_urls?.ecf ?? 'https://ecf.dgii.gov.do'

    // map mínimo; expándelo a tu gusto
    const map: Record<string, string> = {
        'auth.seed': 'UrlGetSeed',
        'auth.get_seed': 'UrlGetSeed',
        'auth.validate_seed': 'UrlTestSeed',
        'consulta.resultado': 'UrlConsultaResultado',
        'consulta.directorio': 'UrlDirectorioServicios',
        'status.obtener': 'UrlStatusServicios',
    }

    for (const row of props.catalog) {
        const k = map[ row.key ]
        if (!k) continue
        out[ k ] = row.path
    }

    return out
}

/* -----------------------------
  Editor
------------------------------ */
const openEditor = ref(false)
const jsonError = ref<string | null>(null)
const baseUrlsError = ref<string | null>(null)

const form = useForm<{
    environment: 'precert' | 'cert' | 'prod'
    cf_prefix: string
    use_directory: boolean
    endpoints_json: string
    base_urls_json: string
}>({
    environment: props.setting.environment,
    cf_prefix: props.setting.cf_prefix,
    use_directory: !!props.setting.use_directory,
    endpoints_json: prettyJson(props.setting.endpoints ?? {}),
    base_urls_json: prettyJson(
        props.setting.base_urls ?? {
            ecf: 'https://ecf.dgii.gov.do',
            fc: 'https://fc.dgii.gov.do',
            status: 'https://statusecf.dgii.gov.do',
        }
    ),
})

function resetEditor() {
    jsonError.value = null
    baseUrlsError.value = null
    form.clearErrors()

    form.environment = props.setting.environment
    form.cf_prefix = props.setting.cf_prefix
    form.use_directory = !!props.setting.use_directory

    const ep = hasEndpoints.value ? (props.setting.endpoints ?? {}) : endpointsFromCatalog()
    form.endpoints_json = prettyJson(ep)

    const bu =
        props.setting.base_urls ?? {
            ecf: 'https://ecf.dgii.gov.do',
            fc: 'https://fc.dgii.gov.do',
            status: 'https://statusecf.dgii.gov.do',
        }
    form.base_urls_json = prettyJson(bu)
}

watch(openEditor, (v) => {
    if (v) resetEditor()
})

function loadFromCatalog() {
    form.endpoints_json = prettyJson(endpointsFromCatalog())
}

function submit() {
    jsonError.value = null
    baseUrlsError.value = null

    const ep = safeParseJson(form.endpoints_json)
    if (!ep.ok) {
        jsonError.value = 'El JSON de endpoints no es válido.'
        return
    }

    const bu = safeParseJson(form.base_urls_json)
    if (!bu.ok) {
        baseUrlsError.value = 'El JSON de base_urls no es válido.'
        return
    }

    form.transform(() => ({
        environment: form.environment,
        cf_prefix: form.cf_prefix?.trim(),
        use_directory: form.use_directory,
        endpoints: ep.value ?? {},
        base_urls: bu.value ?? {},
    }))

    form.post('/erp/services/certificacion-emisor/endpoints', {
        preserveScroll: true,
        onSuccess: () => {
            openEditor.value = false
            router.visit(window.location.href, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            })
        },
    })
}
</script>

<template>

    <Head title="Endpoints DGII" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <header class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Endpoints DGII</h1>
                    <p class="text-sm text-muted-foreground">
                        Empresa: <span class="font-medium">{{ company.name ?? '—' }}</span>
                        <span class="mx-2">•</span>
                        RNC: <span class="font-medium">{{ company.rnc ?? '—' }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Badge variant="secondary" class="capitalize">{{ setting.environment }}</Badge>

                    <Dialog v-model:open="openEditor">
                        <DialogTrigger as-child>
                            <Button variant="secondary">Editar</Button>
                        </DialogTrigger>

                        <DialogContent class="sm:max-w-3xl">
                            <DialogHeader>
                                <DialogTitle>Editar Endpoints</DialogTitle>
                                <DialogDescription>
                                    Edita ambiente, cf_prefix, use_directory, base_urls y endpoints (JSON).
                                </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-4">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label>Ambiente</Label>
                                        <select v-model="form.environment" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                            <option value="precert">precert</option>
                                            <option value="cert">cert</option>
                                            <option value="prod">prod</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>cf_prefix</Label>
                                        <input v-model="form.cf_prefix" class="h-9 w-full rounded-md border bg-background px-3 text-sm" placeholder="testecf | certecf | ecf" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label>use_directory</Label>
                                        <label class="flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm">
                                            <input type="checkbox" v-model="form.use_directory" class="h-4 w-4" />
                                            <span class="text-muted-foreground">Usar directorio DGII</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label>base_urls (JSON)</Label>
                                    <textarea v-model="form.base_urls_json" rows="6" class="w-full rounded-md border bg-background p-2 text-xs font-mono" placeholder='{ "ecf":"https://ecf.dgii.gov.do","fc":"https://fc.dgii.gov.do","status":"https://statusecf.dgii.gov.do" }' />
                                    <p v-if="baseUrlsError" class="text-xs text-destructive">{{ baseUrlsError }}</p>
                                </div>

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <Label>endpoints (JSON)</Label>
                                        <Button type="button" variant="secondary" size="sm" @click="loadFromCatalog">
                                            Cargar desde catálogo (seed)
                                        </Button>
                                    </div>

                                    <textarea v-model="form.endpoints_json" rows="10" class="w-full rounded-md border bg-background p-2 text-xs font-mono" placeholder='{ "UrlDGII":"https://ecf.dgii.gov.do", "UrlGetSeed": "/{cf}/autenticacion/api/autenticacion/semilla" }' />
                                    <p v-if="jsonError" class="text-xs text-destructive">{{ jsonError }}</p>
                                    <p v-if="(form.errors as any)?.endpoints" class="text-xs text-destructive">{{ (form.errors as any).endpoints }}</p>
                                </div>
                            </div>

                            <DialogFooter class="gap-2">
                                <Button variant="secondary" type="button" @click="openEditor = false">Cancelar</Button>
                                <Button type="button" :disabled="form.processing" @click="submit">
                                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Button as-child variant="outline" class="gap-2" title="Volver a Certificación Emisor">
                        <Link href="/erp/services/certificacion-emisor">
                            <!-- <ArrowLeft class="h-4 w-4" /> -->
                            Volver
                        </Link>
                    </Button>
                </div>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Token DGII</CardTitle>
                    <CardDescription>
                        Pre-check: Semilla → Firma → ValidarSemilla. (Ahora mismo estamos validando el flujo y la conectividad)
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary" class="capitalize">Ambiente: {{ setting.environment }}</Badge>
                        <Badge variant="secondary">cf_prefix: {{ setting.cf_prefix }}</Badge>
                    </div>

                    <div v-if="tokenError" class="rounded-lg border border-red-500/30 bg-red-500/5 p-3 text-sm">
                        {{ tokenError }}
                    </div>

                    <div v-if="tokenSuccess" class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
                        {{ tokenSuccess }}
                    </div>

                    <div v-if="tokenDebug" class="rounded-lg border p-3">
                        <div class="text-sm font-medium">Debug</div>

                        <div class="mt-2 text-xs text-muted-foreground">
                            GET Seed: <span class="font-mono break-all">{{ tokenDebug.get_seed_url }}</span>
                        </div>

                        <div class="mt-1 text-xs text-muted-foreground">
                            POST Validar: <span class="font-mono break-all">{{ tokenDebug.validate_url }}</span>
                        </div>

                        <div class="mt-3 text-xs text-muted-foreground">Seed XML</div>
                        <pre class="mt-1 max-h-64 overflow-auto rounded-md bg-muted/40 p-3 text-xs">{{ tokenDebug.seed_xml }}</pre>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Setting actual</CardTitle>
                    <CardDescription>Valores guardados en dgii_company_settings.</CardDescription>
                </CardHeader>

                <CardContent class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-muted-foreground">Ambiente</div>
                        <div class="mt-1 text-sm font-medium">{{ setting.environment }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-muted-foreground">cf_prefix</div>
                        <div class="mt-1 text-sm font-medium">{{ setting.cf_prefix }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-muted-foreground">use_directory</div>
                        <div class="mt-1 text-sm font-medium">{{ setting.use_directory ? 'Sí' : 'No' }}</div>
                    </div>

                    <div v-if="hasBaseUrls" class="rounded-lg border p-4 md:col-span-3">
                        <div class="text-xs text-muted-foreground">base_urls</div>
                        <pre class="mt-2 max-h-56 overflow-auto rounded-md bg-muted/40 p-3 text-xs">{{ prettyJson(setting.base_urls) }}</pre>
                    </div>

                    <div class="rounded-lg border p-4 md:col-span-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-muted-foreground">endpoints (JSON)</div>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary">{{ endpointsCount }} keys</Badge>
                                <Badge :variant="hasEndpoints ? 'secondary' : 'destructive'">{{ hasEndpoints ? 'CUSTOM' : 'VACÍO' }}</Badge>
                            </div>
                        </div>

                        <pre class="mt-2 max-h-80 overflow-auto rounded-md bg-muted/40 p-3 text-xs">{{ prettyJson(setting.endpoints ?? {}) }}</pre>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <CardTitle>Catálogo</CardTitle>
                            <CardDescription>Listado (seed) con preview final.</CardDescription>
                        </div>
                        <Badge variant="secondary">{{ catalog.length }} endpoints</Badge>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Key</th>
                                    <th class="px-3 py-2 text-left">Método</th>
                                    <th class="px-3 py-2 text-left">Host</th>
                                    <th class="px-3 py-2 text-left">Path</th>
                                    <th class="px-3 py-2 text-left">Preview</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in catalog" :key="e.key" class="border-t">
                                    <td class="px-3 py-2">
                                        <div class="font-mono text-xs">{{ e.key }}</div>
                                        <div class="text-xs text-muted-foreground">{{ e.label }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ e.method }}</td>
                                    <td class="px-3 py-2">{{ e.host_key }}</td>
                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ e.path }}</td>
                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ e.preview }}</td>
                                </tr>

                                <tr v-if="catalog.length === 0" class="border-t">
                                    <td colspan="5" class="px-3 py-4 text-sm text-muted-foreground">No hay catálogo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </ErpLayout>
</template>
