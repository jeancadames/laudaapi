<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { useForm } from '@inertiajs/vue3'
import { useToast } from '@/components/ui/toast'
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

const DIAGNOSIS_REQUEST_ENDPOINT = '/contact'
const DIAGNOSIS_TOPIC = 'Solicitud de acceso al Diagnóstico LAUDA 360'

const { toast } = useToast()

const props = defineProps({
    open: { type: Boolean, default: false },

    /**
     * Compatibility prop.
     *
     * Algunos padres históricos todavía envían postUrl="/activation".
     * 9C-C lo conserva para no romper su interface, pero ya NO se utiliza.
     * La solicitud pública siempre entra por /contact.
     */
    postUrl: { type: String, default: '' },
})

const emit = defineEmits([ 'close', 'submitted' ])

const submitting = ref(false)
const serverMessage = ref('')

const form = useForm({
    name: '',
    company: '',
    email: '',
    phone: '',
    solution_interest: '',
    company_size: '',
    main_challenge: 'No sé por dónde comenzar',
    assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
    message: '',
    terms: false,
})

const hasResult = computed(() => serverMessage.value.length > 0)

const resetAll = () => {
    form.reset()
    form.clearErrors()
    serverMessage.value = ''
    submitting.value = false
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            resetAll()
        }
    },
)

const validateClient = () => {
    form.clearErrors()

    if (!form.name.trim()) {
        form.setError('name', 'El nombre es requerido.')
    }

    if (!form.company.trim()) {
        form.setError('company', 'La empresa es requerida.')
    }

    if (!form.email.trim()) {
        form.setError('email', 'El correo electrónico es requerido.')
    } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
        form.setError('email', 'Debes ingresar un correo válido.')
    }

    if (!form.phone.trim()) {
        form.setError('phone', 'El teléfono es requerido.')
    } else if (form.phone.trim().length < 7) {
        form.setError('phone', 'Debes ingresar un teléfono válido.')
    }

    if (!form.company_size) {
        form.setError('company_size', 'Debes indicar el tamaño de la empresa.')
    }

    if (!form.main_challenge) {
        form.setError('main_challenge', 'Debes indicar el principal reto.')
    }

    if (!form.assistance_level) {
        form.setError('assistance_level', 'Debes indicar la modalidad de acompañamiento.')
    }

    if (!form.terms) {
        form.setError('terms', 'Debes aceptar los términos y condiciones.')
    }

    return Object.keys(form.errors).length === 0
}

const payload = () => ({
    name: form.name.trim(),
    email: form.email.trim(),
    phone: form.phone.trim(),
    company: form.company.trim(),
    topic: DIAGNOSIS_TOPIC,
    message: form.message.trim() || null,
    terms: form.terms,
    metadata: {
        source: 'laudaapi.com',
        request_type: 'digital_diagnosis_access_request',
        solution_interest: form.solution_interest || null,
        intake_type: 'digital_transformation_360',
        company_size: form.company_size,
        main_challenge: form.main_challenge,
        assistance_level: form.assistance_level,
        diagnosis_access: 'private_invitation',
    },
})

const applyServerErrors = (errors: Record<string, string[] | string> | undefined) => {
    if (!errors) {
        return
    }

    const fieldMap: Record<string, keyof typeof form.data> = {
        name: 'name',
        company: 'company',
        email: 'email',
        phone: 'phone',
        terms: 'terms',
        'metadata.company_size': 'company_size',
        'metadata.main_challenge': 'main_challenge',
        'metadata.assistance_level': 'assistance_level',
    }

    Object.entries(fieldMap).forEach(([serverField, localField]) => {
        const value = errors[serverField]

        if (!value) {
            return
        }

        const message = Array.isArray(value)
            ? String(value[0] ?? '')
            : String(value)

        if (message) {
            form.setError(localField as never, message)
        }
    })
}

const submit = async () => {
    if (!validateClient() || submitting.value) {
        return
    }

    submitting.value = true
    serverMessage.value = ''

    try {
        await axios.post(
            DIAGNOSIS_REQUEST_ENDPOINT,
            payload(),
        )

        serverMessage.value =
            'Solicitud recibida. Nuestro equipo revisará la información y, '
            + 'cuando se apruebe el acceso, recibirás por correo una invitación '
            + 'privada para iniciar tu Diagnóstico Básico LAUDA 360.'

        emit('submitted')

        toast({
            title: 'Solicitud recibida',
            description:
                'Te enviaremos la invitación al Diagnóstico LAUDA 360 después de la revisión.',
        })
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            if (error.response?.status === 422) {
                const errors = (error.response.data as any)?.errors
                applyServerErrors(errors)

                const firstError = errors
                    ? Object.values(errors).flat().map(String)[0]
                    : null

                toast({
                    title: 'Revisa la solicitud',
                    description:
                        firstError
                        || String((error.response.data as any)?.message ?? 'Hay datos por corregir.'),
                    variant: 'destructive',
                })

                return
            }
        }

        toast({
            title: 'Error',
            description: 'Ocurrió un problema al procesar la solicitud.',
            variant: 'destructive',
        })
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('close')">
        <DialogContent class="sm:max-w-3xl p-0 max-h-[85vh] flex flex-col">
            <div class="flex-1 overflow-y-auto p-8">
                <form
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div
                        class="sm:col-span-2 inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <span class="inline-block h-2 w-2 rounded-full bg-blue-600"></span>
                        LAUDA 360 · Diagnóstico Básico
                    </div>

                    <div class="sm:col-span-2">
                        <DialogTitle class="text-xl font-semibold text-slate-900 dark:text-white">
                            Solicitar acceso al Diagnóstico LAUDA 360
                        </DialogTitle>

                        <DialogDescription class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Cuéntanos brevemente sobre tu empresa. Revisaremos la solicitud y te enviaremos una invitación privada para comenzar el diagnóstico.
                        </DialogDescription>
                    </div>

                    <div>
                        <Input
                            v-model="form.name"
                            placeholder="Nombre y apellido"
                            @input="form.clearErrors('name')"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <Input
                            v-model="form.company"
                            placeholder="Empresa"
                            @input="form.clearErrors('company')"
                        />
                        <InputError class="mt-1" :message="form.errors.company" />
                    </div>

                    <div>
                        <Input
                            v-model="form.email"
                            type="email"
                            placeholder="Email de trabajo"
                            @input="form.clearErrors('email')"
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div>
                        <Input
                            v-model="form.phone"
                            placeholder="Teléfono / WhatsApp"
                            @input="form.clearErrors('phone')"
                        />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>

                    <div class="sm:col-span-2">
                        <Select v-model="form.solution_interest">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Solución de interés (opcional)" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="API para sistemas propios">API para sistemas propios</SelectItem>
                                <SelectItem value="LAUDA ERP / CRM">LAUDA ERP / CRM</SelectItem>
                                <SelectItem value="Facturación electrónica DGII">Facturación electrónica DGII</SelectItem>
                                <SelectItem value="Cumplimiento fiscal">Cumplimiento fiscal</SelectItem>
                                <SelectItem value="LaudaOne B2C">LaudaOne B2C</SelectItem>
                                <SelectItem value="LaudaOne B2B">LaudaOne B2B</SelectItem>
                                <SelectItem value="Presencia Digital">Presencia Digital</SelectItem>
                                <SelectItem value="Transformación integral">Transformación integral</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="sm:col-span-2">
                        <Select
                            v-model="form.company_size"
                            @update:modelValue="form.clearErrors('company_size')"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Tamaño aproximado de la empresa" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="1 a 10 personas">1 a 10 personas</SelectItem>
                                <SelectItem value="11 a 50 personas">11 a 50 personas</SelectItem>
                                <SelectItem value="51 a 200 personas">51 a 200 personas</SelectItem>
                                <SelectItem value="Más de 200 personas">Más de 200 personas</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.company_size" />
                    </div>

                    <div class="sm:col-span-2">
                        <Select
                            v-model="form.main_challenge"
                            @update:modelValue="form.clearErrors('main_challenge')"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Principal reto de transformación" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="No sé por dónde comenzar">No sé por dónde comenzar</SelectItem>
                                <SelectItem value="Organizar procesos y reducir trabajo manual">Organizar procesos y reducir trabajo manual</SelectItem>
                                <SelectItem value="Mejorar captación, clientes y ventas">Mejorar captación, clientes y ventas</SelectItem>
                                <SelectItem value="Digitalizar la operación diaria">Digitalizar la operación diaria</SelectItem>
                                <SelectItem value="Integrar administración, fiscalidad y cumplimiento">Integrar administración, fiscalidad y cumplimiento</SelectItem>
                                <SelectItem value="Centralizar datos, indicadores y BI">Centralizar datos, indicadores y BI</SelectItem>
                                <SelectItem value="Conectar sistemas que hoy trabajan separados">Conectar sistemas que hoy trabajan separados</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.main_challenge" />
                    </div>

                    <div class="sm:col-span-2">
                        <Select
                            v-model="form.assistance_level"
                            @update:modelValue="form.clearErrors('assistance_level')"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Acompañamiento preferido" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="Quiero que LAUDA me recomiende la modalidad">Quiero que LAUDA me recomiende la modalidad</SelectItem>
                                <SelectItem value="LAUDA 360 Guiado">LAUDA 360 Guiado</SelectItem>
                                <SelectItem value="LAUDA 360 Asistido">LAUDA 360 Asistido</SelectItem>
                                <SelectItem value="LAUDA 360 Gestionado">LAUDA 360 Gestionado</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" :message="form.errors.assistance_level" />
                    </div>

                    <div class="sm:col-span-2">
                        <Textarea
                            v-model="form.message"
                            placeholder="Opcional: cuéntanos brevemente qué quieres mejorar o implementar"
                        />
                    </div>

                    <div class="sm:col-span-2 flex items-start gap-3">
                        <Checkbox
                            id="terms"
                            v-model="form.terms"
                            :checked="form.terms"
                            class="mt-1"
                        />
                        <LegalConsent :error="form.errors.terms" />
                    </div>

                    <div
                        v-if="hasResult"
                        class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <div class="font-medium">Solicitud recibida</div>
                        <div class="mt-1 text-slate-600 dark:text-slate-300">
                            {{ serverMessage }}
                        </div>

                        <div class="mt-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-xl"
                                @click="$emit('close')"
                            >
                                Cerrar
                            </Button>
                        </div>
                    </div>

                    <div class="sm:col-span-2 border-t border-slate-200 pt-6 dark:border-slate-800">
                        <Button
                            type="submit"
                            :disabled="submitting"
                            class="w-full rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            {{ submitting ? 'Enviando solicitud…' : 'Solicitar acceso al Diagnóstico LAUDA 360' }}
                        </Button>

                        <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">
                            El acceso al diagnóstico es privado y se habilita mediante invitación.
                        </p>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
