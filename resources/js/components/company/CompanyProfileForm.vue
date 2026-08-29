<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Landmark,
    MapPin,
    Save,
    UserRound,
} from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
</script>

<template>
    <Form
        :action="props.action"
        method="post"
        v-slot="{ errors, processing }"
        class="space-y-5"
    >
        <section
            v-if="props.account"
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
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="name"
                        :value="props.account.name"
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
                        Información declarada que compartirá el App Hub con las soluciones autorizadas.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="company_name">Nombre comercial *</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        required
                        :value="props.initial.company_name"
                        placeholder="Nombre de tu empresa"
                    />
                    <InputError :message="errors.company_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="legal_name">Razón social</Label>
                    <Input
                        id="legal_name"
                        name="legal_name"
                        :value="props.initial.legal_name"
                        placeholder="Razón social registrada"
                    />
                    <InputError :message="errors.legal_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_id">RNC / identificación fiscal</Label>
                    <Input
                        id="tax_id"
                        name="tax_id"
                        :value="props.initial.tax_id"
                        placeholder="Ej. 101000000"
                    />
                    <InputError :message="errors.tax_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="taxpayer_type">Tipo de contribuyente</Label>
                    <select
                        id="taxpayer_type"
                        name="taxpayer_type"
                        :value="props.initial.taxpayer_type ?? ''"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Sin especificar</option>
                        <option value="persona_juridica">
                            Persona jurídica
                        </option>
                        <option value="persona_fisica">
                            Persona física
                        </option>
                    </select>
                    <InputError :message="errors.taxpayer_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="company_size">Tamaño aproximado *</Label>
                    <select
                        id="company_size"
                        name="company_size"
                        required
                        :value="props.initial.company_size"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="" disabled>
                            Selecciona una opción
                        </option>
                        <option value="1-10">1–10 personas</option>
                        <option value="11-50">11–50 personas</option>
                        <option value="51-200">51–200 personas</option>
                        <option value="201+">201 o más</option>
                    </select>
                    <InputError :message="errors.company_size" />
                </div>

                <div class="grid gap-2">
                    <Label for="country_code">País *</Label>
                    <Input
                        id="country_code"
                        name="country_code"
                        required
                        maxlength="2"
                        class="uppercase"
                        :value="props.initial.country_code"
                    />
                    <InputError :message="errors.country_code" />
                </div>
            </div>
        </section>

        <section
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
                        Estos datos pertenecen a la empresa y son independientes del correo de acceso del usuario.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="billing_email">Correo de la empresa *</Label>
                    <Input
                        id="billing_email"
                        name="billing_email"
                        type="email"
                        required
                        :value="props.initial.billing_email"
                        placeholder="empresa@dominio.com"
                    />
                    <InputError :message="errors.billing_email" />
                </div>

                <div class="grid gap-2">
                    <Label for="billing_phone">Teléfono</Label>
                    <Input
                        id="billing_phone"
                        name="billing_phone"
                        type="tel"
                        :value="props.initial.billing_phone"
                        placeholder="809-555-0000"
                    />
                    <InputError :message="errors.billing_phone" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="billing_contact_name">Contacto principal</Label>
                    <Input
                        id="billing_contact_name"
                        name="billing_contact_name"
                        :value="props.initial.billing_contact_name"
                    />
                    <InputError :message="errors.billing_contact_name" />
                </div>
            </div>
        </section>

        <section
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
                    <Label for="address_line1">Dirección</Label>
                    <Input
                        id="address_line1"
                        name="address_line1"
                        :value="props.initial.address_line1"
                        placeholder="Calle, número, sector"
                    />
                    <InputError :message="errors.address_line1" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address_line2">Referencia / complemento</Label>
                    <Input
                        id="address_line2"
                        name="address_line2"
                        :value="props.initial.address_line2"
                    />
                    <InputError :message="errors.address_line2" />
                </div>

                <div class="grid gap-2">
                    <Label for="state">Provincia / estado</Label>
                    <Input
                        id="state"
                        name="state"
                        :value="props.initial.state"
                    />
                    <InputError :message="errors.state" />
                </div>

                <div class="grid gap-2">
                    <Label for="city">Ciudad / municipio</Label>
                    <Input
                        id="city"
                        name="city"
                        :value="props.initial.city"
                    />
                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-2">
                    <Label for="postal_code">Código postal</Label>
                    <Input
                        id="postal_code"
                        name="postal_code"
                        :value="props.initial.postal_code"
                    />
                    <InputError :message="errors.postal_code" />
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-5 flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <CheckCircle2 class="h-5 w-5" />
                </div>
                <div>
                    <p class="font-black text-slate-950 dark:text-white">
                        Preferencias y actividad
                    </p>
                    <p class="text-sm text-slate-500">
                        Configuración base del tenant y actividad económica declarada.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="currency">Moneda *</Label>
                    <select
                        id="currency"
                        name="currency"
                        required
                        :value="props.initial.currency"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="DOP">DOP · Peso dominicano</option>
                        <option value="USD">USD · Dólar</option>
                        <option value="EUR">EUR · Euro</option>
                    </select>
                    <InputError :message="errors.currency" />
                </div>

                <div class="grid gap-2">
                    <Label for="timezone">Zona horaria *</Label>
                    <Input
                        id="timezone"
                        name="timezone"
                        required
                        :value="props.initial.timezone"
                    />
                    <InputError :message="errors.timezone" />
                </div>

                <div class="grid gap-2">
                    <Label for="economic_activity_primary_code">
                        Código actividad económica
                    </Label>
                    <Input
                        id="economic_activity_primary_code"
                        name="economic_activity_primary_code"
                        :value="props.initial.economic_activity_primary_code"
                    />
                    <InputError
                        :message="errors.economic_activity_primary_code"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="economic_activity_primary_name">
                        Actividad económica
                    </Label>
                    <Input
                        id="economic_activity_primary_name"
                        name="economic_activity_primary_name"
                        :value="props.initial.economic_activity_primary_name"
                        placeholder="Descripción de la actividad principal"
                    />
                    <InputError
                        :message="errors.economic_activity_primary_name"
                    />
                </div>
            </div>
        </section>

        <div
            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300"
        >
            <strong>Datos declarados.</strong>
            LAUDAAPI no sustituye estos valores automáticamente con información obtenida de DGII o de una solución operativa. Las diferencias podrán revisarse de forma separada.
        </div>

        <Button
            type="submit"
            class="h-11 w-full font-bold"
            :disabled="processing"
        >
            <Spinner v-if="processing" />
            <Save v-else class="h-4 w-4" />
            {{ props.submitLabel }}
        </Button>
    </Form>
</template>
