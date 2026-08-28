<script setup>
import BrandLogo from '@/components/BrandLogo.vue'
import { Head } from '@inertiajs/vue3'
import {
    ArrowRight,
    Boxes,
    CheckCircle2,
    FileText,
    Menu,
    MessageCircle,
    ShieldCheck,
    Store,
    User,
    Users,
    X,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const APP_URL = 'https://app.laudaapi.com'

const solutions = [
    {
        name: 'Social',
        description: 'Contenido, canales, inbox y captación de oportunidades.',
        href: 'https://social.laudaapi.com',
        icon: MessageCircle,
        color: '#EC4899',
    },
    {
        name: 'CRM',
        description: 'Clientes, oportunidades, pipeline y seguimiento comercial.',
        href: 'https://crm.laudaapi.com',
        icon: Users,
        color: '#2563EB',
    },
    {
        name: 'POS',
        description: 'Ventas, inventario, caja, crédito, rutas y despacho.',
        href: 'https://pos.laudaapi.com',
        icon: Store,
        color: '#16A34A',
    },
    {
        name: 'e-CF',
        description: 'Emisión y seguimiento de comprobantes electrónicos.',
        href: 'https://ecf.laudaapi.com',
        icon: FileText,
        color: '#D97706',
    },
    {
        name: 'Cumplimiento',
        description: 'Obligaciones, calendario y seguimiento fiscal.',
        href: 'https://cumplimiento.laudaapi.com',
        icon: ShieldCheck,
        color: '#0F766E',
    },
    {
        name: 'BYS',
        description: 'Compras, proveedores, recepción y abastecimiento.',
        href: 'https://bys.laudaapi.com',
        icon: Boxes,
        color: '#475569',
    },
]

const mobileOpen = ref(false)
const processing = ref(false)
const success = ref('')
const errors = ref({})

const form = ref({
    name: '',
    company: '',
    email: '',
    phone: '',
    company_size: '',
    main_challenge: 'No sé por dónde comenzar',
    assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
    message: '',
    terms: true,
})

const canSubmit = computed(() =>
    Boolean(
        form.value.name.trim() &&
        form.value.company.trim() &&
        form.value.email.trim() &&
        form.value.terms &&
        !processing.value,
    ),
)

function goLogin() {
    window.location.assign(`${APP_URL}/login`)
}

function goRegister() {
    window.location.assign(`${APP_URL}/register`)
}

function scrollTo(id) {
    document.getElementById(id)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    })
}

function csrfHeaders() {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))

    if (cookie) {
        return {
            'X-XSRF-TOKEN': decodeURIComponent(
                cookie.slice('XSRF-TOKEN='.length),
            ),
        }
    }

    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content')

    return token ? { 'X-CSRF-TOKEN': token } : {}
}

function payload() {
    return {
        name: form.value.name,
        company: form.value.company,
        email: form.value.email,
        phone: form.value.phone,
        topic: 'Solicitud de acceso al Diagnóstico LAUDA 360',
        terms: form.value.terms,
        metadata: {
            source: 'laudaapi.com',
            request_type: 'digital_diagnosis_access_request',
            company_size: form.value.company_size || null,
            main_challenge: form.value.main_challenge,
            assistance_level: form.value.assistance_level,
            intake_type: 'digital_transformation_360',
            diagnosis_access: 'private_invitation',
        },
        message: [
            'Solicitud: Acceso al Diagnóstico LAUDA 360',
            `Tamaño aproximado: ${form.value.company_size || 'No indicado'}`,
            `Reto principal: ${form.value.main_challenge}`,
            `Acompañamiento: ${form.value.assistance_level}`,
            '',
            'Contexto adicional:',
            form.value.message || 'No indicado',
            '',
            'Origen: laudaapi.com',
        ].join('\n'),
    }
}

async function submitContact() {
    if (!canSubmit.value) return

    processing.value = true
    success.value = ''
    errors.value = {}

    try {
        const response = await fetch('/contact', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(payload()),
        })

        const data = await response.json().catch(() => ({}))

        if (response.status === 422) {
            errors.value = data.errors || {}
            return
        }

        if (response.status === 419) {
            errors.value = {
                general:
                    'La sesión expiró. Actualiza la página e intenta nuevamente.',
            }
            return
        }

        if (!response.ok || data.success === false) {
            throw new Error(
                data.message || 'No se pudo enviar la solicitud.',
            )
        }

        success.value =
            data.message ||
            'Gracias. Recibimos tu solicitud y te contactaremos con los próximos pasos.'

        form.value.name = ''
        form.value.company = ''
        form.value.email = ''
        form.value.phone = ''
        form.value.company_size = ''
        form.value.message = ''
    } catch (error) {
        errors.value = {
            general: error?.message || 'Ocurrió un error inesperado.',
        }
    } finally {
        processing.value = false
    }
}
</script>

<template>

    <Head title="LAUDAAPI | Soluciones empresariales">
        <meta name="description" content="Social, CRM, POS, e-CF, Cumplimiento y BYS conectados por una cuenta central LAUDAAPI." />
        <link rel="canonical" href="https://laudaapi.com" />
    </Head>

    <div class="min-h-screen bg-[#FAFAF8] text-slate-950">
        <!-- NAV -->
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-[#FAFAF8]/95 backdrop-blur-xl">
            <div class="mx-auto flex h-18 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
                <a href="/" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#F5333C] text-white">
                        <BrandLogo class="h-5.5 w-5.5" />
                    </span>

                    <span class="text-lg font-black tracking-tight">
                        LAUDAAPI
                    </span>
                </a>

                <nav class="ml-auto hidden items-center gap-7 text-sm font-semibold text-slate-600 lg:flex">
                    <button type="button" class="hover:text-slate-950" @click="scrollTo('soluciones')">
                        Soluciones
                    </button>

                    <button type="button" class="hover:text-slate-950" @click="scrollTo('transformacion')">
                        Transformación 360
                    </button>
                </nav>

                <div class="hidden items-center gap-2 lg:flex">
                    <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-bold hover:bg-slate-100" @click="goLogin">
                        Iniciar sesión
                    </button>

                    <button type="button" class="rounded-xl bg-[#F5333C] px-4 py-2.5 text-sm font-black text-white hover:bg-[#DB2630]" @click="goRegister">
                        Crear cuenta
                    </button>
                </div>

                <button type="button" class="ml-auto grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white lg:hidden" aria-label="Abrir menú" @click="mobileOpen = true">
                    <Menu class="h-5 w-5" />
                </button>
            </div>
        </header>

        <!-- MOBILE -->
        <div v-if="mobileOpen" class="fixed inset-0 z-60 bg-black/40 lg:hidden" @click.self="mobileOpen = false">
            <aside class="ml-auto flex h-full w-80 flex-col bg-white p-5">
                <div class="flex items-center justify-between">
                    <strong>LAUDAAPI</strong>

                    <button type="button" class="grid h-10 w-10 place-items-center rounded-xl border" @click="mobileOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-8 grid gap-2">
                    <button type="button" class="rounded-xl px-4 py-3 text-left font-bold hover:bg-slate-100" @click="mobileOpen = false; scrollTo('soluciones')">
                        Soluciones
                    </button>

                    <button type="button" class="rounded-xl px-4 py-3 text-left font-bold hover:bg-slate-100" @click="mobileOpen = false; scrollTo('transformacion')">
                        Transformación 360
                    </button>
                </div>

                <div class="mt-auto grid gap-2 border-t pt-5">
                    <button type="button" class="rounded-xl border px-4 py-3 font-bold" @click="goLogin">
                        Iniciar sesión
                    </button>

                    <button type="button" class="rounded-xl bg-[#F5333C] px-4 py-3 font-black text-white" @click="goRegister">
                        Crear cuenta
                    </button>
                </div>
            </aside>
        </div>

        <main>
            <!-- HERO -->
            <section class="px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
                <div class="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[1.1fr_0.7fr]">
                    <div>
                        <span class="inline-flex rounded-full bg-red-50 px-3 py-1.5 text-[10px] font-black tracking-[0.16em] text-[#F5333C] uppercase">
                            Ecosistema empresarial
                        </span>

                        <h1 class="mt-6 max-w-4xl text-4xl font-black leading-[0.98] tracking-[-0.05em] sm:text-5xl lg:text-6xl">
                            Soluciones para operar tu empresa.
                            <span class="text-[#F5333C]">
                                Conectadas cuando las necesitas.
                            </span>
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                            Social, CRM, POS, e-CF, Cumplimiento y BYS.
                            Contrata solo lo que necesitas y adminístralo desde
                            una sola cuenta.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#F5333C] px-5 py-3.5 text-sm font-black text-white" @click="scrollTo('soluciones')">
                                Explorar soluciones
                                <ArrowRight class="h-4 w-4" />
                            </button>

                            <button type="button" class="rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm font-black" @click="goRegister">
                                Crear cuenta
                            </button>
                        </div>
                    </div>

                    <div class="rounded-4xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-900/5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black tracking-widest text-[#F5333C] uppercase">
                                    app.laudaapi.com
                                </p>
                                <h2 class="mt-2 text-2xl font-black">
                                    Tu cuenta LAUDAAPI
                                </h2>
                            </div>

                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-[#F5333C]">
                                <User class="h-5 w-5" />
                            </span>
                        </div>

                        <div class="mt-6 grid gap-3 text-sm font-semibold">
                            <p class="rounded-xl bg-slate-50 px-4 py-3">
                                Empresa y usuarios
                            </p>
                            <p class="rounded-xl bg-slate-50 px-4 py-3">
                                Soluciones y planes
                            </p>
                            <p class="rounded-xl bg-slate-50 px-4 py-3">
                                Facturas y pagos
                            </p>
                            <p class="rounded-xl bg-slate-50 px-4 py-3">
                                Acceso central
                            </p>
                        </div>

                        <button type="button" class="mt-6 w-full rounded-xl bg-slate-950 px-5 py-3.5 text-sm font-black text-white" @click="window.location.assign(APP_URL)">
                            Ir a Mi cuenta LAUDAAPI
                        </button>
                    </div>
                </div>
            </section>

            <!-- SOLUCIONES -->
            <section id="soluciones" class="scroll-mt-24 border-y border-slate-200 bg-white px-4 py-18 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <p class="text-[10px] font-black tracking-[0.16em] text-[#F5333C] uppercase">
                        Soluciones
                    </p>

                    <h2 class="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                        Usa solo lo que necesitas.
                    </h2>

                    <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <a v-for="solution in solutions" :key="solution.name" :href="solution.href" class="group rounded-2xl border border-slate-200 bg-[#FAFAF8] p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                            <span class="grid h-11 w-11 place-items-center rounded-xl" :style="{
                                background: `${solution.color}14`,
                                color: solution.color,
                            }">
                                <component :is="solution.icon" class="h-5 w-5" />
                            </span>

                            <h3 class="mt-5 text-lg font-black">
                                {{ solution.name }}
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ solution.description }}
                            </p>

                            <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-black text-slate-500 group-hover:text-[#F5333C]">
                                Conocer solución
                                <ArrowRight class="h-3.5 w-3.5" />
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- DOS CAMINOS -->
            <section class="px-4 py-18 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-5 lg:grid-cols-2">
                        <article class="rounded-4xl border border-slate-200 bg-white p-7">
                            <p class="text-[10px] font-black tracking-widest text-green-700 uppercase">
                                Ya sé lo que necesito
                            </p>

                            <h2 class="mt-3 text-2xl font-black">
                                Contrata una solución.
                            </h2>

                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                Elige una plataforma y gestiona su contratación
                                desde tu cuenta LAUDAAPI.
                            </p>

                            <button type="button" class="mt-6 inline-flex items-center gap-2 text-sm font-black text-[#F5333C]" @click="scrollTo('soluciones')">
                                Explorar soluciones
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </article>

                        <article id="transformacion" class="scroll-mt-24 rounded-4xl border border-slate-200 bg-white p-7">
                            <p class="text-[10px] font-black tracking-widest text-[#F5333C] uppercase">
                                Necesito definir la ruta
                            </p>

                            <h2 class="mt-3 text-2xl font-black">
                                Transformación 360.
                            </h2>

                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                Analizamos tu empresa y priorizamos qué capacidades
                                implementar y en qué orden.
                            </p>

                            <button type="button" class="mt-6 inline-flex items-center gap-2 text-sm font-black text-[#F5333C]" @click="scrollTo('diagnostico')">
                                Solicitar diagnóstico
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </article>
                    </div>
                </div>
            </section>

            <!-- HUB -->
            <section class="px-4 pb-18 sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-7 rounded-4xl bg-slate-950 p-8 text-white lg:flex-row lg:items-center lg:justify-between lg:p-10">
                    <div>
                        <p class="text-[10px] font-black tracking-widest text-red-400 uppercase">
                            App Hub
                        </p>

                        <h2 class="mt-3 text-3xl font-black tracking-[-0.04em]">
                            Una cuenta. Todas tus soluciones.
                        </h2>

                        <p class="mt-3 max-w-2xl text-sm text-slate-300">
                            Empresa, usuarios, planes, facturación y accesos desde
                            un solo lugar.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="rounded-xl bg-[#F5333C] px-5 py-3.5 text-sm font-black" @click="goRegister">
                            Crear cuenta
                        </button>

                        <button type="button" class="rounded-xl border border-white/15 px-5 py-3.5 text-sm font-black" @click="goLogin">
                            Iniciar sesión
                        </button>
                    </div>
                </div>
            </section>

            <!-- DIAGNÓSTICO -->
            <section id="diagnostico" class="scroll-mt-24 border-t border-slate-200 bg-white px-4 py-18 sm:px-6 lg:px-8">
                <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <p class="text-[10px] font-black tracking-widest text-[#F5333C] uppercase">
                            Transformación 360
                        </p>

                        <h2 class="mt-3 text-3xl font-black tracking-[-0.04em]">
                            ¿No sabes por dónde comenzar?
                        </h2>

                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Cuéntanos sobre tu empresa. Revisaremos el contexto y
                            te indicaremos los próximos pasos.
                        </p>

                        <div class="mt-6 grid gap-3 text-sm text-slate-600">
                            <p class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-green-600" />
                                Diagnóstico
                            </p>
                            <p class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-green-600" />
                                Prioridades y roadmap
                            </p>
                            <p class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-green-600" />
                                Implementación según necesidad
                            </p>
                        </div>
                    </div>

                    <form class="rounded-4xl border border-slate-200 bg-[#FAFAF8] p-6" @submit.prevent="submitContact">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <input v-model="form.name" required placeholder="Nombre *" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#F5333C]" />

                            <input v-model="form.company" required placeholder="Empresa *" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#F5333C]" />

                            <input v-model="form.email" required type="email" placeholder="Email *" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#F5333C]" />

                            <input v-model="form.phone" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#F5333C]" />

                            <select v-model="form.company_size" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm sm:col-span-2">
                                <option value="">Tamaño aproximado</option>
                                <option>1 a 10 personas</option>
                                <option>11 a 50 personas</option>
                                <option>51 a 200 personas</option>
                                <option>Más de 200 personas</option>
                            </select>

                            <textarea v-model="form.message" rows="4" placeholder="¿Qué necesitas resolver?" class="resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#F5333C] sm:col-span-2" />
                        </div>

                        <label class="mt-4 flex items-start gap-2 text-xs text-slate-500">
                            <input v-model="form.terms" type="checkbox" class="mt-0.5" />
                            <span>
                                Acepto los
                                <a href="/legal/terminos" class="font-bold underline">
                                    términos
                                </a>
                                y la
                                <a href="/legal/privacidad" class="font-bold underline">
                                    política de privacidad
                                </a>.
                            </span>
                        </label>

                        <p v-if="errors.general" class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700">
                            {{ errors.general }}
                        </p>

                        <p v-if="success" class="mt-4 rounded-xl bg-green-50 p-3 text-sm font-semibold text-green-800">
                            {{ success }}
                        </p>

                        <button type="submit" :disabled="!canSubmit" class="mt-5 w-full rounded-xl bg-[#F5333C] px-5 py-3.5 text-sm font-black text-white disabled:opacity-50">
                            {{ processing ? 'Enviando...' : 'Solicitar diagnóstico' }}
                        </button>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <BrandLogo class="h-5 w-5 text-[#F5333C]" />
                    <span class="text-sm font-black">LAUDAAPI</span>
                </div>

                <div class="flex flex-wrap gap-5 text-xs font-semibold text-slate-500">
                    <a href="/legal">Legal</a>
                    <a href="/legal/terminos">Términos</a>
                    <a href="/legal/privacidad">Privacidad</a>
                    <a href="https://status.laudaapi.com">Status</a>
                </div>
            </div>
        </footer>
    </div>
</template>
