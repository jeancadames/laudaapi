<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCircle2,
    CircleAlert,
    Landmark,
    MapPin,
    Save,
    Settings2,
    UserRound,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';

export type CompanyProfilePayload = {
    company_name: string;
    legal_name: string;
    tax_id: string;
    taxpayer_type: string | null;
    country_code: string;
    currency: string;
    timezone: string;
    company_size: string;
    billing_email: string;
    billing_phone: string;
    billing_contact_name: string;
    address_line1: string;
    address_line2: string;
    state: string;
    city: string;
    postal_code: string;
    economic_activity_primary_code: string;
    economic_activity_primary_name: string;
};

const props = withDefaults(
    defineProps<{
        initial: CompanyProfilePayload;
        action: string;
        submitLabel?: string;
        account?: {
            name: string;
            email: string;
        } | null;
        onboarding?: boolean;
    }>(),
    {
        submitLabel: 'Guardar perfil',
        account: null,
        onboarding: false,
    },
);

const step = ref(1);
const highestStepReached = ref(1);
const totalSteps = 4;

const formState = reactive({
    name: props.account?.name ?? '',
    company_name: props.initial.company_name ?? '',
    legal_name: props.initial.legal_name ?? '',
    tax_id: props.initial.tax_id ?? '',
    taxpayer_type: props.initial.taxpayer_type ?? '',
    company_size: props.initial.company_size ?? '',
    country_code: props.initial.country_code ?? '',
    currency: props.initial.currency ?? 'DOP',
    timezone: props.initial.timezone ?? 'America/Santo_Domingo',
    billing_email: props.initial.billing_email ?? '',
    billing_phone: props.initial.billing_phone ?? '',
    billing_contact_name: props.initial.billing_contact_name ?? '',
    address_line1: props.initial.address_line1 ?? '',
    address_line2: props.initial.address_line2 ?? '',
    state: props.initial.state ?? '',
    city: props.initial.city ?? '',
    postal_code: props.initial.postal_code ?? '',
    economic_activity_primary_code:
        props.initial.economic_activity_primary_code ?? '',
    economic_activity_primary_name:
        props.initial.economic_activity_primary_name ?? '',
});

const onboardingSteps = [
    {
        step: 1,
        title: 'Empresa',
        description: 'Identidad fiscal y comercial.',
    },
    {
        step: 2,
        title: 'Contexto',
        description: 'Tamaño y configuración base.',
    },
    {
        step: 3,
        title: 'Contacto',
        description: 'Contacto y ubicación.',
    },
    {
        step: 4,
        title: 'Confirmar',
        description: 'Actividad y revisión final.',
    },
] as const;

const stepFields: Record<number, string[]> = {
    1: [
        'name',
        'company_name',
        'legal_name',
        'tax_id',
        'taxpayer_type',
    ],
    2: [
        'company_size',
        'country_code',
        'currency',
        'timezone',
    ],
    3: [
        'billing_email',
        'billing_phone',
        'billing_contact_name',
        'address_line1',
        'address_line2',
        'state',
        'city',
        'postal_code',
    ],
    4: [
        'economic_activity_primary_code',
        'economic_activity_primary_name',
    ],
};

const currentStepValid = computed(() => {
    if (!props.onboarding) {
        return true;
    }

    if (step.value === 1) {
        return Boolean(
            (!props.account || formState.name.trim()) &&
                formState.company_name.trim(),
        );
    }

    if (step.value === 2) {
        return Boolean(
            formState.company_size &&
                formState.country_code.trim() &&
                formState.currency &&
                formState.timezone.trim(),
        );
    }

    if (step.value === 3) {
        return Boolean(formState.billing_email.trim());
    }

    return true;
});

function errorBelongsToStep(
    errorKey: string,
    targetStep: number,
): boolean {
    const fields = stepFields[targetStep] ?? [];

    return fields.some(
        (field) =>
            errorKey === field ||
            errorKey.startsWith(`${field}.`),
    );
}

function stepHasErrors(
    errors: Record<string, string>,
    targetStep: number,
): boolean {
    return Object.keys(errors).some((errorKey) =>
        errorBelongsToStep(errorKey, targetStep),
    );
}

function goToStep(target: unknown): void {
    const normalized = Number(target);

    if (
        !Number.isInteger(normalized) ||
        normalized < 1 ||
        normalized > totalSteps
    ) {
        return;
    }

    if (normalized <= highestStepReached.value) {
        step.value = normalized;
    }
}

function next(): void {
    if (
        step.value >= totalSteps ||
        !currentStepValid.value
    ) {
        return;
    }

    step.value += 1;

    highestStepReached.value = Math.max(
        highestStepReached.value,
        step.value,
    );
}

function prev(): void {
    if (step.value > 1) {
        step.value -= 1;
    }
}

function displayValue(value: string | null | undefined): string {
    const normalized = String(value ?? '').trim();

    return normalized !== '' ? normalized : '—';
}

function taxpayerLabel(value: string): string {
    if (value === 'persona_juridica') {
        return 'Persona jurídica';
    }

    if (value === 'persona_fisica') {
        return 'Persona física';
    }

    return 'Sin especificar';
}

function companySizeLabel(value: string): string {
    return (
        {
            '1-10': '1–10 personas',
            '11-50': '11–50 personas',
            '51-200': '51–200 personas',
            '201+': '201 o más',
        }[value] ?? displayValue(value)
    );
}
</script>

<template>
    <Form
        :action="props.action"
        method="post"
        v-slot="{ errors, processing }"
        class="space-y-5"
    >
        <section
            v-if="props.onboarding"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <div
                class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
            >
                <div>
                    <p
                        class="text-xs font-black tracking-widest text-slate-500 uppercase"
                    >
                        Configuración guiada
                    </p>

                    <p
                        class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Paso {{ step }} de {{ totalSteps }}
                    </p>
                </div>

                <span
                    class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    {{ Math.round((step / totalSteps) * 100) }}%
                </span>
            </div>

            <div class="min-w-0 px-2 py-5 sm:px-5">
                <Stepper
                    :model-value="step"
                    class="w-full min-w-0 items-start gap-1 sm:gap-2"
                    @update:model-value="goToStep"
                >
                    <StepperItem
                        v-for="(item, index) in onboardingSteps"
                        :key="item.step"
                        :step="item.step"
                        class="min-w-0 flex-1 flex-col items-stretch gap-1 sm:flex-row sm:items-center sm:gap-2"
                    >
                        <StepperTrigger
                            type="button"
                            class="w-full min-w-0 px-0.5 sm:px-1"
                            :class="{
                                'cursor-not-allowed opacity-45':
                                    item.step > highestStepReached,
                            }"
                            :aria-disabled="
                                item.step > highestStepReached
                            "
                            @click="goToStep(item.step)"
                        >
                            <StepperIndicator
                                class="border border-slate-300 bg-white font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                :class="{
                                    'border-red-500 bg-red-500 text-white':
                                        item.step === step &&
                                        !stepHasErrors(
                                            errors,
                                            item.step,
                                        ),
                                    'border-green-500 bg-green-500 text-white':
                                        item.step < step &&
                                        !stepHasErrors(
                                            errors,
                                            item.step,
                                        ),
                                    'border-red-500 bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-300':
                                        stepHasErrors(
                                            errors,
                                            item.step,
                                        ),
                                }"
                            >
                                <CircleAlert
                                    v-if="
                                        stepHasErrors(
                                            errors,
                                            item.step,
                                        )
                                    "
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />

                                <CheckCircle2
                                    v-else-if="item.step < step"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />

                                <span v-else>
                                    {{ item.step }}
                                </span>
                            </StepperIndicator>

                            <StepperTitle
                                class="max-w-full text-[10px] leading-tight font-bold break-words whitespace-normal text-slate-800 sm:text-xs lg:text-sm dark:text-slate-200"
                            >
                                {{ item.title }}
                            </StepperTitle>

                            <StepperDescription
                                class="hidden max-w-full text-[10px] leading-4 whitespace-normal text-slate-500 sm:block lg:text-[11px]"
                            >
                                {{
                                    stepHasErrors(
                                        errors,
                                        item.step,
                                    )
                                        ? 'Revisar los campos.'
                                        : item.description
                                }}
                            </StepperDescription>
                        </StepperTrigger>

                        <StepperSeparator
                            v-if="
                                index <
                                onboardingSteps.length - 1
                            "
                            class="hidden h-px min-w-0 flex-1 bg-slate-200 sm:block dark:bg-slate-700"
                        />
                    </StepperItem>
                </Stepper>
            </div>

            <div
                class="border-t border-slate-200 bg-slate-50/70 px-5 py-3 dark:border-slate-800 dark:bg-slate-950/30"
            >
                <p class="text-xs text-slate-500">
                    Puedes regresar a cualquier etapa ya alcanzada.
                    Para avanzar, completa los datos obligatorios de
                    la etapa actual.
                </p>
            </div>
        </section>

        <section
            v-if="props.account"
            v-show="!props.onboarding || step === 1"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-4 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <UserRound class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Tu cuenta
                    </p>

                    <p class="text-sm text-slate-500">
                        Identidad personal para iniciar sesión en LAUDAAPI.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Tu nombre</Label>

                    <input
                        id="name"
                        v-model="formState.name"
                        name="name"
                        type="text"
                        maxlength="255"
                        autocomplete="name"
                        required
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    />

                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>Correo de acceso</Label>

                    <Input
                        :value="props.account.email"
                        disabled
                    />

                    <p class="text-xs text-slate-500">
                        Este correo pertenece a tu usuario, no al perfil de la empresa.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-show="!props.onboarding || step === 1"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/40"
                >
                    <Building2 class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Identidad de la empresa
                    </p>

                    <p class="text-sm text-slate-500">
                        Información fiscal y comercial de tu empresa.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="company_name">
                        Nombre comercial *
                    </Label>

                    <input
                        id="company_name"
                        v-model="formState.company_name"
                        name="company_name"
                        type="text"
                        maxlength="255"
                        autocomplete="organization"
                        required
                        placeholder="Nombre de tu empresa"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    />

                    <InputError :message="errors.company_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="legal_name">
                        Razón social
                    </Label>

                    <input
                        id="legal_name"
                        v-model="formState.legal_name"
                        name="legal_name"
                        type="text"
                        maxlength="255"
                        autocomplete="organization"
                        placeholder="Razón social registrada"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    />

                    <InputError :message="errors.legal_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_id">
                        RNC / identificación fiscal
                    </Label>

                    <input
                        id="tax_id"
                        v-model="formState.tax_id"
                        name="tax_id"
                        type="text"
                        maxlength="50"
                        autocomplete="off"
                        placeholder="Ej. 101000000"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    />

                    <InputError :message="errors.tax_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="taxpayer_type">
                        Tipo de contribuyente
                    </Label>

                    <select
                        id="taxpayer_type"
                        v-model="formState.taxpayer_type"
                        name="taxpayer_type"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">
                            Sin especificar
                        </option>

                        <option value="persona_juridica">
                            Persona jurídica
                        </option>

                        <option value="persona_fisica">
                            Persona física
                        </option>
                    </select>

                    <InputError :message="errors.taxpayer_type" />
                </div>
            </div>
        </section>

        <section
            v-show="!props.onboarding || step === 2"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/40"
                >
                    <Settings2 class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Contexto empresarial
                    </p>

                    <p class="text-sm text-slate-500">
                        Información base que utilizará el App Hub para contextualizar tu empresa.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="company_size">
                        Tamaño aproximado *
                    </Label>

                    <select
                        id="company_size"
                        v-model="formState.company_size"
                        name="company_size"
                        required
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="" disabled>
                            Selecciona una opción
                        </option>

                        <option value="1-10">
                            1–10 personas
                        </option>

                        <option value="11-50">
                            11–50 personas
                        </option>

                        <option value="51-200">
                            51–200 personas
                        </option>

                        <option value="201+">
                            201 o más
                        </option>
                    </select>

                    <InputError :message="errors.company_size" />
                </div>

                <div class="grid gap-2">
                    <Label for="country_code">
                        País *
                    </Label>

                    <input
                        id="country_code"
                        v-model="formState.country_code"
                        name="country_code"
                        type="text"
                        maxlength="2"
                        required
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm uppercase shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.country_code" />
                </div>

                <div class="grid gap-2">
                    <Label for="currency">
                        Moneda *
                    </Label>

                    <select
                        id="currency"
                        v-model="formState.currency"
                        name="currency"
                        required
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="DOP">
                            DOP · Peso dominicano
                        </option>

                        <option value="USD">
                            USD · Dólar
                        </option>

                        <option value="EUR">
                            EUR · Euro
                        </option>
                    </select>

                    <InputError :message="errors.currency" />
                </div>

                <div class="grid gap-2">
                    <Label for="timezone">
                        Zona horaria *
                    </Label>

                    <input
                        id="timezone"
                        v-model="formState.timezone"
                        name="timezone"
                        type="text"
                        maxlength="100"
                        required
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.timezone" />
                </div>
            </div>
        </section>

        <section
            v-show="!props.onboarding || step === 3"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <Landmark class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Contacto empresarial
                    </p>

                    <p class="text-sm text-slate-500">
                        Datos de contacto propios de la empresa.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="billing_email">
                        Correo de la empresa *
                    </Label>

                    <input
                        id="billing_email"
                        v-model="formState.billing_email"
                        name="billing_email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        required
                        placeholder="empresa@dominio.com"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.billing_email" />
                </div>

                <div class="grid gap-2">
                    <Label for="billing_phone">
                        Teléfono
                    </Label>

                    <input
                        id="billing_phone"
                        v-model="formState.billing_phone"
                        name="billing_phone"
                        type="tel"
                        maxlength="50"
                        autocomplete="tel"
                        placeholder="809-555-0000"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.billing_phone" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="billing_contact_name">
                        Contacto principal
                    </Label>

                    <input
                        id="billing_contact_name"
                        v-model="formState.billing_contact_name"
                        name="billing_contact_name"
                        type="text"
                        maxlength="255"
                        autocomplete="name"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.billing_contact_name" />
                </div>
            </div>
        </section>

        <section
            v-show="!props.onboarding || step === 3"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <MapPin class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Ubicación
                    </p>

                    <p class="text-sm text-slate-500">
                        Dirección declarada de la empresa.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address_line1">
                        Dirección
                    </Label>

                    <input
                        id="address_line1"
                        v-model="formState.address_line1"
                        name="address_line1"
                        type="text"
                        maxlength="255"
                        autocomplete="street-address"
                        placeholder="Calle, número, sector"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.address_line1" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address_line2">
                        Referencia / complemento
                    </Label>

                    <input
                        id="address_line2"
                        v-model="formState.address_line2"
                        name="address_line2"
                        type="text"
                        maxlength="255"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.address_line2" />
                </div>

                <div class="grid gap-2">
                    <Label for="state">
                        Provincia / estado
                    </Label>

                    <input
                        id="state"
                        v-model="formState.state"
                        name="state"
                        type="text"
                        maxlength="255"
                        autocomplete="address-level1"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.state" />
                </div>

                <div class="grid gap-2">
                    <Label for="city">
                        Ciudad / municipio
                    </Label>

                    <input
                        id="city"
                        v-model="formState.city"
                        name="city"
                        type="text"
                        maxlength="255"
                        autocomplete="address-level2"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-2">
                    <Label for="postal_code">
                        Código postal
                    </Label>

                    <input
                        id="postal_code"
                        v-model="formState.postal_code"
                        name="postal_code"
                        type="text"
                        maxlength="50"
                        autocomplete="postal-code"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError :message="errors.postal_code" />
                </div>
            </div>
        </section>

        <section
            v-show="!props.onboarding || step === 4"
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600 dark:bg-green-950/40"
                >
                    <CheckCircle2 class="h-5 w-5" />
                </div>

                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Actividad económica
                    </p>

                    <p class="text-sm text-slate-500">
                        Actividad principal declarada de la empresa.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="economic_activity_primary_code">
                        Código actividad económica
                    </Label>

                    <input
                        id="economic_activity_primary_code"
                        v-model="
                            formState.economic_activity_primary_code
                        "
                        name="economic_activity_primary_code"
                        type="text"
                        maxlength="20"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError
                        :message="
                            errors.economic_activity_primary_code
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="economic_activity_primary_name">
                        Actividad económica
                    </Label>

                    <input
                        id="economic_activity_primary_name"
                        v-model="
                            formState.economic_activity_primary_name
                        "
                        name="economic_activity_primary_name"
                        type="text"
                        maxlength="255"
                        placeholder="Descripción de la actividad principal"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />

                    <InputError
                        :message="
                            errors.economic_activity_primary_name
                        "
                    />
                </div>
            </div>
        </section>

        <section
            v-if="props.onboarding && step === 4"
            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/50"
        >
            <div class="mb-4">
                <p class="font-black text-slate-950 dark:text-white">
                    Revisa antes de continuar
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Esta será la identidad empresarial central de tu App Hub.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border bg-background p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Empresa
                    </p>

                    <p class="mt-2 font-bold">
                        {{ displayValue(formState.company_name) }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ displayValue(formState.legal_name) }}
                    </p>
                </div>

                <div class="rounded-xl border bg-background p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        RNC / contribuyente
                    </p>

                    <p class="mt-2 font-bold">
                        {{ displayValue(formState.tax_id) }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ taxpayerLabel(formState.taxpayer_type) }}
                    </p>
                </div>

                <div class="rounded-xl border bg-background p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Contexto
                    </p>

                    <p class="mt-2 font-bold">
                        {{ companySizeLabel(formState.company_size) }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ formState.country_code }} ·
                        {{ formState.currency }}
                    </p>
                </div>

                <div class="rounded-xl border bg-background p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Contacto
                    </p>

                    <p class="mt-2 font-bold">
                        {{ displayValue(formState.billing_email) }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ displayValue(formState.billing_phone) }}
                    </p>
                </div>

                <div class="rounded-xl border bg-background p-4 sm:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Ubicación
                    </p>

                    <p class="mt-2 font-bold">
                        {{ displayValue(formState.address_line1) }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ displayValue(formState.city) }}
                        ·
                        {{ displayValue(formState.state) }}
                    </p>
                </div>
            </div>
        </section>

        <div
            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300"
        >
            <strong>Datos declarados.</strong>
            LAUDAAPI no sustituye estos valores automáticamente con información obtenida de DGII o de una solución operativa. Las diferencias podrán revisarse de forma separada.
        </div>

        <div
            v-if="props.onboarding"
            class="flex items-center justify-between pt-1"
        >
            <Button
                v-if="step > 1"
                type="button"
                variant="ghost"
                @click="prev"
            >
                <ArrowLeft class="h-4 w-4" />
                Anterior
            </Button>

            <span v-else />

            <Button
                v-if="step < totalSteps"
                type="button"
                :disabled="!currentStepValid"
                @click="next"
            >
                Siguiente
                <ArrowRight class="h-4 w-4" />
            </Button>

            <Button
                v-else
                type="submit"
                class="font-bold"
                :disabled="processing"
            >
                <Spinner v-if="processing" />

                <CheckCircle2
                    v-else
                    class="h-4 w-4"
                />

                {{
                    processing
                        ? 'Creando empresa...'
                        : props.submitLabel
                }}
            </Button>
        </div>

        <Button
            v-else
            type="submit"
            class="h-11 w-full font-bold"
            :disabled="processing"
        >
            <Spinner v-if="processing" />

            <Save
                v-else
                class="h-4 w-4"
            />

            {{ props.submitLabel }}
        </Button>

        <div
            v-if="Object.keys(errors).length > 0"
            class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
        >
            <p class="font-black">
                Revisa los campos indicados.
            </p>

            <p class="mt-1 text-xs">
                Las etapas con errores aparecen marcadas en el indicador superior.
            </p>
        </div>
    </Form>
</template>
