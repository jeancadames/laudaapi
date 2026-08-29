<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Boxes,
    Building2,
    Calculator,
    CheckCircle2,
    FileText,
    Instagram,
    Landmark,
    Mail,
    MessageCircle,
    RefreshCw,
    ShieldCheck,
    Store,
    TrendingUp,
    Truck,
    User,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Props {
    canRegister?: boolean;
}

withDefaults(defineProps<Props>(), {
    canRegister: true,
});

const APP_URL = 'https://app.laudaapi.com';
const CONTACT_REQUEST_ENDPOINT = '/contact';

/**
 * Soluciones heredadas del Welcome anterior.
 * Se mantienen todas las opciones comerciales visibles.
 */
const solutions = [
    {
        id: 'social',
        name: 'Social',
        category: 'Marketing y canales',
        description: 'Captación social, contenido, inbox y leads.',
        href: 'https://social.laudaapi.com',
        icon: MessageCircle,
        iconColor: 'text-pink-500',
        badgeClass:
            'bg-pink-50 text-pink-900 border-pink-200 dark:bg-pink-950/30 dark:text-pink-300 dark:border-pink-900/50',
        focus: 'Canales + leads',
        code: 'SOC',
    },
    {
        id: 'crm',
        name: 'CRM',
        category: 'Gestión comercial',
        description: 'Clientes, oportunidades y seguimiento comercial.',
        href: 'https://crm.laudaapi.com',
        icon: Users,
        iconColor: 'text-blue-500',
        badgeClass:
            'bg-blue-50 text-blue-900 border-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-900/50',
        focus: 'Pipeline',
        code: 'CRM',
    },
    {
        id: 'pos',
        name: 'POS',
        category: 'Operación',
        description: 'Ventas, inventario, cobros, rutas y despacho.',
        href: 'https://pos.laudaapi.com',
        icon: Store,
        iconColor: 'text-emerald-500',
        badgeClass:
            'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-900/50',
        focus: 'Ventas + inventario',
        code: 'POS',
    },
    {
        id: 'ecf',
        name: 'e-CF',
        category: 'Facturación electrónica',
        description: 'Firma, envío, TrackId y respuesta ante DGII.',
        href: 'https://ecf.laudaapi.com',
        icon: FileText,
        iconColor: 'text-amber-500',
        badgeClass:
            'bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-950/30 dark:text-amber-300 dark:border-amber-900/50',
        focus: 'DGII',
        code: 'e-CF',
    },
    {
        id: 'cumplimiento',
        name: 'Cumplimiento',
        category: 'Fiscal',
        description: 'Obligaciones, documentos y control fiscal.',
        href: 'https://cumplimiento.laudaapi.com',
        icon: ShieldCheck,
        iconColor: 'text-teal-500',
        badgeClass:
            'bg-teal-50 text-teal-900 border-teal-200 dark:bg-teal-950/30 dark:text-teal-300 dark:border-teal-900/50',
        focus: 'Obligaciones',
        code: 'CPL',
    },
    {
        id: 'bys',
        name: 'BYS',
        category: 'Compras',
        description: 'Compras, proveedores, importaciones, recepción y abastecimiento.',
        href: 'https://bys.laudaapi.com',
        icon: Boxes,
        iconColor: 'text-violet-500',
        badgeClass:
            'bg-violet-50 text-violet-900 border-violet-200 dark:bg-violet-950/30 dark:text-violet-300 dark:border-violet-900/50',
        focus: 'Compras + proveedores',
        code: 'BYS',
    },
    {
        id: 'tesoreria',
        name: 'Tesorería',
        category: 'Finanzas',
        description: 'Pagos, bancos, caja, conciliación y nómina aprobada.',
        href: 'https://tesoreria.laudaapi.com',
        icon: Landmark,
        iconColor: 'text-emerald-600',
        badgeClass:
            'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-900/50',
        focus: 'Pagos + bancos',
        code: 'TES',
    },
    {
        id: 'status',
        name: 'Status',
        category: 'Monitoreo',
        description: 'Monitoreo de DGII, APIs, caídas y eventos.',
        href: 'https://status.laudaapi.com',
        icon: Activity,
        iconColor: 'text-sky-500',
        badgeClass:
            'bg-sky-50 text-sky-900 border-sky-200 dark:bg-sky-950/30 dark:text-sky-300 dark:border-sky-900/50',
        focus: 'Disponibilidad',
        code: 'STS',
    },
    {
        id: 'rrhh',
        name: 'RRHH',
        category: 'Gestión interna',
        description: 'Recursos humanos, empleados y procesos internos.',
        href: 'https://rrhh.laudaapi.com',
        icon: Users,
        iconColor: 'text-cyan-500',
        badgeClass:
            'bg-cyan-50 text-cyan-900 border-cyan-200 dark:bg-cyan-950/30 dark:text-cyan-300 dark:border-cyan-900/50',
        focus: 'Personas',
        code: 'RRHH',
    },
    {
        id: 'proyectos',
        name: 'Proyectos',
        category: 'Gestión interna',
        description: 'Tareas, ejecución, entregables y avance.',
        href: 'https://proyectos.laudaapi.com',
        icon: Boxes,
        iconColor: 'text-indigo-500',
        badgeClass:
            'bg-indigo-50 text-indigo-900 border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-300 dark:border-indigo-900/50',
        focus: 'Tareas + avance',
        code: 'PRY',
    },
    {
        id: 'eventos',
        name: 'Eventos',
        category: 'Operación especializada',
        description: 'Eventos, actividades, invitados y operación.',
        href: 'https://eventos.laudaapi.com',
        icon: Activity,
        iconColor: 'text-rose-500',
        badgeClass:
            'bg-rose-50 text-rose-900 border-rose-200 dark:bg-rose-950/30 dark:text-rose-300 dark:border-rose-900/50',
        focus: 'Eventos',
        code: 'EVT',
    },
    {
        id: 'transporte',
        name: 'Transporte personal',
        category: 'Operación especializada',
        description: 'Rutas, unidades, pasajeros y movilidad interna.',
        href: 'https://transporte.laudaapi.com',
        icon: Truck,
        iconColor: 'text-orange-500',
        badgeClass:
            'bg-orange-50 text-orange-900 border-orange-200 dark:bg-orange-950/30 dark:text-orange-300 dark:border-orange-900/50',
        focus: 'Rutas + movilidad',
        code: 'TRN',
    },
    {
        id: 'gruas',
        name: 'Servicios de grúas',
        category: 'Vertical especializado',
        description: 'Asignación, asistencia, evidencia y servicios.',
        href: 'https://gruas.laudaapi.com',
        icon: Truck,
        iconColor: 'text-yellow-600',
        badgeClass:
            'bg-yellow-50 text-yellow-900 border-yellow-200 dark:bg-yellow-950/30 dark:text-yellow-300 dark:border-yellow-900/50',
        focus: 'Asistencia',
        code: 'GRU',
    },
    {
        id: 'loans',
        name: 'Loans',
        category: 'Vertical especializado',
        description: 'Préstamos, cuotas, cartera y cobranza.',
        href: 'https://loans.laudaapi.com',
        icon: Calculator,
        iconColor: 'text-lime-600',
        badgeClass:
            'bg-lime-50 text-lime-900 border-lime-200 dark:bg-lime-950/30 dark:text-lime-300 dark:border-lime-900/50',
        focus: 'Cartera',
        code: 'LNS',
    },
    {
        id: 'dealers',
        name: 'Dealers',
        category: 'Vertical especializado',
        description: 'Inventario, ventas, financiamiento y clientes.',
        href: 'https://dealers.laudaapi.com',
        icon: Store,
        iconColor: 'text-fuchsia-500',
        badgeClass:
            'bg-fuchsia-50 text-fuchsia-900 border-fuchsia-200 dark:bg-fuchsia-950/30 dark:text-fuchsia-300 dark:border-fuchsia-900/50',
        focus: 'Vehículos + ventas',
        code: 'DLR',
    },
    {
        id: 'bi',
        name: 'BI',
        category: 'Inteligencia',
        description: 'Dashboards, métricas, reporting e inteligencia.',
        href: 'https://bi.laudaapi.com',
        icon: TrendingUp,
        iconColor: 'text-blue-600',
        badgeClass:
            'bg-blue-50 text-blue-900 border-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-900/50',
        focus: 'Data + decisiones',
        code: 'BI',
    },
];

const serviceModels = [
    {
        id: 'guided',
        title: 'LAUDA 360 Guiado',
        level: 'LAUDA orienta',
        badge: 'Guiado',
        description:
            'Tu equipo ejecuta el roadmap con metodología LAUDA, guías y soporte puntual por email.',
        ideal:
            'Para empresas con capacidad interna para ejecutar y coordinar la transformación.',
        icon: CheckCircle2,
        badgeClass:
            'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-900/50',
        iconColor: 'text-emerald-500',
    },
    {
        id: 'assisted',
        title: 'LAUDA 360 Asistido',
        level: 'Trabajamos juntos',
        badge: 'Recomendado',
        description:
            'LAUDA y tu equipo ejecutan juntos la transformación, combinando conocimiento del negocio e implementación.',
        ideal:
            'Para empresas con responsables internos, pero sin un equipo especializado de transformación.',
        icon: Users,
        badgeClass:
            'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-900/50',
        iconColor: 'text-blue-500',
    },
    {
        id: 'managed',
        title: 'LAUDA 360 Gestionado',
        level: 'LAUDA lidera',
        badge: 'Gestionado',
        description:
            'LAUDA dirige y coordina el programa completo como una oficina externa de transformación digital.',
        ideal:
            'Para empresas sin la estructura, tiempo o experiencia interna para liderar el proceso.',
        icon: ShieldCheck,
        badgeClass:
            'bg-red-50 text-red-800 border-red-200 dark:bg-red-950/30 dark:text-red-300 dark:border-red-900/50',
        iconColor: 'text-red-500',
    },
];

/**
 * Opciones del card interactivo.
 * App Hub + todas las soluciones + Transformación 360.
 */
const ecosystemOptions = [
    {
        id: 'app-hub',
        code: 'HUB',
        name: 'Mi cuenta LAUDAAPI',
        category: 'Cuenta central',
        focus: 'Empresa + planes + pagos',
        status: 'Central',
        href: 'https://app.laudaapi.com',
        icon: User,
        iconColor: 'text-red-500',
        badgeClass:
            'bg-red-50 text-red-900 border-red-200 dark:bg-red-950/30 dark:text-red-300 dark:border-red-900/50',
    },
    ...solutions.map((solution) => ({
        id: solution.id,
        code: solution.code,
        name: solution.name,
        category: solution.category,
        focus: solution.focus,
        status: 'Solución',
        href: solution.href,
        icon: solution.icon,
        iconColor: solution.iconColor,
        badgeClass: solution.badgeClass,
    })),
    {
        id: 't360',
        code: '360',
        name: 'Transformación 360',
        category: 'Servicio estratégico',
        focus: 'Diagnóstico + roadmap',
        status: 'Servicio',
        href: '#transformacion',
        icon: Building2,
        iconColor: 'text-slate-700 dark:text-slate-200',
        badgeClass:
            'bg-slate-50 text-slate-900 border-slate-200 dark:bg-slate-800/60 dark:text-slate-200 dark:border-slate-700',
    },
];

const features = [
    'Una cuenta central para empresa, usuarios, planes, facturas y pagos',
    'Soluciones independientes que puedes contratar según tu necesidad',
    'Acceso central a las plataformas contratadas desde App Hub',
    'Transformación 360 opcional para diagnóstico, roadmap e implementación',
];

const currentIndex = ref(0);
const currentOption = computed(() => ecosystemOptions[ currentIndex.value ]);

let timer: ReturnType<typeof setInterval>;

function restartTimer() {
    if (timer) {
        clearInterval(timer);
    }

    timer = setInterval(() => {
        currentIndex.value =
            (currentIndex.value + 1) % ecosystemOptions.length;
    }, 3200);
}

function selectOption(index: number) {
    currentIndex.value = index;
    restartTimer();
}

function openCurrentOption() {
    const href = currentOption.value.href;

    if (href.startsWith('#')) {
        document
            .querySelector(href)
            ?.scrollIntoView({ behavior: 'smooth' });

        return;
    }

    window.location.assign(href);
}

function goLogin() {
    window.location.assign(`${APP_URL}/login`);
}

function goRegister() {
    window.location.assign(`${APP_URL}/register`);
}

function scrollToId(id: string) {
    document
        .getElementById(id)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Formulario original de Transformación 360.
 * Conserva el contrato /contact y metadata del Welcome anterior.
 */
const companySizes = [
    '1 a 10 personas',
    '11 a 50 personas',
    '51 a 200 personas',
    'Más de 200 personas',
];

const transformationChallenges = [
    'No sé por dónde comenzar',
    'Organizar procesos y reducir trabajo manual',
    'Mejorar captación, clientes y ventas',
    'Digitalizar la operación diaria',
    'Integrar administración, fiscalidad y cumplimiento',
    'Centralizar datos, indicadores y BI',
    'Conectar sistemas que hoy trabajan separados',
];

const assistanceLevels = [
    'Quiero que LAUDA me recomiende la modalidad',
    'LAUDA 360 Guiado — autoservicio + soporte por email',
    'LAUDA 360 Asistido — trabajo conjunto',
    'LAUDA 360 Gestionado — LAUDA lidera',
];

const contactProcessing = ref(false);
const contactSuccessMessage = ref('');
const contactErrors = ref<Record<string, any>>({});
let contactSuccessTimer: ReturnType<typeof setTimeout> | null = null;

const contactForm = ref({
    name: '',
    company: '',
    phone: '',
    email: '',
    company_size: '',
    main_challenge: 'No sé por dónde comenzar',
    assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
    message: '',
    terms: true,
});

const canSubmitContact = computed(() => {
    return Boolean(
        contactForm.value.name.trim() &&
        contactForm.value.company.trim() &&
        contactForm.value.email.trim() &&
        contactForm.value.terms &&
        !contactProcessing.value,
    );
});

function resetContactForm() {
    contactForm.value = {
        name: '',
        company: '',
        phone: '',
        email: '',
        company_size: '',
        main_challenge: 'No sé por dónde comenzar',
        assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
        message: '',
        terms: true,
    };
}

function getCsrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || ''
    );
}

function getXsrfCookie() {
    const prefix = 'XSRF-TOKEN=';
    const item = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(prefix));

    if (!item) return '';

    const value = item.slice(prefix.length);

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

function getContactCsrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {};
    const xsrfToken = getXsrfCookie();

    if (xsrfToken) {
        headers[ 'X-XSRF-TOKEN' ] = xsrfToken;

        return headers;
    }

    const metaToken = getCsrfToken();

    if (metaToken) {
        headers[ 'X-CSRF-TOKEN' ] = metaToken;
    }

    return headers;
}

async function refreshContactCsrf() {
    const response = await fetch('/', {
        method: 'GET',
        headers: {
            Accept: 'text/html',
        },
        credentials: 'same-origin',
        cache: 'no-store',
    });

    if (!response.ok) {
        return false;
    }

    const html = await response.text();
    const documentFresh = new DOMParser().parseFromString(html, 'text/html');
    const freshMetaToken =
        documentFresh
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';

    const currentMeta = document.querySelector(
        'meta[name="csrf-token"]',
    );

    if (currentMeta && freshMetaToken) {
        currentMeta.setAttribute('content', freshMetaToken);
    }

    return Boolean(getXsrfCookie() || freshMetaToken);
}

function buildContactPayload() {
    const form = contactForm.value;

    return {
        name: form.name,
        company: form.company,
        phone: form.phone,
        email: form.email,
        topic: 'Solicitud de acceso al Diagnóstico LAUDA 360',
        message: form.message,
        terms: form.terms,
        metadata: {
            source: 'laudaapi.com',
            request_type: 'digital_diagnosis_access_request',
            intake_type: 'digital_transformation_360',
            company_size: form.company_size,
            main_challenge: form.main_challenge,
            assistance_level: form.assistance_level,
            diagnosis_access: 'apphub_native',
        },
    };
}

async function sendContactRequest(payload: ReturnType<typeof buildContactPayload>) {
    return fetch(CONTACT_REQUEST_ENDPOINT, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...getContactCsrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
}

async function submitContact() {
    if (!canSubmitContact.value) return;

    contactSuccessMessage.value = '';
    contactErrors.value = {};
    contactProcessing.value = true;

    try {
        const payload = buildContactPayload();
        let response = await sendContactRequest(payload);

        if (response.status === 419) {
            const refreshed = await refreshContactCsrf();

            if (refreshed) {
                response = await sendContactRequest(payload);
            }
        }

        const data = await response.json().catch(() => ({}));

        if (response.status === 419) {
            contactErrors.value = {
                general:
                    'La sesión de seguridad expiró. Actualice la página e intente nuevamente.',
            };
            return;
        }

        if (response.status === 422) {
            contactErrors.value = data.errors || {};
            return;
        }

        if (!response.ok || data.success === false) {
            throw new Error(
                data.message ||
                'No se pudo enviar la solicitud en este momento.',
            );
        }

        contactSuccessMessage.value =
            data.message ||
            'Solicitud recibida. Revisa tu correo para continuar.';

        if (contactSuccessTimer) {
            clearTimeout(contactSuccessTimer);
        }

        contactSuccessTimer = setTimeout(() => {
            contactSuccessMessage.value = '';
            contactSuccessTimer = null;
        }, 8000);

        resetContactForm();
    } catch (error: any) {
        contactErrors.value = {
            general:
                error?.message ||
                'Ocurrió un error al procesar la solicitud.',
        };
    } finally {
        contactProcessing.value = false;
    }
}

onMounted(() => {
    restartTimer();
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }

    if (contactSuccessTimer) {
        clearTimeout(contactSuccessTimer);
        contactSuccessTimer = null;
    }
});
</script>

<template>

    <Head title="LAUDAAPI — Soluciones empresariales y Transformación 360">
        <meta name="description" content="Ecosistema de soluciones empresariales con App Hub central y Transformación 360 opcional." />
    </Head>

    <div class="flex min-h-screen w-full flex-col items-center bg-[#FAFAF8] px-4 py-4 text-[#1b1b18] dark:bg-[#0a0a0a]">
        <!-- NAV -->
        <nav class="mb-4 flex w-full max-w-5xl items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-[#0c0c0c]">
            <Link href="/" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-500 text-white shadow-md shadow-red-500/20">
                    <BrandLogo class="h-5 w-5" />
                </span>

                <div class="leading-none">
                    <p class="text-sm font-black tracking-tight text-slate-900 dark:text-white">
                        LAUDAAPI
                    </p>
                    <p class="mt-1 text-[8px] font-black tracking-[0.18em] text-red-500 uppercase">
                        Ecosistema empresarial
                    </p>
                </div>
            </Link>

            <div class="hidden items-center gap-5 lg:flex">
                <button type="button" class="text-xs font-black text-slate-500 hover:text-slate-900 dark:hover:text-white" @click="scrollToId('soluciones')">
                    Soluciones
                </button>

                <button type="button" class="text-xs font-black text-slate-500 hover:text-slate-900 dark:hover:text-white" @click="scrollToId('transformacion')">
                    Transformación 360
                </button>

                <button type="button" class="text-xs font-black text-slate-500 hover:text-slate-900 dark:hover:text-white" @click="scrollToId('diagnostico')">
                    Diagnóstico
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" class="hidden rounded-xl px-4 py-2 text-xs font-black text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 sm:inline-flex dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white" @click="goLogin">
                    Iniciar sesión
                </button>

                <button v-if="canRegister" type="button" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-md transition-all hover:bg-red-600 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white" @click="goRegister">
                    Crear cuenta
                </button>
            </div>
        </nav>

        <!-- MAIN CARD -->
        <main class="flex w-full max-w-5xl flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-2xl lg:flex-row dark:border-slate-800 dark:bg-[#0c0c0c]">
            <!-- LEFT · CARD INTERACTIVO -->
            <div class="relative flex h-84 w-full shrink-0 items-center justify-center overflow-hidden bg-linear-to-br from-[#FFF4F2] via-[#FFE2DE] to-[#FFD4CF] lg:h-auto lg:w-96 dark:from-[#1a0505] dark:via-[#2d0a0a] dark:to-[#1a0505]">
                <div class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/45 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-red-400/20 blur-3xl" />

                <div class="z-10 w-full max-w-72 rounded-3xl border border-white/50 bg-white/70 p-6 shadow-2xl backdrop-blur-xl dark:border-white/10 dark:bg-black/55">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0 -translate-x-2" enter-to-class="opacity-100 translate-x-0" mode="out-in">
                            <span :key="currentOption.id" class="truncate text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">
                                {{ currentOption.category }} ·
                                {{ currentOption.code }}
                            </span>
                        </transition>

                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0 scale-90" enter-to-class="opacity-100 scale-100" mode="out-in">
                            <span :key="`${currentOption.id}-status`" :class="[
                                'shrink-0 rounded-full border px-3 py-1 text-[9px] font-black shadow-sm',
                                currentOption.badgeClass,
                            ]">
                                {{ currentOption.status }}
                            </span>
                        </transition>
                    </div>

                    <div class="mb-4 flex flex-col">
                        <span class="mb-1 text-[9px] font-bold tracking-wide text-slate-400 uppercase">
                            Ecosistema LAUDAAPI
                        </span>

                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0" enter-to-class="opacity-100" mode="out-in">
                            <span :key="`${currentOption.id}-name`" class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                                {{ currentOption.name }}
                            </span>
                        </transition>
                    </div>

                    <div class="my-3 border-t border-dashed border-slate-300 dark:border-slate-700" />

                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex flex-col">
                            <span class="text-[9px] font-bold tracking-tight text-slate-400 uppercase">
                                Enfoque
                            </span>

                            <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0" enter-to-class="opacity-100" mode="out-in">
                                <span :key="`${currentOption.id}-focus`" class="truncate text-lg font-black" :class="currentOption.iconColor">
                                    {{ currentOption.focus }}
                                </span>
                            </transition>
                        </div>

                        <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-slate-900">
                            <transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 scale-0 rotate-[-20deg]" enter-to-class="opacity-100 scale-100 rotate-0" mode="out-in">
                                <component :is="currentOption.icon" :key="`${currentOption.id}-icon`" class="h-5 w-5" :class="currentOption.iconColor" />
                            </transition>
                        </div>
                    </div>

                    <button type="button" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-[11px] font-black text-slate-700 transition-all hover:border-red-300 hover:text-red-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-200" @click="openCurrentOption">
                        {{
                            currentOption.id === 't360'
                                ? 'Conocer Transformación 360'
                                : currentOption.id === 'app-hub'
                                    ? 'Ir a Mi cuenta'
                                    : 'Conocer solución'
                        }}
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>

                    <div class="mt-5 flex flex-wrap items-center justify-center gap-1.5">
                        <button v-for="(option, i) in ecosystemOptions" :key="option.id" type="button" :title="option.name" :aria-label="`Ver ${option.name}`" :class="[
                            'rounded-full transition-all duration-300',
                            i === currentIndex
                                ? 'h-1.5 w-5 bg-red-500'
                                : 'h-1.5 w-1.5 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600',
                        ]" @click="selectOption(i)" />
                    </div>
                </div>
            </div>

            <!-- RIGHT · COPY -->
            <div class="flex flex-1 flex-col justify-center p-8 sm:p-10 lg:p-14">
                <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 dark:border-red-900/50 dark:bg-red-950/30">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
                    <span class="text-[9.5px] font-black tracking-widest text-red-700 uppercase dark:text-red-400">
                        Soluciones · App Hub · Transformación 360 · RD
                    </span>
                </div>

                <h1 class="mb-3 text-4xl leading-none font-black tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                    Una cuenta.<br />
                    <span class="text-red-600">Todo tu ecosistema.</span>
                </h1>

                <p class="mb-8 max-w-md text-sm leading-relaxed font-medium text-slate-500 dark:text-slate-400">
                    Contrata soluciones empresariales independientes, administra
                    tu relación con LAUDAAPI desde App Hub o utiliza
                    Transformación 360 cuando necesitas una ruta completa de
                    diagnóstico e implementación.
                </p>

                <ul class="mb-9 flex flex-col gap-3">
                    <li v-for="item in features" :key="item" class="flex items-center gap-3 text-[13px] font-bold text-slate-700 dark:text-slate-200">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <CheckCircle2 class="h-3 w-3 text-green-600 dark:text-green-400" />
                        </div>
                        {{ item }}
                    </li>
                </ul>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <button v-if="canRegister" type="button" class="rounded-2xl bg-slate-900 px-8 py-4 text-base font-black text-white shadow-xl transition-all duration-200 hover:scale-[1.03] hover:bg-red-600 active:scale-95 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white" @click="goRegister">
                            Crear cuenta
                        </button>

                        <button type="button" class="rounded-2xl border-2 border-slate-200 bg-white px-8 py-4 text-base font-black text-slate-700 transition-all duration-200 hover:scale-[1.03] hover:border-slate-900 hover:text-black dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:border-white dark:hover:text-white" @click="scrollToId('soluciones')">
                            Ver soluciones
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[10px] font-semibold text-slate-400">
                            App Hub central · Soluciones independientes
                        </span>

                        <div class="h-4 w-px bg-slate-200 dark:bg-slate-800" />

                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <span class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase">
                                    16
                                </span>
                                <span class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white">
                                    Soluciones
                                </span>
                            </div>

                            <div class="h-7 w-px bg-slate-100 dark:bg-slate-800" />

                            <div class="flex flex-col">
                                <span class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase">
                                    360
                                </span>
                                <span class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white">
                                    Opcional
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- CÓMO FUNCIONA -->
        <section class="mt-8 w-full max-w-5xl">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="mb-10 text-center">
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                        Dos formas de comenzar
                    </p>

                    <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        Elige una solución o define primero la ruta.
                    </h2>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg">
                            <Store class="h-5 w-5" />
                        </div>

                        <p class="text-[9px] font-black tracking-widest text-emerald-600 uppercase">
                            Ya sé lo que necesito
                        </p>

                        <h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                            Contrata una solución directamente.
                        </h3>

                        <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Explora el catálogo, elige la plataforma y administra
                            la contratación desde tu cuenta LAUDAAPI.
                        </p>

                        <button type="button" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-red-500" @click="scrollToId('soluciones')">
                            Ver soluciones
                            <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-500 text-white shadow-lg">
                            <Building2 class="h-5 w-5" />
                        </div>

                        <p class="text-[9px] font-black tracking-widest text-red-500 uppercase">
                            Necesito definir la ruta
                        </p>

                        <h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                            Comienza con Transformación 360.
                        </h3>

                        <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Diagnóstico, prioridades, roadmap y nivel de
                            acompañamiento según la realidad de tu empresa.
                        </p>

                        <button type="button" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-red-500" @click="scrollToId('transformacion')">
                            Conocer Transformación 360
                            <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- SOLUCIONES · TODAS -->
        <section id="soluciones" class="mt-6 w-full max-w-5xl scroll-mt-20">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="mb-8 text-center">
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                        Ecosistema LAUDAAPI
                    </p>

                    <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        Todas las soluciones.
                    </h2>

                    <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        Desde marketing y ventas hasta operación, administración,
                        verticales especializadas e inteligencia.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <a v-for="solution in solutions" :key="solution.id" :href="solution.href" class="group rounded-2xl border border-slate-100 bg-slate-50/50 p-5 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-slate-900">
                            <component :is="solution.icon" class="h-5 w-5" :class="solution.iconColor" />
                        </div>

                        <p class="mb-1 text-[8px] font-black tracking-widest text-slate-400 uppercase">
                            {{ solution.category }}
                        </p>

                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-black text-slate-900 dark:text-white">
                                {{ solution.name }}
                            </h3>

                            <ArrowRight class="h-4 w-4 text-slate-300 transition-transform group-hover:translate-x-1 group-hover:text-red-500" />
                        </div>

                        <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ solution.description }}
                        </p>
                    </a>
                </div>
            </div>
        </section>

        <!-- TRANSFORMACIÓN 360 -->
        <section id="transformacion" class="mt-6 w-full max-w-5xl scroll-mt-20">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="mb-8 text-center">
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                        Transformación 360
                    </p>

                    <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        Un roadmap. Tres niveles de acompañamiento.
                    </h2>

                    <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        El diagnóstico define por dónde comenzar. La modalidad
                        define cuánto ejecuta tu equipo y cuánto asume LAUDA.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <article v-for="model in serviceModels" :key="model.id" class="flex h-full flex-col rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-slate-900">
                                <component :is="model.icon" class="h-5 w-5" :class="model.iconColor" />
                            </div>

                            <span :class="[
                                'rounded-full border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase',
                                model.badgeClass,
                            ]">
                                {{ model.badge }}
                            </span>
                        </div>

                        <p class="mt-5 text-[9px] font-black tracking-widest text-slate-400 uppercase">
                            {{ model.level }}
                        </p>

                        <h3 class="mt-1 text-xl font-black text-slate-900 dark:text-white">
                            {{ model.title }}
                        </h3>

                        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ model.description }}
                        </p>

                        <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/40">
                            <p class="text-[8px] font-black tracking-widest text-slate-400 uppercase">
                                Ideal para
                            </p>

                            <p class="mt-1.5 text-xs font-semibold leading-relaxed text-slate-600 dark:text-slate-300">
                                {{ model.ideal }}
                            </p>
                        </div>

                        <button type="button" class="mt-auto pt-5 text-left text-sm font-black text-red-500" @click="
                            contactForm.assistance_level =
                            model.id === 'guided'
                                ? 'LAUDA 360 Guiado — autoservicio + soporte por email'
                                : model.id === 'assisted'
                                    ? 'LAUDA 360 Asistido — trabajo conjunto'
                                    : 'LAUDA 360 Gestionado — LAUDA lidera';
                        scrollToId('diagnostico')
                            ">
                            Solicitar esta modalidad →
                        </button>
                    </article>
                </div>
            </div>
        </section>

        <!-- DIAGNÓSTICO -->
        <section id="diagnostico" class="mt-6 w-full max-w-5xl scroll-mt-20">
            <div class="overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="grid lg:grid-cols-[0.8fr_1.2fr]">
                    <div class="bg-slate-950 p-8 text-white sm:p-10 lg:p-12">
                        <p class="text-[10px] font-black tracking-[0.3em] text-red-400 uppercase">
                            Diagnóstico LAUDA 360
                        </p>

                        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            Cuéntanos dónde está tu empresa hoy.
                        </h2>

                        <p class="mt-4 text-sm leading-relaxed text-slate-400">
                            Revisamos tu contexto, identificamos el punto de
                            partida y te indicamos los próximos pasos para
                            construir una ruta de transformación.
                        </p>

                        <div class="mt-8 space-y-4">
                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                <p class="text-xs text-slate-300">
                                    Diagnóstico de madurez y brechas.
                                </p>
                            </div>

                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                <p class="text-xs text-slate-300">
                                    Priorización y roadmap.
                                </p>
                            </div>

                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                <p class="text-xs text-slate-300">
                                    Modalidad Guiado, Asistido o Gestionado.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form class="p-6 sm:p-8 lg:p-10" @submit.prevent="submitContact">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Nombre *
                                </label>
                                <input v-model="contactForm.name" type="text" autocomplete="name" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white" />
                                <p v-if="contactErrors.name" class="mt-1 text-xs font-bold text-red-500">
                                    {{
                                        Array.isArray(contactErrors.name)
                                            ? contactErrors.name[ 0 ]
                                            : contactErrors.name
                                    }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Empresa *
                                </label>
                                <input v-model="contactForm.company" type="text" autocomplete="organization" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white" />
                                <p v-if="contactErrors.company" class="mt-1 text-xs font-bold text-red-500">
                                    {{
                                        Array.isArray(contactErrors.company)
                                            ? contactErrors.company[ 0 ]
                                            : contactErrors.company
                                    }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Email *
                                </label>
                                <input v-model="contactForm.email" type="email" autocomplete="email" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white" />
                                <p v-if="contactErrors.email" class="mt-1 text-xs font-bold text-red-500">
                                    {{
                                        Array.isArray(contactErrors.email)
                                            ? contactErrors.email[ 0 ]
                                            : contactErrors.email
                                    }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Teléfono
                                </label>
                                <input v-model="contactForm.phone" type="tel" autocomplete="tel" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white" />
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Tamaño aproximado
                                </label>
                                <select v-model="contactForm.company_size" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="size in companySizes" :key="size" :value="size">
                                        {{ size }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Reto principal
                                </label>
                                <select v-model="contactForm.main_challenge" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white">
                                    <option v-for="challenge in transformationChallenges" :key="challenge" :value="challenge">
                                        {{ challenge }}
                                    </option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Nivel de acompañamiento
                                </label>
                                <select v-model="contactForm.assistance_level" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white">
                                    <option v-for="level in assistanceLevels" :key="level" :value="level">
                                        {{ level }}
                                    </option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-black text-slate-700 dark:text-slate-300">
                                    Contexto adicional
                                </label>
                                <textarea v-model="contactForm.message" rows="4" placeholder="Describe brevemente qué te gustaría mejorar o transformar." class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-400 focus:bg-white dark:border-slate-800 dark:bg-slate-900/40 dark:text-white" />
                            </div>
                        </div>

                        <label class="mt-5 flex items-start gap-3">
                            <input v-model="contactForm.terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-red-500" />

                            <span class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Acepto los
                                <Link href="/legal/terminos" class="font-black text-slate-700 underline dark:text-slate-200">
                                    términos
                                </Link>
                                y la
                                <Link href="/legal/privacidad" class="font-black text-slate-700 underline dark:text-slate-200">
                                    política de privacidad
                                </Link>.
                            </span>
                        </label>

                        <div v-if="contactErrors.general" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                            {{ contactErrors.general }}
                        </div>

                        <div v-if="contactSuccessMessage" role="status" aria-live="polite" class="fixed top-5 right-5 z-[100] max-w-sm rounded-2xl border border-emerald-200 bg-white p-4 text-sm font-bold text-emerald-800 shadow-2xl dark:border-emerald-900/50 dark:bg-slate-950 dark:text-emerald-300">
                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                                <div>
                                    <p class="font-black">Solicitud recibida.</p>
                                    <p class="mt-1 font-semibold">{{ contactSuccessMessage }}</p>
                                </div>
                            </div>
                        </div>

                        <button type="submit" :disabled="!canSubmitContact" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-4 text-base font-black text-white shadow-xl transition-all hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white">
                            {{
                                contactProcessing
                                    ? 'Enviando...'
                                    : 'Solicitar diagnóstico'
                            }}

                            <ArrowRight v-if="!contactProcessing" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- APP HUB -->
        <section class="mt-6 w-full max-w-5xl">
            <div class="rounded-[2.5rem] border border-slate-200 bg-slate-900 p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left">
                    <div class="flex-1">
                        <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase">
                            app.laudaapi.com
                        </p>

                        <h2 class="mb-2 text-2xl font-black tracking-tight text-white sm:text-3xl">
                            Una cuenta para todo el ecosistema.
                        </h2>

                        <p class="mb-4 max-w-lg text-sm text-slate-400">
                            Administra empresa, usuarios, soluciones, planes,
                            facturas, pagos y accesos desde tu cuenta central
                            LAUDAAPI.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-3">
                        <button type="button" class="rounded-2xl bg-white px-7 py-4 text-base font-black text-slate-900 shadow-lg transition-all hover:scale-105 hover:bg-red-500 hover:text-white" @click="goRegister">
                            Crear cuenta
                        </button>

                        <button type="button" class="rounded-2xl border border-white/15 bg-white/5 px-7 py-4 text-base font-black text-white transition-all hover:bg-white/10" @click="goLogin">
                            Iniciar sesión
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="mt-8 mb-4 w-full max-w-5xl">
            <div class="flex flex-col gap-5 border-t border-slate-200 pt-6 dark:border-slate-800">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
                    <p class="text-[10px] font-black tracking-[0.4em] text-slate-400 uppercase dark:text-slate-600">
                        Soluciones empresariales LAUDAAPI
                    </p>

                    <div class="flex items-center gap-5">
                        <Link href="/legal/privacidad" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Privacidad
                        </Link>

                        <Link href="/legal/terminos" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Términos
                        </Link>

                        <a href="https://status.laudaapi.com" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Status
                        </a>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                    <div class="flex items-center gap-4">
                        <a href="https://instagram.com/laudaapi" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-pink-600">
                            <Instagram class="h-3.5 w-3.5" />
                            @laudaapi
                        </a>

                        <a href="mailto:contacto@laudaapi.com" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-red-600">
                            <Mail class="h-3.5 w-3.5" />
                            contacto@laudaapi.com
                        </a>
                    </div>

                    <p class="text-[10px] font-bold text-slate-400">
                        © 2026 LAUDAAPI · República Dominicana
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
