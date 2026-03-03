<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from '@/components/ui/toast'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import InputError from '@/components/InputError.vue'
import Input from '@/components/ui/input/Input.vue'
import Button from '@/components/ui/button/Button.vue'
import Select from '@/components/ui/select/Select.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import LegalConsent from '@/components/Legal/LegalConsent.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogContent from '@/components/ui/dialog/DialogContent.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'

const { toast } = useToast()

const props = defineProps({
    open: { type: Boolean, default: false },
    postUrl: { type: String, default: '' },
})

const emit = defineEmits([ 'close', 'submitted' ])

const form = useForm({
    name: '',
    company: '',
    role: '',
    email: '',
    phone: '',
    whatsapp: '',
    topic: '',
    otherTopic: '',
    system: '',
    volume: '',
    message: '',
    terms: false, // ← importante
})

// ⭐ ref para autofocus (sin errores TS)
const nameInput = ref<HTMLInputElement | null>(null)

const resetAll = () => {
    form.reset()
    form.clearErrors()
}

watch(
    () => props.open,
    (val) => {
        if (!val) {
            resetAll()
            return
        }

        // autofocus suave
        setTimeout(() => {
            nameInput.value?.focus()
        }, 50)
    }
)

watch(
    () => form.topic,
    (val) => {
        if (val !== 'Otro') {
            form.otherTopic = ''
            form.clearErrors('otherTopic')
        }
    }
)

const validateClient = () => {
    form.clearErrors()

    if (!form.name?.trim()) form.setError('name', 'El nombre es requerido')
    if (!form.company?.trim()) form.setError('company', 'La empresa es requerida')
    if (!form.role?.trim()) form.setError('role', 'El cargo es requerido')
    if (!form.email?.trim()) form.setError('email', 'El email es requerido')
    if (!form.phone?.trim()) form.setError('phone', 'El teléfono es requerido')

    if (!form.system) form.setError('system', 'Selecciona tu sistema actual')
    if (!form.volume) form.setError('volume', 'Selecciona el volumen mensual')

    if (!form.topic) form.setError('topic', 'Selecciona un tema')
    if (form.topic === 'Otro' && !form.otherTopic?.trim()) {
        form.setError('otherTopic', 'Describe el tema de interés')
    }

    if (!form.message?.trim()) form.setError('message', 'Este campo es requerido')

    // ⭐ Validación del checkbox de términos (aquí sí funciona perfecto)
    if (!form.terms) {
        form.setError('terms', 'Debes aceptar los términos y condiciones.')
    }

    return Object.keys(form.errors).length === 0
}

const submit = async () => {
    if (!validateClient()) return

    // Caso sin postUrl → solo emitir evento
    if (!props.postUrl) {

        toast({
            title: 'Éxito',
            description: 'Formulario enviado correctamente.',
        })

        emit('submitted', { ...form.data() })
        emit('close')
        resetAll()

        return
    }

    try {
        await axios.post(props.postUrl, form.data())

        toast({
            title: 'Éxito',
            description: 'Solicitud procesada correctamente.',
        })

        emit('submitted')
        emit('close')
        resetAll()

    } catch (error) {
        toast({
            title: 'Error',
            description: 'Ocurrió un problema al procesar la solicitud.',
            variant: 'destructive',
        })
    }
}


</script>

<template>
    <Dialog :open="open" @update:open="$emit('close')">
        <DialogContent class="sm:max-w-3xl p-0 max-h-[85vh] flex flex-col">

            <!-- Contenido scrollable -->
            <div class="flex-1 overflow-y-auto p-8">
                <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    <!-- Badge -->
                    <div class="sm:col-span-2 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700
                    dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        <span class="inline-block h-2 w-2 rounded-full bg-red-600"></span>
                        Contacto · LaudaAPI
                    </div>

                    <!-- Header -->
                    <div class="sm:col-span-2 flex items-start justify-between mt-2">
                        <div class="min-w-0">
                            <DialogTitle class="text-2xl font-semibold text-slate-900 dark:text-white">
                                Solicitar información de LaudaAPI
                            </DialogTitle>

                            <DialogDescription class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Cuéntanos tu escenario y te contactamos.
                            </DialogDescription>
                        </div>

                    </div>

                    <!-- Tus campos aquí (nombre, empresa, selects, textarea, etc.) -->
                    <!-- Nombre -->
                    <div>
                        <Input :class="form.errors.name ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.name" placeholder="Nombre y Apellido" @input="form.clearErrors('name')" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Empresa -->
                    <div>
                        <Input :class="form.errors.company ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.company" placeholder="Empresa" @input="form.clearErrors('company')" />
                        <InputError class="mt-1" :message="form.errors.company" />
                    </div>

                    <!-- Cargo -->
                    <div>
                        <Input :class="form.errors.role ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.role" placeholder="Cargo / Rol" @input="form.clearErrors('role')" />
                        <InputError class="mt-1" :message="form.errors.role" />
                    </div>

                    <!-- Email -->
                    <div>
                        <Input type="email" :class="form.errors.email ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.email" placeholder="Email" @input="form.clearErrors('email')" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <Input c :class="form.errors.phone ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.phone" placeholder="Teléfono" @input="form.clearErrors('phone')" />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>

                    <!-- Whatsapp -->
                    <div>
                        <Input :class="form.errors.whatsapp ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.whatsapp" placeholder="Whatsapp" @input="form.clearErrors('whatsapp')" />
                        <InputError class="mt-1" :message="form.errors.whatsapp" />
                    </div>

                    <!-- Área de interés -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.topic" @update:modelValue="form.clearErrors('topic')">
                            <SelectTrigger class="input w-full" :class="form.errors.topic ? 'border-red-600 focus:ring-red-600' : ''">
                                <SelectValue placeholder="Área de interés principal" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="API para sistemas propios">API para sistemas propios</SelectItem>
                                <SelectItem value="Integración con LAUDA ERP Modular">Integración con LAUDA ERP Modular</SelectItem>
                                <SelectItem value="Servicios Web / Webhooks / Multi-tenant">Servicios Web / Webhooks / Multi‑tenant</SelectItem>
                                <SelectItem value="Facturación electrónica DGII">Facturación electrónica DGII</SelectItem>
                                <SelectItem value="Certificación como emisor electrónico">Certificación como emisor electrónico</SelectItem>
                                <SelectItem value="Cumplimiento fiscal y calendario fiscal">Cumplimiento fiscal y calendario fiscal</SelectItem>
                                <SelectItem value="LaudaAPI Todo-en-Uno">LaudaAPI Todo‑en‑Uno</SelectItem>
                                <SelectItem value="Ventas (Retail / Mayorista / QuickServe)">Ventas (Retail / Mayorista / QuickServe)</SelectItem>
                                <SelectItem value="Social Commerce (Facebook/Instagram Shop)">Social Commerce</SelectItem>
                                <SelectItem value="Otro">Otro</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.topic" />
                    </div>

                    <!-- Campo "Otro" -->
                    <div v-if="form.topic === 'Otro'" class="sm:col-span-2">
                        <Input :class="form.errors.otherTopic ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.otherTopic" placeholder="Describe el tema de interés" @input="form.clearErrors('otherTopic')" />
                        <InputError class="mt-1" :message="form.errors.otherTopic" />
                    </div>

                    <!-- Sistema actual -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.system" @update:modelValue="form.clearErrors('system')">
                            <SelectTrigger class="input w-full" :class="form.errors.system ? 'border-red-600 focus:ring-red-600' : ''">
                                <SelectValue placeholder="Sistema que utilizas actualmente" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="ERP propio / desarrollo interno">ERP propio / desarrollo interno</SelectItem>
                                <SelectItem value="Odoo / SAP / Dynamics / NetSuite">Odoo / SAP / Dynamics / NetSuite</SelectItem>
                                <SelectItem value="Software comercial">Software comercial</SelectItem>
                                <SelectItem value="Excel / Manual">Excel / Manual</SelectItem>
                                <SelectItem value="Sistema Legacy">Sistema Legacy</SelectItem>
                                <SelectItem value="No estoy seguro">No estoy seguro</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.system" />
                    </div>

                    <!-- Volumen -->
                    <div class="sm:col-span-2">
                        <Select v-model="form.volume" @update:modelValue="form.clearErrors('volume')">
                            <SelectTrigger class="input w-full" :class="form.errors.volume ? 'border-red-600 focus:ring-red-600' : ''">
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
                        <Textarea :class="form.errors.message ? 'border-red-600 focus:ring-red-600' : ''" v-model="form.message" placeholder="Cuéntanos brevemente tu caso o necesidad" @input="form.clearErrors('message')" />
                        <InputError class="mt-1" :message="form.errors.message" />
                    </div>

                    <div class="sm:col-span-2 flex items-start gap-3">
                        <Checkbox :checked="form.terms" v-model="form.terms" id="terms" class="mt-1" />
                        <LegalConsent :error="form.errors.terms" />
                    </div>

                    <!-- Footer fijo con el botón -->
                    <div class="border-t border-slate-200 dark:border-slate-800 p-6 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing" class="w-full inline-flex items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">
                            {{ form.processing ? 'Enviando…' : 'Enviar solicitud' }}
                        </Button>

                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 text-center">
                            Usaremos tus datos solo para contactarte sobre esta solicitud.
                        </p>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
