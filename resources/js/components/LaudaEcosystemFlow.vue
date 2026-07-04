<script setup lang="ts">
import {
    Activity,
    ArrowDown,
    CheckCircle2,
    Landmark,
    MessageCircle,
    Radar,
    Radio,
    ReceiptText,
    ShieldCheck,
    Signal,
    Store,
    Users,
    Waypoints,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type NodeId = 'social' | 'crm' | 'pos' | 'ecf' | 'cumplimiento' | 'status';

/* ------------------------------------------------------------------ */
/*  Nodos del ambiente                                                */
/* ------------------------------------------------------------------ */
const nodes = [
    {
        id: 'social',
        code: 'SOCIAL',
        title: 'SocialLaudaAPI',
        role: 'Capta',
        icon: MessageCircle,
        accent: '#ec4899',
        x: 138,
        y: 205,
        simple: 'Convierte conversaciones en oportunidades.',
        receives: 'Mensajes, comentarios, formularios y campañas.',
        process: 'Organiza cada interacción y la vuelve un lead trazable.',
        responds: 'Entrega el lead al CRM con origen, contexto y seguimiento.',
    },
    {
        id: 'crm',
        code: 'CRM',
        title: 'CrmLaudaAPI',
        role: 'Convierte',
        icon: Users,
        accent: '#a855f7',
        x: 360,
        y: 115,
        simple: 'Vende y organiza el pipeline. No factura.',
        receives: 'Leads, clientes, contactos y oportunidades.',
        process: 'Da seguimiento comercial, califica y ordena el pipeline.',
        responds: 'Cuando la oportunidad está lista, solicita la operación al POS.',
    },
    {
        id: 'pos',
        code: 'POS',
        title: 'LaudaAPI POS',
        role: 'Opera',
        icon: Store,
        accent: '#22c55e',
        x: 585,
        y: 250,
        simple: 'La fuente de verdad operativa.',
        receives: 'Solicitudes del CRM y operaciones internas.',
        process: 'Pedidos, ventas, facturas, inventario, CxC, caja, conduces y cobros.',
        responds: 'Genera la operación y envía la petición fiscal al e-CF.',
    },
    {
        id: 'ecf',
        code: 'E-CF',
        title: 'LaudaAPI e-CF',
        role: 'Responde',
        icon: ReceiptText,
        accent: '#3b82f6',
        x: 810,
        y: 128,
        simple: 'Recibe la petición y responde ante la DGII.',
        receives: 'Peticiones fiscales generadas desde el POS.',
        process: 'Genera el XML, firma, envía a la DGII y consulta el estado.',
        responds: 'Devuelve TrackId, acuse, aceptación, rechazo o error procesable.',
    },
    {
        id: 'cumplimiento',
        code: 'CUMPLIMIENTO',
        title: 'Cumplimiento',
        role: 'Ordena',
        icon: ShieldCheck,
        accent: '#f59e0b',
        x: 985,
        y: 222,
        simple: 'Ordena todo lo que el e-CF procesa.',
        receives: 'Estados, comprobantes, XML, acuses y metadata del e-CF.',
        process: 'Organiza vencimientos, documentos y trazabilidad fiscal.',
        responds: 'Prepara control fiscal, alertas y base para reportes.',
    },
    {
        id: 'status',
        code: 'STATUS',
        title: 'LaudaAPI Status',
        role: 'Vigila',
        icon: Radar,
        accent: '#10b981',
        x: 585,
        y: 400,
        simple: 'No opera: observa y alerta.',
        receives: 'Health checks, latencia, errores y disponibilidad.',
        process: 'Monitorea endpoints internos, el e-CF y los servicios de la DGII.',
        responds: 'Reporta disponibilidad, degradación, incidentes o fallos.',
    },
] as const;

/* Ente externo, no es producto */
const dgii = { x: 1165, y: 250 };

/* ------------------------------------------------------------------ */
/*  Conexiones                                                        */
/* ------------------------------------------------------------------ */
const connections = [
    { id: 'social_crm', d: 'M138 205 C 210 135, 292 118, 360 115', from: '#ec4899', to: '#a855f7', kind: 'main' },
    { id: 'crm_pos', d: 'M360 115 C 452 165, 505 208, 585 250', from: '#a855f7', to: '#22c55e', kind: 'main' },
    { id: 'pos_ecf', d: 'M585 250 C 672 175, 732 150, 810 128', from: '#22c55e', to: '#3b82f6', kind: 'main' },
    { id: 'ecf_cumpl', d: 'M810 128 C 886 148, 936 184, 985 222', from: '#3b82f6', to: '#f59e0b', kind: 'main' },
    { id: 'ecf_dgii', d: 'M810 150 C 942 186, 1052 220, 1148 248', from: '#3b82f6', to: '#64748b', kind: 'fiscal' },
    { id: 'status_ecf', d: 'M810 370 C 810 300, 810 210, 810 158', from: '#10b981', to: '#10b981', kind: 'monitor' },
    { id: 'status_dgii', d: 'M642 402 C 902 452, 1080 398, 1150 272', from: '#10b981', to: '#10b981', kind: 'monitor' },
] as const;

const ambientConns = connections.filter((c) => c.kind !== 'monitor');

/* ------------------------------------------------------------------ */
/*  Simulación (narrativa del flujo)                                  */
/* ------------------------------------------------------------------ */
type Step = {
    node: NodeId;
    conn: string;
    from: string;
    to: string;
    msg: string;
    reverse?: boolean;
};

const steps: Step[] = [
    { node: 'social', conn: 'social_crm', from: 'Cliente', to: 'Social', msg: 'nueva interacción capturada' },
    { node: 'crm', conn: 'crm_pos', from: 'Social', to: 'CRM', msg: 'lead trazable, con origen y contexto' },
    { node: 'pos', conn: 'pos_ecf', from: 'CRM', to: 'POS', msg: 'oportunidad aprobada → operación' },
    { node: 'ecf', conn: 'ecf_dgii', from: 'POS', to: 'e-CF', msg: 'petición fiscal emitida' },
    { node: 'ecf', conn: 'ecf_dgii', from: 'e-CF', to: 'DGII', msg: 'XML firmado y enviado' },
    { node: 'ecf', conn: 'ecf_dgii', from: 'DGII', to: 'e-CF', reverse: true, msg: 'comprobante aceptado · TrackId' },
    { node: 'cumplimiento', conn: 'ecf_cumpl', from: 'e-CF', to: 'Cumplimiento', msg: 'estados y documentos ordenados' },
    { node: 'status', conn: 'status_ecf', from: 'Status', to: 'e-CF / DGII', msg: 'endpoints y servicios monitoreados' },
];

const stepIndex = ref(0);
const selectedId = ref<NodeId>('pos');

const activeStep = computed(() => steps[stepIndex.value % steps.length]);
const activeNode = computed(() => activeStep.value.node as NodeId);
const activeConn = computed(() => connections.find((c) => c.id === activeStep.value.conn)!);
const selectedNode = computed(() => nodes.find((n) => n.id === selectedId.value)!);

function selectNode(id: NodeId) {
    selectedId.value = id;
}
function isLit(id: NodeId) {
    return activeNode.value === id || selectedId.value === id;
}

/* rgba helper para colorear con el acento sin depender de clases Tailwind */
function rgba(hex: string, a: number) {
    const h = hex.replace('#', '');
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${a})`;
}

function pctStyle(x: number, y: number) {
    return { left: `${(x / 1240) * 100}%`, top: `${(y / 500) * 100}%` };
}

function cardStyle(node: (typeof nodes)[number]) {
    const on = isLit(node.id as NodeId);
    return {
        borderColor: rgba(node.accent, on ? 0.85 : 0.22),
        background: `linear-gradient(155deg, ${rgba(node.accent, on ? 0.22 : 0.1)}, rgba(9, 12, 22, 0.92))`,
        boxShadow: on
            ? `0 0 0 1px ${rgba(node.accent, 0.55)}, 0 14px 48px ${rgba(node.accent, 0.4)}`
            : '0 8px 26px rgba(0, 0, 0, 0.45)',
    };
}

/* ------------------------------------------------------------------ */
/*  Consola en vivo                                                   */
/* ------------------------------------------------------------------ */
type LogLine = { id: number; time: string; from: string; to: string; msg: string; ms: number };
const log = ref<LogLine[]>([]);
let logId = 0;

function clock() {
    return new Date().toLocaleTimeString('es-DO', { hour12: false });
}

watch(
    stepIndex,
    () => {
        const s = activeStep.value;
        log.value.unshift({
            id: logId++,
            time: clock(),
            from: s.from,
            to: s.to,
            msg: s.msg,
            ms: 24 + Math.floor(Math.random() * 70),
        });
        if (log.value.length > 5) log.value.pop();
    },
    { immediate: true },
);

/* ------------------------------------------------------------------ */
/*  Estado de servicios e-CF / DGII                                   */
/* ------------------------------------------------------------------ */
const services = ref([
    { name: 'Recepción e-CF', base: 40, ms: 40 },
    { name: 'Consulta de estado', base: 55, ms: 55 },
    { name: 'Aprobación comercial', base: 70, ms: 70 },
    { name: 'Disponibilidad DGII', base: 62, ms: 62 },
]);

function jitter() {
    services.value = services.value.map((s) => ({
        ...s,
        ms: Math.max(18, Math.round(s.base + (Math.random() - 0.5) * 46)),
    }));
}
function svcState(ms: number) {
    if (ms < 75) return { label: 'operativo', color: '#10b981' };
    if (ms < 120) return { label: 'lento', color: '#f59e0b' };
    return { label: 'degradado', color: '#ef4444' };
}

/* ------------------------------------------------------------------ */
/*  Timers                                                            */
/* ------------------------------------------------------------------ */
let stepTimer: ReturnType<typeof setInterval> | undefined;
let jitterTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    stepTimer = setInterval(() => {
        stepIndex.value = (stepIndex.value + 1) % steps.length;
    }, 2400);
    jitterTimer = setInterval(jitter, 2000);
});
onUnmounted(() => {
    if (stepTimer) clearInterval(stepTimer);
    if (jitterTimer) clearInterval(jitterTimer);
});
</script>

<template>
    <section
        id="ecosistema"
        class="relative w-full overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white p-5 shadow-2xl sm:p-8 lg:p-10 dark:border-slate-800 dark:bg-[#0a0a0c]"
    >
        <!-- ================= HEADER ================= -->
        <div class="relative z-10 mx-auto mb-8 max-w-3xl text-center">
            <div
                class="mb-4 inline-flex items-center gap-2 rounded-full border border-slate-300/70 bg-slate-100/70 px-3 py-1 dark:border-slate-700 dark:bg-slate-900/70"
            >
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-70"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                <span class="text-[10px] font-black tracking-[0.28em] text-slate-600 uppercase dark:text-slate-300">
                    Ecosistema en vivo · LaudaAPI
                </span>
            </div>

            <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl lg:text-[3.25rem] lg:leading-[1.05] dark:text-white">
                Cinco productos.
                <span class="bg-gradient-to-r from-red-500 via-fuchsia-500 to-amber-500 bg-clip-text text-transparent">
                    Un mismo ambiente.
                </span>
            </h2>

            <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed font-medium text-slate-500 sm:text-base dark:text-slate-400">
                Social capta, CRM convierte, POS opera, e-CF responde ante la DGII y Cumplimiento ordena lo fiscal.
                Status vigila cada conexión. No integras cinco sistemas: entras a un solo organismo.
            </p>
        </div>

        <!-- ================= SALA DE CONTROL (desktop) ================= -->
        <div class="hidden lg:block">
            <div
                class="control-screen relative mx-auto aspect-[1240/500] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-slate-800 bg-[#080a12]"
            >
                <!-- barrido de escaneo -->
                <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
                    <div class="scanline"></div>
                </div>

                <svg viewBox="0 0 1240 500" class="absolute inset-0 z-10 h-full w-full" preserveAspectRatio="none" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                        <linearGradient v-for="c in ambientConns" :id="'grad-' + c.id" :key="'grad-' + c.id" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" :stop-color="c.from" />
                            <stop offset="100%" :stop-color="c.to" />
                        </linearGradient>
                        <filter id="lauda-soft-glow">
                            <feGaussianBlur stdDeviation="4" result="b" />
                            <feMerge>
                                <feMergeNode in="b" />
                                <feMergeNode in="SourceGraphic" />
                            </feMerge>
                        </filter>
                        <radialGradient id="membrane-fill" cx="46%" cy="42%" r="72%">
                            <stop offset="0%" stop-color="rgba(148,163,255,0.10)" />
                            <stop offset="70%" stop-color="rgba(99,102,241,0.045)" />
                            <stop offset="100%" stop-color="rgba(15,23,42,0)" />
                        </radialGradient>
                    </defs>

                    <!-- MEMBRANA: "somos un ambiente" -->
                    <rect
                        x="46" y="54" width="1030" height="404" rx="150"
                        fill="url(#membrane-fill)"
                        stroke="rgba(148,163,184,0.45)"
                        stroke-width="1.5"
                        stroke-dasharray="2 12"
                        class="membrane"
                    />

                    <!-- Conexiones de monitoreo (Status) -->
                    <path
                        v-for="c in connections.filter((x) => x.kind === 'monitor')"
                        :key="c.id"
                        :d="c.d"
                        fill="none"
                        stroke="#10b981"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-dasharray="2 10"
                        class="monitor-line"
                        opacity="0.6"
                    />

                    <!-- Conexiones principales + fiscal -->
                    <path
                        v-for="c in ambientConns"
                        :key="c.id"
                        :id="'conn-' + c.id"
                        :d="c.d"
                        fill="none"
                        :stroke="'url(#grad-' + c.id + ')'"
                        :stroke-width="c.kind === 'fiscal' ? 2.5 : 4"
                        stroke-linecap="round"
                        stroke-dasharray="9 13"
                        class="flow-line"
                        :opacity="activeConn.id === c.id ? 1 : 0.5"
                        filter="url(#lauda-soft-glow)"
                    />

                    <!-- Paquetes ambientales (siempre vivos) -->
                    <template v-for="(c, i) in ambientConns" :key="'pkt-' + c.id">
                        <circle r="3.5" :fill="c.to" opacity="0.9">
                            <animateMotion :dur="'6s'" :begin="i * 1.1 + 's'" repeatCount="indefinite" rotate="auto">
                                <mpath :href="'#conn-' + c.id" :xlink:href="'#conn-' + c.id" />
                            </animateMotion>
                        </circle>
                    </template>

                    <!-- Paquete DESTACADO del paso activo -->
                    <g :key="'active-' + stepIndex">
                        <circle r="11" fill="rgba(239,68,68,0.25)">
                            <animateMotion dur="1.1s" fill="freeze" :keyPoints="activeStep.reverse ? '1;0' : '0;1'" keyTimes="0;1" calcMode="linear">
                                <mpath :href="'#conn-' + activeConn.id" :xlink:href="'#conn-' + activeConn.id" />
                            </animateMotion>
                        </circle>
                        <circle r="5" fill="#ffffff">
                            <animateMotion dur="1.1s" fill="freeze" :keyPoints="activeStep.reverse ? '1;0' : '0;1'" keyTimes="0;1" calcMode="linear">
                                <mpath :href="'#conn-' + activeConn.id" :xlink:href="'#conn-' + activeConn.id" />
                            </animateMotion>
                        </circle>
                    </g>
                </svg>

                <!-- Etiqueta AMBIENTE -->
                <div class="pointer-events-none absolute top-4 left-6 z-20 flex items-center gap-2">
                    <Waypoints class="h-3.5 w-3.5 text-slate-500" />
                    <span class="text-[9px] font-black tracking-[0.3em] text-slate-500 uppercase">Ambiente LaudaAPI</span>
                </div>

                <!-- Nodo externo DGII -->
                <div
                    class="absolute z-20 -translate-x-1/2 -translate-y-1/2"
                    :style="pctStyle(dgii.x, dgii.y)"
                >
                    <div class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-600/60 bg-slate-900/90 px-3.5 py-3 shadow-xl backdrop-blur">
                        <Landmark class="h-5 w-5 text-slate-300" />
                        <p class="text-[9px] font-black tracking-widest text-slate-400 uppercase">DGII</p>
                        <span class="rounded-full bg-slate-700/70 px-2 py-0.5 text-[8px] font-bold tracking-wide text-slate-300 uppercase">externo</span>
                    </div>
                </div>

                <!-- Nodos-producto -->
                <button
                    v-for="node in nodes"
                    :key="node.id"
                    type="button"
                    class="group absolute z-30 w-36 -translate-x-1/2 -translate-y-1/2 text-left transition-transform duration-500 focus:outline-none"
                    :class="activeNode === node.id ? 'scale-[1.07]' : 'scale-100 hover:scale-[1.04]'"
                    :style="pctStyle(node.x, node.y)"
                    @click="selectNode(node.id as NodeId)"
                >
                    <div class="relative overflow-hidden rounded-2xl border p-3.5 backdrop-blur-xl transition-all duration-500" :style="cardStyle(node)">
                        <span
                            v-if="activeNode === node.id"
                            class="absolute -top-6 -right-6 h-16 w-16 animate-ping rounded-full"
                            :style="{ background: rgba(node.accent, 0.18) }"
                        ></span>

                        <div class="relative flex items-center justify-between">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-xl"
                                :style="{ background: rgba(node.accent, 0.16), color: node.accent }"
                            >
                                <component :is="node.icon" class="h-4.5 w-4.5" />
                            </span>
                            <span
                                v-if="activeNode === node.id"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[8px] font-black uppercase"
                                :style="{ background: rgba(node.accent, 0.2), color: node.accent }"
                            >
                                <Zap class="h-2.5 w-2.5" /> activo
                            </span>
                            <span v-else class="text-[9px] font-black tracking-widest uppercase" :style="{ color: rgba(node.accent, 0.9) }">
                                {{ node.role }}
                            </span>
                        </div>

                        <h3 class="relative mt-2.5 text-[13px] font-black text-white">{{ node.title }}</h3>
                        <p class="relative mt-0.5 text-[10px] leading-tight font-medium text-slate-400">{{ node.simple }}</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- ================= STACK (mobile / tablet) ================= -->
        <div class="lg:hidden">
            <div class="space-y-2.5">
                <template v-for="(node, index) in nodes" :key="node.id">
                    <button
                        type="button"
                        class="relative w-full overflow-hidden rounded-2xl border p-4 text-left transition-all"
                        :style="cardStyle(node)"
                        @click="selectNode(node.id as NodeId)"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :style="{ background: rgba(node.accent, 0.16), color: node.accent }">
                                <component :is="node.icon" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="text-[9px] font-black tracking-widest uppercase" :style="{ color: node.accent }">{{ node.code }}</p>
                                    <span v-if="activeNode === node.id" class="h-1.5 w-1.5 animate-pulse rounded-full" :style="{ background: node.accent }"></span>
                                </div>
                                <h3 class="text-sm font-black text-white">{{ node.title }}</h3>
                                <p class="text-[11px] font-medium text-slate-400">{{ node.simple }}</p>
                            </div>
                            <span class="text-[9px] font-black tracking-wide uppercase" :style="{ color: rgba(node.accent, 0.85) }">{{ node.role }}</span>
                        </div>
                    </button>
                    <div v-if="index < nodes.length - 1" class="flex justify-center">
                        <ArrowDown class="h-4 w-4" :style="{ color: rgba(nodes[index + 1].accent, 0.8) }" />
                    </div>
                </template>
            </div>
        </div>

        <!-- ================= CONSOLA + PROGRESO ================= -->
        <div class="mt-6 grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
            <!-- Consola en vivo -->
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-[#0b0e16]">
                <div class="flex items-center justify-between border-b border-slate-800 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <Radio class="h-3.5 w-3.5 animate-pulse text-red-400" />
                        <span class="text-[10px] font-black tracking-[0.2em] text-slate-300 uppercase">Transmisión en vivo</span>
                    </div>
                    <div class="flex gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-red-500/70"></span>
                        <span class="h-2 w-2 rounded-full bg-amber-500/70"></span>
                        <span class="h-2 w-2 rounded-full bg-emerald-500/70"></span>
                    </div>
                </div>
                <div class="space-y-1 p-3 font-mono text-[11px]">
                    <transition-group name="logline">
                        <div
                            v-for="(line, i) in log"
                            :key="line.id"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5"
                            :class="i === 0 ? 'bg-slate-800/60' : 'opacity-60'"
                        >
                            <span class="text-slate-600">{{ line.time }}</span>
                            <span class="font-bold text-sky-400">{{ line.from }}</span>
                            <span class="text-slate-600">→</span>
                            <span class="font-bold text-emerald-400">{{ line.to }}</span>
                            <span class="truncate text-slate-400">{{ line.msg }}</span>
                            <span class="ml-auto shrink-0 rounded bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-bold text-emerald-400">{{ line.ms }}ms</span>
                        </div>
                    </transition-group>
                </div>
            </div>

            <!-- Estado de servicios -->
            <div class="rounded-2xl border border-slate-800 bg-[#0b0e16] p-4">
                <div class="mb-3 flex items-center gap-2">
                    <Signal class="h-3.5 w-3.5 text-emerald-400" />
                    <span class="text-[10px] font-black tracking-[0.2em] text-slate-300 uppercase">Estado e-CF / DGII</span>
                </div>
                <div class="space-y-2.5">
                    <div v-for="s in services" :key="s.name">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-slate-300">{{ s.name }}</span>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold" :style="{ color: svcState(s.ms).color }">
                                <span class="h-1.5 w-1.5 rounded-full" :style="{ background: svcState(s.ms).color }"></span>
                                {{ s.ms }}ms
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-800">
                            <div
                                class="h-full rounded-full transition-all duration-700 ease-out"
                                :style="{ width: Math.min(100, (s.ms / 140) * 100) + '%', background: svcState(s.ms).color }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= INSPECTOR ================= -->
        <div class="mt-6 grid gap-4 lg:grid-cols-[0.85fr_1.15fr]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-950/50">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl" :style="{ background: rgba(selectedNode.accent, 0.14), color: selectedNode.accent }">
                        <component :is="selectedNode.icon" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-[10px] font-black tracking-widest uppercase" :style="{ color: selectedNode.accent }">{{ selectedNode.code }}</p>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white">{{ selectedNode.title }}</h3>
                    </div>
                </div>
                <p class="text-sm leading-relaxed font-medium text-slate-600 dark:text-slate-300">{{ selectedNode.simple }}</p>

                <div class="mt-5 flex flex-wrap gap-1.5">
                    <button
                        v-for="node in nodes"
                        :key="'tab-' + node.id"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-[10px] font-black transition-all"
                        :style="
                            selectedId === node.id
                                ? { background: node.accent, borderColor: node.accent, color: '#fff' }
                                : { borderColor: rgba(node.accent, 0.3), color: node.accent }
                        "
                        @click="selectNode(node.id as NodeId)"
                    >
                        {{ node.code }}
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                    <p class="mb-2.5 text-[10px] font-black tracking-widest text-slate-400 uppercase">Recibe</p>
                    <p class="text-[13px] leading-relaxed text-slate-600 dark:text-slate-300">{{ selectedNode.receives }}</p>
                </div>
                <div class="rounded-2xl border p-4" :style="{ borderColor: rgba(selectedNode.accent, 0.35), background: rgba(selectedNode.accent, 0.06) }">
                    <p class="mb-2.5 text-[10px] font-black tracking-widest uppercase" :style="{ color: selectedNode.accent }">Procesa</p>
                    <p class="text-[13px] leading-relaxed text-slate-700 dark:text-slate-200">{{ selectedNode.process }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/10">
                    <p class="mb-2.5 text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400">Responde</p>
                    <p class="text-[13px] leading-relaxed text-slate-700 dark:text-slate-200">{{ selectedNode.responds }}</p>
                </div>
            </div>
        </div>

        <!-- ================= CIERRE ================= -->
        <div class="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-red-500/10 text-red-500">
                    <CheckCircle2 class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white">Para tu cliente, se ve simple.</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Cada producto recibe una petición, hace su parte y responde al siguiente. Nadie tiene que entender
                        cinco sistemas: entiende un flujo automático.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2 rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-900">
                <Activity class="h-3.5 w-3.5 text-emerald-500" />
                <span class="text-xs font-black tracking-widest text-slate-600 uppercase dark:text-slate-300">API-first</span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.control-screen {
    background-image:
        radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.12), transparent 55%),
        linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
    background-size:
        100% 100%,
        34px 34px,
        34px 34px;
}

.flow-line {
    animation: flow-dash 1s linear infinite;
}
.monitor-line {
    animation: flow-dash 1.4s linear infinite;
}
@keyframes flow-dash {
    to {
        stroke-dashoffset: -44;
    }
}

.membrane {
    animation: membrane-breathe 6s ease-in-out infinite;
    transform-origin: center;
}
@keyframes membrane-breathe {
    0%,
    100% {
        opacity: 0.7;
        stroke-dashoffset: 0;
    }
    50% {
        opacity: 1;
        stroke-dashoffset: -28;
    }
}

.scanline {
    position: absolute;
    top: 0;
    left: -30%;
    width: 30%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(120, 160, 255, 0.06), transparent);
    animation: sweep 7s linear infinite;
}
@keyframes sweep {
    to {
        left: 130%;
    }
}

.logline-enter-active {
    transition: all 0.4s ease;
}
.logline-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}
.logline-move {
    transition: transform 0.4s ease;
}

@media (prefers-reduced-motion: reduce) {
    .flow-line,
    .monitor-line,
    .membrane,
    .scanline {
        animation: none !important;
    }
    :deep(.animate-ping),
    :deep(.animate-pulse) {
        animation: none !important;
    }
}
</style>