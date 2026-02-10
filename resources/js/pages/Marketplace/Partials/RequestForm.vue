<script setup lang="ts">
import { watch, ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from '@/components/ui/toast'
import axios from 'axios'
import InputError from '@/components/InputError.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'
import Select from '@/components/ui/select/Select.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'
import LegalConsent from '@/components/Legal/LegalConsent.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogContent from '@/components/ui/dialog/DialogContent.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue'

const { toast } = useToast()

const props = defineProps({
    open: { type: Boolean, default: false },
    postUrl: { type: String, default: '' },
})
const emit = defineEmits([ 'close', 'submitted' ])

/**
 * ✅ OPCIONAL (recomendado):
 * - true  => si es "pending", cierra modal como antes
 * - false => NO cierra automático, siempre muestra "Resultado"
 */
const AUTO_CLOSE_ON_PENDING = false

const form = useForm({
    name: '',
    company: '',
    role: '',
    email: '',
    phone: '',
    topic: '',
    otherTopic: '',
    system: '',
    volume: '',
    message: '',
    terms: false,
})

// ✅ estado para feedback y CTA
const serverMessage = ref<string>('')
const actionUrl = ref<string>('')    // e.g. /subscriber/activation o /login
const actionLabel = ref<string>('')  // e.g. "Ir a iniciar trial"

// opcional: para mostrar tipo/estado detectado
const inferredState = ref<'pending' | 'accepted' | 'trialing' | 'converted' | 'unknown'>('unknown')

const hasResult = computed(() => !!serverMessage.value)
const hasCta = computed(() => !!actionUrl.value)

const resetAll = () => {
    form.reset()
    form.clearErrors()

    serverMessage.value = ''
    actionUrl.value = ''
    actionLabel.value = ''
    inferredState.value = 'unknown'
}

watch(
    () => props.open,
    (val) => {
        if (!val) resetAll()
    }
)

const validateClient = () => {
    form.clearErrors()

    if (!form.name?.trim()) form.setError('name', 'El nombre es requerido')
    if (!form.company?.trim()) form.setError('company', 'La empresa es requerida')
    if (!form.role?.trim()) form.setError('role', 'El cargo o rol es requerido')

    if (!form.email?.trim()) {
        form.setError('email', 'El email es requerido')
    } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
        form.setError('email', 'Ingresa un email válido')
    }

    if (form.phone && form.phone.trim().length < 8) {
        form.setError('phone', 'El número de teléfono es inválido')
    }

    if (!form.topic) form.setError('topic', 'Selecciona un área de interés')
    if (form.topic === 'Otro' && !form.otherTopic?.trim()) {
        form.setError('otherTopic', 'Describe brevemente el tema')
    }

    if (!form.system) form.setError('system', 'Selecciona tu sistema actual')
    if (!form.volume) form.setError('volume', 'Selecciona tu volumen mensual')

    if (form.message && form.message.trim().length < 3) {
        form.setError('message', 'El mensaje es muy corto')
    }

    if (!form.terms) {
        form.setError('terms', 'Debes aceptar los términos y condiciones.')
    }

    return Object.keys(form.errors).length === 0
}

/**
 * ✅ NEW: decide CTA según message (robusto)
 * Importante: esto es heurística basada en texto.
 * Ideal sería que backend devuelva next_url/next_label.
 */
const computeActionFromMessage = (msg: string) => {
    const m = (msg || '').toLowerCase()

    // reset
    actionUrl.value = ''
    actionLabel.value = ''
    inferredState.value = 'unknown'

    // ------------------------
    // accepted => ir a activar trial desde panel
    // cubre: "acceso concedido", "correo confirmado", "ya puedes iniciar tu prueba", etc.
    // ------------------------
    const looksAccepted =
        m.includes('acceso concedido') ||
        (m.includes('correo') && (m.includes('confirm') || m.includes('verific'))) ||
        m.includes('iniciar tu prueba') ||
        m.includes('subscriber/activation')

    if (looksAccepted) {
        inferredState.value = 'accepted'
        actionUrl.value = '/subscriber/activation'
        actionLabel.value = 'Ir a iniciar trial'
        return
    }

    // ------------------------
    // trialing/activo => ir al dashboard subscriber
    // ------------------------
    const looksTrialing =
        (m.includes('trial') && (m.includes('activo') || m.includes('ya está activo') || m.includes('trialing'))) ||
        m.includes('ya tienes empresa y trial activo') ||
        m.includes('activación completada')

    if (looksTrialing) {
        inferredState.value = 'trialing'
        actionUrl.value = '/subscriber'
        actionLabel.value = 'Ir al dashboard'
        return
    }

    // ------------------------
    // converted / cuenta activa => ir a login (o panel si ya tiene sesión)
    // ------------------------
    const looksConverted =
        (m.includes('cuenta') && (m.includes('activa') || m.includes('ya está activa'))) ||
        m.includes('converted') ||
        m.includes('suscripción activa')

    if (looksConverted) {
        inferredState.value = 'converted'
        actionUrl.value = '/login'
        actionLabel.value = 'Iniciar sesión'
        return
    }

    // ------------------------
    // pending => revisar correo (sin CTA)
    // ------------------------
    const looksPending =
        m.includes('revisa tu correo') ||
        m.includes('solicitud recibida') ||
        m.includes('solicitud pendiente') ||
        m.includes('te reenviamos el correo')

    if (looksPending) {
        inferredState.value = 'pending'
        return
    }

    // unknown -> sin CTA
    inferredState.value = 'unknown'
}

const submit = async () => {
    if (!validateClient()) return

    // sin postUrl → emit
    if (!props.postUrl) {
        emit('submitted', { ...form.data() })
        toast({ title: 'Éxito', description: 'Formulario enviado correctamente.' })
        emit('close')
        resetAll()
        return
    }

    try {
        const res = await axios.post(props.postUrl, form.data())
        const msg = String(res?.data?.message ?? 'Solicitud procesada correctamente.')

        serverMessage.value = msg
        computeActionFromMessage(msg)

        emit('submitted')

        toast({
            title: 'Éxito',
            description: msg,
        })

        // ✅ comportamiento controlado por flag
        if (!hasCta.value && inferredState.value === 'pending' && AUTO_CLOSE_ON_PENDING) {
            emit('close')
            resetAll()
        }
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            if (error.response?.status === 422) {
                const errors = (error.response.data as any)?.errors

                const firstError =
                    errors?.email?.[ 0 ] ||
                    errors?.name?.[ 0 ] ||
                    errors?.company?.[ 0 ] ||
                    errors?.terms?.[ 0 ]

                if (firstError) {
                    toast({
                        title: 'Error',
                        description: firstError,
                        variant: 'destructive',
                    })
                    return
                }
            }
        }

        toast({
            title: 'Error',
            description: 'Ocurrió un problema al procesar la solicitud.',
            variant: 'destructive',
        })
    }
}

const goToAction = () => {
    if (!actionUrl.value) return
    window.location.href = actionUrl.value
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('close')">
        <DialogContent class="sm:max-w-3xl p-0 max-h-[85vh] flex flex-col">
            <div class="flex-1 overflow-y-auto p-8">
                <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700
                   dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        <span class="inline-block h-2 w-2 rounded-full bg-blue-600"></span>
                        Activación · Prueba gratis 30 días
                    </div>

                    <!-- Header -->
                    <div class="sm:col-span-2 flex items-start justify-between mt-2">
                        <div class="min-w-0">
                            <DialogTitle class="text-xl font-semibold text-slate-900 dark:text-white">
                                Activar prueba gratis de LaudaAPI
                            </DialogTitle>

                            <DialogDescription class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Sin tarjeta · Sin compromiso · Configuramos tu entorno según tu operación real.
                            </DialogDescription>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <Input :class="form.errors.name ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.name" placeholder="Nombre y Apellido" @input="form.clearErrors('name')" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Empresa -->
                    <div>
                        <Input :class="form.errors.company ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.company" placeholder="Empresa" @input="form.clearErrors('company')" />
                        <InputError class="mt-1" :message="form.errors.company" />
                    </div>

                    <!-- Cargo -->
                    <div>
                        <Input :class="form.errors.role ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.role" placeholder="Cargo / Rol" @input="form.clearErrors('role')" />
                        <InputError class="mt-1" :message="form.errors.role" />
                    </div>

                    <!-- Email -->
                    <div>
                        <Input type="email" :class="form.errors.email ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.email" placeholder="Email de trabajo" @input="form.clearErrors('email')" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <!-- Teléfono -->
                    <div class="sm:col-span-2">
                        <Input :class="form.errors.phone ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.phone" placeholder="Teléfono / WhatsApp (opcional)" @input="form.clearErrors('phone')" />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>

                    <!-- Área de interés -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.topic" @update:modelValue="form.clearErrors('topic')">
                            <SelectTrigger class="input w-full" :class="form.errors.topic ? 'border-blue-600 focus:ring-blue-600' : ''">
                                <SelectValue placeholder="¿Qué deseas probar primero?" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="API para sistemas propios">API para sistemas propios</SelectItem>
                                <SelectItem value="Integración con LAUDA ERP Modular">Integración con LAUDA ERP Modular</SelectItem>
                                <SelectItem value="Servicios Web / Webhooks / Multi-tenant">Servicios Web / Webhooks / Multi-tenant</SelectItem>

                                <SelectItem value="Facturación electrónica DGII">Facturación electrónica DGII</SelectItem>
                                <SelectItem value="Certificación como emisor electrónico">Certificación como emisor electrónico</SelectItem>
                                <SelectItem value="Cumplimiento fiscal y calendario fiscal">Cumplimiento fiscal y calendario fiscal</SelectItem>

                                <SelectItem value="LaudaAPI Todo-en-Uno">LaudaAPI Todo-en-Uno</SelectItem>
                                <SelectItem value="Ventas (Retail / Mayorista / QuickServe)">Ventas (Retail / Mayorista / QuickServe)</SelectItem>
                                <SelectItem value="Social Commerce (Facebook/Instagram Shop)">Social Commerce (Facebook/Instagram Shop)</SelectItem>

                                <SelectItem value="Otro">Otro</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.topic" />
                    </div>

                    <!-- Campo "Otro" -->
                    <div v-if="form.topic === 'Otro'" class="sm:col-span-2">
                        <Input :class="form.errors.otherTopic ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.otherTopic" placeholder="Describe brevemente tu caso" @input="form.clearErrors('otherTopic')" />
                        <InputError class="mt-1" :message="form.errors.otherTopic" />
                    </div>

                    <!-- Sistema actual -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.system" @update:modelValue="form.clearErrors('system')">
                            <SelectTrigger class="input w-full" :class="form.errors.system ? 'border-blue-600 focus:ring-blue-600' : ''">
                                <SelectValue placeholder="Sistema que utilizas actualmente" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="ERP propio / desarrollo interno">ERP propio / desarrollo interno</SelectItem>
                                <SelectItem value="Odoo / SAP / Dynamics / NetSuite">Odoo / SAP / Dynamics / NetSuite</SelectItem>
                                <SelectItem value="Software comercial (QuickBooks, Zoho, Alegra, etc.)">Software comercial (QuickBooks, Zoho, Alegra, etc.)</SelectItem>
                                <SelectItem value="Excel / Manual">Excel / Manual</SelectItem>
                                <SelectItem value="Sistema Legacy">Sistema Legacy</SelectItem>
                                <SelectItem value="No estoy seguro">No estoy seguro</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.system" />
                    </div>

                    <!-- Volumen mensual -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.volume" @update:modelValue="form.clearErrors('volume')">
                            <SelectTrigger class="input w-full" :class="form.errors.volume ? 'border-blue-600 focus:ring-blue-600' : ''">
                                <SelectValue placeholder="Volumen mensual (documentos / transacciones)" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="1 – 500">1 – 500</SelectItem>
                                <SelectItem value="500 – 5,000">500 – 5,000</SelectItem>
                                <SelectItem value="5,000 – 50,000">5,000 – 50,000</SelectItem>
                                <SelectItem value="50,000+">50,000+</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.volume" />
                    </div>

                    <!-- Mensaje -->
                    <div class="sm:col-span-2">
                        <Textarea :class="form.errors.message ? 'border-blue-600 focus:ring-blue-600' : ''" v-model="form.message" placeholder="Opcional: cuéntanos qué te gustaría probar primero" @input="form.clearErrors('message')" />
                        <InputError class="mt-1" :message="form.errors.message" />
                    </div>

                    <div class="sm:col-span-2 flex items-start gap-3">
                        <Checkbox :checked="form.terms" v-model="form.terms" id="terms" class="mt-1" />
                        <LegalConsent :error="form.errors.terms" />
                    </div>

                    <!-- ✅ Resultado inline (siempre que haya respuesta del server) -->
                    <div v-if="hasResult" class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        <div class="font-medium">Resultado</div>
                        <div class="mt-1 text-slate-600 dark:text-slate-300">
                            {{ serverMessage }}
                        </div>

                        <div v-if="hasCta" class="mt-3 flex gap-2">
                            <Button type="button" class="rounded-xl" @click="goToAction">
                                {{ actionLabel }}
                            </Button>
                            <Button type="button" variant="outline" class="rounded-xl" @click="$emit('close')">
                                Cerrar
                            </Button>
                        </div>

                        <div v-else class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Si no recibes el correo en 2–3 minutos, revisa spam o solicita nuevamente.
                        </div>
                    </div>

                    <!-- Footer fijo con el botón -->
                    <div class="border-t border-slate-200 dark:border-slate-800 p-6 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing" class="w-full inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">
                            {{ form.processing ? 'Activando prueba…' : 'Activar prueba gratis 30 días' }}
                        </Button>

                        <p class="text-xs text-slate-500 dark:text-slate-400 text-center">
                            Sin tarjeta · Sin compromiso · Te ayudamos a configurarlo.
                        </p>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
